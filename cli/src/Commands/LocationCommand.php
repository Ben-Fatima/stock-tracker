<?php

namespace StockTracker\Cli\Commands;

use StockTracker\Cli\HttpClient;
use StockTracker\Cli\Output;

class LocationCommand {

    protected HttpClient $http;
    protected Output $out;

    public function __construct(HttpClient $http, Output $out)
    {
        $this->http = $http;
        $this->out = $out;
    }

    public function list(array $options): void
    {
        $query = [];

        if (isset($options['search'])) {
            $query['search'] = $options['search'];
        }

        if (isset($options['page'])) {
            $query['page'] = $options['page'];
        }

        if (isset($options['per-page'])) {
            $query['per_page'] = $options['per-page'];
        }

        if (isset($options['sort'])) {
            $query['sort'] = $options['sort'];
        }

        if (isset($options['direction'])) {
            $query['direction'] = $options['direction'];
        }

        $req = $this->http->get('/locations', $query);

        if ($req['status'] === 200) {
            $locations = $req['body']['data'] ?? [];
            $this->out->table($locations, ['id', 'code', 'address']);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function show(array $args): void
    {
        $id = $args[0] ?? null;

        if ($id === null || !ctype_digit((string) $id) || (int) $id < 1) {
            $this->out->error('Usage: locations:show <positive location id>');
            return;
        }

        $req = $this->http->get("/locations/{$id}");

        if ($req['status'] === 200) {
            $this->out->success($req['body']['message'] ?? 'Location retrieved successfully');
            $this->out->detail($req['body']['data'] ?? []);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function create(array $options): void
    {
        $required = ['code', 'address'];

        foreach ($required as $option) {
            if (!isset($options[$option]) || $options[$option] === '') {
                $this->out->error('Usage: locations:create --code=... --address=...');
                return;
            }
        }

        $body = [
            'code' => $options['code'],
            'address' => $options['address'],
        ];

        $req = $this->http->post('/locations', $body);

        if ($req['status'] === 201) {
            $this->out->success($req['body']['message'] ?? 'Location created successfully');
            $this->out->detail($req['body']['data'] ?? []);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function update(array $args, array $options): void
    {
        $id = $args[0] ?? null;

        if ($id === null || !ctype_digit((string) $id) || (int) $id < 1) {
            $this->out->error('Usage: locations:update <positive location id> [options]');
            return;
        }

        $body = [];

        if (isset($options['code'])) {
            $body['code'] = $options['code'];
        }

        if (isset($options['address'])) {
            $body['address'] = $options['address'];
        }

        if ($body === []) {
            $this->out->error('Usage: locations:update <id> --code=... --address=...');
            return;
        }

        $req = $this->http->patch("/locations/{$id}", $body);

        if ($req['status'] === 200) {
            $this->out->success($req['body']['message'] ?? 'Location updated successfully');
            $this->out->detail($req['body']['data'] ?? []);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function delete(array $args): void
    {
        $id = $args[0] ?? null;

        if ($id === null || !ctype_digit((string) $id) || (int) $id < 1) {
            $this->out->error('Usage: locations:delete <positive location id>');
            return;
        }

        $req = $this->http->delete("/locations/{$id}");

        if ($req['status'] === 200) {
            $this->out->success($req['body']['message'] ?? 'Location deleted successfully');
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

}
