# BlogWriter — WordPress Plugin

Automated AI blog content generation for WordPress using **OpenRouter BYOK** (Bring Your Own Key).
Batch post creation, scheduled publishing, OCR document extraction, AES-256 encryption and
STRiX-compliant security. Developed by **Guiding Key Technologies**.

## Features

- Batch post creation (1–50 posts per run)
- Brand voice customization (name, tone, style, SEO keywords)
- URL content extraction for reference material
- Scheduled publishing (minutes / hours / days / weeks)
- OpenRouter BYOK with auto-fallback across 25+ free models
- OCR text extraction (PDF, DOCX, TXT, JPG, PNG) via OCR.space / Baidu / self-hosted
- AES-256-CBC encryption, nonce verification, rate limiting, audit logging
- STRiX-compliant security (CSP headers, X-Frame-Options, input sanitization)

## Requirements

- WordPress 5.0+, PHP 7.4+, MySQL 5.6+
- PHP extensions: `curl`, `json`, `openssl`, `mbstring`, `fileinfo`
- An OpenRouter API key from https://openrouter.ai

## Installation

1. Copy the `blogwriter` folder into `/wp-content/plugins/`.
2. Activate the plugin from the WordPress admin.
3. Go to **BlogWriter → Settings**, paste your OpenRouter API key, save.

Optionally define an encryption key in `wp-config.php`:

```php
define('BLOGWRITER_ENCRYPTION_KEY', 'a-32-character-random-key');
```

## Usage

1. **BlogWriter → New Job** — configure job name, brand, reference URL, requirements,
   number of posts, schedule interval and post status.
2. Click **Start Job**. The job runs on the WordPress cron and can be paused/resumed,
   or triggered immediately with **Run Now**.
3. Monitor progress under **BlogWriter → Jobs**.

## REST API

| Method | Endpoint                        | Purpose              |
|--------|---------------------------------|----------------------|
| POST   | `/wp-json/blogwriter/v1/job`    | Create a job         |
| GET    | `/wp-json/blogwriter/v1/job/{id}` | Get job status     |
| POST   | `/wp-json/blogwriter/v1/job/{id}/pause` | Pause a job |
| POST   | `/wp-json/blogwriter/v1/job/{id}/resume` | Resume a job |
| DELETE | `/wp-json/blogwriter/v1/job/{id}` | Delete a job       |

All REST endpoints require the `manage_options` capability and a valid WP nonce.

## WP-CLI

```bash
wp eval "BlogWriter_Cron_Manager::run_scheduled_jobs();"   # run due jobs
wp eval "BlogWriter_Security::run_audit();"                # security audit
wp eval "BlogWriter_API::validate_remote();"               # validate API key
wp eval "BlogWriter_Encryption::reencrypt_all();"          # re-encrypt data
wp cron event list --field=hook | grep blogwriter          # list cron jobs
```

## File Structure

```
blogwriter/
├── blogwriter.php            # Main plugin file
├── LICENSE                   # MIT License
├── includes/
│   ├── class-main.php        # Core orchestration + REST API
│   ├── class-admin.php       # Admin interface
│   ├── class-api-handler.php # OpenRouter API calls
│   ├── class-cron-manager.php# Cron scheduling
│   ├── class-ocr-handler.php # OCR integration
│   ├── class-encryption.php  # AES-256-CBC
│   ├── class-validator.php   # STRiX input validation
│   ├── class-logger.php      # Audit logging
│   ├── class-security.php    # Security headers/audit
│   └── class-database.php    # Database operations
├── assets/
│   ├── css/admin-style.css
│   ├── js/admin-script.js
│   ├── js/guards.js
│   └── images/icon.png
└── views/
    ├── dashboard.php
    ├── new-job.php
    ├── jobs-list.php
    ├── settings.php
    ├── security.php
    └── ocr.php
```

## Security

- AES-256-CBC encryption for all stored data (API keys)
- OpenSSL key management (`BLOGWRITER_ENCRYPTION_KEY` or generated option)
- Prepared statements, `sanitize_text_field()`, `esc_html()`, `wp_kses()`
- Nonce verification on every form and REST action
- MIME + size validation on uploads, malicious-content scan
- Transient-based API rate limiting
- Audit logging to database + `WP_DEBUG` log
- Hardenened security headers (CSP, X-Frame-Options, nosniff)

## License

MIT — see `LICENSE`. Copyright (c) 2026 Guiding Key Technologies.
