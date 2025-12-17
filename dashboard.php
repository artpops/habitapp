<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$habits = get_habits($user['id']);
$completedIds = get_today_completions($user['id']);
$summary = completion_summary($user['id']);
$heatmap = build_heatmap($user['id']);
$collectibles = list_collectibles($user['id']);
$progressClass = $summary['percentage'] >= 90 ? 'success' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Habit App</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="topbar">
        <div>
            <p class="muted">Welcome back,</p>
            <h2><?= sanitize($user['username']); ?></h2>
        </div>
        <nav>
            <a class="btn ghost" href="/profile.php?user=<?= urlencode($user['username']); ?>">Public profile</a>
            <a class="btn ghost" href="/logout.php">Logout</a>
        </nav>
    </header>

    <main class="layout">
        <section class="card">
            <div class="card-header">
                <div>
                    <p class="muted">Today</p>
                    <h3><?= date('F j, Y'); ?></h3>
                </div>
                <div class="progress-text <?= $progressClass; ?>"><?= $summary['completed']; ?>/<?= max($summary['total'], 1); ?> completed — <?= $summary['percentage']; ?>%</div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill <?= $progressClass; ?>" style="width: <?= $summary['percentage']; ?>%"></div>
            </div>
            <div class="task-columns">
                <div class="tasks">
                    <div class="task-header">
                        <h4>My Habits</h4>
                        <button class="btn small" id="addHabitBtn">Add New Habit</button>
                    </div>
                    <ul class="habit-list" id="habitList">
                        <?php if (empty($habits)): ?>
                            <li class="empty">Add a habit to start tracking.</li>
                        <?php endif; ?>
                        <?php foreach ($habits as $habit): ?>
                            <li class="habit-item" data-habit-id="<?= $habit['id']; ?>">
                                <label class="checkbox">
                                    <input type="checkbox" <?= in_array($habit['id'], $completedIds, true) ? 'checked' : ''; ?> data-habit="<?= $habit['id']; ?>">
                                    <span class="checkmark"></span>
                                    <span class="habit-name"><?= sanitize($habit['name']); ?></span>
                                </label>
                                <?php if (!empty($habit['description'])): ?>
                                    <p class="muted small"><?= sanitize($habit['description']); ?></p>
                                <?php endif; ?>
                                <div class="habit-actions">
                                    <button class="btn tiny reorder" data-direction="up">↑</button>
                                    <button class="btn tiny reorder" data-direction="down">↓</button>
                                    <button class="btn tiny edit">Edit</button>
                                    <button class="btn tiny danger delete">Delete</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tasks">
                    <h4>Collectibles</h4>
                    <div class="collectibles" id="collectiblesGrid">
                        <?php if (empty($collectibles)): ?>
                            <p class="muted">Finish 90% of today to unlock your first collectible.</p>
                        <?php else: ?>
                            <?php foreach ($collectibles as $item): ?>
                                <div class="collectible">
                                    <img src="/awards/<?= urlencode($item['filename']); ?>" alt="Collectible">
                                    <small class="muted">Earned <?= date('M j', strtotime($item['earned_date'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h3>Monthly Heatmap</h3>
                <p class="muted">Track your streak visually</p>
            </div>
            <div class="heatmap" id="heatmap">
                <?php foreach ($heatmap as $day): ?>
                    <div class="heat-cell <?= $day['color']; ?>" title="<?= $day['date']; ?> — <?= $day['percent']; ?>%"><?= date('j', strtotime($day['date'])); ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <div class="modal" id="habitModal" hidden>
        <div class="modal-content">
            <h3 id="habitModalTitle">Add Habit</h3>
            <form id="habitForm">
                <input type="hidden" name="habit_id" id="habitId">
                <label>Name
                    <input type="text" name="name" id="habitName" required maxlength="100">
                </label>
                <label>Description
                    <textarea name="description" id="habitDescription" rows="2"></textarea>
                </label>
                <div class="modal-actions">
                    <button type="button" class="btn ghost" id="closeHabitModal">Cancel</button>
                    <button type="submit" class="btn primary">Save</button>
                </div>
                <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
            </form>
        </div>
    </div>

    <script>
        const csrfToken = '<?= csrf_token(); ?>';
    </script>
    <script src="/assets/js/app.js"></script>
</body>
</html>
