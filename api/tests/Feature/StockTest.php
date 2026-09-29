<?php

use App\Models\Product;
use App\Models\Location;
use App\Models\StockMovement;


function seedModels(): array
{
    $product  = Product::create([
        'sku'            => 'SKU-' . uniqid(),
        'name'           => 'Test Product',
        'unit_cost_cents' => 1000,
        'min_stock'      => 0,
    ]);
    $location = Location::create([
        'code'    => 'LOC-' . uniqid(),
        'address' => 'Test Address',
    ]);

    return [$product, $location];
}

function seedProduct(array $overrides = []): Product
{
    return Product::create(array_merge([
        'sku'            => 'SKU-' . uniqid(),
        'name'           => 'Test Product',
        'unit_cost_cents' => 1000,
        'min_stock'      => 0,
    ], $overrides));
}

function seedLocation(array $overrides = []): Location
{
    return Location::create(array_merge([
        'code'    => 'LOC-' . uniqid(),
        'address' => 'Test Address',
    ], $overrides));
}

function addMovement(int $productId, int $locationId, string $type, int $qty): void
{
    StockMovement::create([
        'product_id'  => $productId,
        'location_id' => $locationId,
        'type'        => $type,
        'qty'         => $qty,
    ]);
}

describe('Stock & Reports API', function() {

    it('GET /stocks returns 200 + message + paginator shape', function() {
        $this->get('/stocks', ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(200);
        $this->seeJson(['message' => 'Stock list']);
        $this->seeJsonStructure([
            'message',
            'data' => ['current_page', 'data', 'per_page', 'total'],
        ]);
    });

    it('GET /stocks returns empty data when no movements', function() {
        $this->get('/stocks', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true);
        $items = $json['data']['data'];
        expect($items)->toBe([]);
    });

    it('GET /stocks groups by product_id and location_id with sku and code', function () {
        [$p, $l] = seedModels();
        addMovement($p->id, $l->id, 'IN', 25);

        $this->get('/stocks', ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect(count($items))->toBe(1);
        expect($items[0]['product_id'])->toBe($p->id);
        expect($items[0]['location_id'])->toBe($l->id);
        expect($items[0]['sku'])->toBe($p->sku);
        expect($items[0]['code'])->toBe($l->code);
        expect($items[0]['stock_on_hand'])->toBe(25);
    });

    it('GET /stocks excludes zero stock rows', function () {
        [$p, $l] = seedModels();
        addMovement($p->id, $l->id, 'IN', 10);
        addMovement($p->id, $l->id, 'OUT', -10);  // sum = 0

        $this->get('/stocks', ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect($items)->toBe([]);  // zero balance excluded
    });

    it('GET /stocks handles multiple product+location pairs as separate rows', function () {
        [$p1, $l1] = seedModels();
        [$p2, $l2] = seedModels();

        addMovement($p1->id, $l1->id, 'IN', 5);
        addMovement($p2->id, $l2->id, 'IN', 8);

        $this->get('/stocks', ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect(count($items))->toBe(2);
    });

    it('GET /stocks?sku filters by product sku', function(){
        [$p1,$l] = seedModels();
        [$p2,] = seedModels();

        addMovement($p1->id, $l->id, 'IN', 10);
        addMovement($p2->id, $l->id, 'IN', 10);

        $this->get("/stocks?sku={$p1->sku}", ['HTTP_ACCEPT' => 'application/json']);
        $json = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect($items[0]['sku'])->toBe($p1->sku);
        expect($items[0]['stock_on_hand'])->toBe(10);
    });

    it('GET /stocks?location filters by location code', function () {
        [$p, $l1] = seedModels();
        $l2 = seedLocation();

        addMovement($p->id, $l1->id, 'IN', 15);
        addMovement($p->id, $l2->id, 'IN', 30);

        $this->get("/stocks?location={$l2->code}", ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect(count($items))->toBe(1);
        expect($items[0]['code'])->toBe($l2->code);
        expect($items[0]['stock_on_hand'])->toBe(30);
    });

    it('GET /stocks?sku&location applies both filters together', function () {
        [$p1, $l1] = seedModels();
        $p2 = seedProduct();
        $l2 = seedLocation();

        addMovement($p1->id, $l1->id, 'IN', 7);
        addMovement($p2->id, $l2->id, 'IN', 14);

        $this->get("/stocks?sku={$p1->sku}&location={$l1->code}", ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect(count($items))->toBe(1);
        expect($items[0]['sku'])->toBe($p1->sku);
        expect($items[0]['code'])->toBe($l1->code);
    });

    it('GET /stocks defaults per_page to 20' ,function(){
        $this->get('/stocks', ['HTTP_ACCEPT' => 'application/json']);
        $json = json_decode($this->response->getContent(), true);
        expect($json['data']['per_page'])->toBe(20);
    });
    
    it('GET /stocks?per_page=999 clamps to 100', function () {
        $this->get('/stocks?per_page=999', ['HTTP_ACCEPT' => 'application/json']);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['data']['per_page'])->toBe(100);
    });

    it('GET /stocks?per_page=-5 falls back to 20', function () {
        $this->get('/stocks?per_page=-5', ['HTTP_ACCEPT' => 'application/json']);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['data']['per_page'])->toBe(20);
    });

    it('GET /products/{id}/stocks returns 200 + message + paginator shape', function(){
        $p = seedProduct();
        $this->get("/products/{$p->id}/stocks", ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(200);
        $this->seeJson(['message' => 'Stock for product']);
        $this->seeJsonStructure([
            'message',
            'data' => ['current_page', 'data', 'per_page', 'total'],
        ]);
    });

    it('GET /products/{id}/stocks returns empty list when no movements for product', function(){
        $p = seedProduct();
        $this->get("/products/{$p->id}/stocks", ['HTTP_ACCEPT' => 'application/json']);
        $json = json_decode($this->response->getContent(),true);
        expect(count($json['data']['data']))->toBe(0);
    });

    it('GET /products/{id}/stocks returns rows across all locations for that product', function(){
        [$p,$l1] = seedModels();
        $l2 = seedLocation();

        addMovement($p->id, $l1->id, 'IN',10);
        addMovement($p->id, $l2->id, 'IN',10);

        $this->get("/products/{$p->id}/stocks", ['HTTP_ACCEPT' => 'application/json']);
        $json = json_decode($this->response->getContent(),true);
        $items = $json['data']['data'];

        expect(count($items))->toBe(2);
        expect($items[0]['product_id'])->toBe($p->id);
        expect($items[1]['product_id'])->toBe($p->id); 
    });

    it('GET /locations/{id}/stocks returns 200 + message + paginator shape', function () {
        $l = seedLocation();

        $this->get("/locations/{$l->id}/stocks", ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeJson(['message' => 'Stock for location']);
        $this->seeJsonStructure([
            'message',
            'data' => ['current_page', 'data', 'per_page', 'total'],
        ]);
    });

    it('GET /locations/{id}/stocks returns empty list when no movements for location', function () {
        $l = seedLocation();

        $this->get("/locations/{$l->id}/stocks", ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];
        expect($items)->toBe([]);
    });

    it('GET /locations/{id}/stocks returns rows across all products for that location', function () {
        $l = seedLocation();
        $p1 = seedProduct();
        $p2 = seedProduct();

        addMovement($p1->id, $l->id, 'IN', 6);
        addMovement($p2->id, $l->id, 'IN', 12);

        $this->get("/locations/{$l->id}/stocks", ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect(count($items))->toBe(2);
        expect($items[0]['location_id'])->toBe($l->id);
        expect($items[1]['location_id'])->toBe($l->id);
    });

    it('GET /reports/low-stock returns 200 + message + threshold', function(){

        $this->get('/reports/low-stock', ['HTTP_ACCEPT' => 'application/json']);
        $json = json_decode($this->response->getContent(), true) ?? [];

        $this->seeStatusCode(200);
        $this->seeJson(['message' => 'Low stock report']);
        expect($json['threshold'])->toBe(StockMovement::MIN_ALLOWED_QTY);
        $this->seeJsonStructure([
            'message',
            'data' => ['current_page', 'data', 'per_page', 'total'],
            'threshold',
        ]);
    });

    it('low stock report includes rows below products.min_stock, excludes equal rows', function () {
        $l = seedLocation();

        $low     = seedProduct(['min_stock' => 10]);
        $exact   = seedProduct(['min_stock' => 8]);
        $ok      = seedProduct(['min_stock' => 3]);

        addMovement($low->id, $l->id, 'IN', 5);
        addMovement($exact->id, $l->id, 'IN', 8);
        addMovement($ok->id, $l->id, 'IN', 10);

        $this->get('/reports/low-stock', ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect(count($items))->toBe(1);
        expect($items[0]['product_id'])->toBe($low->id);
        expect($items[0]['stock_on_hand'])->toBe(5);
    });

    it('low stock report returns empty list when no low-stock rows', function () {
        $p = seedProduct(['min_stock' => 2]);
        $l = seedLocation();

        addMovement($p->id, $l->id, 'IN', 20);

        $this->get('/reports/low-stock', ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect($items)->toBe([]);
    });

    it('stock_on_hand is correct after multiple IN / OUT / ADJUST movements', function () {
        [$p, $l] = seedModels();

        addMovement($p->id, $l->id, 'IN', 50);
        addMovement($p->id, $l->id, 'OUT', -20);
        addMovement($p->id, $l->id, 'ADJUST', -5);

        $this->get('/stocks', ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect($items[0]['stock_on_hand'])->toBe(25);  // 50 - 20 - 5 = 25
    });
});
