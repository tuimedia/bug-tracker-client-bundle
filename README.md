# bug-tracker-client-bundle

[![CI](https://github.com/tuimedia/bug-tracker-client-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/tuimedia/bug-tracker-client-bundle/actions/workflows/ci.yml)

Symfony bundle for consumer projects to proxy bug reports to a bug tracker instance. Runs server-side so the API key never reaches the browser and reporter identity cannot be spoofed.

## How it works

The bundle registers a catch-all proxy route under a configurable prefix (default `/api/feedback`). A request to `/api/feedback/{path}` is forwarded to the tracker at `/api/{path}` with the API key attached, but only when `{path}` falls under `public/tickets` or `public/attachments` — anything else gets a 404 without reaching the tracker. On write requests (POST/PUT/PATCH), `reporterEmail` is stripped from the incoming payload and replaced with the authenticated user's identifier — everything else passes through as-is so new tracker fields work without a bundle update.

```
Browser → POST /api/feedback/public/tickets
            ↓ (ROLE_FEEDBACK check)
            ↓ strip reporterEmail, inject from session
          POST /api/public/tickets  →  bug tracker
```

## Installation

### With Symfony Flex (Flex 2.x, recommended)

Add the bundle's recipe endpoint to your project's `composer.json` before requiring:

```json
"extra": {
    "symfony": {
        "endpoint": [
            "github://tuimedia/bug-tracker-client-bundle:main",
            "flex://defaults"
        ]
    }
}
```

Then:

```bash
composer require tuimedia/bug-tracker-client-bundle
```

Flex will automatically register the bundle, create `config/packages/tui_bug_tracker.yaml`, `config/routes/tui_bug_tracker.yaml`, and add the env vars to `.env`.

### Without Flex (or Flex 1.x)

```bash
composer require tuimedia/bug-tracker-client-bundle
```

Register the bundle in `config/bundles.php`:

```php
Tui\BugTrackerBundle\TuiBugTrackerBundle::class => ['all' => true],
```

## Configuration

Create `config/packages/tui_bug_tracker.yaml`:

```yaml
tui_bug_tracker:
    base_url: '%env(BUG_TRACKER_BASE_URL)%'
    api_key: '%env(BUG_TRACKER_API_KEY)%'
    # required_role: ROLE_FEEDBACK  # default — change if your app uses a different role name
```

Add to your `.env`:

```dotenv
BUG_TRACKER_BASE_URL=https://bugs.example.com
BUG_TRACKER_API_KEY=your-project-api-key
```

Import the bundle routes in `config/routes/tui_bug_tracker.yaml`:

```yaml
tui_bug_tracker:
    resource: '@TuiBugTrackerBundle/Resources/config/routes.yaml'
    prefix: /api/feedback   # change this prefix if needed
```

## Routes registered

| Method | Consumer path | Forwards to |
|--------|--------------|-------------|
| POST | `/api/feedback/public/tickets` | `POST /api/public/tickets` |
| GET | `/api/feedback/public/tickets/mine[/{id}]` | `GET /api/public/tickets/mine[/{id}]` |
| POST | `/api/feedback/public/attachments/presign` | `POST /api/public/attachments/presign` |
| GET | `/api/feedback/public/attachments/mine/{id}` | `GET /api/public/attachments/mine/{id}` |
| GET, POST, PUT, PATCH, DELETE | `/api/feedback/public/tickets/…`, `/api/feedback/public/attachments/…` | `/api/public/tickets/…`, `/api/public/attachments/…` |

Anything outside `public/tickets` and `public/attachments` is rejected with a 404 before it reaches the tracker.

The prefix is set at import time — no bundle config needed.

## Security

- All routes require the configured role (`ROLE_FEEDBACK` by default).
- Only paths under `public/tickets` and `public/attachments` are forwarded; anything else gets a 404 before it reaches the tracker.
- `GET public/tickets/mine` and `POST public/tickets` always carry the authenticated user's `reporterEmail`, so a caller can't drop the field to read or file tickets as someone else. Elsewhere, if `reporterEmail` is present in the payload it's overwritten with `$user->getUserIdentifier()` from the session — the caller cannot spoof it.
- The API key is injected server-side via `Authorization: Bearer`; it never appears in responses or logs.

## Requirements

- PHP 8.3+
- Symfony 6.4 or 7.x

## Running tests

```bash
composer install
vendor/bin/phpunit
```
