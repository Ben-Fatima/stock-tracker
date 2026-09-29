<?php

namespace StockTracker\Cli;

class ArgParser
{
    public static function parse(array $argv): array
    {
        $parts = array_slice($argv, 1);
        $options = [];
        $arguments = [];

        if (count($parts) === 0) {
            return [
                'command' => '',
                'options' => $options,
                'arguments' => $arguments,
            ];
        }

        $command = $parts[0];

        for ($i = 1, $length = count($parts); $i < $length; $i++) {
            $part = $parts[$i];

            if (!str_starts_with($part, '--')) {
                $arguments[] = $part;
                continue;
            }

            $option = substr($part, 2);

            if (str_contains($option, '=')) {
                [$key, $value] = explode('=', $option, 2);
                $options[$key] = $value;
                continue;
            }

            $options[$option] = true;
        }

        return [
            'command' => $command,
            'arguments' => $arguments,
            'options' => $options,
        ];
    }
}
