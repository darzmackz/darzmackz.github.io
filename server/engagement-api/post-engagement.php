<?php

declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    respond(500, ['ok' => false, 'error' => 'Missing config.php']);
}

$config = require $configPath;

handleCors($config);

$action = trim((string)($_GET['action'] ?? ''));

try {
    $pdo = createPdo($config);

    if ($action === '') {
        respond(400, ['ok' => false, 'error' => 'Missing action']);
    }

    if ($action === 'get') {
        $path = normalizePath((string)($_GET['path'] ?? ''));
        ensurePath($path);
        respond(200, buildStatsResponse($pdo, $path));
    }

    if ($action === 'get-comments') {
        $path = normalizePath((string)($_GET['path'] ?? ''));
        ensurePath($path);
        respond(200, [
            'ok' => true,
            'path' => $path,
            'comments' => fetchApprovedComments($pdo, $path),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['ok' => false, 'error' => 'Method not allowed for this action']);
    }

    $input = decodeJsonBody();

    if ($action === 'sync-post') {
        $path = normalizePath((string)($input['path'] ?? ''));
        ensurePath($path);
        upsertPostMetadata($pdo, [
            'path' => $path,
            'title' => limitString((string)($input['title'] ?? ''), 255),
            'url' => limitString((string)($input['url'] ?? ''), 500),
            'description' => limitString((string)($input['description'] ?? ''), 5000),
            'published_at' => normalizeDateTime((string)($input['published_at'] ?? '')),
            'categories' => normalizeStringArray($input['categories'] ?? []),
            'tags' => normalizeStringArray($input['tags'] ?? []),
        ]);
        respond(200, ['ok' => true, 'message' => 'Post metadata synced']);
    }

    if ($action === 'view') {
        $path = normalizePath((string)($input['path'] ?? ''));
        $title = limitString((string)($input['title'] ?? ''), 255);
        $url = limitString((string)($input['url'] ?? ''), 500);
        $visitorToken = trim((string)($input['visitor_token'] ?? ''));
        ensurePath($path);
        ensureVisitorToken($visitorToken);
        ensureTotalsRow($pdo, $path, $title, $url);
        registerView($pdo, $path, visitorHash($visitorToken), (int)($config['view_cooldown_seconds'] ?? 21600));
        respond(200, buildStatsResponse($pdo, $path));
    }

    if ($action === 'react') {
        $path = normalizePath((string)($input['path'] ?? ''));
        $title = limitString((string)($input['title'] ?? ''), 255);
        $url = limitString((string)($input['url'] ?? ''), 500);
        $visitorToken = trim((string)($input['visitor_token'] ?? ''));
        $reaction = trim((string)($input['reaction'] ?? ''));
        ensurePath($path);
        ensureVisitorToken($visitorToken);
        ensureReaction($reaction);
        ensureTotalsRow($pdo, $path, $title, $url);
        registerReaction($pdo, $path, visitorHash($visitorToken), $reaction);
        respond(200, buildStatsResponse($pdo, $path));
    }

    if ($action === 'comment') {
        $path = normalizePath((string)($input['path'] ?? ''));
        $title = limitString((string)($input['title'] ?? ''), 255);
        $url = limitString((string)($input['url'] ?? ''), 500);
        $visitorToken = trim((string)($input['visitor_token'] ?? ''));
        $authorName = limitString(trim((string)($input['author_name'] ?? '')), 80);
        $authorEmail = normalizeEmail((string)($input['author_email'] ?? ''));
        $authorWebsite = normalizeWebsite((string)($input['author_website'] ?? ''));
        $commentBody = limitString(trim((string)($input['comment_body'] ?? '')), 2000);
        $honeypot = trim((string)($input['company'] ?? ''));

        ensurePath($path);
        ensureVisitorToken($visitorToken);
        ensureCommentInput($authorName, $authorEmail, $commentBody, $honeypot);
        ensureTotalsRow($pdo, $path, $title, $url);
        insertComment($pdo, [
            'post_path' => $path,
            'author_name' => $authorName,
            'author_email_hash' => hash('sha256', strtolower($authorEmail)),
            'author_website' => $authorWebsite,
            'comment_body' => $commentBody,
            'visitor_hash' => visitorHash($visitorToken),
        ]);
        respond(200, [
            'ok' => true,
            'message' => 'Comment posted successfully.',
            'comments' => fetchApprovedComments($pdo, $path),
        ]);
    }

    if ($action === 'inquiry') {
        $name = limitString(trim((string)($input['name'] ?? '')), 120);
        $email = normalizeEmail((string)($input['email'] ?? ''));
        $subject = limitString(trim((string)($input['subject'] ?? '')), 255);
        $message = limitString(trim((string)($input['message'] ?? '')), 5000);
        $pageUrl = limitString(trim((string)($input['page_url'] ?? '')), 500);
        $honeypot = trim((string)($input['company'] ?? ''));

        ensureInquiryInput($name, $email, $message, $honeypot);
        insertInquiry($pdo, [
            'sender_name' => $name,
            'sender_email_hash' => hash('sha256', strtolower($email)),
            'sender_email_mask' => maskEmail($email),
            'subject' => $subject,
            'message_body' => $message,
            'page_url' => $pageUrl,
            'ip_hash' => requestIpHash(),
        ]);
        respond(200, [
            'ok' => true,
            'message' => 'Your message has been sent successfully.',
        ]);
    }

    respond(400, ['ok' => false, 'error' => 'Unsupported action']);
} catch (Throwable $e) {
    respond(500, ['ok' => false, 'error' => $e->getMessage()]);
}

function handleCors(array $config): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = $config['allowed_origins'] ?? [];

    if ($origin && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function createPdo(array $config): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        (int)$config['db_port'],
        $config['db_name']
    );

    return new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function decodeJsonBody(): array
{
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        throw new RuntimeException('Invalid JSON body');
    }
    return $input;
}

function normalizePath(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return rtrim($path, '/') . '/';
}

function normalizeDateTime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $timestamp);
}

function normalizeStringArray($value): array
{
    if (!is_array($value)) {
        return [];
    }
    $items = [];
    foreach ($value as $item) {
        $text = trim((string)$item);
        if ($text !== '') {
            $items[] = limitString($text, 80);
        }
    }
    return array_values(array_unique($items));
}

function normalizeEmail(string $email): string
{
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Invalid email address');
    }
    return $email;
}

function normalizeWebsite(string $website): string
{
    $website = trim($website);
    if ($website === '') {
        return '';
    }
    if (!preg_match('~^https?://~i', $website)) {
        $website = 'https://' . $website;
    }
    if (!filter_var($website, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Invalid website URL');
    }
    return limitString($website, 255);
}

function limitString(string $value, int $maxLength): string
{
    return mb_substr($value, 0, $maxLength);
}

function ensurePath(string $path): void
{
    if ($path === '' || strlen($path) > 255) {
        throw new RuntimeException('Invalid post path');
    }
}

function ensureVisitorToken(string $visitorToken): void
{
    if ($visitorToken === '' || strlen($visitorToken) > 200) {
        throw new RuntimeException('Invalid visitor token');
    }
}

function ensureReaction(string $reaction): void
{
    if (!in_array($reaction, ['fire', 'love', 'mindblown', 'helpful'], true)) {
        throw new RuntimeException('Invalid reaction');
    }
}

function ensureCommentInput(string $authorName, string $authorEmail, string $commentBody, string $honeypot): void
{
    if ($honeypot !== '') {
        throw new RuntimeException('Spam rejected');
    }
    if ($authorName === '' || $commentBody === '') {
        throw new RuntimeException('Missing required comment fields');
    }
    if ($authorEmail === '') {
        throw new RuntimeException('Email is required');
    }
}

function ensureInquiryInput(string $name, string $email, string $message, string $honeypot): void
{
    if ($honeypot !== '') {
        throw new RuntimeException('Spam rejected');
    }
    if ($name === '' || $email === '' || $message === '') {
        throw new RuntimeException('Missing required inquiry fields');
    }
}

function visitorHash(string $visitorToken): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $visitorToken . '|' . $ip . '|' . $userAgent);
}

function requestIpHash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256', $ip);
}

function maskEmail(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return '';
    }
    $local = $parts[0];
    $domain = $parts[1];
    if (strlen($local) <= 2) {
        return substr($local, 0, 1) . '*@' . $domain;
    }
    return substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2)) . '@' . $domain;
}

function ensureTotalsRow(PDO $pdo, string $path, string $title, string $url): void
{
    $statement = $pdo->prepare(
        'INSERT INTO post_engagement_totals (post_path, post_title, post_url)
         VALUES (:post_path, :post_title, :post_url)
         ON DUPLICATE KEY UPDATE
         post_title = CASE WHEN VALUES(post_title) <> "" THEN VALUES(post_title) ELSE post_title END,
         post_url = CASE WHEN VALUES(post_url) <> "" THEN VALUES(post_url) ELSE post_url END'
    );
    $statement->execute([
        ':post_path' => $path,
        ':post_title' => $title,
        ':post_url' => $url,
    ]);
}

function upsertPostMetadata(PDO $pdo, array $post): void
{
    $statement = $pdo->prepare(
        'INSERT INTO post_metadata (post_path, post_title, post_url, post_description, published_at, categories_json, tags_json)
         VALUES (:post_path, :post_title, :post_url, :post_description, :published_at, :categories_json, :tags_json)
         ON DUPLICATE KEY UPDATE
         post_title = VALUES(post_title),
         post_url = VALUES(post_url),
         post_description = VALUES(post_description),
         published_at = VALUES(published_at),
         categories_json = VALUES(categories_json),
         tags_json = VALUES(tags_json)'
    );
    $statement->execute([
        ':post_path' => $post['path'],
        ':post_title' => $post['title'],
        ':post_url' => $post['url'],
        ':post_description' => $post['description'],
        ':published_at' => $post['published_at'],
        ':categories_json' => json_encode($post['categories'], JSON_UNESCAPED_SLASHES),
        ':tags_json' => json_encode($post['tags'], JSON_UNESCAPED_SLASHES),
    ]);
}

function registerView(PDO $pdo, string $path, string $visitorHash, int $cooldownSeconds): void
{
    $pdo->beginTransaction();

    $select = $pdo->prepare(
        'SELECT id, UNIX_TIMESTAMP(last_viewed_at) AS last_seen
         FROM post_engagement_views
         WHERE post_path = :post_path AND visitor_hash = :visitor_hash
         LIMIT 1'
    );
    $select->execute([
        ':post_path' => $path,
        ':visitor_hash' => $visitorHash,
    ]);
    $row = $select->fetch();

    $shouldIncrement = true;
    if ($row) {
        $lastSeen = (int)$row['last_seen'];
        if ((time() - $lastSeen) < $cooldownSeconds) {
            $shouldIncrement = false;
        }
    }

    if ($row) {
        $update = $pdo->prepare(
            'UPDATE post_engagement_views
             SET last_viewed_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $update->execute([':id' => $row['id']]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO post_engagement_views (post_path, visitor_hash)
             VALUES (:post_path, :visitor_hash)'
        );
        $insert->execute([
            ':post_path' => $path,
            ':visitor_hash' => $visitorHash,
        ]);
    }

    if ($shouldIncrement) {
        $increment = $pdo->prepare(
            'UPDATE post_engagement_totals
             SET views = views + 1
             WHERE post_path = :post_path'
        );
        $increment->execute([':post_path' => $path]);
    }

    $pdo->commit();
}

function registerReaction(PDO $pdo, string $path, string $visitorHash, string $reaction): void
{
    $columnMap = [
        'fire' => 'fire_count',
        'love' => 'love_count',
        'mindblown' => 'mindblown_count',
        'helpful' => 'helpful_count',
    ];

    $pdo->beginTransaction();

    $select = $pdo->prepare(
        'SELECT id, reaction
         FROM post_engagement_reactions
         WHERE post_path = :post_path AND visitor_hash = :visitor_hash
         LIMIT 1'
    );
    $select->execute([
        ':post_path' => $path,
        ':visitor_hash' => $visitorHash,
    ]);
    $existing = $select->fetch();

    if ($existing && $existing['reaction'] === $reaction) {
        $pdo->commit();
        return;
    }

    if ($existing) {
        $decrementColumn = $columnMap[$existing['reaction']];
        $pdo->exec(
            sprintf(
                "UPDATE post_engagement_totals SET %s = GREATEST(%s - 1, 0) WHERE post_path = %s",
                $decrementColumn,
                $decrementColumn,
                $pdo->quote($path)
            )
        );

        $update = $pdo->prepare(
            'UPDATE post_engagement_reactions
             SET reaction = :reaction, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $update->execute([
            ':reaction' => $reaction,
            ':id' => $existing['id'],
        ]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO post_engagement_reactions (post_path, visitor_hash, reaction)
             VALUES (:post_path, :visitor_hash, :reaction)'
        );
        $insert->execute([
            ':post_path' => $path,
            ':visitor_hash' => $visitorHash,
            ':reaction' => $reaction,
        ]);
    }

    $incrementColumn = $columnMap[$reaction];
    $pdo->exec(
        sprintf(
            "UPDATE post_engagement_totals SET %s = %s + 1 WHERE post_path = %s",
            $incrementColumn,
            $incrementColumn,
            $pdo->quote($path)
        )
    );

    $pdo->commit();
}

function insertComment(PDO $pdo, array $comment): void
{
    $statement = $pdo->prepare(
        'INSERT INTO post_comments (post_path, author_name, author_email_hash, author_website, comment_body, visitor_hash, status)
         VALUES (:post_path, :author_name, :author_email_hash, :author_website, :comment_body, :visitor_hash, "approved")'
    );
    $statement->execute([
        ':post_path' => $comment['post_path'],
        ':author_name' => $comment['author_name'],
        ':author_email_hash' => $comment['author_email_hash'],
        ':author_website' => $comment['author_website'],
        ':comment_body' => $comment['comment_body'],
        ':visitor_hash' => $comment['visitor_hash'],
    ]);
}

function fetchApprovedComments(PDO $pdo, string $path): array
{
    $statement = $pdo->prepare(
        'SELECT id, author_name, author_website, comment_body, created_at
         FROM post_comments
         WHERE post_path = :post_path AND status = "approved"
         ORDER BY created_at DESC'
    );
    $statement->execute([':post_path' => $path]);
    return $statement->fetchAll() ?: [];
}

function insertInquiry(PDO $pdo, array $inquiry): void
{
    $statement = $pdo->prepare(
        'INSERT INTO contact_inquiries (sender_name, sender_email_hash, sender_email_mask, subject, message_body, page_url, ip_hash, status)
         VALUES (:sender_name, :sender_email_hash, :sender_email_mask, :subject, :message_body, :page_url, :ip_hash, "new")'
    );
    $statement->execute([
        ':sender_name' => $inquiry['sender_name'],
        ':sender_email_hash' => $inquiry['sender_email_hash'],
        ':sender_email_mask' => $inquiry['sender_email_mask'],
        ':subject' => $inquiry['subject'],
        ':message_body' => $inquiry['message_body'],
        ':page_url' => $inquiry['page_url'],
        ':ip_hash' => $inquiry['ip_hash'],
    ]);
}

function buildStatsResponse(PDO $pdo, string $path): array
{
    $statement = $pdo->prepare(
        'SELECT post_path, views, fire_count, love_count, mindblown_count, helpful_count
         FROM post_engagement_totals
         WHERE post_path = :post_path
         LIMIT 1'
    );
    $statement->execute([':post_path' => $path]);
    $row = $statement->fetch();

    if (!$row) {
        return [
            'ok' => true,
            'path' => $path,
            'views' => 0,
            'reactions' => [
                'fire' => 0,
                'love' => 0,
                'mindblown' => 0,
                'helpful' => 0,
            ],
            'total_reactions' => 0,
        ];
    }

    $reactions = [
        'fire' => (int)$row['fire_count'],
        'love' => (int)$row['love_count'],
        'mindblown' => (int)$row['mindblown_count'],
        'helpful' => (int)$row['helpful_count'],
    ];

    return [
        'ok' => true,
        'path' => $row['post_path'],
        'views' => (int)$row['views'],
        'reactions' => $reactions,
        'total_reactions' => array_sum($reactions),
    ];
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
