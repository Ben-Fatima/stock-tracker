<?php

namespace StockTracker\Cli;

class HttpClient {
    /**
     * @var string
     */
    protected string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @return array{status: int, body: array|null}
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * @return array{status: int, body: array|null}
     */
    public function post(string $path, array $body): array
    {
        return $this->request('POST', $path, [], $body);
    }

    /**
     * @return array{status: int, body: array|null}
     */
    public function patch(string $path, array $body): array
    {
        return $this->request('PATCH', $path, [], $body);
    }

    /**
     * @return array{status: int, body: array|null}
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * @return array{status: int, body: array|null}
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        array $body = []
    ): array {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $cn = curl_init($url);
        curl_setopt($cn, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cn, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($cn, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
        ]);

        if ($body !== []) {
            curl_setopt($cn, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($cn);
        $status = curl_getinfo($cn, CURLINFO_HTTP_CODE);
        $error = curl_error($cn);
        curl_close($cn);

        if ($error !== '') {
            return [
                'status' => 0,
                'body' => ['message' => 'Network error: ' . $error],
            ];
        }

        return [
            'status' => $status,
            'body' => json_decode($raw, true),
        ];
    }

}