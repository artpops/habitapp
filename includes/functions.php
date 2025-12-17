<?php
require_once __DIR__ . '/config.php';

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function regenerate_csrf_token(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        regenerate_csrf_token();
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return hash_equals(csrf_token(), $token);
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_habits(int $userId): array
{
    $conn = db();
    $stmt = $conn->prepare('SELECT id, name, description, sort_order, is_active FROM habits WHERE user_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $habits = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $habits;
}

function get_today_completions(int $userId): array
{
    $conn = db();
    $today = date('Y-m-d');
    $stmt = $conn->prepare('SELECT habit_id FROM habit_completions WHERE user_id = ? AND completion_date = ?');
    $stmt->bind_param('is', $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $completed = [];
    while ($row = $result->fetch_assoc()) {
        $completed[] = (int) $row['habit_id'];
    }
    $stmt->close();
    return $completed;
}

function get_todos(int $userId, string $date): array
{
    $conn = db();
    $stmt = $conn->prepare('SELECT id, title, notes, task_date, is_completed, sort_order FROM todos WHERE user_id = ? AND task_date = ? ORDER BY sort_order ASC, id ASC');
    $stmt->bind_param('is', $userId, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $todos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $todos;
}

function build_heatmap(int $userId): array
{
    $start = new DateTime('first day of this month');
    $end = new DateTime('last day of this month');
    $conn = db();

    $startStr = $start->format('Y-m-d');
    $endStr = $end->format('Y-m-d');

    $habitStmt = $conn->prepare('SELECT completion_date, COUNT(*) as total FROM habit_completions WHERE user_id = ? AND completion_date BETWEEN ? AND ? GROUP BY completion_date');
    $habitStmt->bind_param('iss', $userId, $startStr, $endStr);
    $habitStmt->execute();
    $habitResult = $habitStmt->get_result();
    $habitCompletionMap = [];
    while ($row = $habitResult->fetch_assoc()) {
        $habitCompletionMap[$row['completion_date']] = (int) $row['total'];
    }
    $habitStmt->close();

    $todoStmt = $conn->prepare('SELECT task_date, COUNT(*) as total, SUM(is_completed) as done FROM todos WHERE user_id = ? AND task_date BETWEEN ? AND ? GROUP BY task_date');
    $todoStmt->bind_param('iss', $userId, $startStr, $endStr);
    $todoStmt->execute();
    $todoResult = $todoStmt->get_result();
    $todoTotals = [];
    $todoCompleted = [];
    while ($row = $todoResult->fetch_assoc()) {
        $todoTotals[$row['task_date']] = (int) $row['total'];
        $todoCompleted[$row['task_date']] = (int) ($row['done'] ?? 0);
    }
    $todoStmt->close();

    $totalHabits = count(get_habits($userId));
    $heatmap = [];
    $cursor = clone $start;
    $today = new DateTime('today');

    while ($cursor <= $end) {
        $dateStr = $cursor->format('Y-m-d');
        $habitsCompleted = $habitCompletionMap[$dateStr] ?? 0;
        $todosForDay = $todoTotals[$dateStr] ?? 0;
        $todosCompleted = $todoCompleted[$dateStr] ?? 0;
        $totalTasks = $totalHabits + $todosForDay;
        $completedTasks = $habitsCompleted + $todosCompleted;
        $percent = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
        if ($cursor > $today) {
            $color = 'gray';
        } elseif ($percent >= 90) {
            $color = 'green';
        } elseif ($percent >= 50) {
            $color = 'yellow';
        } elseif ($percent > 0) {
            $color = 'red';
        } else {
            $color = 'gray';
        }
        $heatmap[] = [
            'date' => $dateStr,
            'percent' => $percent,
            'color' => $color,
        ];
        $cursor->modify('+1 day');
    }

    return $heatmap;
}

function completion_summary(int $userId, ?string $date = null): array
{
    $date = $date ?: date('Y-m-d');
    $conn = db();

    $habitsTotal = count(get_habits($userId));
    $habitsCompleted = 0;
    if ($habitsTotal > 0) {
        $stmt = $conn->prepare('SELECT COUNT(*) as total FROM habit_completions WHERE user_id = ? AND completion_date = ?');
        $stmt->bind_param('is', $userId, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $habitsCompleted = (int) $row['total'];
        $stmt->close();
    }

    $todoTotalsStmt = $conn->prepare('SELECT COUNT(*) as total, SUM(is_completed) as done FROM todos WHERE user_id = ? AND task_date = ?');
    $todoTotalsStmt->bind_param('is', $userId, $date);
    $todoTotalsStmt->execute();
    $todoTotalsResult = $todoTotalsStmt->get_result()->fetch_assoc() ?: ['total' => 0, 'done' => 0];
    $todoTotalsStmt->close();

    $todoTotal = (int) $todoTotalsResult['total'];
    $todoCompleted = (int) ($todoTotalsResult['done'] ?? 0);

    $habitPercent = $habitsTotal > 0 ? round(($habitsCompleted / $habitsTotal) * 100) : 100;
    $todoPercent = $todoTotal > 0 ? round(($todoCompleted / $todoTotal) * 100) : 100;

    $overallTotal = $habitsTotal + $todoTotal;
    $overallCompleted = $habitsCompleted + $todoCompleted;
    $overallPercent = $overallTotal > 0 ? round(($overallCompleted / $overallTotal) * 100) : 100;

    return [
        'habits' => [
            'total' => $habitsTotal,
            'completed' => $habitsCompleted,
            'percentage' => $habitPercent,
        ],
        'todos' => [
            'total' => $todoTotal,
            'completed' => $todoCompleted,
            'percentage' => $todoPercent,
        ],
        'overall' => [
            'total' => $overallTotal,
            'completed' => $overallCompleted,
            'percentage' => $overallPercent,
        ],
    ];
}

function list_collectibles(int $userId): array
{
    $conn = db();
    $stmt = $conn->prepare('SELECT filename, earned_date FROM user_collectibles WHERE user_id = ? ORDER BY earned_date DESC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $items;
}

function scan_award_files(): array
{
    $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
    $files = [];
    $dir = __DIR__ . '/../awards';
    if (!is_dir($dir)) {
        return $files;
    }
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) {
            $files[] = $file;
        }
    }
    sort($files);
    return $files;
}

function attempt_daily_reward(int $userId, ?string $date = null): array
{
    $date = $date ?: date('Y-m-d');
    $today = date('Y-m-d');
    if ($date >= $today) {
        return ['awarded' => false, 'message' => 'Rewards unlock after midnight.'];
    }

    $conn = db();
    $summary = completion_summary($userId, $date);
    $meetsHabits = $summary['habits']['percentage'] >= 90;
    $meetsTodos = $summary['todos']['percentage'] >= 90;
    if (!$meetsHabits || !$meetsTodos) {
        return ['awarded' => false, 'message' => 'Complete 90% of habits and to-dos to earn a collectible.'];
    }

    $conn->begin_transaction();
    try {
        $check = $conn->prepare('SELECT id FROM daily_rewards WHERE user_id = ? AND reward_date = ? FOR UPDATE');
        $check->bind_param('is', $userId, $date);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            $conn->commit();
            return ['awarded' => false, 'message' => 'Collectible already awarded for that day.'];
        }
        $check->close();

        $owned = list_collectibles($userId);
        $ownedFiles = array_column($owned, 'filename');
        $available = array_values(array_diff(scan_award_files(), $ownedFiles));

        if (empty($available)) {
            $conn->commit();
            return ['awarded' => false, 'message' => 'Collection complete!'];
        }

        $selected = $available[array_rand($available)];
        $insertCollectible = $conn->prepare('INSERT INTO user_collectibles (user_id, filename, earned_date) VALUES (?, ?, ?)');
        $insertCollectible->bind_param('iss', $userId, $selected, $date);
        $insertCollectible->execute();
        $collectibleId = $conn->insert_id;
        $insertCollectible->close();

        $insertReward = $conn->prepare('INSERT INTO daily_rewards (user_id, reward_date, collectible_id) VALUES (?, ?, ?)');
        $insertReward->bind_param('isi', $userId, $date, $collectibleId);
        $insertReward->execute();
        $insertReward->close();

        $conn->commit();
        return [
            'awarded' => true,
            'filename' => $selected,
            'message' => 'New collectible unlocked!'
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['awarded' => false, 'message' => 'Could not award collectible.'];
    }
}

function profile_stats(int $userId): array
{
    $conn = db();

    $daysStmt = $conn->prepare('SELECT COUNT(DISTINCT day) as days_tracked FROM (
        SELECT completion_date AS day FROM habit_completions WHERE user_id = ?
        UNION ALL
        SELECT task_date AS day FROM todos WHERE user_id = ?
    ) AS d');
    $daysStmt->bind_param('ii', $userId, $userId);
    $daysStmt->execute();
    $daysResult = $daysStmt->get_result()->fetch_assoc() ?: ['days_tracked' => 0];
    $daysStmt->close();

    $habitCountStmt = $conn->prepare('SELECT COUNT(*) as habit_count FROM habits WHERE user_id = ?');
    $habitCountStmt->bind_param('i', $userId);
    $habitCountStmt->execute();
    $habitResult = $habitCountStmt->get_result();
    $habitCount = ($habitResult->fetch_assoc()['habit_count'] ?? 0);
    $habitCountStmt->close();

    $habitChecksStmt = $conn->prepare('SELECT COUNT(*) as total_checks FROM habit_completions WHERE user_id = ?');
    $habitChecksStmt->bind_param('i', $userId);
    $habitChecksStmt->execute();
    $habitChecks = ($habitChecksStmt->get_result()->fetch_assoc()['total_checks'] ?? 0);
    $habitChecksStmt->close();

    $todoStmt = $conn->prepare('SELECT COUNT(*) as total_tasks, SUM(is_completed) as done FROM todos WHERE user_id = ?');
    $todoStmt->bind_param('i', $userId);
    $todoStmt->execute();
    $todoData = $todoStmt->get_result()->fetch_assoc() ?: ['total_tasks' => 0, 'done' => 0];
    $todoStmt->close();

    $daysTracked = (int) $daysResult['days_tracked'];
    $totalCapacity = ($habitCount * max($daysTracked, 1)) + (int) $todoData['total_tasks'];
    $totalCompleted = $habitChecks + (int) ($todoData['done'] ?? 0);
    $completionRate = $totalCapacity > 0 ? round(($totalCompleted / $totalCapacity) * 100) : 0;

    $collectibles = list_collectibles($userId);

    return [
        'days_tracked' => $daysTracked,
        'completion_rate' => $completionRate,
        'collectibles_count' => count($collectibles),
    ];
}
?>
