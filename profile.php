<?php
require_once __DIR__ . '/includes/auth.php';

$usernameParam = trim($_GET['user'] ?? '');
if (!$usernameParam) {
    http_response_code(404);
    exit('Profile not found');
}

$conn = db();
$stmt = $conn->prepare('SELECT id, username FROM users WHERE username = ?');
$stmt->bind_param('s', $usernameParam);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    exit('Profile not found');
}

$heatmap = build_heatmap((int) $user['id']);
$collectibles = list_collectibles((int) $user['id']);
$stats = profile_stats((int) $user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($user['username']); ?> — Profile</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="topbar">
        <div>
            <p class="muted">Public profile</p>
            <h2>@<?= sanitize($user['username']); ?></h2>
        </div>
        <nav>
            <a class="btn ghost" href="/index.php">Home</a>
        </nav>
    </header>

    <main class="layout">
        <section class="card">
            <div class="card-header">
                <h3>Stats</h3>
            </div>
            <div class="stats-grid">
                <div class="stat">
                    <p class="muted">Days Tracked</p>
                    <strong><?= $stats['days_tracked']; ?></strong>
                </div>
                <div class="stat">
                    <p class="muted">Completion Rate</p>
                    <strong><?= $stats['completion_rate']; ?>%</strong>
                </div>
                <div class="stat">
                    <p class="muted">Collectibles</p>
                    <strong><?= $stats['collectibles_count']; ?></strong>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h3>Monthly Heatmap</h3>
                <p class="muted">Aggregated activity only</p>
            </div>
            <div class="heatmap">
                <?php foreach ($heatmap as $day): ?>
                    <div class="heat-cell <?= $day['color']; ?>" title="<?= $day['date']; ?> — <?= $day['percent']; ?>%"><?= date('j', strtotime($day['date'])); ?></div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h3>Collectibles</h3>
            </div>
            <div class="collectibles">
                <?php if (empty($collectibles)): ?>
                    <p class="muted">No collectibles yet.</p>
                <?php else: ?>
                    <?php foreach ($collectibles as $item): ?>
                        <div class="collectible">
                            <img src="/awards/<?= urlencode($item['filename']); ?>" alt="Collectible">
                            <small class="muted">Earned <?= date('M j', strtotime($item['earned_date'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
