<?php

namespace Tui\BugTrackerBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Tui\BugTrackerBundle\Controller\BugTrackerProxyController;

class BugTrackerProxyControllerTest extends TestCase
{
    private function makeController(
        MockHttpClient $httpClient,
        string $role = 'ROLE_FEEDBACK',
        string $userEmail = 'user@example.com',
        bool $isGranted = true,
    ): BugTrackerProxyController {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn($userEmail);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn($isGranted);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
        ]);

        $controller = new BugTrackerProxyController($httpClient, $role);
        $controller->setContainer($container);

        return $controller;
    }

    public function testPostStripsAndInjectsReporterEmail(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = ['method' => $method, 'body' => json_decode($options['body'], true)];
            return new MockResponse('{"status":"ok"}', ['http_code' => 201]);
        });

        $request = Request::create('/api/feedback/tickets', 'POST', content: json_encode([
            'title' => 'Something is broken',
            'reporterEmail' => 'spoofed@evil.com',
        ]));

        $response = $this->makeController($httpClient, userEmail: 'real@example.com')
            ->proxy('tickets', $request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('real@example.com', $captured['body']['reporterEmail']);
        $this->assertSame('Something is broken', $captured['body']['title']);
    }

    public function testReporterEmailNotInjectedWhenAbsent(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = json_decode($options['body'], true);
            return new MockResponse('{"status":"ok"}', ['http_code' => 201]);
        });

        $request = Request::create('/api/feedback/attachments/presign', 'POST', content: json_encode([
            'contentType' => 'image/png',
            'filename' => 'screenshot.png',
        ]));

        $this->makeController($httpClient)->proxy('attachments/presign', $request);

        $this->assertArrayNotHasKey('reporterEmail', $captured);
    }

    public function testPostPassesOtherFieldsThrough(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = json_decode($options['body'], true);
            return new MockResponse('{"status":"ok"}', ['http_code' => 201]);
        });

        $request = Request::create('/api/feedback/tickets', 'POST', content: json_encode([
            'title' => 'Bug',
            'stepsToReproduce' => 'Click the button',
            'submittedUrl' => 'https://app.example.com/page',
            'reporterName' => 'Alice',
            'reporterUserIdInSource' => 'user-123',
            'tagIds' => [1, 2],
            'unknownFutureField' => 'passes through',
        ]));

        $this->makeController($httpClient)->proxy('tickets', $request);

        $this->assertSame('Bug', $captured['title']);
        $this->assertSame('Click the button', $captured['stepsToReproduce']);
        $this->assertSame('user-123', $captured['reporterUserIdInSource']);
        $this->assertSame([1, 2], $captured['tagIds']);
        $this->assertSame('passes through', $captured['unknownFutureField']);
    }

    public function testGetForwardsQueryParamsWithNoBody(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? null];
            return new MockResponse('{"items":[]}', ['http_code' => 200]);
        });

        $request = Request::create('/api/feedback/tickets', 'GET', ['status' => 'open', 'page' => '2']);

        $this->makeController($httpClient)->proxy('tickets', $request);

        $this->assertSame('GET', $captured['method']);
        $this->assertStringContainsString('status=open', $captured['url']);
        $this->assertStringContainsString('page=2', $captured['url']);
        $this->assertNull($captured['body']);
    }

    public function testDeleteForwardedWithoutBody(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = ['method' => $method, 'body' => $options['body'] ?? null];
            return new MockResponse('', ['http_code' => 204]);
        });

        $request = Request::create('/api/feedback/tickets/42', 'DELETE');

        $response = $this->makeController($httpClient)->proxy('tickets/42', $request);

        $this->assertSame('DELETE', $captured['method']);
        $this->assertNull($captured['body']);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testTrackerErrorStatusIsForwarded(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"errors":{"title":"This value should not be blank."}}', ['http_code' => 422]),
        ]);

        $request = Request::create('/api/feedback/tickets', 'POST', content: json_encode(['title' => '']));

        $response = $this->makeController($httpClient)->proxy('tickets', $request);

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
    }

    public function testEmptyResponseBodyHandled(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 204]),
        ]);

        $request = Request::create('/api/feedback/tickets/42', 'DELETE');

        $response = $this->makeController($httpClient)->proxy('tickets/42', $request);

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testInvalidJsonBodyReturns400(): void
    {
        $httpClient = new MockHttpClient();

        $request = Request::create('/api/feedback/tickets', 'POST', content: 'not json {{{');

        $response = $this->makeController($httpClient)->proxy('tickets', $request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testRoleCheckUsesConfiguredRole(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user@example.com');
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_CUSTOM')
            ->willReturn(true);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
        ]);

        $httpClient = new MockHttpClient([new MockResponse('{}', ['http_code' => 200])]);
        $controller = new BugTrackerProxyController($httpClient, 'ROLE_CUSTOM');
        $controller->setContainer($container);

        $request = Request::create('/api/feedback/tickets', 'GET');
        $controller->proxy('tickets', $request);
    }

    public function testAccessDeniedWhenRoleMissing(): void
    {
        $this->expectException(AccessDeniedException::class);

        $httpClient = new MockHttpClient();
        $request = Request::create('/api/feedback/tickets', 'GET');

        $this->makeController($httpClient, isGranted: false)->proxy('tickets', $request);
    }
}
