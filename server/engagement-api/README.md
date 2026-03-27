# Engagement API

This folder contains the PHP + MySQL backend that powers visitor counts, reactions, post metadata sync, database-backed comments, contact/inquiry management, and the admin content index/dashboard snapshots for the site and admin panel.

## What it does

- Tracks per-post visitor counts with cooldown dedupe
- Tracks one active reaction per visitor per post
- Stores approved post comments
- Stores contact and inquiry submissions
- Encrypts inquiry email addresses at rest
- Lets the admin panel list, review, reply to, comment on, update, and delete inquiries
- Syncs post metadata into MySQL for reporting and management
- Stores lightweight content indexes for posts, pages, portfolio items, sitemap data, and dashboard health snapshots
- Applies per-endpoint rate limiting and audit logging

## Files

- `config.example.php`: copy to `config.php` and fill in your server values
- `schema.sql`: full schema for fresh installs
- `migrations/2026-03-26-inquiry-management.sql`: upgrade script for older installs
- `migrations/2026-03-27-admin-site-index.sql`: adds content-index and snapshot tables for faster admin loads
- `post-engagement.php`: the public and admin API endpoint

## Required config

Add these values to `config.php`:

- `encryption_key`: long random secret used to encrypt inquiry email addresses
- `admin_api_key`: separate secret used only by the admin panel for admin-only inquiry actions
- `allowed_origins`: GitHub Pages/admin origins allowed to call the API
- `mail.enabled`: set to `true` only if PHP `mail()` is configured and working

## Deployment

1. For a fresh install, import `schema.sql` into MySQL.
2. For an existing install, run `migrations/2026-03-26-inquiry-management.sql`, then `migrations/2026-03-27-admin-site-index.sql`, and then review `schema.sql`.
3. Copy `config.example.php` to `config.php`.
4. Fill in database credentials, `encryption_key`, `admin_api_key`, allowed origins, and optional mail settings.
5. Upload `config.php` and `post-engagement.php` to your PHP-capable server.
6. Set `engagement_api_base` in `_config.yml` to the public URL of `post-engagement.php`.
7. In the admin panel Settings screen, save the same `admin_api_key` locally in the browser.

## Public endpoints

### Get counts

`GET post-engagement.php?action=get&path=/2026/03/25/example-post/`

### Register a view

`POST post-engagement.php?action=view`

```json
{
  "path": "/2026/03/25/example-post/",
  "title": "Example Post",
  "url": "https://example.com/2026/03/25/example-post/",
  "visitor_token": "visitor-abc123"
}
```

### Register a reaction

`POST post-engagement.php?action=react`

```json
{
  "path": "/2026/03/25/example-post/",
  "title": "Example Post",
  "url": "https://example.com/2026/03/25/example-post/",
  "visitor_token": "visitor-abc123",
  "reaction": "love"
}
```

### Get comments

`GET post-engagement.php?action=get-comments&path=/2026/03/25/example-post/`

### Submit a comment

`POST post-engagement.php?action=comment`

```json
{
  "path": "/2026/03/25/example-post/",
  "title": "Example Post",
  "url": "https://example.com/2026/03/25/example-post/",
  "visitor_token": "visitor-abc123",
  "author_name": "Jane",
  "author_email": "jane@example.com",
  "comment_body": "Great post!",
  "company": ""
}
```

### Submit an inquiry

`POST post-engagement.php?action=inquiry`

```json
{
  "name": "Jane",
  "email": "jane@example.com",
  "subject": "Collaboration",
  "message": "I'd like to work with you.",
  "page_url": "https://darzmackz.github.io/contact/",
  "company": ""
}
```

### Sync post metadata

`POST post-engagement.php?action=sync-post`

## Admin endpoints

These require:

- an allowed `Origin`
- the `X-Admin-Key` header matching `admin_api_key`

Endpoints:

- `GET action=admin-list-inquiries`
- `GET action=admin-get-inquiry&id=123`
- `GET action=admin-get-dashboard-snapshot`
- `GET action=admin-get-site-data`
- `POST action=admin-update-inquiry`
- `POST action=admin-add-inquiry-comment`
- `POST action=admin-reply-inquiry`
- `POST action=admin-delete-inquiry`
- `POST action=admin-sync-site-data`

## Security notes

- All DB writes use prepared statements.
- Inquiry email addresses are encrypted before storage.
- Sensitive admin actions require a separate admin API key and allowed origin.
- API responses use stricter headers, including `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, and a restrictive API CSP.
- Public-facing write endpoints use rate limiting and honeypot spam fields.
- Production deployments should keep `debug` set to `false`.
