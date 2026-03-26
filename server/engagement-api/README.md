# Engagement API

This folder contains a small PHP + MySQL backend for blog visitor counts, reactions, comments, post metadata, and contact inquiries.

## What it does

- Tracks per-post visitor counts
- Tracks per-post reactions
- Stores approved post comments
- Stores contact and inquiry submissions
- Syncs post metadata into MySQL
- Limits repeat visitor increments with a cooldown window
- Allows one active reaction per visitor token per post
- Returns aggregate counts for the blog frontend and admin panel

## Files

- `config.example.php`: copy to `config.php` and fill in your server values
- `schema.sql`: MySQL tables for aggregate counts and visitor dedupe
- `post-engagement.php`: the API endpoint

## Deployment

1. Import `schema.sql` into your MySQL database.
2. Copy `config.example.php` to `config.php`.
3. Fill in your database credentials and allowed origins.
4. Upload both `config.php` and `post-engagement.php` to your PHP-capable server.
5. Set `engagement_api_base` in `_config.yml` to the public URL of `post-engagement.php`.

Example:

`https://your-nextcloud-server.example.com/post-engagement.php`

## Frontend contract

### Get counts

`GET post-engagement.php?action=get&path=/2026/03/25/example-post/`

Response:

```json
{
  "ok": true,
  "path": "/2026/03/25/example-post/",
  "views": 12,
  "reactions": {
    "fire": 2,
    "love": 5,
    "mindblown": 1,
    "helpful": 3
  },
  "total_reactions": 11
}
```

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
  "author_website": "https://example.com",
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

```json
{
  "path": "/2026/03/25/example-post/",
  "title": "Example Post",
  "url": "https://example.com/2026/03/25/example-post/",
  "description": "Post summary",
  "published_at": "2026-03-25T00:00:00+08:00",
  "categories": ["music", "feature"],
  "tags": ["lyrics", "karaoke"]
}
```

## Notes

- This API expects CORS requests from your GitHub Pages site and admin panel.
- The visitor token is generated in the browser and combined with request metadata server-side.
- Adjust the view cooldown in `config.php` if you want stricter or looser dedupe.
