<?php
declare(strict_types=1);

const PUBLIC_GET_ACTIONS = ['get', 'get-comments'];
const ADMIN_GET_ACTIONS = ['admin-list-inquiries', 'admin-get-inquiry', 'admin-get-dashboard-snapshot', 'admin-get-site-data'];
const POST_ACTIONS = [
    'sync-post',
    'view',
    'react',
    'comment',
    'inquiry',
    'admin-update-inquiry',
    'admin-delete-inquiry',
    'admin-add-inquiry-comment',
    'admin-reply-inquiry',
    'admin-sync-site-data',
];
const ALL_ACTIONS = [
    'get',
    'get-comments',
    'admin-list-inquiries',
    'admin-get-inquiry',
    'admin-get-dashboard-snapshot',
    'admin-get-site-data',
    'sync-post',
    'view',
    'react',
    'comment',
    'inquiry',
    'admin-update-inquiry',
    'admin-delete-inquiry',
    'admin-add-inquiry-comment',
    'admin-reply-inquiry',
    'admin-sync-site-data',
];

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    respond(500, ['ok' => false, 'error' => 'Missing config.php']);
}

$config = require $configPath;
$debug = !empty($config['debug']);

bootstrapRuntime($config);
handleCors($config);
$action = trim((string)($_GET['action'] ?? ''));

try {
    ensureKnownAction($action);
    $pdo = createPdo($config);

    if ($action === '') {
        respond(400, ['ok' => false, 'error' => 'Missing action']);
    }

    if ($action === 'get') {
        $path = normalizePath((string)($_GET['path'] ?? ''));
        ensurePath($path);
        setPublicJsonCacheHeaders(15, 45);
        respond(200, buildStatsResponse($pdo, $path));
    }

    if ($action === 'get-comments') {
        $path = normalizePath((string)($_GET['path'] ?? ''));
        ensurePath($path);
        setPublicJsonCacheHeaders(30, 120);
        respond(200, [
            'ok' => true,
            'path' => $path,
            'comments' => fetchApprovedComments($pdo, $path),
        ]);
    }

    if ($action === 'admin-list-inquiries') {
        assertAllowedOrigin($config);
        assertAdminAuthorized($config);

        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($_GET['page_size'] ?? 20)));
        $status = normalizeInquiryStatusFilter((string)($_GET['status'] ?? 'all'));
        $search = sanitizeSearchTerm((string)($_GET['search'] ?? ''));
        ensureRateLimit($pdo, $config, 'admin-list-inquiries', clientFingerprint(), 3600, 180);
        $result = fetchInquiries($pdo, $status, $search, $page, $pageSize);

        respond(200, [
            'ok' => true,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'page' => $page,
                'page_size' => $pageSize,
            ],
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $result['total'],
                'pages' => (int)max(1, ceil($result['total'] / $pageSize)),
            ],
            'items' => $result['items'],
        ]);
    }

    if ($action === 'admin-get-inquiry') {
        assertAllowedOrigin($config);
        assertAdminAuthorized($config);

        $inquiryId = (int)($_GET['id'] ?? 0);
        ensurePositiveId($inquiryId, 'Invalid inquiry id');
        ensureRateLimit($pdo, $config, 'admin-get-inquiry', clientFingerprint(), 3600, 240);
        $inquiry = fetchInquiryDetail($pdo, $inquiryId, $config);
        if (!$inquiry) {
            respond(404, ['ok' => false, 'error' => 'Inquiry not found']);
        }

        maybeMarkInquiryRead($pdo, $inquiryId);
        $inquiry = fetchInquiryDetail($pdo, $inquiryId, $config);
        respond(200, ['ok' => true, 'inquiry' => $inquiry]);
    }

    if ($action === 'admin-get-dashboard-snapshot') {
        assertAllowedOrigin($config);
        assertAdminAuthorized($config);
        ensureRateLimit($pdo, $config, 'admin-get-dashboard-snapshot', clientFingerprint(), 3600, 240);
        respond(200, [
            'ok' => true,
            'dashboard' => buildAdminDashboardSnapshot($pdo),
        ]);
    }

    if ($action === 'admin-get-site-data') {
        assertAllowedOrigin($config);
        assertAdminAuthorized($config);
        ensureRateLimit($pdo, $config, 'admin-get-site-data', clientFingerprint(), 3600, 240);
        respond(200, [
            'ok' => true,
            'site_data' => buildAdminSiteDataPayload($pdo),
        ]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['ok' => false, 'error' => 'Method not allowed for this action']);
    }

    assertAllowedOrigin($config);
    assertJsonRequest($config, $action);
    $input = decodeJsonBody();

    if ($action === 'sync-post') {
        ensureRateLimit($pdo, $config, 'sync-post', clientFingerprint(), 120, 120);

        $path = normalizePath((string)($input['path'] ?? ''));
        ensurePath($path);
        upsertPostMetadata($pdo, [
            'path' => $path,
            'title' => limitString((string)($input['title'] ?? ''), 255),
            'url' => normalizePageUrl((string)($input['url'] ?? '')),
            'description' => limitString(normalizeText((string)($input['description'] ?? '')), 5000),
            'published_at' => normalizeDateTime((string)($input['published_at'] ?? '')),
            'categories' => normalizeStringArray($input['categories'] ?? []),
            'tags' => normalizeStringArray($input['tags'] ?? []),
        ]);
        respond(200, ['ok' => true, 'message' => 'Post metadata synced']);
    }

    if ($action === 'view') {
        ensureRateLimit($pdo, $config, 'view', clientFingerprint(), 3600, 120);

        $path = normalizePath((string)($input['path'] ?? ''));
        $title = limitString(normalizeText((string)($input['title'] ?? '')), 255);
        $url = normalizePageUrl((string)($input['url'] ?? ''));
        $visitorToken = trim((string)($input['visitor_token'] ?? ''));
        ensurePath($path);
        ensureVisitorToken($visitorToken);
        ensureTotalsRow($pdo, $path, $title, $url);
        registerView($pdo, $path, visitorHash($visitorToken), (int)($config['view_cooldown_seconds'] ?? 21600));
        respond(200, buildStatsResponse($pdo, $path));
    }

    if ($action === 'react') {
        ensureRateLimit($pdo, $config, 'react', clientFingerprint(), 3600, 40);

        $path = normalizePath((string)($input['path'] ?? ''));
        $title = limitString(normalizeText((string)($input['title'] ?? '')), 255);
        $url = normalizePageUrl((string)($input['url'] ?? ''));
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
        ensureRateLimit($pdo, $config, 'comment', clientFingerprint(), 3600, 10);

        $path = normalizePath((string)($input['path'] ?? ''));
        $title = limitString(normalizeText((string)($input['title'] ?? '')), 255);
        $url = normalizePageUrl((string)($input['url'] ?? ''));
        $visitorToken = trim((string)($input['visitor_token'] ?? ''));
        $authorName = limitString(normalizeText((string)($input['author_name'] ?? '')), 80);
        $authorEmail = normalizeEmail((string)($input['author_email'] ?? ''));
        $commentBody = limitString(normalizeMultilineText((string)($input['comment_body'] ?? '')), 2000);
        $honeypot = trim((string)($input['company'] ?? ''));

        ensurePath($path);
        ensureVisitorToken($visitorToken);
        ensureCommentInput($authorName, $authorEmail, $commentBody, $honeypot);
        ensureTotalsRow($pdo, $path, $title, $url);
        insertComment($pdo, [
            'post_path' => $path,
            'author_name' => $authorName,
            'author_email_hash' => hash('sha256', strtolower($authorEmail)),
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
        ensureRateLimit($pdo, $config, 'inquiry', clientFingerprint(), 3600, 8);

        $name = limitString(normalizeText((string)($input['name'] ?? '')), 120);
        $email = normalizeEmail((string)($input['email'] ?? ''));
        $subject = limitString(normalizeText((string)($input['subject'] ?? '')), 255);
        $message = limitString(normalizeMultilineText((string)($input['message'] ?? '')), 5000);
        $pageUrl = normalizePageUrl((string)($input['page_url'] ?? ''));
        $honeypot = trim((string)($input['company'] ?? ''));

        ensureInquiryInput($name, $email, $message, $honeypot);
        $inquiryId = insertInquiry($pdo, [
            'sender_name' => $name,
            'sender_email_hash' => hash('sha256', strtolower($email)),
            'sender_email_mask' => maskEmail($email),
            'sender_email_encrypted' => encryptValue($email, $config),
            'subject' => $subject,
            'message_body' => $message,
            'page_url' => $pageUrl,
            'ip_hash' => requestIpHash(),
        ]);
        logAuditEvent($pdo, 'public_inquiry_created', [
            'inquiry_id' => $inquiryId,
            'page_url' => $pageUrl,
        ], 'public');
        respond(200, [
            'ok' => true,
            'message' => 'Your message has been sent successfully.',
        ]);
    }

    if ($action === 'admin-update-inquiry') {
        assertAdminAuthorized($config);
        ensureRateLimit($pdo, $config, 'admin-update-inquiry', clientFingerprint(), 3600, 200);

        $inquiryId = (int)($input['id'] ?? 0);
        $status = normalizeInquiryStatus((string)($input['status'] ?? ''));
        ensurePositiveId($inquiryId, 'Invalid inquiry id');

        updateInquiryStatus($pdo, $inquiryId, $status);
        logAuditEvent($pdo, 'admin_inquiry_status_updated', [
            'inquiry_id' => $inquiryId,
            'status' => $status,
        ], 'admin');
        respond(200, ['ok' => true, 'message' => 'Inquiry status updated.']);
    }

    if ($action === 'admin-delete-inquiry') {
        assertAdminAuthorized($config);
        ensureRateLimit($pdo, $config, 'admin-delete-inquiry', clientFingerprint(), 3600, 40);

        $inquiryId = (int)($input['id'] ?? 0);
        $confirmation = trim((string)($input['confirm_delete'] ?? ''));
        ensurePositiveId($inquiryId, 'Invalid inquiry id');
        if ($confirmation !== 'DELETE') {
            throw new RuntimeException('Delete confirmation missing');
        }

        deleteInquiry($pdo, $inquiryId);
        logAuditEvent($pdo, 'admin_inquiry_deleted', [
            'inquiry_id' => $inquiryId,
        ], 'admin');
        respond(200, ['ok' => true, 'message' => 'Inquiry deleted.']);
    }

    if ($action === 'admin-add-inquiry-comment') {
        assertAdminAuthorized($config);
        ensureRateLimit($pdo, $config, 'admin-add-inquiry-comment', clientFingerprint(), 3600, 120);

        $inquiryId = (int)($input['id'] ?? 0);
        $commentBody = limitString(normalizeMultilineText((string)($input['comment_body'] ?? '')), 4000);
        $visibility = normalizeCommentVisibility((string)($input['visibility'] ?? 'internal'));
        $adminName = limitString(normalizeText((string)($input['admin_name'] ?? 'Admin')), 80);
        ensurePositiveId($inquiryId, 'Invalid inquiry id');
        if ($commentBody === '') {
            throw new RuntimeException('Comment is required');
        }

        addInquiryComment($pdo, [
            'inquiry_id' => $inquiryId,
            'admin_name' => $adminName,
            'comment_body' => $commentBody,
            'visibility' => $visibility,
        ]);
        logAuditEvent($pdo, 'admin_inquiry_comment_added', [
            'inquiry_id' => $inquiryId,
            'visibility' => $visibility,
        ], 'admin');
        respond(200, [
            'ok' => true,
            'message' => 'Comment added.',
            'inquiry' => fetchInquiryDetail($pdo, $inquiryId, $config),
        ]);
    }

    if ($action === 'admin-reply-inquiry') {
        assertAdminAuthorized($config);
        ensureRateLimit($pdo, $config, 'admin-reply-inquiry', clientFingerprint(), 3600, 60);

        $inquiryId = (int)($input['id'] ?? 0);
        $subject = limitString(normalizeText((string)($input['subject'] ?? '')), 255);
        $message = limitString(normalizeMultilineText((string)($input['message'] ?? '')), 5000);
        $adminName = limitString(normalizeText((string)($input['admin_name'] ?? 'Admin')), 80);
        $visibility = normalizeCommentVisibility((string)($input['comment_visibility'] ?? 'internal'));
        ensurePositiveId($inquiryId, 'Invalid inquiry id');
        if ($message === '') {
            throw new RuntimeException('Reply message is required');
        }

        $inquiry = fetchInquiryDetail($pdo, $inquiryId, $config);
        if (!$inquiry) {
            respond(404, ['ok' => false, 'error' => 'Inquiry not found']);
        }

        $delivery = sendInquiryReply($config, (string)$inquiry['sender_email'], $subject, $message);
        addInquiryReply($pdo, [
            'inquiry_id' => $inquiryId,
            'admin_name' => $adminName,
            'reply_subject' => $subject,
            'reply_body' => $message,
            'sent_to_email_mask' => (string)$inquiry['sender_email_mask'],
            'delivery_channel' => $delivery['channel'],
            'delivery_status' => $delivery['status'],
            'delivery_message' => $delivery['message'],
        ]);

        if (!empty($input['comment_body'])) {
            addInquiryComment($pdo, [
                'inquiry_id' => $inquiryId,
                'admin_name' => $adminName,
                'comment_body' => limitString(normalizeMultilineText((string)$input['comment_body']), 4000),
                'visibility' => $visibility,
            ]);
        }

        updateInquiryStatus($pdo, $inquiryId, 'replied');
        logAuditEvent($pdo, 'admin_inquiry_replied', [
            'inquiry_id' => $inquiryId,
            'delivery_status' => $delivery['status'],
            'delivery_channel' => $delivery['channel'],
        ], 'admin');
        respond(200, [
            'ok' => true,
            'message' => $delivery['status'] === 'sent' ? 'Reply sent successfully.' : 'Reply saved. Email delivery is not configured yet.',
            'delivery' => $delivery,
            'inquiry' => fetchInquiryDetail($pdo, $inquiryId, $config),
        ]);
    }

    if ($action === 'admin-sync-site-data') {
        assertAdminAuthorized($config);
        ensureRateLimit($pdo, $config, 'admin-sync-site-data', clientFingerprint(), 3600, 60);
        syncAdminSiteData($pdo, $input);

        $posts = normalizeContentItemsPayload($input['posts'] ?? [], 'post');
        $pages = normalizeContentItemsPayload($input['pages'] ?? [], 'page');
        $portfolio = normalizeContentItemsPayload($input['portfolio'] ?? [], 'portfolio');
        logAuditEvent($pdo, 'admin_site_data_synced', [
            'posts' => count($posts),
            'pages' => count($pages),
            'portfolio' => count($portfolio),
        ], 'admin');
        respond(200, [
            'ok' => true,
            'message' => 'Site data synced.',
            'dashboard' => buildAdminDashboardSnapshot($pdo),
        ]);
    }
    respond(400, ['ok' => false, 'error' => 'Unsupported action']);
} catch (Throwable $e) {
    safeHandleException($e, $config, $pdo ?? null, $action, $debug);
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
    header('Access-Control-Allow-Headers: Content-Type, X-Admin-Key');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: accelerometer=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");

    if (!empty($config['https_only']) && isHttpsRequest()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function setPublicJsonCacheHeaders(int $maxAge, int $staleWhileRevalidate): void
{
    header(sprintf('Cache-Control: public, max-age=%d, stale-while-revalidate=%d', $maxAge, $staleWhileRevalidate));
}

function bootstrapRuntime(array $config): void
{
    if (!headers_sent()) {
        header_remove('X-Powered-By');
    }

    ini_set('display_errors', !empty($config['debug']) ? '1' : '0');
    ini_set('log_errors', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', !empty($config['https_only']) ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');

    if (function_exists('set_time_limit')) {
        @set_time_limit((int)($config['request_timeout_seconds'] ?? 10));
    }
}

function ensureKnownAction(string $action): void
{
    if ($action === '') {
        return;
    }

    if (!in_array($action, ALL_ACTIONS, true)) {
        throw new RuntimeException('Unsupported action');
    }
}

function assertAllowedOrigin(array $config): void
{
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    $allowedOrigins = $config['allowed_origins'] ?? [];
    if ($origin === '' || !in_array($origin, $allowedOrigins, true)) {
        throw new RuntimeException('Origin not allowed');
    }
}

function assertAdminAuthorized(array $config): void
{
    $expected = (string)($config['admin_api_key'] ?? '');
    $provided = trim((string)($_SERVER['HTTP_X_ADMIN_KEY'] ?? ''));
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        throw new RuntimeException('Admin authorization failed');
    }
}

function assertJsonRequest(array $config, string $action = ''): void
{
    $maxBodyBytes = (int)($config['request_limits']['max_body_bytes'] ?? 16384);
    if ($action === 'admin-sync-site-data') {
        $maxBodyBytes = max($maxBodyBytes, (int)($config['request_limits']['admin_sync_max_body_bytes'] ?? 1048576));
    }
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength < 1) {
        throw new RuntimeException('Request body is required');
    }
    if ($contentLength > $maxBodyBytes) {
        throw new RuntimeException('Request body too large');
    }

    $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
    if ($contentType === '' || strpos($contentType, 'application/json') !== 0) {
        throw new RuntimeException('Content-Type must be application/json');
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
        $text = normalizeText((string)$item);
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
    return strtolower($email);
}

function normalizeText(string $value): string
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return trim($value);
}

function normalizeMultilineText(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return trim($value);
}

function sanitizeSearchTerm(string $value): string
{
    return limitString(normalizeText($value), 120);
}

function normalizePageUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Invalid URL');
    }

    $parts = parse_url($value);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new RuntimeException('Invalid URL');
    }

    return limitString($value, 500);
}

function normalizeInquiryStatus(string $status): string
{
    $status = strtolower(trim($status));
    if (!in_array($status, ['new', 'read', 'replied', 'closed', 'spam'], true)) {
        throw new RuntimeException('Invalid inquiry status');
    }
    return $status;
}

function normalizeInquiryStatusFilter(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === '' || $status === 'all') {
        return 'all';
    }
    return normalizeInquiryStatus($status);
}

function normalizeCommentVisibility(string $visibility): string
{
    $visibility = strtolower(trim($visibility));
    if (!in_array($visibility, ['internal', 'public'], true)) {
        throw new RuntimeException('Invalid comment visibility');
    }
    return $visibility;
}

function limitString(string $value, int $maxLength): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }
    return substr($value, 0, $maxLength);
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

function ensurePositiveId(int $value, string $message): void
{
    if ($value < 1) {
        throw new RuntimeException($message);
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

function requireExistingInquiry(PDO $pdo, int $inquiryId): void
{
    $statement = $pdo->prepare('SELECT id FROM contact_inquiries WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $inquiryId]);
    if (!$statement->fetchColumn()) {
        throw new RuntimeException('Inquiry not found');
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
    return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

function clientFingerprint(): string
{
    return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
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

function encryptValue(string $value, array $config): string
{
    $secret = (string)($config['encryption_key'] ?? '');
    if ($secret === '') {
        throw new RuntimeException('Missing encryption key');
    }
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL support is required');
    }

    $key = hash('sha256', $secret, true);
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed');
    }
    return base64_encode($iv . $ciphertext);
}

function decryptValue(string $payload, array $config): string
{
    $secret = (string)($config['encryption_key'] ?? '');
    if ($secret === '' || $payload === '') {
        return '';
    }
    if (!function_exists('openssl_decrypt')) {
        return '';
    }

    $decoded = base64_decode($payload, true);
    if ($decoded === false || strlen($decoded) < 17) {
        return '';
    }

    $key = hash('sha256', $secret, true);
    $iv = substr($decoded, 0, 16);
    $ciphertext = substr($decoded, 16);
    $plain = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
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
    try {
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
    } catch (Throwable $e) {
        rollbackIfNeeded($pdo);
        throw $e;
    }
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
    try {
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
                    'UPDATE post_engagement_totals SET %s = GREATEST(%s - 1, 0) WHERE post_path = %s',
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
                'UPDATE post_engagement_totals SET %s = %s + 1 WHERE post_path = %s',
                $incrementColumn,
                $incrementColumn,
                $pdo->quote($path)
            )
        );

        $pdo->commit();
    } catch (Throwable $e) {
        rollbackIfNeeded($pdo);
        throw $e;
    }
}

function insertComment(PDO $pdo, array $comment): void
{
    $statement = $pdo->prepare(
        'INSERT INTO post_comments (post_path, author_name, author_email_hash, comment_body, visitor_hash, status)
         VALUES (:post_path, :author_name, :author_email_hash, :comment_body, :visitor_hash, "approved")'
    );
    $statement->execute([
        ':post_path' => $comment['post_path'],
        ':author_name' => $comment['author_name'],
        ':author_email_hash' => $comment['author_email_hash'],
        ':comment_body' => $comment['comment_body'],
        ':visitor_hash' => $comment['visitor_hash'],
    ]);
}

function fetchApprovedComments(PDO $pdo, string $path): array
{
    $statement = $pdo->prepare(
        'SELECT id, author_name, comment_body, created_at
         FROM post_comments
         WHERE post_path = :post_path AND status = "approved"
         ORDER BY created_at DESC'
    );
    $statement->execute([':post_path' => $path]);
    return $statement->fetchAll() ?: [];
}

function insertInquiry(PDO $pdo, array $inquiry): int
{
    $statement = $pdo->prepare(
        'INSERT INTO contact_inquiries (
            sender_name,
            sender_email_hash,
            sender_email_mask,
            sender_email_encrypted,
            subject,
            message_body,
            page_url,
            ip_hash,
            status
         ) VALUES (
            :sender_name,
            :sender_email_hash,
            :sender_email_mask,
            :sender_email_encrypted,
            :subject,
            :message_body,
            :page_url,
            :ip_hash,
            "new"
         )'
    );
    $statement->execute([
        ':sender_name' => $inquiry['sender_name'],
        ':sender_email_hash' => $inquiry['sender_email_hash'],
        ':sender_email_mask' => $inquiry['sender_email_mask'],
        ':sender_email_encrypted' => $inquiry['sender_email_encrypted'],
        ':subject' => $inquiry['subject'],
        ':message_body' => $inquiry['message_body'],
        ':page_url' => $inquiry['page_url'],
        ':ip_hash' => $inquiry['ip_hash'],
    ]);
    return (int)$pdo->lastInsertId();
}

function fetchInquiries(PDO $pdo, string $status, string $search, int $page, int $pageSize): array
{
    $where = [];
    $params = [];
    if ($status !== 'all') {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }
    if ($search !== '') {
        $where[] = '(sender_name LIKE :search OR subject LIKE :search OR message_body LIKE :search OR sender_email_mask LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM contact_inquiries ' . $whereSql);
    $countStatement->execute($params);
    $total = (int)$countStatement->fetchColumn();

    $offset = ($page - 1) * $pageSize;
    $sql = 'SELECT id, sender_name, sender_email_mask, subject, message_body, status, page_url, created_at, updated_at, read_at, replied_at, closed_at
            FROM contact_inquiries ' . $whereSql . '
            ORDER BY created_at DESC
            LIMIT ' . (int)$pageSize . ' OFFSET ' . (int)$offset;
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $items = $statement->fetchAll() ?: [];

    foreach ($items as &$item) {
        $item['preview'] = limitString(normalizeText((string)$item['message_body']), 140);
        unset($item['message_body']);
    }

    return [
        'total' => $total,
        'items' => $items,
    ];
}

function fetchInquiryDetail(PDO $pdo, int $inquiryId, array $config): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, sender_name, sender_email_hash, sender_email_mask, sender_email_encrypted, subject, message_body, page_url, ip_hash, status, created_at, updated_at, read_at, replied_at, closed_at
         FROM contact_inquiries
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute([':id' => $inquiryId]);
    $row = $statement->fetch();
    if (!$row) {
        return null;
    }

    $row['sender_email'] = decryptValue((string)$row['sender_email_encrypted'], $config);
    unset($row['sender_email_encrypted']);
    $row['comments'] = fetchInquiryComments($pdo, $inquiryId);
    $row['replies'] = fetchInquiryReplies($pdo, $inquiryId);
    return $row;
}

function maybeMarkInquiryRead(PDO $pdo, int $inquiryId): void
{
    $statement = $pdo->prepare(
        'UPDATE contact_inquiries
         SET status = CASE WHEN status = "new" THEN "read" ELSE status END,
             read_at = CASE WHEN read_at IS NULL THEN CURRENT_TIMESTAMP ELSE read_at END,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $statement->execute([':id' => $inquiryId]);
}

function updateInquiryStatus(PDO $pdo, int $inquiryId, string $status): void
{
    $fields = ['status = :status', 'updated_at = CURRENT_TIMESTAMP'];
    if ($status === 'read') {
        $fields[] = 'read_at = COALESCE(read_at, CURRENT_TIMESTAMP)';
    }
    if ($status === 'replied') {
        $fields[] = 'replied_at = CURRENT_TIMESTAMP';
    }
    if ($status === 'closed') {
        $fields[] = 'closed_at = CURRENT_TIMESTAMP';
    }

    $statement = $pdo->prepare(
        'UPDATE contact_inquiries
         SET ' . implode(', ', $fields) . '
         WHERE id = :id'
    );
    $statement->execute([
        ':status' => $status,
        ':id' => $inquiryId,
    ]);
}

function deleteInquiry(PDO $pdo, int $inquiryId): void
{
    requireExistingInquiry($pdo, $inquiryId);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM contact_inquiry_comments WHERE inquiry_id = :id')->execute([':id' => $inquiryId]);
        $pdo->prepare('DELETE FROM contact_inquiry_replies WHERE inquiry_id = :id')->execute([':id' => $inquiryId]);
        $pdo->prepare('DELETE FROM contact_inquiries WHERE id = :id')->execute([':id' => $inquiryId]);
        $pdo->commit();
    } catch (Throwable $e) {
        rollbackIfNeeded($pdo);
        throw $e;
    }
}

function addInquiryComment(PDO $pdo, array $comment): void
{
    $statement = $pdo->prepare(
        'INSERT INTO contact_inquiry_comments (inquiry_id, admin_name, comment_body, visibility)
         VALUES (:inquiry_id, :admin_name, :comment_body, :visibility)'
    );
    $statement->execute([
        ':inquiry_id' => $comment['inquiry_id'],
        ':admin_name' => $comment['admin_name'],
        ':comment_body' => $comment['comment_body'],
        ':visibility' => $comment['visibility'],
    ]);

    $pdo->prepare('UPDATE contact_inquiries SET updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute([':id' => $comment['inquiry_id']]);
}

function fetchInquiryComments(PDO $pdo, int $inquiryId): array
{
    $statement = $pdo->prepare(
        'SELECT id, admin_name, comment_body, visibility, created_at, updated_at
         FROM contact_inquiry_comments
         WHERE inquiry_id = :inquiry_id
         ORDER BY created_at ASC'
    );
    $statement->execute([':inquiry_id' => $inquiryId]);
    return $statement->fetchAll() ?: [];
}

function addInquiryReply(PDO $pdo, array $reply): void
{
    $statement = $pdo->prepare(
        'INSERT INTO contact_inquiry_replies (
            inquiry_id,
            admin_name,
            reply_subject,
            reply_body,
            sent_to_email_mask,
            delivery_channel,
            delivery_status,
            delivery_message
         ) VALUES (
            :inquiry_id,
            :admin_name,
            :reply_subject,
            :reply_body,
            :sent_to_email_mask,
            :delivery_channel,
            :delivery_status,
            :delivery_message
         )'
    );
    $statement->execute([
        ':inquiry_id' => $reply['inquiry_id'],
        ':admin_name' => $reply['admin_name'],
        ':reply_subject' => $reply['reply_subject'],
        ':reply_body' => $reply['reply_body'],
        ':sent_to_email_mask' => $reply['sent_to_email_mask'],
        ':delivery_channel' => $reply['delivery_channel'],
        ':delivery_status' => $reply['delivery_status'],
        ':delivery_message' => $reply['delivery_message'],
    ]);

    $pdo->prepare(
        'UPDATE contact_inquiries
         SET updated_at = CURRENT_TIMESTAMP, replied_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    )->execute([':id' => $reply['inquiry_id']]);
}

function fetchInquiryReplies(PDO $pdo, int $inquiryId): array
{
    $statement = $pdo->prepare(
        'SELECT id, admin_name, reply_subject, reply_body, sent_to_email_mask, delivery_channel, delivery_status, delivery_message, created_at
         FROM contact_inquiry_replies
         WHERE inquiry_id = :inquiry_id
         ORDER BY created_at DESC'
    );
    $statement->execute([':inquiry_id' => $inquiryId]);
    return $statement->fetchAll() ?: [];
}

function sendInquiryReply(array $config, string $email, string $subject, string $message): array
{
    $mail = is_array($config['mail'] ?? null) ? $config['mail'] : [];
    if (empty($mail['enabled']) || $email === '') {
        return [
            'channel' => 'log_only',
            'status' => 'not_configured',
            'message' => 'Email delivery is not configured on the server.',
        ];
    }

    $fromAddress = trim((string)($mail['from_address'] ?? ''));
    if ($fromAddress === '') {
        return [
            'channel' => 'log_only',
            'status' => 'not_configured',
            'message' => 'Reply sender address is missing.',
        ];
    }

    $fromName = trim((string)($mail['from_name'] ?? 'METAXENOPY.YT'));
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromAddress . '>',
        'Reply-To: ' . $fromAddress,
    ];

    $sent = @mail($email, $subject !== '' ? $subject : 'Reply from METAXENOPY.YT', $message, implode("\r\n", $headers));
    return [
        'channel' => 'mail',
        'status' => $sent ? 'sent' : 'failed',
        'message' => $sent ? 'Reply email sent.' : 'Reply email could not be delivered by PHP mail().',
    ];
}

function StringOrEmpty($value): string
{
    return is_string($value) ? $value : '';
}
function normalizeContentItemsPayload($items, string $type): array
{
    if (!is_array($items)) {
        return [];
    }

    $normalized = [];
    foreach (array_values($items) as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $key = slugifySnapshotKey((string)($item['key'] ?? $item['name'] ?? $item['path'] ?? $index));
        if ($key === '') {
            continue;
        }

        $pathValue = $type === 'portfolio' ? '' : normalizePath((string)($item['path'] ?? ''));
        $title = limitString(normalizeText((string)($item['title'] ?? '')), 255);
        $status = limitString(strtolower(normalizeText((string)($item['status'] ?? ''))), 40);
        $publishedAt = normalizeDateTime((string)($item['published_at'] ?? ''));
        $wordCount = max(0, (int)($item['word_count'] ?? 0));
        $sortOrder = max(0, (int)($item['sort_order'] ?? $index));
        $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
        $url = trim((string)($item['url'] ?? ''));
        if ($url !== '') {
            $url = normalizePageUrl($url);
        }

        $searchParts = [$title, $key, $pathValue, $url, json_encode($meta, JSON_UNESCAPED_SLASHES)];

        $normalized[] = [
            'item_type' => $type,
            'item_key' => limitString($key, 255),
            'item_path' => limitString($pathValue, 255),
            'item_url' => limitString($url, 500),
            'title' => $title,
            'status' => $status,
            'published_at' => $publishedAt,
            'sort_order' => $sortOrder,
            'word_count' => $wordCount,
            'meta' => $meta,
            'search_text' => limitString(normalizeMultilineText(implode(' ', array_filter($searchParts))), 10000),
        ];
    }

    return $normalized;
}

function normalizeSitemapPayload($items): array
{
    if (!is_array($items)) {
        return [];
    }

    $normalized = [];
    foreach (array_values($items) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $loc = trim((string)($item['loc'] ?? $item['url'] ?? ''));
        if ($loc === '') {
            continue;
        }
        $normalized[] = [
            'loc' => normalizePageUrl($loc),
            'type' => limitString(normalizeText((string)($item['type'] ?? 'page')), 40),
            'lastmod' => normalizeDateTime((string)($item['lastmod'] ?? '')),
        ];
    }

    return $normalized;
}

function normalizeHealthPayload($items): array
{
    if (!is_array($items)) {
        return [];
    }

    $normalized = [];
    foreach (array_values($items) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = limitString(normalizeText((string)($item['t'] ?? $item['title'] ?? '')), 160);
        $detail = limitString(normalizeText((string)($item['d'] ?? $item['detail'] ?? '')), 500);
        if ($title === '' && $detail === '') {
            continue;
        }
        $normalized[] = ['t' => $title, 'd' => $detail];
    }

    return $normalized;
}

function slugifySnapshotKey(string $value): string
{
    $value = strtolower(normalizeText($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function syncAdminSiteData(PDO $pdo, array $input): void
{
    $posts = normalizeContentItemsPayload($input['posts'] ?? [], 'post');
    $pages = normalizeContentItemsPayload($input['pages'] ?? [], 'page');
    $portfolio = normalizeContentItemsPayload($input['portfolio'] ?? [], 'portfolio');
    $sitemap = normalizeSitemapPayload($input['sitemap'] ?? []);
    $health = normalizeHealthPayload($input['health'] ?? []);

    $pdo->beginTransaction();
    try {
        replaceSiteContentItems($pdo, 'post', $posts);
        replaceSiteContentItems($pdo, 'page', $pages);
        replaceSiteContentItems($pdo, 'portfolio', $portfolio);
        saveSiteSnapshot($pdo, 'sitemap', ['items' => $sitemap, 'count' => count($sitemap)]);
        saveSiteSnapshot($pdo, 'content_health', ['items' => $health, 'count' => count($health)]);
        $pdo->commit();
    } catch (Throwable $e) {
        rollbackIfNeeded($pdo);
        throw $e;
    }
}

function replaceSiteContentItems(PDO $pdo, string $type, array $items): void
{
    $delete = $pdo->prepare('DELETE FROM site_content_items WHERE item_type = :item_type');
    $delete->execute([':item_type' => $type]);

    if (!$items) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO site_content_items (item_type, item_key, item_path, item_url, title, status, published_at, sort_order, word_count, meta_json, search_text, indexed_at)
         VALUES (:item_type, :item_key, :item_path, :item_url, :title, :status, :published_at, :sort_order, :word_count, :meta_json, :search_text, CURRENT_TIMESTAMP)'
    );

    foreach ($items as $item) {
        $insert->execute([
            ':item_type' => $type,
            ':item_key' => $item['item_key'],
            ':item_path' => $item['item_path'],
            ':item_url' => $item['item_url'],
            ':title' => $item['title'],
            ':status' => $item['status'],
            ':published_at' => $item['published_at'],
            ':sort_order' => $item['sort_order'],
            ':word_count' => $item['word_count'],
            ':meta_json' => json_encode($item['meta'], JSON_UNESCAPED_SLASHES),
            ':search_text' => $item['search_text'],
        ]);
    }
}

function saveSiteSnapshot(PDO $pdo, string $key, array $payload): void
{
    $statement = $pdo->prepare(
        'INSERT INTO site_snapshots (snapshot_key, payload_json, generated_at)
         VALUES (:snapshot_key, :payload_json, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), generated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        ':snapshot_key' => $key,
        ':payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
    ]);
}

function fetchSiteSnapshot(PDO $pdo, string $key): array
{
    $statement = $pdo->prepare(
        'SELECT payload_json, generated_at
         FROM site_snapshots
         WHERE snapshot_key = :snapshot_key
         LIMIT 1'
    );
    $statement->execute([':snapshot_key' => $key]);
    $row = $statement->fetch();
    if (!$row) {
        return [];
    }

    $payload = json_decode((string)$row['payload_json'], true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $payload['generated_at'] = $row['generated_at'];
    return $payload;
}

function fetchSiteContentItems(PDO $pdo, string $type): array
{
    $statement = $pdo->prepare(
        'SELECT item_key, item_path, item_url, title, status, published_at, sort_order, word_count, meta_json, indexed_at
         FROM site_content_items
         WHERE item_type = :item_type
         ORDER BY published_at DESC, sort_order ASC, updated_at DESC'
    );
    $statement->execute([':item_type' => $type]);
    $items = [];
    while ($row = $statement->fetch()) {
        $meta = json_decode((string)$row['meta_json'], true);
        $item = [
            'key' => $row['item_key'],
            'path' => $row['item_path'],
            'url' => $row['item_url'],
            'title' => $row['title'],
            'status' => $row['status'],
            'published_at' => $row['published_at'],
            'sort_order' => (int)$row['sort_order'],
            'word_count' => (int)$row['word_count'],
            'meta' => is_array($meta) ? $meta : [],
            'indexed_at' => $row['indexed_at'],
        ];
        if ($type === 'post') {
            $item['name'] = $row['item_key'];
        }
        $items[] = $item;
    }

    if ($type !== 'post' || !$items) {
        return $items;
    }

    $metricsStatement = $pdo->query(
        'SELECT post_path, views, fire_count, love_count, mindblown_count, helpful_count
         FROM post_engagement_totals'
    );
    $metricsMap = [];
    while ($row = $metricsStatement->fetch()) {
        $reactions = [
            'fire' => (int)$row['fire_count'],
            'love' => (int)$row['love_count'],
            'mindblown' => (int)$row['mindblown_count'],
            'helpful' => (int)$row['helpful_count'],
        ];
        $metricsMap[(string)$row['post_path']] = [
            'path' => $row['post_path'],
            'views' => (int)$row['views'],
            'reactions' => $reactions,
            'total_reactions' => array_sum($reactions),
        ];
    }

    foreach ($items as &$item) {
        $sitePath = '';
        if (is_array($item['meta'] ?? null)) {
            $sitePath = normalizePath((string)($item['meta']['site_path'] ?? ''));
        }
        $engagementPath = $sitePath !== '' ? $sitePath : $item['path'];
        $item['engagement'] = $metricsMap[$engagementPath] ?? [
            'path' => $engagementPath,
            'views' => 0,
            'reactions' => ['fire' => 0, 'love' => 0, 'mindblown' => 0, 'helpful' => 0],
            'total_reactions' => 0,
        ];
    }
    unset($item);

    return $items;
}

function buildAdminDashboardSnapshot(PDO $pdo): array
{
    $posts = fetchSiteContentItems($pdo, 'post');
    $pages = fetchSiteContentItems($pdo, 'page');
    $portfolio = fetchSiteContentItems($pdo, 'portfolio');
    $health = fetchSiteSnapshot($pdo, 'content_health');

    $published = 0;
    $drafts = 0;
    $scheduled = 0;
    $wordsPublished = 0;
    $postsWithDescriptions = 0;
    $postsWithSocialImages = 0;
    $totalViews = 0;
    $totalReactions = 0;

    foreach ($posts as $post) {
        if (($post['status'] ?? '') === 'published') {
            $published += 1;
            $wordsPublished += (int)($post['word_count'] ?? 0);
        } elseif (($post['status'] ?? '') === 'draft') {
            $drafts += 1;
        } elseif (($post['status'] ?? '') === 'scheduled') {
            $scheduled += 1;
        }

        $meta = is_array($post['meta'] ?? null) ? $post['meta'] : [];
        if (!empty($meta['description'])) {
            $postsWithDescriptions += 1;
        }
        if (!empty($meta['social_image'])) {
            $postsWithSocialImages += 1;
        }

        $metrics = is_array($post['engagement'] ?? null) ? $post['engagement'] : [];
        $totalViews += (int)($metrics['views'] ?? 0);
        $totalReactions += (int)($metrics['total_reactions'] ?? 0);
    }

    $latestPosts = $posts;
    usort($latestPosts, static function (array $a, array $b): int {
        return strcmp((string)($b['published_at'] ?? ''), (string)($a['published_at'] ?? ''));
    });
    usort($posts, static function (array $a, array $b): int {
        $viewDiff = (int)($b['engagement']['views'] ?? 0) <=> (int)($a['engagement']['views'] ?? 0);
        if ($viewDiff !== 0) {
            return $viewDiff;
        }
        return (int)($b['engagement']['total_reactions'] ?? 0) <=> (int)($a['engagement']['total_reactions'] ?? 0);
    });

    $topPosts = array_slice(array_values(array_filter($posts, static function (array $post): bool {
        return ($post['status'] ?? '') === 'published';
    })), 0, 5);

    $indexablePages = 0;
    $trackedPolicyPages = [];
    foreach ($pages as $page) {
        $meta = is_array($page['meta'] ?? null) ? $page['meta'] : [];
        if (empty($meta['noindex'])) {
            $indexablePages += 1;
        }
        $trackedPolicyPages[] = $page['key'];
    }

    $latestPost = $latestPosts[0]['title'] ?? 'No posts yet';
    $generatedAt = latestAdminDataTimestamp($posts, $pages, $portfolio, $health['generated_at'] ?? null);

    return [
        'generated_at' => $generatedAt,
        'stats' => [
            ['label' => 'Published Posts', 'value' => $published],
            ['label' => 'Drafts', 'value' => $drafts],
            ['label' => 'Scheduled', 'value' => $scheduled],
            ['label' => 'Pages', 'value' => count($pages)],
            ['label' => 'Portfolio Items', 'value' => count($portfolio)],
            ['label' => 'Words Published', 'value' => $wordsPublished],
        ],
        'analytics' => [
            ['label' => 'Average words per post', 'value' => count($posts) > 0 ? (int)round($wordsPublished / max(1, $published)) : 0],
            ['label' => 'Posts with meta descriptions', 'value' => $postsWithDescriptions . ' of ' . count($posts)],
            ['label' => 'Posts with social images', 'value' => $postsWithSocialImages . ' of ' . count($posts)],
            ['label' => 'Tracked views', 'value' => $totalViews],
            ['label' => 'Tracked reactions', 'value' => $totalReactions],
        ],
        'summary' => [
            ['label' => 'Latest post', 'value' => $latestPost],
            ['label' => 'Indexable pages', 'value' => $indexablePages],
            ['label' => 'Tracked policy pages', 'value' => implode(', ', $trackedPolicyPages)],
        ],
        'health' => is_array($health['items'] ?? null) ? $health['items'] : [],
        'engagement' => array_map(static function (array $post): array {
            $sitePath = '';
            if (is_array($post['meta'] ?? null)) {
                $sitePath = normalizePath((string)($post['meta']['site_path'] ?? ''));
            }
            return [
                'title' => $post['title'] ?: ($post['name'] ?? $post['key']),
                'path' => $sitePath !== '' ? $sitePath : $post['path'],
                'views' => (int)($post['engagement']['views'] ?? 0),
                'total_reactions' => (int)($post['engagement']['total_reactions'] ?? 0),
                'reactions' => $post['engagement']['reactions'] ?? ['fire' => 0, 'love' => 0, 'mindblown' => 0, 'helpful' => 0],
            ];
        }, $topPosts),
    ];
}

function buildAdminSiteDataPayload(PDO $pdo): array
{
    $posts = fetchSiteContentItems($pdo, 'post');
    $pages = fetchSiteContentItems($pdo, 'page');
    $portfolio = fetchSiteContentItems($pdo, 'portfolio');
    $sitemap = fetchSiteSnapshot($pdo, 'sitemap');

    return [
        'generated_at' => latestAdminDataTimestamp($posts, $pages, $portfolio, $sitemap['generated_at'] ?? null),
        'posts' => $posts,
        'pages' => $pages,
        'portfolio' => $portfolio,
        'sitemap' => is_array($sitemap['items'] ?? null) ? $sitemap['items'] : [],
        'dashboard' => buildAdminDashboardSnapshot($pdo),
    ];
}

function latestAdminDataTimestamp(array $posts, array $pages, array $portfolio, ?string $snapshotGeneratedAt): string
{
    $timestamps = [];
    foreach ([$posts, $pages, $portfolio] as $items) {
        foreach ($items as $item) {
            if (!empty($item['indexed_at'])) {
                $timestamps[] = strtotime((string)$item['indexed_at']) ?: 0;
            }
        }
    }
    if ($snapshotGeneratedAt) {
        $timestamps[] = strtotime($snapshotGeneratedAt) ?: 0;
    }
    $timestamp = max($timestamps ?: [time()]);
    return date('Y-m-d H:i:s', $timestamp);
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

function ensureRateLimit(PDO $pdo, array $config, string $action, string $fingerprint, int $windowSeconds, int $maxHits): void
{
    $limits = is_array($config['rate_limits'] ?? null) ? $config['rate_limits'] : [];
    if (isset($limits[$action])) {
        $configured = $limits[$action];
        $windowSeconds = max(1, (int)($configured['window_seconds'] ?? $windowSeconds));
        $maxHits = max(1, (int)($configured['max_hits'] ?? $maxHits));
    }

    $bucket = (int)floor(time() / $windowSeconds);
    $rateKey = hash('sha256', $action . '|' . $fingerprint . '|' . $bucket);
    $expiresAt = date('Y-m-d H:i:s', ($bucket + 1) * $windowSeconds);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM api_rate_limits WHERE expires_at < CURRENT_TIMESTAMP')->execute();
        $statement = $pdo->prepare(
            'INSERT INTO api_rate_limits (rate_key, action_name, request_fingerprint, hits, expires_at)
             VALUES (:rate_key, :action_name, :request_fingerprint, 1, :expires_at)
             ON DUPLICATE KEY UPDATE hits = hits + 1, expires_at = VALUES(expires_at), updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            ':rate_key' => $rateKey,
            ':action_name' => $action,
            ':request_fingerprint' => $fingerprint,
            ':expires_at' => $expiresAt,
        ]);

        $check = $pdo->prepare('SELECT hits FROM api_rate_limits WHERE rate_key = :rate_key LIMIT 1');
        $check->execute([':rate_key' => $rateKey]);
        $hits = (int)$check->fetchColumn();
        $pdo->commit();
    } catch (Throwable $e) {
        rollbackIfNeeded($pdo);
        throw $e;
    }

    if ($hits > $maxHits) {
        throw new RuntimeException('Rate limit exceeded');
    }
}

function logAuditEvent(?PDO $pdo, string $eventType, array $details, string $actorType): void
{
    if (!$pdo) {
        return;
    }

    $statement = $pdo->prepare(
        'INSERT INTO api_audit_log (event_type, actor_type, request_fingerprint, details_json)
         VALUES (:event_type, :actor_type, :request_fingerprint, :details_json)'
    );
    $statement->execute([
        ':event_type' => $eventType,
        ':actor_type' => $actorType,
        ':request_fingerprint' => clientFingerprint(),
        ':details_json' => json_encode($details, JSON_UNESCAPED_SLASHES),
    ]);
}

function safeHandleException(Throwable $e, array $config, ?PDO $pdo, string $action, bool $debug): void
{
    $message = $e->getMessage();
    $status = 500;

    if ($pdo) {
        rollbackIfNeeded($pdo);
    }

    if (stripos($message, 'not found') !== false) {
        $status = 404;
    } elseif (stripos($message, 'not allowed') !== false || stripos($message, 'authorization failed') !== false || stripos($message, 'origin not allowed') !== false) {
        $status = 403;
    } elseif (
        stripos($message, 'invalid ') === 0 ||
        stripos($message, 'missing ') === 0 ||
        stripos($message, 'spam rejected') === 0 ||
        stripos($message, 'delete confirmation') === 0 ||
        stripos($message, 'request body') === 0 ||
        stripos($message, 'content-type') === 0
    ) {
        $status = 400;
    } elseif (stripos($message, 'rate limit exceeded') !== false) {
        $status = 429;
    }

    if ($pdo) {
        try {
            logAuditEvent($pdo, 'api_exception', [
                'action' => $action,
                'error' => $message,
                'status' => $status,
            ], 'system');
        } catch (Throwable $loggingError) {
        }
    }

    $publicMessage = $debug || $status < 500 ? $message : 'Server error';
    respond($status, ['ok' => false, 'error' => $publicMessage]);
}

function rollbackIfNeeded(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

function isHttpsRequest(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) === '443');
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
