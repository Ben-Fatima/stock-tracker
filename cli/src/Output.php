<?php

namespace StockTracker\Cli;

class Output {

    /**
     * colors
     */
    private const GREEN = "\033[32m";
    private const RED = "\033[31m";
    private const YELLOW = "\033[33m";
    private const RESET = "\033[0m";

    public function success(string $msg): void {
        echo self::GREEN . $msg . self::RESET . PHP_EOL;
    }

    public function info(string $msg): void {
        echo self::YELLOW . $msg . self::RESET . PHP_EOL;
    }

    public function error(string $msg): void {
        echo self::RED . $msg . self::RESET . PHP_EOL;
    }

    public function detail(array $record): void
    {
        foreach ($record as $key => $value) {
            $label = str_pad(ucfirst((string) $key) . ':', 20);
            $value = is_scalar($value) || $value === null
                ? (string) $value
                : print_r($value, true);

            echo $label . ' ' . $value . PHP_EOL;
        }
    }

    public function table(array $rows, array $columns): void
    {
        if ($rows === []) {
            echo '(no records)' . PHP_EOL;
            return;
        }

        $widths = [];

        foreach ($columns as $column) {
            $widths[$column] = strlen((string) $column);
        }

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $value = (string) ($row[$column] ?? '');
                $widths[$column] = max($widths[$column], strlen($value));
            }
        }

        $header = '';
        foreach ($columns as $column) {
            $header .= str_pad(ucfirst((string) $column), $widths[$column] + 2);
        }

        echo $header . PHP_EOL;
        echo str_repeat('-', strlen($header)) . PHP_EOL;

        foreach ($rows as $row) {
            $line = '';

            foreach ($columns as $column) {
                $value = (string) ($row[$column] ?? '');
                $line .= str_pad($value, $widths[$column] + 2);
            }

            echo $line . PHP_EOL;
        }
    }
}