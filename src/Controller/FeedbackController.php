<?php

namespace Tui\BugTrackerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tui\BugTrackerBundle\Client\BugTrackerClient;

#[IsGranted('ROLE_FEEDBACK')]
class FeedbackController extends AbstractController
{
    public function __construct(private readonly BugTrackerClient $client)
    {
    }

    /**
     * Proxy ticket submissions to the bug tracker.
     *
     * The frontend payload passes through as-is — the tracker's SubmitTicketRequest
     * is the validation authority. The only bundle concern is:
     *   - strip reporter identity fields (prevent spoofing)
     *   - inject them from the authenticated server-side session
     *
     * This means new optional tracker fields work without a bundle update.
     */
    #[Route('/api/feedback/tickets', name: 'tui_bug_tracker_submit_ticket', methods: ['POST'])]
    public function submitTicket(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        // Strip fields that must come from the server — prevents the caller spoofing
        // the reporter identity.
        unset($payload['reporterEmail'], $payload['reporterUserIdInSource'], $payload['reporterName']);

        $user = $this->getUser();
        $payload['reporterEmail'] = $user->getUserIdentifier();

        $response = $this->client->submitTicket($payload);

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
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $response = $this->client->presignScreenshot(
            $payload['contentType'] ?? '',
            $payload['filename'] ?? '',
        );

        return new JsonResponse(
            $response->toArray(throw: false),
            $response->getStatusCode(),
        );
    }
}
