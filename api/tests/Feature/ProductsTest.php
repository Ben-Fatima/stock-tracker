<?php

use App\Models\Product;

function seedProduct(array $overrides = []): Product
{
    return Product::create(array_merge([
        'sku' => 'SKU-' . uniqid(),
        'name' => 'Product ' . uniqid(),
        'unit_cost_cents' => 1000,
        'min_stock' => 0,
    ], $overrides));
}

describe('Products API', function () {

    it('GET /products returns paginated list (empty)', function () {
        $this->get('/products', ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeJson(['message' => 'List of products']);
        $this->seeJsonStructure([
            'message',
            'data' => ['current_page', 'data', 'per_page', 'total'],
        ]);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];
        expect($items)->toBe([]);
    });

    it('POST /products creates a product', function () {
        $this->post('/products', [
            'sku' => 'SKU-001',
            'name' => 'Coffee',
            'unit_cost_cents' => 1500,
            'min_stock' => 3,
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(201);
        $this->seeJson(['message' => 'Product created successfully']);
        $this->seeJson(['sku' => 'SKU-001', 'name' => 'Coffee']);
        $this->seeInDatabase('products', ['sku' => 'SKU-001']);
    });

    it('POST /products rejects missing required fields', function () {
        $this->post('/products', [
            'sku' => '',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(422);
        $this->seeJsonStructure(['message', 'errors']);
    });

    it('POST /products rejects duplicate sku', function () {
        seedProduct(['sku' => 'DUP-1']);

        $this->post('/products', [
            'sku' => 'DUP-1',
            'name' => 'Another',
            'unit_cost_cents' => 2000,
            'min_stock' => 1,
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(422);
    });

    it('POST /products rejects negative integers', function () {
        $this->post('/products', [
            'sku' => 'SKU-NEG',
            'name' => 'Bad',
            'unit_cost_cents' => -1,
            'min_stock' => -5,
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(422);
    });

    it('GET /products/{id} returns a product', function () {
        $p = seedProduct(['sku' => 'SKU-SHOW', 'name' => 'Show Me']);

        $this->get("/products/{$p->id}", ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeJson([
            'message' => 'Product retrieved successfully',
            'sku' => 'SKU-SHOW',
            'name' => 'Show Me'
            ]);
    });

    it('GET /products/{id} returns 404 if missing', function () {
        $this->get('/products/999999', ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(404);
    });

    it('PATCH /products/{id} partially updates fields', function () {
        $p = seedProduct(['sku' => 'SKU-PATCH', 'name' => 'Before']);

        $this->patch("/products/{$p->id}", [
            'name' => 'After',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeJson(['message' => 'Product updated successfully']);
        $this->seeInDatabase('products', [
            'id' => $p->id,
            'sku' => 'SKU-PATCH',
            'name' => 'After'
        ]);
    });

    it('PATCH /products/{id} returns 400 when no fields provided', function () {
        $p = seedProduct();

        $this->patch("/products/{$p->id}", [], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(400);
        $this->seeJson(['message' => 'No data provided for update']);
    });

    it('PATCH /products/{id} returns 404 if missing', function () {
        $this->patch('/products/999999', ['name' => 'X'], ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(404);
    });

    it('PATCH /products/{id} allows keeping same sku (unique ignore self)', function () {
        $p = seedProduct(['sku' => 'SKU-SAME']);

        $this->patch("/products/{$p->id}", [
            'sku' => 'SKU-SAME',
            'name' => 'Updated Name',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeInDatabase('products', [
            'id' => $p->id,
            'sku' => 'SKU-SAME',
            'name' => 'Updated Name'
        ]);
    });

    it('PATCH /products/{id} rejects changing sku to an existing one', function () {
        $a = seedProduct(['sku' => 'SKU-A']);
        $b = seedProduct(['sku' => 'SKU-B']);

        $this->patch("/products/{$b->id}", [
            'sku' => $a->sku,
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(422);
    });

    it('PATCH /products/{id} rejects invalid values', function () {
        $p = seedProduct();

        $this->patch("/products/{$p->id}", [
            'unit_cost_cents' => -10,
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(422);
    });

    it('DELETE /products/{id} deletes a product', function () {
        $p = seedProduct(['sku' => 'SKU-DEL']);

        $this->delete("/products/{$p->id}", [], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeJson(['message' => 'Product deleted successfully']);

        $count = $this->app->make('db')
                ->table('products')
                ->where('id', $p->id)
                ->count();

        expect($count)->toBe(0);

        $this->get("/products/{$p->id}", ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(404);
    });

    it('GET /products supports search by q (sku or name)', function () {
        seedProduct(['sku' => 'FIND-ME', 'name' => 'Coffee Beans']);
        seedProduct(['sku' => 'OTHER', 'name' => 'Tea']);

        $this->get('/products?q=Coffee', ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(200);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];

        expect(count($items))->toBe(1);
        expect($items[0]['sku'])->toBe('FIND-ME');
    });

    it('GET /products supports sort + dir, and falls back to id asc for invalid sort', function () {
        $first  = seedProduct(['sku' => 'S1', 'name' => 'AAA']);
        $second = seedProduct(['sku' => 'S2', 'name' => 'ZZZ']);


        $this->get('/products?sort=name&dir=desc', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];
        expect($items[0]['id'])->toBe($second->id);


        $this->get('/products?sort=not-allowed&dir=desc', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];
        expect($items[0]['id'])->toBe($first->id);
    });

    it('GET /products clamps per_page to max 100 and defaults to 50 for invalid', function () {
        for ($i = 0; $i < 120; $i++) {
            seedProduct(['sku' => 'BULK-' . $i]);
        }


        $this->get('/products?per_page=999', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];
        expect($json['data']['per_page'])->toBe(100);
        expect(count($items))->toBe(100);


        $this->get('/products?per_page=-5', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data']['data'] ?? [];
        expect($json['data']['per_page'])->toBe(50);
        expect(count($items))->toBe(50);
    });
});
