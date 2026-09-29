<?php

use App\Models\Product;
use App\Models\Location;
use App\Models\StockMovement;

function seedModels() {
    $product = Product::create([
        'name' => 'Test Product',
        'sku' => 'TEST-001' . uniqid(),
        'unit_cost_cents' => 1000,
        'min_stock' => 10,
    ]);
    $location = Location::create([
        'code' => 'WH-001' . uniqid(),
        'address' => 'Warehouse 1',
    ]);
    
    return [$product, $location];
}

function postMovement(object $test, array $data): void
{
    $test->post('/movements', $data, ['HTTP_ACCEPT' => 'application/json']);
}

describe('Movements API', function () {
    it('GET /movements returns 200 + message + paginator shape', function () {
        $this->get('/movements', ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeJson(['message' => 'Movement history']);
        $this->seeJsonStructure([
            'message',
            'data' => ['current_page', 'data', 'per_page', 'total'],
        ]);
    });

    it('GET /movements returns empty data when no movements', function () {
        $this->get('/movements', ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];
        expect($items)->toBe([]);
    });

    it('GET /movements orders by created_at desc (newest first)', function () {
        [$p, $l] = seedModels();

        // Create two movements; the second is newer
        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 10, 'note' => 'first',
        ]);
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::now()->addSecond());
        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 20, 'note' => 'second',
        ]);

        $this->get('/movements', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        // newest (qty 20) should come first
        expect($items[0]['note'])->toBe('second');
        expect($items[1]['note'])->toBe('first');
    });

    it('GET /movements?product_id filters by product', function () {
        [$p1, $l1] = seedModels();
        [$p2, $l2] = seedModels();
        
        postMovement($this,[
            'product_id' => $p1->id,
            'location_id' => $l1->id,
            'type' => 'IN',
            'qty' => 40,
            'note' => 'first'
        ]);

        postMovement($this,[
            'product_id' => $p2->id,
            'location_id' => $l1->id,
            'type' => 'IN',
            'qty' => 40,
            'note' => 'second'
        ]);

        postMovement($this,[
            'product_id' => $p2->id,
            'location_id' => $l1->id,
            'type' => 'IN',
            'qty' => 40,
            'note' => 'third'
        ]);

        postMovement($this,[
            'product_id' => $p1->id,
            'location_id' => $l1->id,
            'type' => 'IN',
            'qty' => 10,
            'note' => '4th'
        ]);

    $this->get("/movements?product_id={$p1->id}", ['HTTP_ACCEPT' => 'application/json']);
    $json  = json_decode($this->response->getContent(), true) ?? [];
    $items = $json['data']['data'] ?? [];

    expect($items[0]['product_id'])->toBe($p1->id);
    expect($items[1]['product_id'])->toBe($p1->id);

    });

    it('GET /movements?location_id filters by locations', function () {
        [$p1, $l1] = seedModels();
        [$p2, $l2] = seedModels();
        
        postMovement($this,[
            'product_id' => $p1->id,
            'location_id' => $l1->id,
            'type' => 'IN',
            'qty' => 40,
            'note' => 'first'
        ]);

        postMovement($this,[
            'product_id' => $p2->id,
            'location_id' => $l2->id,
            'type' => 'IN',
            'qty' => 40,
            'note' => 'second'
        ]);

        postMovement($this,[
            'product_id' => $p2->id,
            'location_id' => $l1->id,
            'type' => 'IN',
            'qty' => 40,
            'note' => 'third'
        ]);

        postMovement($this,[
            'product_id' => $p1->id,
            'location_id' => $l2->id,
            'type' => 'IN',
            'qty' => 10,
            'note' => '4th'
        ]);

    $this->get("/movements?location_id={$l1->id}", ['HTTP_ACCEPT' => 'application/json']);
    $json  = json_decode($this->response->getContent(), true) ?? [];
    $items = $json['data']['data'] ?? [];

    expect($items[0]['location_id'])->toBe($l1->id);
    expect($items[1]['location_id'])->toBe($l1->id);

    });

    it('GET /movements?type=IN returns only IN movements', function () {
        [$p, $l] = seedModels();

        postMovement($this, ['product_id' => $p->id, 'location_id' => $l->id, 'type' => 'IN',     'qty' => 10]);
        postMovement($this, ['product_id' => $p->id, 'location_id' => $l->id, 'type' => 'ADJUST', 'qty' => 5]);

        $this->get('/movements?type=IN', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect(count($items))->toBe(1);
        expect($items[0]['type'])->toBe('IN');
    });

    it('GET /movements?type=INVALID ignores the filter (returns all)', function () {
        [$p, $l] = seedModels();

        postMovement($this, ['product_id' => $p->id, 'location_id' => $l->id, 'type' => 'IN', 'qty' => 10]);

        $this->get('/movements?type=INVALID', ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(200);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];
        expect(count($items))->toBe(1);
    });

    it('GET /movements supports combining filters', function () {
        [$p, $l1] = seedModels();
        [, $l2]  = seedModels();

        postMovement($this, ['product_id' => $p->id, 'location_id' => $l1->id, 'type' => 'IN',     'qty' => 10]);
        postMovement($this, ['product_id' => $p->id, 'location_id' => $l2->id, 'type' => 'IN',     'qty' => 20]);
        postMovement($this, ['product_id' => $p->id, 'location_id' => $l1->id, 'type' => 'ADJUST', 'qty' => 5]);

        $url = "/movements?product_id={$p->id}&location_id={$l1->id}&type=IN";
        $this->get($url, ['HTTP_ACCEPT' => 'application/json']);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        // only the first movement matches all three filters
        expect(count($items))->toBe(1);
    });

    it('GET /movements defaults per_page to 20', function () {
        $this->get('/movements', ['HTTP_ACCEPT' => 'application/json']);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['data']['per_page'])->toBe(20);
    });

    it('GET /movements?per_page=999 clamps to 100', function () {
        $this->get('/movements?per_page=999', ['HTTP_ACCEPT' => 'application/json']);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['data']['per_page'])->toBe(100);
    });

    it('GET /movements?per_page=-5 falls back to default 20', function () {
        $this->get('/movements?per_page=-5', ['HTTP_ACCEPT' => 'application/json']);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['data']['per_page'])->toBe(20);
    });

    it('POST /movements reject missing product id', function () {
        [,$l] = seedModels();
        $this->post('/movements', [
            'location_id' => $l->id,
            'type' => 'IN',
            'qty' => 10,
            'note' => 'missing product id'
        ], ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(422);
    });

    it('POST /movements rejects missing location_id', function () {
        [$p] = seedModels();
        $this->post('/movements', [
            'product_id' => $p->id, 'type' => 'IN', 'qty' => 5,
        ], ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(422);
    });

    it('POST /movements rejects missing type', function () {
        [$p, $l] = seedModels();
        $this->post('/movements', [
            'product_id' => $p->id, 'location_id' => $l->id, 'qty' => 5,
        ], ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(422);
    });

    it('POST /movements rejects missing qty', function () {
        [$p, $l] = seedModels();
        $this->post('/movements', [
            'product_id' => $p->id, 'location_id' => $l->id, 'type' => 'IN',
        ], ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(422);
    });

    it('POST /movements rejects qty = 0', function () {
        [$p, $l] = seedModels();
        $this->post('/movements', [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 0,
        ], ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(422);
    });

    it('POST /movements rejects invalid type', function () {
        [$p, $l] = seedModels();
        $this->post('/movements', [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'TRANSFER', 'qty' => 5,
        ], ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(422);
    });

    it('POST /movements rejects non-existing product_id', function () {
        [, $l] = seedModels();
        $this->post('/movements', [
            'product_id' => 999999, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 5,
        ], ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(422);
    });

    it('POST /movements rejects non-existing location_id', function () {
        [$p] = seedModels();
        $this->post('/movements', [
            'product_id' => $p->id, 'location_id' => 999999,
            'type' => 'IN', 'qty' => 5,
        ], ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(422);
    });

    it('POST /movements accepts note as nullable and string', function () {
        [$p, $l] = seedModels();

        // without note
        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 10,
        ]);
        $this->seeStatusCode(201);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['data']['note'] ?? null)->toBeNull();

        // with note
        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 5, 'note' => 'Restocking',
        ]);
        $this->seeStatusCode(201);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['data']['note'])->toBe('Restocking');
    });

    it('IN: negative qty is stored as positive', function () {
        [$p, $l] = seedModels();

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => -7,
        ]);

        $this->seeStatusCode(201);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['data']['qty'])->toBe(7);
        expect($json['current_stock'])->toBe(7);
    });

    it('OUT: qty is always stored as negative', function () {
        [$p, $l] = seedModels();

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 20,
        ]);

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'OUT', 'qty' => 5,
        ]);

        $this->seeStatusCode(201);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['data']['qty'])->toBe(-5);
        expect($json['current_stock'])->toBe(15);
    });

    it('ADJUST: qty sign is kept as sent', function () {
        [$p, $l] = seedModels();

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'ADJUST', 'qty' => 10,
        ]);
        $this->seeStatusCode(201);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['data']['qty'])->toBe(10);

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'ADJUST', 'qty' => -3,
        ]);
        $this->seeStatusCode(201);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['data']['qty'])->toBe(-3);
        expect($json['current_stock'])->toBe(7);
    });

    it('OUT succeeds when stock is sufficient', function() {
        [$p, $l] = seedModels();
        postMovement($this, [
            'product_id' => $p->id,
            'location_id' => $l->id,
            'type' => 'IN',
            'qty' => 100
        ]);
        $this->seeStatusCode(201);

        postMovement($this, [
            'product_id' => $p->id,
            'location_id' => $l->id,
            'type' => 'OUT',
            'qty' => 90
        ]);
        $this->seeStatusCode(201);
        $this->seeJson(['message' => 'Movement added successfully']);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['current_stock'])->toBe(10);
        $this->seeInDatabase('stock_movements', [
            'product_id'  => $p->id,
            'location_id' => $l->id,
            'type'        => 'OUT',
            'qty'         => -90,
        ]);
    });

    it('OUT fails when stock would go negative', function () {
        [$p, $l] = seedModels();

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 10,
        ]);

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'OUT', 'qty' => 15,
        ]);

        $this->seeStatusCode(422);
        $json = json_decode($this->response->getContent(), true) ?? [];
        expect($json['current_stock'])->toBe(10);
        expect($json['requested_out'])->toBe(15);
    });

    it('OUT fail does NOT insert a movement row (transaction integrity)', function () {
        [$p, $l] = seedModels();

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 5,
        ]);

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'OUT', 'qty' => 100,
        ]);
        $this->seeStatusCode(422);

        $count = $this->app->make('db')
                ->table('stock_movements')
                ->where('product_id', $p->id)
                ->where('location_id', $l->id)
                ->count();
        expect($count)->toBe(1);
        expect(StockMovement::stockOnHand($p->id, $l->id))->toBe(5);
    });

    it('after IN, current_stock equals DB sum', function () {
        [$p, $l] = seedModels();

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 25,
        ]);
        $this->seeStatusCode(201);
        $json = json_decode($this->response->getContent(), true) ?? [];

        $dbStock = StockMovement::stockOnHand($p->id, $l->id);
        expect($json['current_stock'])->toBe($dbStock);
        expect($dbStock)->toBe(25);
    });

    it('after OUT, current_stock equals DB sum', function () {
        [$p, $l] = seedModels();

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 30,
        ]);
        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'OUT', 'qty' => 12,
        ]);
        $this->seeStatusCode(201);
        $json = json_decode($this->response->getContent(), true) ?? [];

        $dbStock = StockMovement::stockOnHand($p->id, $l->id);
        expect($json['current_stock'])->toBe($dbStock);
        expect($dbStock)->toBe(18);             // 30 - 12
    });

    it('after ADJUST, current_stock equals DB sum', function () {
        [$p, $l] = seedModels();

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 20,
        ]);
        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'ADJUST', 'qty' => -8,
        ]);
        $this->seeStatusCode(201);
        $json = json_decode($this->response->getContent(), true) ?? [];

        $dbStock = StockMovement::stockOnHand($p->id, $l->id);
        expect($json['current_stock'])->toBe($dbStock);
        expect($dbStock)->toBe(12);
    });

    it('index returns abs(qty) for IN and OUT movements', function () {
        [$p, $l] = seedModels();

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'IN', 'qty' => 15,
        ]);
        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'OUT', 'qty' => 5,
        ]);

        $this->get('/movements', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        foreach ($items as $item) {
            expect($item['qty'])->toBeGreaterThan(0);
        }
    });

    it('index returns qty as-is for ADJUST movements', function () {
        [$p, $l] = seedModels();

        postMovement($this, [
            'product_id' => $p->id, 'location_id' => $l->id,
            'type' => 'ADJUST', 'qty' => -7,
        ]);

        $this->get('/movements', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect($items[0]['qty'])->toBe(-7);
    });
});
