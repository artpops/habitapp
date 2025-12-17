<?php
// Database configuration
const DB_HOST = 'localhost';
const DB_USER = 'habit_user';
const DB_PASS = 'secure_password';
const DB_NAME = 'habit_app';
const APP_TIMEZONE = 'UTC';

if (!headers_sent()) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set(APP_TIMEZONE);

function db(): mysqli
{
    static $conn;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_errno) {
        http_response_code(500);
        exit('Database connection failed');
    }
    $conn->set_charset('utf8mb4');
    ensure_schema($conn);
    return $conn;
}

function ensure_schema(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    // Ensure core tables exist; if not, prompt to run installer instead of fatally erroring
    $requiredTables = ['users', 'habits', 'habit_completions', 'user_collectibles', 'daily_rewards'];
    foreach ($requiredTables as $table) {
        $stmt = $conn->prepare('SHOW TABLES LIKE ?');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        if (!$exists) {
            http_response_code(500);
            exit('Missing required tables. Please run install.php.');
        }
    }

    // Create the to-dos table on the fly for users upgrading from earlier versions
    $todoTable = 'todos';
    $stmt = $conn->prepare('SHOW TABLES LIKE ?');
    $stmt->bind_param('s', $todoTable);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        $createSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS todos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    notes TEXT,
    task_date DATE NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
SQL;

        if (!$conn->query($createSql)) {
            error_log('Failed to create todos table: ' . $conn->error);
            http_response_code(500);
            exit('Database is missing the todos table. Please rerun install.php.');
        }
    }

    $checked = true;
}
?>
