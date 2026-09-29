<?php

namespace StockTracker\Cli\Commands;

use StockTracker\Cli\HttpClient;
use StockTracker\Cli\Output;

class StockCommand {

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

        if (isset($options['sku'])) {
            $query['sku'] = $options['sku'];
        }

        if (isset($options['location'])) {
            $query['location'] = $options['location'];
        }

        $this->addPaginationOptions($query, $options);

        $req = $this->http->get('/stocks', $query);

        if ($req['status'] === 200) {
            $this->printStockTable($req['body']['data']['data'] ?? []);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function product(array $args, array $options): void
    {
        $id = $args[0] ?? null;

        if (!$this->isPositiveId($id)) {
            $this->out->error('Usage: stocks:product <positive product id> [--page=...] [--per-page=...]');
            return;
        }

        $query = [];
        $this->addPaginationOptions($query, $options);

        $req = $this->http->get("/products/{$id}/stocks", $query);

        if ($req['status'] === 200) {
            $this->printStockTable($req['body']['data']['data'] ?? []);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function location(array $args, array $options): void
    {
        $id = $args[0] ?? null;

        if (!$this->isPositiveId($id)) {
            $this->out->error('Usage: stocks:location <positive location id> [--page=...] [--per-page=...]');
            return;
        }

        $query = [];
        $this->addPaginationOptions($query, $options);

        $req = $this->http->get("/locations/{$id}/stocks", $query);

        if ($req['status'] === 200) {
            $this->printStockTable($req['body']['data']['data'] ?? []);
            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    public function lowStock(array $options): void
    {
        $query = [];
        $this->addPaginationOptions($query, $options);

        $req = $this->http->get('/reports/low-stock', $query);

        if ($req['status'] === 200) {
            $this->printStockTable($req['body']['data']['data'] ?? []);

            if (isset($req['body']['threshold'])) {
                $this->out->info("Report threshold: {$req['body']['threshold']}");
            }

            return;
        }

        $this->out->error($req['body']['message'] ?? 'Request failed');
    }

    private function addPaginationOptions(array &$query, array $options): void
    {
        if (isset($options['page'])) {
            $query['page'] = $options['page'];
        }

        if (isset($options['per-page'])) {
            $query['per_page'] = $options['per-page'];
        }
    }

    private function isPositiveId($id): bool
    {
        return $id !== null && ctype_digit((string) $id) && (int) $id > 0;
    }

    private function printStockTable(array $rows): void
    {
        $this->out->table($rows, ['product_id', 'location_id', 'sku', 'code', 'stock_on_hand']);
    }

}
