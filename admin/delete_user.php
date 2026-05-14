<?php
// admin/delete_user.php
session_start();

if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['error'] = 'Invalid user ID.';
    header('Location: manage_users.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    $_SESSION['error'] = 'Database unavailable.';
    header('Location: manage_users.php');
    exit;
}

$stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'User deleted successfully.';
    } else {
        $_SESSION['error'] = 'Database error deleting user.';
    }
    $stmt->close();
} else {
    $_SESSION['error'] = 'Database error preparing delete statement.';
}

header('Location: manage_users.php');
exit;
