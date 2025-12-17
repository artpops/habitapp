<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
    header('Location: /dashboard.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $message = 'Invalid request token.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $response = login_user($username, $password);
        if ($response['success']) {
            header('Location: /dashboard.php');
            exit;
        }
        $message = $response['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habit App - Login</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="hero-graphic">
            <div class="hero-circle"></div>
            <div class="hero-illustration">🙋‍♀️</div>
        </div>
        <h1>Hi there!</h1>
        <p class="lead">Ready to build some habits?</p>
        <?php if ($message): ?>
            <div class="alert alert-error"><?= $message ?></div>
        <?php endif; ?>
        <form method="POST" class="form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
            <label>Username
                <input type="text" name="username" required maxlength="50">
            </label>
            <label>Password
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="btn primary">Continue</button>
        </form>
        <p class="muted">No account? <a href="/register.php">Create one</a></p>
    </div>
</body>
</html>
