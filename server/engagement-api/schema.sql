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

CREATE TABLE IF NOT EXISTS post_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_path VARCHAR(255) NOT NULL,
    author_name VARCHAR(80) NOT NULL,
    author_email_hash CHAR(64) NOT NULL,
    author_website VARCHAR(255) NOT NULL DEFAULT '',
    comment_body TEXT NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    status ENUM('approved', 'pending', 'spam', 'hidden') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY post_comments_path_idx (post_path),
    KEY post_comments_status_idx (status)
);

CREATE TABLE IF NOT EXISTS contact_inquiries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sender_name VARCHAR(120) NOT NULL,
    sender_email_hash CHAR(64) NOT NULL,
    sender_email_mask VARCHAR(190) NOT NULL DEFAULT '',
    subject VARCHAR(255) NOT NULL DEFAULT '',
    message_body TEXT NOT NULL,
    page_url VARCHAR(500) NOT NULL DEFAULT '',
    ip_hash CHAR(64) NOT NULL,
    status ENUM('new', 'reviewed', 'closed', 'spam') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY contact_inquiries_status_idx (status)
);
