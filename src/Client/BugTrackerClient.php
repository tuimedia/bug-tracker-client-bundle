<?php

namespace Tui\BugTrackerBundle\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class BugTrackerClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function post(string $path, array $payload): ResponseInterface
    {
        return $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . $path, [
            'auth_bearer' => $this->apiKey,
            'json' => $payload,
        ]);
    }
}
