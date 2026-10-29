<?php
declare(strict_types=1);

namespace Core\Http;

use Core\Exceptions\HttpException;
use Core\Http\ClientResponse;

class HttpClient
{
    public function __construct(
        private int $timeout = 5,
        private int $connectTimeout = 2,
        private array $defaultHeaders = []
    ) {
    }

    public function get(string $url, array $headers = []): ClientResponse
    {
        return $this->request('GET', $url, null, $headers);
    }

    public function post(string $url, mixed $body = null, array $headers = []): ClientResponse
    {
        return $this->request('POST', $url, $body, $headers);
    }

    public function put(string $url, mixed $body = null, array $headers = []): ClientResponse
    {
        return $this->request('PUT', $url, $body, $headers);
    }

    public function patch(string $url, mixed $body = null, array $headers = []): ClientResponse
    {
        return $this->request('PATCH', $url, $body, $headers);
    }

    public function delete(string $url, array $headers = []): ClientResponse
    {
        return $this->request('DELETE', $url, null, $headers);
    }

    public function request(string $method, string $url, mixed $body = null, array $headers = []): ClientResponse
    {
        $headers = $headers + $this->defaultHeaders;
        $lowered = array_change_key_case($headers, CASE_LOWER);

        $lines = ['Expect:'];
        $payload = null;

        if (is_array($body)) {
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!isset($lowered['content-type'])) {
                $lines[] = 'Content-Type: application/json';
            }
        } elseif (is_string($body)) {
            $payload = $body;
        }

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . str_replace(["\r", "\n"], '', (string) $value);
        }

        $responseHeaders = [];

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $lines,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }

        $result = curl_exec($curl);
        $errno = curl_errno($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($result === false) {
            throw $errno === CURLE_OPERATION_TIMEDOUT
                ? HttpException::gatewayTimeout('Upstream service timed out')
                : HttpException::badGateway('Upstream service unreachable');
        }

        return new ClientResponse($status, (string) $result, $responseHeaders);
    }
}