<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');
$user = current_user();
$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = $_GET['date'] ?? date('Y-m-d');
    json_response(['todos' => get_todos($user['id'], $date)]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!verify_csrf()) {
    json_response(['error' => 'Invalid CSRF token'], 400);
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $title = trim($input['title'] ?? '');
        $notes = trim($input['notes'] ?? '');
        $date = $input['task_date'] ?? date('Y-m-d');
        if ($title === '') {
            json_response(['error' => 'Title is required'], 400);
        }
        $orderStmt = $conn->prepare('SELECT COALESCE(MAX(sort_order), 0) as max_order FROM todos WHERE user_id = ? AND task_date = ?');
        $orderStmt->bind_param('is', $user['id'], $date);
        $orderStmt->execute();
        $maxOrder = ($orderStmt->get_result()->fetch_assoc()['max_order'] ?? 0) + 1;
        $orderStmt->close();

        $stmt = $conn->prepare('INSERT INTO todos (user_id, title, notes, task_date, sort_order) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('isssi', $user['id'], $title, $notes, $date, $maxOrder);
        $stmt->execute();
        $todoId = $stmt->insert_id;
        $stmt->close();
        json_response(['todo' => ['id' => $todoId, 'title' => $title, 'notes' => $notes, 'task_date' => $date, 'is_completed' => false, 'sort_order' => $maxOrder]]);
        break;

    case 'PUT':
        $todoId = (int) ($input['id'] ?? 0);
        if (!$todoId) {
            json_response(['error' => 'To-do not found'], 404);
        }

        $currentStmt = $conn->prepare('SELECT id, task_date, sort_order FROM todos WHERE id = ? AND user_id = ?');
        $currentStmt->bind_param('ii', $todoId, $user['id']);
        $currentStmt->execute();
        $current = $currentStmt->get_result()->fetch_assoc();
        $currentStmt->close();
        if (!$current) {
            json_response(['error' => 'To-do not found'], 404);
        }

        if (array_key_exists('completed', $input)) {
            $completed = (bool) $input['completed'];
            $update = $conn->prepare('UPDATE todos SET is_completed = ? WHERE id = ? AND user_id = ?');
            $flag = $completed ? 1 : 0;
            $update->bind_param('iii', $flag, $todoId, $user['id']);
            $update->execute();
            $update->close();
            $summary = completion_summary($user['id'], $current['task_date']);
            $reward = attempt_daily_reward($user['id'], $current['task_date']);
            json_response(['success' => true, 'summary' => $summary, 'reward' => $reward]);
        }

        if (!empty($input['direction'])) {
            $direction = $input['direction'] === 'up' ? 'up' : 'down';
            $order = (int) $current['sort_order'];
            $comparison = $direction === 'up' ? '<' : '>';
            $sortDir = $direction === 'up' ? 'DESC' : 'ASC';
            $neighborStmt = $conn->prepare("SELECT id, sort_order FROM todos WHERE user_id = ? AND task_date = ? AND sort_order $comparison ? ORDER BY sort_order $sortDir LIMIT 1");
            $neighborStmt->bind_param('isi', $user['id'], $current['task_date'], $order);
            $neighborStmt->execute();
            $neighbor = $neighborStmt->get_result()->fetch_assoc();
            $neighborStmt->close();
            if ($neighbor) {
                $swap1 = $conn->prepare('UPDATE todos SET sort_order = ? WHERE id = ?');
                $swap1->bind_param('ii', $neighbor['sort_order'], $todoId);
                $swap1->execute();
                $swap1->close();

                $swap2 = $conn->prepare('UPDATE todos SET sort_order = ? WHERE id = ?');
                $swap2->bind_param('ii', $order, $neighbor['id']);
                $swap2->execute();
                $swap2->close();
            }
            json_response(['success' => true]);
        }

        $title = trim($input['title'] ?? '');
        $notes = trim($input['notes'] ?? '');
        if ($title === '') {
            json_response(['error' => 'Title is required'], 400);
        }
        $update = $conn->prepare('UPDATE todos SET title = ?, notes = ? WHERE id = ? AND user_id = ?');
        $update->bind_param('ssii', $title, $notes, $todoId, $user['id']);
        $update->execute();
        $update->close();
        json_response(['success' => true]);
        break;

    case 'DELETE':
        $todoId = (int) ($input['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM todos WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $todoId, $user['id']);
        $stmt->execute();
        $stmt->close();
        json_response(['success' => true]);
        break;

    default:
        json_response(['error' => 'Unsupported method'], 405);
}
?>
