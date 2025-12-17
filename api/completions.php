<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');
$user = current_user();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!verify_csrf()) {
    json_response(['error' => 'Invalid CSRF token'], 400);
}

$habitId = (int) ($input['habit_id'] ?? 0);
$status = (bool) ($input['completed'] ?? false);
$date = date('Y-m-d');
$conn = db();

// Ensure habit belongs to user
$check = $conn->prepare('SELECT id FROM habits WHERE id = ? AND user_id = ?');
$check->bind_param('ii', $habitId, $user['id']);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    json_response(['error' => 'Habit not found'], 404);
}
$check->close();

if ($status) {
    $stmt = $conn->prepare('INSERT INTO habit_completions (user_id, habit_id, completion_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE completed_at = CURRENT_TIMESTAMP');
    $stmt->bind_param('iis', $user['id'], $habitId, $date);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare('DELETE FROM habit_completions WHERE user_id = ? AND habit_id = ? AND completion_date = ?');
    $stmt->bind_param('iis', $user['id'], $habitId, $date);
    $stmt->execute();
    $stmt->close();
}

$summary = completion_summary($user['id'], $date);
$reward = attempt_daily_reward($user['id'], $date);
json_response(['summary' => $summary, 'reward' => $reward]);
?>
