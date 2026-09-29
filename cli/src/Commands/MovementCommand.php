<?php

namespace StockTracker\Cli\Commands;

use StockTracker\Cli\HttpClient;
use StockTracker\Cli\Output;

class MovementCommand {

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

        if (isset($options['product-id'])) {
            $query['product_id'] = $options['product-id'];
        }

        if (isset($options['location-id'])) {
            $query['location_id'] = $options['location-id'];
        }

        if (isset($options['type'])) {
            $type = strtoupper((string) $options['type']);

            if (!in_array($type, ['IN', 'OUT', 'ADJUST'], true)) {
                $this->out->error('Invalid type. Use IN, OUT, or ADJUST.');
                return;
            }

            $query['type'] = $type;
        }

        if (isset($options['page'])) {
            $query['page'] = $options['page'];
        }

        if (isset($options['per-page'])) {
            $query['per_page'] = $options['per-page'];
        }

        $req = $this->http->get('/movements', $query);

        if ($req['status'] === 200) {
            $movements = $req['body']['data']['data'] ?? [];
            $this->out->table($movements, ['id', 'product_id', 'location_id', 'type', 'qty', 'note', 'created_at']);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function create(array $options): void
    {
        $required = ['product-id', 'location-id', 'type', 'qty'];

        foreach ($required as $option) {
            if (!isset($options[$option]) || $options[$option] === '') {
                $this->out->error('Usage: movements:create --product-id=... --location-id=... --type=IN|OUT|ADJUST --qty=... [--note=...]');
                return;
            }
        }

        $productId = filter_var($options['product-id'], FILTER_VALIDATE_INT);
        $locationId = filter_var($options['location-id'], FILTER_VALIDATE_INT);
        $qty = filter_var($options['qty'], FILTER_VALIDATE_INT);
        $type = strtoupper((string) $options['type']);

        if ($productId === false || $productId < 1 || $locationId === false || $locationId < 1) {
            $this->out->error('Product ID and location ID must be positive integers.');
            return;
        }

        if (!in_array($type, ['IN', 'OUT', 'ADJUST'], true)) {
            $this->out->error('Invalid type. Use IN, OUT, or ADJUST.');
            return;
        }

        if ($qty === false || $qty === 0) {
            $this->out->error('Quantity must be a non-zero integer.');
            return;
        }

        $body = [
            'product_id' => $productId,
            'location_id' => $locationId,
            'type' => $type,
            'qty' => $qty,
        ];

        if (isset($options['note']) && $options['note'] !== '') {
            $body['note'] = $options['note'];
        }

        $req = $this->http->post('/movements', $body);

        if ($req['status'] === 201) {
            $this->out->success($req['body']['message'] ?? 'Movement added successfully');
            $this->out->detail($req['body']['data'] ?? []);

            if (isset($req['body']['current_stock'])) {
                $this->out->info("Current stock: {$req['body']['current_stock']}");
            }

            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }
}