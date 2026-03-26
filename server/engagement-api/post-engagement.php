<?php

declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    respond(500, ['ok' => false, 'error' => 'Missing config.php']);
}

$config = require $configPath;

handleCors($config);

$action = $_GET['action'] ?? '';

try {
    $pdo = createPdo($config);

    if ($action === 'get') {
        $path = normalizePath($_GET['path'] ?? '');
        ensurePath($path);
        respond(200, buildStatsResponse($pdo, $path));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['ok' => false, 'error' => 'Method not allowed']);
    }

    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        respond(400, ['ok' => false, 'error' => 'Invalid JSON body']);
    }

    $path = normalizePath($input['path'] ?? '');
    $title = trim((string)($input['title'] ?? ''));
    $url = trim((string)($input['url'] ?? ''));
    $visitorToken = trim((string)($input['visitor_token'] ?? ''));

    ensurePath($path);
    ensureVisitorToken($visitorToken);

    ensureTotalsRow($pdo, $path, $title, $url);
    $visitorHash = visitorHash($visitorToken);

    if ($action === 'view') {
        registerView($pdo, $path, $visitorHash, (int)($config['view_cooldown_seconds'] ?? 21600));
        respond(200, buildStatsResponse($pdo, $path));
    }

    if ($action === 'react') {
        $reaction = trim((string)($input['reaction'] ?? ''));
        ensureReaction($reaction);
        registerReaction($pdo, $path, $visitorHash, $reaction);
        respond(200, buildStatsResponse($pdo, $path));
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

function visitorHash(string $visitorToken): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $visitorToken . '|' . $ip . '|' . $userAgent);
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

