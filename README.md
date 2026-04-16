# bug-tracker-client-bundle

[![CI](https://github.com/tuimedia/bug-tracker-client-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/tuimedia/bug-tracker-client-bundle/actions/workflows/ci.yml)

Symfony bundle for consumer projects (explore-work, skills-nav, convo) to proxy bug reports to the Tui bug tracker. Runs server-side so the API key never reaches the browser and reporter identity cannot be spoofed.

## How it works

The bundle registers a catch-all proxy route under a configurable prefix (default `/api/feedback`). Any request to `/api/feedback/{path}` is forwarded to the tracker at `/api/{path}` with the API key attached. On write requests (POST/PUT/PATCH), `reporterEmail` is stripped from the incoming payload and replaced with the authenticated user's identifier — everything else passes through as-is so new tracker fields work without a bundle update.

```
Browser → POST /api/feedback/tickets
            ↓ (ROLE_FEEDBACK check)
            ↓ strip reporterEmail, inject from session
          POST /api/tickets  →  bug tracker
```

## Installation

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
| POST | `/api/feedback/tickets` | `POST /api/tickets` |
| POST | `/api/feedback/attachments/presign` | `POST /api/attachments/presign` |
| GET, DELETE, … | `/api/feedback/{anything}` | `/api/{anything}` |

The prefix is set at import time — no bundle config needed.

## Security

- All routes require the configured role (`ROLE_FEEDBACK` by default).
- On write requests, `reporterEmail` is always overwritten with `$user->getUserIdentifier()` from the authenticated session. The caller cannot spoof it.
- The API key is injected server-side via `Authorization: Bearer`; it never appears in responses or logs.

## Requirements

- PHP 8.3+
- Symfony 7.x

## Running tests

```bash
composer install
vendor/bin/phpunit
```
