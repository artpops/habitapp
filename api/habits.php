<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');
$user = current_user();
$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['habits' => get_habits($user['id'])]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!verify_csrf()) {
    json_response(['error' => 'Invalid CSRF token'], 400);
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');
        if (!$name) {
            json_response(['error' => 'Name is required'], 400);
        }
        $orderStmt = $conn->prepare('SELECT COALESCE(MAX(sort_order), 0) as max_order FROM habits WHERE user_id = ?');
        $orderStmt->bind_param('i', $user['id']);
        $orderStmt->execute();
        $maxOrder = ($orderStmt->get_result()->fetch_assoc()['max_order'] ?? 0) + 1;
        $orderStmt->close();

        $stmt = $conn->prepare('INSERT INTO habits (user_id, name, description, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('issi', $user['id'], $name, $description, $maxOrder);
        $stmt->execute();
        $habitId = $stmt->insert_id;
        $stmt->close();
        json_response(['habit' => ['id' => $habitId, 'name' => $name, 'description' => $description, 'sort_order' => $maxOrder]]);
        break;

    case 'PUT':
        $habitId = (int) ($input['id'] ?? 0);
        if (!$habitId) {
            json_response(['error' => 'Habit not found'], 404);
        }
        if (!empty($input['direction'])) {
            $direction = $input['direction'] === 'up' ? 'up' : 'down';
            $currentStmt = $conn->prepare('SELECT id, sort_order FROM habits WHERE id = ? AND user_id = ?');
            $currentStmt->bind_param('ii', $habitId, $user['id']);
            $currentStmt->execute();
            $current = $currentStmt->get_result()->fetch_assoc();
            $currentStmt->close();
            if (!$current) {
                json_response(['error' => 'Habit not found'], 404);
            }
            $order = (int) $current['sort_order'];
            $comparison = $direction === 'up' ? '<' : '>';
            $sortDir = $direction === 'up' ? 'DESC' : 'ASC';
            $neighborStmt = $conn->prepare("SELECT id, sort_order FROM habits WHERE user_id = ? AND sort_order $comparison ? ORDER BY sort_order $sortDir LIMIT 1");
            $neighborStmt->bind_param('ii', $user['id'], $order);
            $neighborStmt->execute();
            $neighbor = $neighborStmt->get_result()->fetch_assoc();
            $neighborStmt->close();
            if ($neighbor) {
                $swap1 = $conn->prepare('UPDATE habits SET sort_order = ? WHERE id = ?');
                $swap1->bind_param('ii', $neighbor['sort_order'], $habitId);
                $swap1->execute();
                $swap1->close();

                $swap2 = $conn->prepare('UPDATE habits SET sort_order = ? WHERE id = ?');
                $swap2->bind_param('ii', $order, $neighbor['id']);
                $swap2->execute();
                $swap2->close();
            }
            json_response(['success' => true]);
        }
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');
        $stmt = $conn->prepare('UPDATE habits SET name = ?, description = ? WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ssii', $name, $description, $habitId, $user['id']);
        $stmt->execute();
        $stmt->close();
        json_response(['success' => true]);
        break;

    case 'DELETE':
        $habitId = (int) ($input['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM habits WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $habitId, $user['id']);
        $stmt->execute();
        $stmt->close();
        json_response(['success' => true]);
        break;

    default:
        json_response(['error' => 'Unsupported method'], 405);
}
?>
