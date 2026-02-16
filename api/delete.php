<?php
/**
 * Delete User API Endpoint
 * 
 * Soft-deletes a user by setting status to 'deleted'.
 * Does NOT remove from DB — preserves data integrity.
 * 
 * POST /api/delete.php
 * Body: { "id": int }
 * 
 * Response: { success: bool, total: int, message: string }
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// --- Read and validate JSON body ---
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request. JSON body with "id" required.']);
    exit;
}

$userId = filter_var($data['id'], FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if ($userId === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID. Must be a positive integer.']);
    exit;
}

try {
    $db = Database::getConnection();

    // Check if user exists and is active
    $checkStmt = $db->prepare("SELECT id, status FROM users WHERE id = :id");
    $checkStmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $checkStmt->execute();
    $user = $checkStmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found.']);
        exit;
    }

    if ($user['status'] === 'deleted') {
        http_response_code(409);
        echo json_encode(['error' => 'User already deleted.']);
        exit;
    }

    // Soft delete: update status to 'deleted'
    $deleteStmt = $db->prepare("UPDATE users SET status = 'deleted' WHERE id = :id AND status = 'active'");
    $deleteStmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $deleteStmt->execute();

    if ($deleteStmt->rowCount() === 0) {
        http_response_code(409);
        echo json_encode(['error' => 'User could not be deleted. May already be deleted.']);
        exit;
    }

    // Get updated total count
    $countStmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $newTotal = (int)$countStmt->fetch()['total'];

    // Invalidate cache on delete
    $cacheDir = __DIR__ . '/../cache';
    if (is_dir($cacheDir)) {
        $cacheFiles = glob($cacheDir . '/*.json');
        foreach ($cacheFiles as $file) {
            unlink($file);
        }
    }

    echo json_encode([
        'success' => true,
        'total'   => $newTotal,
        'message' => 'User deleted successfully.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Failed to delete user',
        'message' => 'An internal error occurred.'
    ]);
}
