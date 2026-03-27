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
