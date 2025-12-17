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

function build_heatmap(int $userId): array
{
    $start = new DateTime('first day of this month');
    $end = new DateTime('last day of this month');
    $conn = db();

    $stmt = $conn->prepare('SELECT completion_date, COUNT(*) as total FROM habit_completions WHERE user_id = ? AND completion_date BETWEEN ? AND ? GROUP BY completion_date');
    $startStr = $start->format('Y-m-d');
    $endStr = $end->format('Y-m-d');
    $stmt->bind_param('iss', $userId, $startStr, $endStr);
    $stmt->execute();
    $result = $stmt->get_result();
    $completionMap = [];
    while ($row = $result->fetch_assoc()) {
        $completionMap[$row['completion_date']] = (int) $row['total'];
    }
    $stmt->close();

    $totalHabits = max(count(get_habits($userId)), 1);
    $heatmap = [];
    $cursor = clone $start;
    while ($cursor <= $end) {
        $dateStr = $cursor->format('Y-m-d');
        $completed = $completionMap[$dateStr] ?? 0;
        $percent = $totalHabits > 0 ? round(($completed / $totalHabits) * 100) : 0;
        if ($cursor > new DateTime()) {
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
    $totalHabits = count(get_habits($userId));
    $completedCount = 0;
    if ($totalHabits > 0) {
        $stmt = $conn->prepare('SELECT COUNT(*) as total FROM habit_completions WHERE user_id = ? AND completion_date = ?');
        $stmt->bind_param('is', $userId, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $completedCount = (int) $row['total'];
        $stmt->close();
    }
    $percentage = $totalHabits > 0 ? round(($completedCount / $totalHabits) * 100) : 0;
    return [
        'total' => $totalHabits,
        'completed' => $completedCount,
        'percentage' => $percentage,
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
    $conn = db();
    $summary = completion_summary($userId, $date);
    if ($summary['total'] === 0 || $summary['percentage'] < 90) {
        return ['awarded' => false, 'message' => 'Keep going to earn a collectible!'];
    }

    $conn->begin_transaction();
    try {
        $check = $conn->prepare('SELECT id FROM daily_rewards WHERE user_id = ? AND reward_date = ? FOR UPDATE');
        $check->bind_param('is', $userId, $date);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            $conn->commit();
            return ['awarded' => false, 'message' => 'Collectible already awarded today.'];
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
    $daysStmt = $conn->prepare('SELECT COUNT(DISTINCT completion_date) as days_tracked, SUM(1) as total_checks FROM habit_completions WHERE user_id = ?');
    $daysStmt->bind_param('i', $userId);
    $daysStmt->execute();
    $result = $daysStmt->get_result();
    $data = $result->fetch_assoc() ?: ['days_tracked' => 0, 'total_checks' => 0];
    $daysStmt->close();

    $habitCountStmt = $conn->prepare('SELECT COUNT(*) as habit_count FROM habits WHERE user_id = ?');
    $habitCountStmt->bind_param('i', $userId);
    $habitCountStmt->execute();
    $habitResult = $habitCountStmt->get_result();
    $habitCount = ($habitResult->fetch_assoc()['habit_count'] ?? 0);
    $habitCountStmt->close();

    $collectibles = list_collectibles($userId);

    $completionRate = $habitCount > 0 && $data['days_tracked'] > 0
        ? round(($data['total_checks'] / ($habitCount * $data['days_tracked'])) * 100)
        : 0;

    return [
        'days_tracked' => (int) $data['days_tracked'],
        'completion_rate' => $completionRate,
        'collectibles_count' => count($collectibles),
    ];
}
?>
