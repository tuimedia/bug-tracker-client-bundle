<?php

namespace Tui\BugTrackerBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BugTrackerProxyController
{
    private const ALLOWED_PATH_PATTERN = '#^public/(tickets|attachments)(/|$)#';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $requiredRole,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function proxy(string $path, Request $request): Response
    {
        if (!$this->authorizationChecker->isGranted($this->requiredRole)) {
            throw new AccessDeniedException();
        }

        if (!preg_match(self::ALLOWED_PATH_PATTERN, $path)) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $query = $request->query->all();

        // tickets/mine has no per-reporter scoping upstream beyond this param, so it's
        // always forced to the caller rather than only overridden when present.
        if ($path === 'public/tickets/mine') {
            $query['reporterEmail'] = $this->tokenStorage->getToken()?->getUser()?->getUserIdentifier();
        } elseif (array_key_exists('reporterEmail', $query)) {
            $query['reporterEmail'] = $this->tokenStorage->getToken()?->getUser()?->getUserIdentifier();
        }

        $options = ['query' => $query, 'max_redirects' => 0];

        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            try {
                $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return new JsonResponse(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
            }

            // Ticket creation has no NotBlank on reporterEmail upstream, so it's always
            // forced to the caller rather than only overridden when present. Elsewhere,
            // only override reporterEmail if the caller sent it — prevents spoofing
            // without injecting the field into endpoints that don't expect it.
            if ($path === 'public/tickets') {
                $payload['reporterEmail'] = $this->tokenStorage->getToken()?->getUser()?->getUserIdentifier();
            } elseif (array_key_exists('reporterEmail', $payload)) {
                $payload['reporterEmail'] = $this->tokenStorage->getToken()?->getUser()?->getUserIdentifier();
            }

            $options['json'] = $payload;
        }

        $response = $this->client->request($request->getMethod(), '/api/' . $path, $options);

        $statusCode = $response->getStatusCode();

        // Pass redirects through — lets the browser follow presigned S3 URLs directly.
        if ($statusCode >= 300 && $statusCode < 400) {
            $location = $response->getHeaders(throw: false)['location'][0] ?? null;
            return new Response(null, $statusCode, array_filter(['Location' => $location]));
        }

        $content = $response->getContent(throw: false);
        $decoded = $content !== '' ? json_decode($content, true) : null;

        if ($decoded === null && $content !== '') {
            return new Response($content, $statusCode, ['Content-Type' => 'text/html']);
        }

        return new JsonResponse($decoded, $statusCode);
    }
}
