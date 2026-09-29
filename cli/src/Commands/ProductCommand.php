<?php

namespace StockTracker\Cli\Commands;

use StockTracker\Cli\HttpClient;
use StockTracker\Cli\Output;

class ProductCommand {

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

        $req = $this->http->get('/products', $query);

        if ($req['status'] === 200) {
            $products = $req['body']['data']['data'] ?? [];
            $this->out->table($products, ['id', 'sku', 'name', 'unit_cost_cents', 'min_stock']);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function show(array $args): void
    {
        $id = $args[0] ?? null;

        if ($id === null || !ctype_digit((string) $id) || (int) $id < 1) {
            $this->out->error('Usage: products:show <positive product id>');
            return;
        }

        $req = $this->http->get("/products/{$id}");

        if ($req['status'] === 200) {
            $this->out->success($req['body']['message'] ?? 'Product retrieved successfully');
            $this->out->detail($req['body']['data'] ?? []);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function create(array $options): void
    {
        $required = ['sku', 'name', 'unit-cost-cents', 'min-stock'];

        foreach ($required as $option) {
            if (!isset($options[$option]) || $options[$option] === '') {
                $this->out->error('Usage: products:create --sku=... --name=... --unit-cost-cents=... --min-stock=...');
                return;
            }
        }

        $body = [
            'sku' => $options['sku'],
            'name' => $options['name'],
            'unit_cost_cents' => (int) $options['unit-cost-cents'],
            'min_stock' => (int) $options['min-stock'],
        ];

        $req = $this->http->post('/products', $body);

        if ($req['status'] === 201) {
            $this->out->success($req['body']['message'] ?? 'Product created successfully');
            $this->out->detail($req['body']['data'] ?? []);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function update(array $args, array $options): void
    {
        $id = $args[0] ?? null;

        if ($id === null || !ctype_digit((string) $id) || (int) $id < 1) {
            $this->out->error('Usage: products:update <positive product id> [options]');
            return;
        }

        $body = [];

        if (isset($options['sku'])) {
            $body['sku'] = $options['sku'];
        }

        if (isset($options['name'])) {
            $body['name'] = $options['name'];
        }

        if (isset($options['unit-cost-cents'])) {
            $body['unit_cost_cents'] = (int) $options['unit-cost-cents'];
        }

        if (isset($options['min-stock'])) {
            $body['min_stock'] = (int) $options['min-stock'];
        }

        if ($body === []) {
            $this->out->error('Usage: products:update <id> --sku=... --name=... --unit-cost-cents=... --min-stock=...');
            return;
        }

        $req = $this->http->patch("/products/{$id}", $body);

        if ($req['status'] === 200) {
            $this->out->success($req['body']['message'] ?? 'Product updated successfully');
            $this->out->detail($req['body']['data'] ?? []);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function delete(array $args): void
    {
        $id = $args[0] ?? null;

        if ($id === null || !ctype_digit((string) $id) || (int) $id < 1) {
            $this->out->error('Usage: products:delete <positive product id>');
            return;
        }

        $req = $this->http->delete("/products/{$id}");

        if ($req['status'] === 200) {
            $this->out->success($req['body']['message'] ?? 'Product deleted successfully');
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

}