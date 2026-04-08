CREATE TABLE IF NOT EXISTS post_engagement_totals (
    post_path VARCHAR(255) NOT NULL PRIMARY KEY,
    post_title VARCHAR(255) NOT NULL DEFAULT '',
    post_url VARCHAR(500) NOT NULL DEFAULT '',
    views INT UNSIGNED NOT NULL DEFAULT 0,
    fire_count INT UNSIGNED NOT NULL DEFAULT 0,
    love_count INT UNSIGNED NOT NULL DEFAULT 0,
    mindblown_count INT UNSIGNED NOT NULL DEFAULT 0,
    helpful_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS post_engagement_views (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_path VARCHAR(255) NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    last_viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY post_visitor_unique (post_path, visitor_hash),
    KEY post_views_path_idx (post_path)
);

CREATE TABLE IF NOT EXISTS post_engagement_reactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_path VARCHAR(255) NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    reaction ENUM('fire', 'love', 'mindblown', 'helpful') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY post_reaction_unique (post_path, visitor_hash),
    KEY post_reaction_path_idx (post_path)
);

CREATE TABLE IF NOT EXISTS post_metadata (
    post_path VARCHAR(255) NOT NULL PRIMARY KEY,
    post_title VARCHAR(255) NOT NULL DEFAULT '',
    post_url VARCHAR(500) NOT NULL DEFAULT '',
    post_description TEXT NULL,
    published_at DATETIME NULL,
    categories_json JSON NULL,
    tags_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS site_content_items (
    item_type ENUM('post', 'page', 'portfolio') NOT NULL,
    item_key VARCHAR(255) NOT NULL,
    item_path VARCHAR(255) NOT NULL DEFAULT '',
    item_url VARCHAR(500) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(40) NOT NULL DEFAULT '',
    published_at DATETIME NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    word_count INT UNSIGNED NOT NULL DEFAULT 0,
    meta_json JSON NULL,
    search_text TEXT NULL,
    indexed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (item_type, item_key),
    KEY site_content_items_type_sort_idx (item_type, sort_order, published_at),
    KEY site_content_items_type_status_idx (item_type, status),
    KEY site_content_items_path_idx (item_path)
);

CREATE TABLE IF NOT EXISTS site_snapshots (
    snapshot_key VARCHAR(80) NOT NULL PRIMARY KEY,
    payload_json JSON NULL,
    generated_at DATETIME NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS post_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_path VARCHAR(255) NOT NULL,
    author_name VARCHAR(80) NOT NULL,
    author_email_hash CHAR(64) NOT NULL,
    comment_body TEXT NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    status ENUM('approved', 'pending', 'spam', 'hidden') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY post_comments_path_idx (post_path),
    KEY post_comments_status_idx (status),
    KEY post_comments_public_lookup_idx (post_path, status, created_at)
);

CREATE TABLE IF NOT EXISTS contact_inquiries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sender_name VARCHAR(120) NOT NULL,
    sender_email_hash CHAR(64) NOT NULL,
    sender_email_mask VARCHAR(190) NOT NULL DEFAULT '',
    sender_email_encrypted TEXT NOT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    message_body TEXT NOT NULL,
    page_url VARCHAR(500) NOT NULL DEFAULT '',
    ip_hash CHAR(64) NOT NULL,
    status ENUM('new', 'read', 'replied', 'closed', 'spam') NOT NULL DEFAULT 'new',
    read_at TIMESTAMP NULL DEFAULT NULL,
    replied_at TIMESTAMP NULL DEFAULT NULL,
    closed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY contact_inquiries_status_idx (status),
    KEY contact_inquiries_created_idx (created_at)
);

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


