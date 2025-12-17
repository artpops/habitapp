<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    if (!empty($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    return null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /index.php');
        exit;
    }
}

function login_user(string $username, string $password): array
{
    $conn = db();
    $stmt = $conn->prepare('SELECT id, username, email, password_hash FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
        ];
        regenerate_csrf_token();
        return [
            'success' => true,
            'message' => 'Login successful.',
        ];
    }

    return [
        'success' => false,
        'message' => 'Invalid credentials.',
    ];
}

function register_user(string $username, string $email, string $password): array
{
    $conn = db();
    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        return [
            'success' => false,
            'message' => 'Username or email already exists.',
        ];
    }
    $stmt->close();

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $insert = $conn->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $insert->bind_param('sss', $username, $email, $hash);

    if ($insert->execute()) {
        $_SESSION['user'] = [
            'id' => $insert->insert_id,
            'username' => $username,
            'email' => $email,
        ];
        regenerate_csrf_token();
        $insert->close();
        return [
            'success' => true,
            'message' => 'Registration successful.',
        ];
    }

    $insert->close();
    return [
        'success' => false,
        'message' => 'Registration failed. Please try again.',
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
?>
