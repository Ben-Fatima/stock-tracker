<?php

use App\Models\Location;

function seedLocation( array $overrides = []) {
    return Location::create( array_merge(
        [
            'code' => 'LOC-' . uniqid(),
            'address' => 'ADD-' . uniqid(),
        ],
        $overrides
    ));
}

describe('Location Tests', function(){

    it('GET /locations returns paginated list', function () {
        $this->get('/locations', ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(200);
        $this->seeJsonStructure(['current_page', 'data', 'per_page', 'total']);
    });

    it('GET /locations returns empty data when no locations', function () {
        $this->get('/locations', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data'] ?? [];
        expect($items)->toBe([]);
    });

    it('POST /locations creates a location', function () {
        $this->post('/locations', [
            'code'    => 'WH-001',
            'address' => '123 Main Street',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(201);
        $this->seeJson(['message' => 'Location created successfully']);
        $this->seeJson(['code' => 'WH-001', 'address' => '123 Main Street']);
        $this->seeInDatabase('locations', ['code' => 'WH-001']);
    });

    it('POST /locations rejects missing code', function () {
        $this->post('/locations', [
            'address' => 'Some Address',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(422);
        $this->seeJsonStructure(['message', 'errors']);
    });

    it('POST /locations rejects missing address', function () {
        $this->post('/locations', [
            'code' => 'WH-002',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(422);
    });

    it('POST /locations rejects address shorter than 3 chars', function () {
        $this->post('/locations', [
            'code'    => 'WH-003',
            'address' => 'AB',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(422);
    });

    it('POST /locations rejects duplicate code', function () {
        seedLocation(['code' => 'DUP-LOC']);

        $this->post('/locations', [
            'code'    => 'DUP-LOC',
            'address' => 'Different Address',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(422);
    });

    it('POST /locations ignores extra fields', function () {
        $this->post('/locations', [
            'code'    => 'WH-EXTRA',
            'address' => 'Real Address',
            'phone'   => '555-1234',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(201);
        $this->seeInDatabase('locations', ['code' => 'WH-EXTRA']);

        // verify the extra field was NOT persisted
        $json = json_decode($this->response->getContent(), true);
        expect($json['data'])->not->toHaveKey('phone');
    });

    it('GET /locations/{id} returns a location', function () {
        $loc = seedLocation(['code' => 'WH-SHOW', 'address' => 'Show Street']);

        $this->get("/locations/{$loc->id}", ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeJson([
            'message' => 'Location retrieved successfully',
            'code'    => 'WH-SHOW',
            'address' => 'Show Street',
        ]);
    });

    it('GET /locations/{id} returns 404 when not found', function () {
        $this->get('/locations/999999', ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(404);
    });

    it('PATCH /locations/{id} updates only address', function () {
        $loc = seedLocation(['code' => 'WH-UPD', 'address' => 'Old Address']);

        $this->patch("/locations/{$loc->id}", [
            'address' => 'New Address',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeJson(['message' => 'Location updated successfully']);
        $this->seeInDatabase('locations', [
            'id'      => $loc->id,
            'code'    => 'WH-UPD',     // unchanged
            'address' => 'New Address', // updated
        ]);
    });

    it('PATCH /locations/{id} updates only code', function () {
        $loc = seedLocation(['code' => 'OLD-CODE', 'address' => 'Same Address']);

        $this->patch("/locations/{$loc->id}", [
            'code' => 'NEW-CODE',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeInDatabase('locations', [
            'id'      => $loc->id,
            'code'    => 'NEW-CODE',    // updated
            'address' => 'Same Address', // unchanged
        ]);
    });

    it('PATCH /locations/{id} returns 400 when no fields provided', function () {
        $loc = seedLocation();

        $this->patch("/locations/{$loc->id}", [], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(400);
        $this->seeJson(['message' => 'No data provided for update']);
    });

    it('PATCH /locations/{id} returns 404 when not found', function () {
        $this->patch('/locations/999999', ['code' => 'X'], ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(404);
    });

    it('PATCH /locations/{id} rejects address shorter than 3', function () {
        $loc = seedLocation();

        $this->patch("/locations/{$loc->id}", [
            'address' => 'AB',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(422);
    });

    it('PATCH /locations/{id} allows keeping same code (unique ignore self)', function () {
        $loc = seedLocation(['code' => 'KEEP-ME']);

        $this->patch("/locations/{$loc->id}", [
            'code'    => 'KEEP-ME',
            'address' => 'Updated Address',
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeInDatabase('locations', [
            'id'      => $loc->id,
            'code'    => 'KEEP-ME',
            'address' => 'Updated Address',
        ]);
    });

    it('PATCH /locations/{id} rejects changing code to an existing one', function () {
        $a = seedLocation(['code' => 'CODE-A']);
        $b = seedLocation(['code' => 'CODE-B']);

        $this->patch("/locations/{$b->id}", [
            'code' => $a->code,
        ], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(422);
    });

    it('DELETE /locations/{id} deletes a location', function () {
        $loc = seedLocation(['code' => 'WH-DEL']);

        $this->delete("/locations/{$loc->id}", [], ['HTTP_ACCEPT' => 'application/json']);

        $this->seeStatusCode(200);
        $this->seeJson(['message' => 'Location deleted successfully']);

        // verify row is gone from DB
        $count = $this->app->make('db')
                ->table('locations')
                ->where('id', $loc->id)
                ->count();
        expect($count)->toBe(0);

        // verify GET now returns 404
        $this->get("/locations/{$loc->id}", ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(404);
    });

    it('DELETE /locations/{id} returns 404 when not found', function () {
        $this->delete('/locations/999999', [], ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(404);
    });

    it('GET /locations supports search by q (code or address)', function () {
        seedLocation(['code' => 'FIND-ME', 'address' => 'Warehouse Lane']);
        seedLocation(['code' => 'OTHER',   'address' => 'Some Place']);

        $this->get('/locations?q=Warehouse', ['HTTP_ACCEPT' => 'application/json']);
        $this->seeStatusCode(200);

        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data'] ?? [];

        expect(count($items))->toBe(1);
        expect($items[0]['code'])->toBe('FIND-ME');
    });

    it('GET /locations supports sort + dir, falls back to id asc for invalid sort', function () {
        $first  = seedLocation(['code' => 'AAA']);
        $second = seedLocation(['code' => 'ZZZ']);

        // valid sort: code desc => ZZZ first
        $this->get('/locations?sort=code&dir=desc', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data'] ?? [];
        expect($items[0]['id'])->toBe($second->id);

        // invalid sort: falls back to id asc => AAA first
        $this->get('/locations?sort=not-allowed&dir=desc', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        $items = $json['data'] ?? [];
        expect($items[0]['id'])->toBe($first->id);
    });

    it('GET /locations clamps per_page to max 100 and defaults to 50 for invalid', function () {
        for ($i = 0; $i < 120; $i++) {
            seedLocation();
        }

        // per_page=999 clamps to 100
        $this->get('/locations?per_page=999', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        expect($json['per_page'])->toBe(100);
        expect(count($json['data']))->toBe(100);

        // per_page=-5 (non-positive) falls back to default 50
        $this->get('/locations?per_page=-5', ['HTTP_ACCEPT' => 'application/json']);
        $json  = json_decode($this->response->getContent(), true) ?? [];
        expect($json['per_page'])->toBe(50);
        expect(count($json['data']))->toBe(50);
    });
});

