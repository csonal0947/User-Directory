<?php
/**
 * Search Users API Endpoint
 * 
 * Case-insensitive search by first name and/or last name.
 * Returns top 6 results ordered by fname DESC.
 * 
 * GET /api/search.php?q=searchterm
 * 
 * Response: { users: [], total: int, cached: bool, loadTime: float }
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';

#Validates & sanitizes the q parameter
$query = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_SPECIAL_CHARS);

if ($query === null || $query === '' || $query === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Search query parameter "q" is required.']);
    exit;
}

// Trim and limit length for safety
$query = trim($query);
if (strlen($query) > 200) {
    $query = substr($query, 0, 200);
}

$startTime = microtime(true);

// --- Caching layer ---
$cacheDir = __DIR__ . '/../cache';
#Checks if a cached response exists
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$cacheKey = 'search_' . strtolower($query);
$cacheFile = $cacheDir . '/' . md5($cacheKey) . '.json';
$cacheTTL = 60;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $cachedData = json_decode(file_get_contents($cacheFile), true);
    if ($cachedData !== null) {
        $cachedData['cached'] = true;
        $cachedData['loadTime'] = round((microtime(true) - $startTime) * 1000, 2);
        echo json_encode($cachedData);
        exit;
    }
}
# Runs a LIKE query for case-insensitive search in fname and lname, returning top 6 results
try {
    $db = Database::getConnection();

    $searchTerm = '%' . $query . '%';

    // Search in fname, lname, and combined full name (case-insensitive via LOWER)
    $stmt = $db->prepare("
        SELECT id, fname, lname, email, review, created_at
        FROM users
        WHERE status = 'active'
          AND (
            LOWER(fname) LIKE LOWER(:term1) 
            OR LOWER(lname) LIKE LOWER(:term2)
            OR LOWER(CONCAT(fname, ' ', lname)) LIKE LOWER(:term3)
          )
        ORDER BY fname ASC, lname ASC, id ASC
        LIMIT 6
    ");
    $stmt->bindValue(':term1', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':term2', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':term3', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();

    $users = $stmt->fetchAll();

    // Get count of ALL matching active users (not just top 6) so the UI can show "Showing 6 of 200 matches"
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM users
        WHERE status = 'active'
          AND (
            LOWER(fname) LIKE LOWER(:term1) 
            OR LOWER(lname) LIKE LOWER(:term2)
            OR LOWER(CONCAT(fname, ' ', lname)) LIKE LOWER(:term3)
          )
    ");
    $countStmt->bindValue(':term1', $searchTerm, PDO::PARAM_STR);
    $countStmt->bindValue(':term2', $searchTerm, PDO::PARAM_STR);
    $countStmt->bindValue(':term3', $searchTerm, PDO::PARAM_STR);
    $countStmt->execute();
    $matchTotal = (int)$countStmt->fetch()['total'];

    // Also get the overall active user count for the header badge
    $totalStmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $overallTotal = (int)$totalStmt->fetch()['total'];

    $loadTime = round((microtime(true) - $startTime) * 1000, 2);
    #Packages the response, writes it to cache, and returns JSON
    $response = [
        'users'        => $users,
        'matchTotal'   => $matchTotal,
        'total'        => $overallTotal,
        'cached'       => false,
        'loadTime'     => $loadTime,
    ];

    // Write to cache
    file_put_contents($cacheFile, json_encode($response), LOCK_EX);

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Search failed',
        'message' => 'An internal error occurred.'
    ]);
}
