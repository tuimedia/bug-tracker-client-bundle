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
     * Submit a ticket to the tracker.
     *
     * $payload is the merged body (frontend fields + server-side enrichment).
     * The tracker's own validation is the schema authority — this method does
     * no field-level validation so new optional tracker fields pass through
     * without a bundle update.
     *
     * @param array<string, mixed> $payload
     */
    public function submitTicket(array $payload): ResponseInterface
    {
        return $this->httpClient->request('POST', $this->url('/api/tickets'), [
            'auth_bearer' => $this->apiKey,
            'json' => $payload,
        ]);
    }

    /**
     * Request a presigned S3 PUT URL for a screenshot upload.
     */
    public function presignScreenshot(string $contentType, string $filename): ResponseInterface
    {
        return $this->httpClient->request('POST', $this->url('/api/attachments/presign'), [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'contentType' => $contentType,
                'filename' => $filename,
            ],
        ]);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . $path;
    }
}
