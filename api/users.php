<?php
/**
 * Users API Endpoint
 * 
 * Fetches paginated users with caching support.
 * Supports lazy loading with offset/limit pagination.
 * 
 * GET /api/users.php?offset=0&limit=10
 * 
 * Response: { users: [], total: int, hasMore: bool, cached: bool, loadTime: float }
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';

// --- Input validation & sanitization ---
$offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT, [
    'options' => ['default' => 0, 'min_range' => 0]
]);
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, [
    'options' => ['default' => 10, 'min_range' => 1, 'max_range' => 50]
]);

$startTime = microtime(true);

// --- Caching layer (file-based, 60s TTL) ---
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$cacheKey = "users_offset_{$offset}_limit_{$limit}";
$cacheFile = $cacheDir . '/' . md5($cacheKey) . '.json';
$cacheTTL = 60; // seconds
$cached = false;

// Check if valid cache exists
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $cachedData = json_decode(file_get_contents($cacheFile), true);
    if ($cachedData !== null) {
        $cachedData['cached'] = true;
        $cachedData['loadTime'] = round((microtime(true) - $startTime) * 1000, 2);
        echo json_encode($cachedData);
        exit;
    }
}

try {
    $db = Database::getConnection();

    // Get total active user count
    $countStmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $total = (int)$countStmt->fetch()['total'];

    // Fetch paginated users (descending by fname as required)
    $stmt = $db->prepare("
        SELECT id, fname, lname, email, review, created_at 
        FROM users 
        WHERE status = 'active' 
        ORDER BY fname DESC, id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $users = $stmt->fetchAll();

    $hasMore = ($offset + $limit) < $total;
    $loadTime = round((microtime(true) - $startTime) * 1000, 2);

    $response = [
        'users'    => $users,
        'total'    => $total,
        'hasMore'  => $hasMore,
        'cached'   => false,
        'loadTime' => $loadTime,
    ];

    // Write to cache
    file_put_contents($cacheFile, json_encode($response), LOCK_EX);

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Failed to fetch users',
        'message' => 'An internal error occurred.'
    ]);
}
