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

        $query = $request->query->all();

        // Override reporterEmail in query string for GET requests — prevents spoofing.
        if (array_key_exists('reporterEmail', $query)) {
            $query['reporterEmail'] = $this->tokenStorage->getToken()?->getUser()?->getUserIdentifier();
        }

        $options = ['query' => $query];

        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            try {
                $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return new JsonResponse(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
            }

            // Only override reporterEmail if the caller sent it — prevents spoofing
            // without injecting the field into endpoints that don't expect it.
            if (array_key_exists('reporterEmail', $payload)) {
                $payload['reporterEmail'] = $this->tokenStorage->getToken()?->getUser()?->getUserIdentifier();
            }

            $options['json'] = $payload;
        }

        $response = $this->client->request($request->getMethod(), '/api/' . $path, $options);

        $content = $response->getContent(throw: false);
        $decoded = $content !== '' ? json_decode($content, true) : null;

        if ($decoded === null && $content !== '') {
            return new Response($content, $response->getStatusCode(), ['Content-Type' => 'text/html']);
        }

        return new JsonResponse($decoded, $response->getStatusCode());
    }
}
