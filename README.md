# BlogWriter · Guiding Key Technologies

WordPress Plugin + Cloud Web App for Automated Blog Content Generation using AI.

This repository ships BlogWriter in two deployable forms:

1. **`wordpress-plugin/`** — native WordPress plugin (`blogwriter/`), packaged as `blogwriter-wordpress-plugin.zip`
2. **`web-app/`** — cloud-deployable Node.js/Express app (Vercel · Netlify · Railway · Render · Fly.io · Docker)

---

## 📋 Contents

- Overview
- Features
- Requirements
- Installation
- Configuration
- Usage
- API Reference
- Cron Scheduling
- File Structure
- Security
- Troubleshooting
- Support

---

## 🚀 Overview

BlogWriter automates blog content generation using **OpenRouter's free API models** with
**BYOK (Bring Your Own Key)** architecture — full control over costs and model selection.
It uses **MAS (Multi-Agent System) technology** for intelligent content and includes
**Baidu OCR integration** for extracting text from images and PDFs.

| Why BlogWriter | Benefit |
|---|---|
| BYOK Architecture | Use your own API key — no hidden costs |
| 25+ Free AI Models | Auto-fallback ensures reliability |
| Scheduled Publishing | Set intervals (hours, minutes, days) |
| AES-256 Encryption | All data encrypted at rest and in transit |
| MAS Technology | Multi-Agent System for intelligent content |
| Baidu OCR Integration | Extract text from images and PDFs |
| STRiX Compliant | Validated against cybersecurity guidelines |

## ✨ Features

**Core Content Generation**

- Batch post creation — 1–50 posts per run
- Brand voice customization — brand name, tone, style
- Content requirements — topics, keywords, structure
- URL content extraction — reference content from any URL
- Scheduled publishing — minutes, hours, days, weeks
- Draft/published status — review before publishing

**AI & API Support**

- OpenRouter BYOK — bring your own key
- 25+ free models with auto-fallback:
  Meta LLaMA 3.1/3.2 · Mistral 7B/8x7B · Google Gemma 2 · OpenAI GPT-4o-mini ·
  Anthropic Claude 3 · DeepSeek Chat/Coder · Qwen 2.5 · Microsoft Phi-3 · and 15+ more

**Document Processing**

- OCR integration (OCR.space / Baidu Cloud / self-hosted)
- PDF, DOCX, JPG, PNG, TXT extraction

**Security & Privacy (STRiX Compliant)**

- AES-256 encryption, right-click + DevTools guards
- Encrypted API keys, input validation, CSRF protection (nonces)
- Rate limiting, audit logging, hardened security headers

## 🔧 Requirements

| WordPress | Minimum |
|---|---|
| WordPress | 5.0+ |
| PHP | 7.4+ |
| MySQL | 5.6+ |
| Memory | 128 MB+ |
| Max execution | 300 s |

PHP extensions: `curl`, `json`, `openssl`, `mbstring`, `fileinfo`.
API requirements: OpenRouter API key from https://openrouter.ai (Baidu OCR optional).

## 📦 Installation

### WordPress plugin

1. Copy `wordpress-plugin/blogwriter/` into `/wp-content/plugins/` (or upload the ZIP via
   **Plugins → Add New → Upload Plugin**).
2. Activate, then configure under **BlogWriter → Settings**.

### Cloud web app

```bash
cd web-app
npm install
cp .env.example .env      # add OPENROUTER_API_KEY
npm start                 # http://localhost:8080
```

Docker: `docker compose up --build` (see `web-app/README.md` for Vercel/Netlify/Railway).

## ⚙️ Configuration

```php
[
    'openrouter_api_key' => 'sk-or-v1-xxxxxxxxxxxxx',
    'openrouter_model' => 'meta-llama/llama-3.1-8b-instruct:free',
    'ocr_endpoint' => 'https://api.ocr.space/parse/image',
    'default_post_status' => 'draft',
    'default_word_count' => 1000,
    'max_posts_per_batch' => 10,
    'encryption_enabled' => true,
    'rate_limit_requests' => 60,
    'rate_limit_window' => 60
]
```

## 📝 Usage

Create a job: **BlogWriter → New Job**, set job name, brand, reference URL,
content requirements, number of posts, schedule interval and status, then **Start Job**.
Monitor under **BlogWriter → Jobs** (Pause / Resume / Run Now / Delete).

## 🔌 API Reference

### WordPress REST API (`/wp-json/blogwriter/v1`)

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/job` | Create a job |
| GET | `/job/{id}` | Job status |
| POST | `/job/{id}/pause` | Pause job |
| POST | `/job/{id}/resume` | Resume job |
| DELETE | `/job/{id}` | Delete job |

Requires `manage_options` capability + WP nonce (`X-WP-Nonce`).

### Web app API (`/api`)

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/status` | App + settings status |
| GET/POST | `/settings` | Runtime settings / set API key + model |
| GET/POST | `/jobs` | List / create jobs |
| GET | `/jobs/:id` | Job detail + generated posts |
| POST | `/jobs/:id/run` | Generate now |
| POST | `/jobs/:id/pause` · `/resume` | Control job |
| DELETE | `/jobs/:id` | Delete job |
| POST | `/ocr` | OCR upload (`multipart/form-data`, field `document`) |

## ⏰ Cron Scheduling

WordPress uses `wp_schedule_event()` on the `blogwriter_generate_posts` hook. Intervals:
minutes (5–45), hours (1–24), days (1–7), weeks (1–2).

```bash
wp cron event list --field=hook | grep blogwriter   # list events
wp cron event run blogwriter_generate_posts        # run manually
wp eval "BlogWriter_Cron_Manager::run_scheduled_jobs();"
```

The web app runs its own in-process scheduler (tick every `SCHEDULER_TICK_MS`, default 60 s)
and checks due jobs; on serverless platforms trigger `POST /api/jobs/:id/run` via an external cron.

## 📁 File Structure

```
blogbot/
├── README.md                       # This file
├── blogwriter-wordpress-plugin.zip # Ready-to-upload plugin
├── wordpress-plugin/
│   └── blogwriter/
│       ├── blogwriter.php          # Main plugin file
│       ├── LICENSE
│       ├── README.md
│       ├── includes/               # classes: main, admin, api, cron, ocr,
│       │                           #   encryption, validator, logger, security, database
│       ├── assets/                 # css, js, images
│       └── views/                  # dashboard, new-job, jobs-list, settings, security, ocr
└── web-app/
    ├── server.js                   # Standalone server
    ├── api/index.js                # Vercel serverless entry
    ├── netlify/functions/api.js    # Netlify serverless entry
    ├── Dockerfile · docker-compose.yml
    ├── vercel.json · netlify.toml · .env.example
    ├── src/                        # app, routes, config, db, encryption,
    │                               #   validator, ai, ocr, scheduler
    └── public/                     # HTML dashboard, app.js, style.css
```

## 🛡️ Security

- AES-256-CBC (WordPress) / AES-256-GCM (web app) encryption for stored API keys
- OpenSSL key management (`BLOGWRITER_ENCRYPTION_KEY`)
- Prepared statements / `$wpdb->prepare`, input sanitization, output escaping
- Nonce verification on every admin + REST action
- MIME + size validation on uploads with malicious-content scan
- Transient-based rate limiting
- Audit logging (database + debug log)
- Hardened headers: CSP, `X-Frame-Options: DENY`, nosniff, referrer-policy

### STRiX compliance highlights

- Authentication & access control — capability checks, nonces
- Data protection — AES-256 at rest, TLS in transit
- Input validation — SQLi/XSS/CSRF prevention
- API security — encrypted keys, rate limiting, sanitization
- Logging — audit trail, error handling, activity hooks

## 🐛 Troubleshooting

1. **Invalid API key** — verify the format (`sk-or-v1-...`), regenerate at openrouter.ai.
2. **OCR not responding** — check endpoint reachability and firewall rules.
3. **Cron not running** — enable `ALTERNATE_WP_CRON` or add a system cron for `wp-cron.php`.
4. **Decryption failed** — confirm `BLOGWRITER_ENCRYPTION_KEY` is stable, re-encrypt data.

## 📞 Support

- Email: kgadde131@gmail.com
- Website: guidingkey.com
- Support: support@guidingkey.com
- Security reporting: email with PGP on request; response within 48 h

## 📄 License

MIT — see `LICENSE` files. Copyright (c) 2026 Guiding Key Technologies.

---

Built by Guiding Key Technologies · STRiX Compliant · AES-256 Encrypted · MAS Technology
