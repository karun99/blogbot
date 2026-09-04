# BlogWriter — Cloud Web App

Standalone, cloud-deployable version of **BlogWriter** — automated AI blog content
generation using **OpenRouter BYOK** (Bring Your Own Key). Same engine as the
WordPress plugin, packaged as a Node.js/Express app that runs anywhere:
Vercel, Netlify, Railway, Render, Fly.io, or any Docker host.

## Features

- **Job-based automation** — create jobs with brand voice, reference URLs,
  content requirements, word count, tone, SEO keywords
- **Scheduled publishing** — intervals in minutes / hours / days / weeks
- **Auto-fallback AI** — tries your preferred model, then 25+ free OpenRouter models
- **OCR extraction** — PDF, DOCX, TXT, JPG, PNG via OCR.space / Baidu / self-hosted
- **Generated posts** — saved as JSON under `data/posts/<jobId>/`
- **Security** — AES-256-GCM encryption for API keys, optional admin token,
  rate limiting, hardened headers

## Tech Stack

- **Backend** — Node.js 18+, Express, Multer
- **Frontend** — Vanilla HTML/CSS/JS (no framework)
- **Storage** — JSON files (ephemeral on serverless; persistent with volume on Docker)
- **AI** — OpenRouter REST API
- **Deployment** — Docker / docker-compose / Vercel / Netlify / any Node host

## Quick Start (local)

```bash
npm install
cp .env.example .env     # add your OPENROUTER_API_KEY
npm start                # http://localhost:8080
```

## Docker

```bash
docker build -t blogwriter-web .
docker run -p 8080:8080 \
  -e OPENROUTER_API_KEY=sk-or-v1-... \
  -e BLOGWRITER_ENCRYPTION_KEY=your-long-random-key \
  -v $(pwd)/data:/app/data \
  blogwriter-web
```

Or with docker-compose:

```bash
cp .env.example .env
docker compose up --build
```

## Deploy to a cloud platform

### Vercel

Import the repo; Vercel will use `vercel.json` (serverless entry: `api/index.js`).
Set env vars `OPENROUTER_API_KEY`, `BLOGWRITER_ENCRYPTION_KEY`, `ADMIN_TOKEN`.
Note: serverless file storage is ephemeral — use a persistent volume / DB adapter
for production data. Trigger generation on demand via the API.

### Netlify

Import the repo; Netlify uses `netlify.toml` (function: `netlify/functions/api.js`).
Install `serverless-http` (`npm i serverless-http`) if not bundled.

### Railway / Render / Fly.io

Run the Dockerfile or `npm start` with `PORT` set by the platform. Add a persistent
volume mounted at `/app/data`.

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `OPENROUTER_API_KEY` | — | OpenRouter key (BYOK) |
| `OPENROUTER_MODEL` | `meta-llama/llama-3.1-8b-instruct:free` | Preferred model |
| `OCR_ENDPOINT` | `https://api.ocr.space/parse/image` | OCR.space / Baidu / self-hosted |
| `OCR_API_KEY` | — | OCR API key |
| `BLOGWRITER_ENCRYPTION_KEY` | dev default | AES-256-GCM key for API keys at rest |
| `ADMIN_TOKEN` | — | When set, `/api/*` requires header `x-admin-token` |
| `SCHEDULER_TICK_MS` | `60000` | Scheduler tick for due jobs |
| `PORT` | `8080` | HTTP port |
| `DATA_DIR` | `./data` | Persistence directory |

## API

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/status` | App + settings status |
| GET | `/api/settings` | Runtime settings |
| POST | `/api/settings` | Set API key / model |
| GET | `/api/jobs` | List jobs |
| POST | `/api/jobs` | Create a job |
| GET | `/api/jobs/:id` | Job detail + generated posts |
| POST | `/api/jobs/:id/run` | Generate now |
| POST | `/api/jobs/:id/pause` | Pause job |
| POST | `/api/jobs/:id/resume` | Resume job |
| DELETE | `/api/jobs/:id` | Delete job |
| POST | `/api/ocr` | Upload a document for OCR (`multipart/form-data`, field `document`) |

Example — create a job:

```bash
curl -X POST http://localhost:8080/api/jobs \
  -H "Content-Type: application/json" \
  -d '{
    "job_name": "MAS Tech Weekly",
    "brand_name": "MAS Technology",
    "requirements": "Write about AI trends with statistics.",
    "num_posts": 3,
    "interval_value": 24,
    "interval_unit": "hours",
    "post_status": "draft"
  }'
```

## Structure

```
web-app/
├── server.js              # Standalone entry
├── api/index.js           # Vercel serverless entry
├── netlify/functions/api.js  # Netlify serverless entry
├── Dockerfile
├── docker-compose.yml
├── vercel.json
├── netlify.toml
├── .env.example
├── src/
│   ├── app.js             # Express app factory
│   ├── routes.js          # REST API
│   ├── config.js          # Env config
│   ├── db.js              # JSON persistence
│   ├── encryption.js      # AES-256-GCM
│   ├── validator.js       # Input sanitization
│   ├── ai.js              # OpenRouter client + fallback
│   ├── ocr.js             # OCR + DOCX parsing
│   └── scheduler.js       # Interval scheduler
└── public/
    ├── index.html         # Admin dashboard
    ├── app.js
    └── style.css
```

## License

MIT — Copyright (c) 2026 Guiding Key Technologies.
