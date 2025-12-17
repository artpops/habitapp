<?php
require_once __DIR__ . '/includes/config.php';

$conn = db();
$sql = file_get_contents(__DIR__ . '/install.sql');
if (!$conn->multi_query($sql)) {
    echo 'Error running install: ' . $conn->error;
    exit;
}

do {
    if ($result = $conn->store_result()) {
        $result->free();
    }
} while ($conn->more_results() && $conn->next_result());

echo 'Database tables installed successfully.';
?>
