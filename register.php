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
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$email) {
            $message = 'Please enter a valid email address.';
        } elseif ($password !== $confirm) {
            $message = 'Passwords do not match.';
        } else {
            $response = register_user($username, $email, $password);
            if ($response['success']) {
                header('Location: /dashboard.php');
                exit;
            }
            $message = $response['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="hero-graphic">
            <div class="hero-circle secondary"></div>
            <div class="hero-illustration">🌱</div>
        </div>
        <h1>Join Habit App</h1>
        <p class="lead">Track habits, earn collectibles, share progress.</p>
        <?php if ($message): ?>
            <div class="alert alert-error"><?= $message ?></div>
        <?php endif; ?>
        <form method="POST" class="form">
            <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
            <label>Username
                <input type="text" name="username" required maxlength="50">
            </label>
            <label>Email
                <input type="email" name="email" required>
            </label>
            <label>Password
                <input type="password" name="password" required minlength="6">
            </label>
            <label>Confirm Password
                <input type="password" name="confirm_password" required minlength="6">
            </label>
            <button type="submit" class="btn primary">Create Account</button>
        </form>
        <p class="muted">Already have an account? <a href="/index.php">Log in</a></p>
    </div>
</body>
</html>
