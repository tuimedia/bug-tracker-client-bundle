<?php

namespace Tui\BugTrackerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BugTrackerProxyController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $requiredRole,
    ) {
    }

    public function proxy(string $path, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->requiredRole);

        $options = ['query' => $request->query->all()];

        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            try {
                $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return new JsonResponse(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
            }

            // Strip reporterEmail — must come from the authenticated session to
            // prevent spoofing. Everything else passes through as-is.
            unset($payload['reporterEmail']);
            $payload['reporterEmail'] = $this->getUser()->getUserIdentifier();

            $options['json'] = $payload;
        }

        $response = $this->client->request($request->getMethod(), '/api/' . $path, $options);

        $content = $response->getContent(throw: false);

        return new JsonResponse(
            $content !== '' ? json_decode($content, true) : null,
            $response->getStatusCode(),
        );
    }
}
