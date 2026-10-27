<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Product;

class LowStockMailer
{
    private const HOST = 'localhost';
    private const PORT = 1025;
    private const FROM = 'alerts@stocktracker.local';
    private const TO = 'warehouse@stocktracker.local';

    public static function send(Product $product, Location $location, int $currentStock): void
    {
        $shortage = $product->min_stock - $currentStock;

        $body = implode("\r\n", [
            "Low stock alert",
            "",
            "Product:   {$product->sku} - {$product->name}",
            "Location:  {$location->code}",
            "On hand:   {$currentStock}",
            "Minimum:   {$product->min_stock}",
            "Shortage:  {$shortage}",
        ]);

        $smtp = fsockopen(self::HOST, self::PORT);
        if ($smtp === false) {
            return;
        }

        $commands = [
            'EHLO stocktracker.local',
            'MAIL FROM:<' . self::FROM . '>',
            'RCPT TO:<' . self::TO . '>',
            'DATA',
            "Subject: Low stock: {$product->sku}\r\n\r\n{$body}\r\n.",
            'QUIT',
        ];

        foreach ($commands as $command) {
            fwrite($smtp, $command . "\r\n");
            fgets($smtp);
        }

        fclose($smtp);
    }
}
