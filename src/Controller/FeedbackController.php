<?php

namespace Tui\BugTrackerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tui\BugTrackerBundle\Client\BugTrackerClient;

class FeedbackController extends AbstractController
{
    public function __construct(
        private readonly BugTrackerClient $client,
        private readonly string $requiredRole,
    ) {
    }

    /**
     * Proxy ticket submissions to the bug tracker.
     *
     * The frontend payload passes through as-is — the tracker's SubmitTicketRequest
     * is the validation authority. The only bundle concern is:
     *   - strip reporter identity fields (prevent spoofing)
     *   - inject reporterEmail from the authenticated server-side session
     *
     * This means new optional tracker fields work without a bundle update.
     */
    #[Route('/api/feedback/tickets', name: 'tui_bug_tracker_submit_ticket', methods: ['POST'])]
    public function submitTicket(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->requiredRole);

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        // Strip only the field the server must own — prevents the caller spoofing
        // their email address. All other fields (including reporterUserIdInSource,
        // which the consumer app knows from its own session) pass through as-is.
        unset($payload['reporterEmail']);

        $user = $this->getUser();
        $payload['reporterEmail'] = $user->getUserIdentifier();

        $response = $this->client->post('/api/tickets', $payload);

        return new JsonResponse(
            $response->toArray(throw: false),
            $response->getStatusCode(),
        );
    }

    /**
     * Proxy presign requests to the bug tracker. Pure pass-through — no
     * server-side enrichment needed; the API key identifies the project.
     */
    #[Route('/api/feedback/screenshot-url', name: 'tui_bug_tracker_screenshot_url', methods: ['POST'])]
    public function screenshotUrl(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->requiredRole);

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $response = $this->client->post('/api/attachments/presign', $payload);

        return new JsonResponse(
            $response->toArray(throw: false),
            $response->getStatusCode(),
        );
    }
}
