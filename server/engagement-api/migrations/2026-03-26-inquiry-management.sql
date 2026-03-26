ALTER TABLE post_comments DROP COLUMN IF EXISTS author_website;

ALTER TABLE contact_inquiries
    ADD COLUMN IF NOT EXISTS sender_email_encrypted TEXT NOT NULL AFTER sender_email_mask,
    ADD COLUMN IF NOT EXISTS read_at TIMESTAMP NULL DEFAULT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS replied_at TIMESTAMP NULL DEFAULT NULL AFTER read_at,
    ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP NULL DEFAULT NULL AFTER replied_at;

ALTER TABLE contact_inquiries
    MODIFY COLUMN status ENUM('new', 'read', 'replied', 'closed', 'spam') NOT NULL DEFAULT 'new';

CREATE TABLE IF NOT EXISTS contact_inquiry_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    inquiry_id BIGINT UNSIGNED NOT NULL,
    admin_name VARCHAR(80) NOT NULL DEFAULT 'Admin',
    comment_body TEXT NOT NULL,
    visibility ENUM('internal', 'public') NOT NULL DEFAULT 'internal',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY inquiry_comments_lookup_idx (inquiry_id, created_at)
);

CREATE TABLE IF NOT EXISTS contact_inquiry_replies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    inquiry_id BIGINT UNSIGNED NOT NULL,
    admin_name VARCHAR(80) NOT NULL DEFAULT 'Admin',
    reply_subject VARCHAR(255) NOT NULL DEFAULT '',
    reply_body TEXT NOT NULL,
    sent_to_email_mask VARCHAR(190) NOT NULL DEFAULT '',
    delivery_channel VARCHAR(40) NOT NULL DEFAULT 'log_only',
    delivery_status VARCHAR(40) NOT NULL DEFAULT 'not_configured',
    delivery_message VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY inquiry_replies_lookup_idx (inquiry_id, created_at)
);

CREATE TABLE IF NOT EXISTS api_rate_limits (
    rate_key CHAR(64) NOT NULL PRIMARY KEY,
    action_name VARCHAR(120) NOT NULL,
    request_fingerprint CHAR(64) NOT NULL,
    hits INT UNSIGNED NOT NULL DEFAULT 1,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY api_rate_limits_expires_idx (expires_at),
    KEY api_rate_limits_action_idx (action_name)
);

CREATE TABLE IF NOT EXISTS api_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(120) NOT NULL,
    actor_type VARCHAR(40) NOT NULL DEFAULT 'system',
    request_fingerprint CHAR(64) NOT NULL,
    details_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY api_audit_log_event_idx (event_type, created_at),
    KEY api_audit_log_actor_idx (actor_type, created_at)
);

