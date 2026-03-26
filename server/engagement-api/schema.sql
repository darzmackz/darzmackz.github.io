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
