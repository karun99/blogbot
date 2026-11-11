# Architecture

BlogWriter has two deliverables in one repository: a WordPress plugin and a
self-hostable web app that schedules and generates blog posts with a
bring-your-own OpenRouter key.

## Components

| Component | Path | Responsibility |
|---|---|---|
| Web server | `web-app/server.js` | Express bootstrap; mounts the app and starts the scheduler outside tests |
| App factory | `web-app/src/app.js` | Middleware, routes, static assets |
| Routes | `web-app/src/routes.js` | Job CRUD, generation, export and admin endpoints |
| Validation | `web-app/src/validator.js` | Input sanitisation, enum allow-lists, API-key checks |
| Encryption | `web-app/src/encryption.js` | AES-256-GCM at rest for stored keys |
| Storage | `web-app/src/db.js` | JSON-backed job and post store |
| Scheduler | `web-app/src/scheduler.js` | Interval-driven generation ticks |
| AI client | `web-app/src/ai.js` | OpenRouter request/response handling |
| OCR | `web-app/src/ocr.js` | Source-document text extraction |
| WordPress plugin | `wordpress-plugin/` | In-site publishing integration |

## Request flow

1. A create-job request is sanitised by `validator.js` before it reaches the
   store, so unknown enum values and out-of-range numbers cannot persist.
2. API keys are encrypted with AES-256-GCM and a fresh IV per record. A tampered
   ciphertext decrypts to an empty string rather than garbage.
3. The scheduler wakes on `SCHEDULER_TICK_MS`, finds due jobs, calls the AI
   client and writes drafts or published posts according to the job settings.
4. Routes expose job state, generated posts and an admin surface guarded by
   `ADMIN_TOKEN`.

## Trust boundaries

- The OpenRouter key is supplied by the user and stored encrypted; it is never
  returned to the browser in plaintext.
- Every generated post is attributable to a job and a source document.
- The scheduler is disabled when `NODE_ENV=test`, so tests never spend tokens.

## Verification

```bash
cd web-app
npm install
npm test
```

The suite covers input clamping, enum fallbacks, URL validation, key validation
and the encrypted round-trip, including tamper rejection. CI runs the same
command on every push.
