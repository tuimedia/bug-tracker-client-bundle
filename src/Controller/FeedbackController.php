<?php

namespace Tui\BugTrackerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FeedbackController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $requiredRole,
    ) {
    }

    #[Route('/api/feedback/tickets', name: 'tui_bug_tracker_submit_ticket', methods: ['POST'])]
    public function submitTicket(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->requiredRole);

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        // Strip only reporterEmail — must come from the authenticated session to
        // prevent spoofing. Everything else (including reporterUserIdInSource,
        // which the consumer app knows from its own session) passes through.
        unset($payload['reporterEmail']);
        $payload['reporterEmail'] = $this->getUser()->getUserIdentifier();

        $response = $this->client->request('POST', '/api/tickets', ['json' => $payload]);

        return new JsonResponse($response->toArray(throw: false), $response->getStatusCode());
    }

    #[Route('/api/feedback/screenshot-url', name: 'tui_bug_tracker_screenshot_url', methods: ['POST'])]
    public function screenshotUrl(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->requiredRole);

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $response = $this->client->request('POST', '/api/attachments/presign', ['json' => $payload]);

        return new JsonResponse($response->toArray(throw: false), $response->getStatusCode());
    }
}
