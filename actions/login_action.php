<?php
// actions/login_action.php
// Authenticate portal users from POSTed email and password.

session_start();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/login.php');
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Basic server-side validation
if ($email === '' || $password === '') {
    $_SESSION['error'] = 'Please provide both email and password.';
    $_SESSION['old'] = ['email' => $email];
    header('Location: ../admin/login.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Please provide a valid email address.';
    $_SESSION['old'] = ['email' => $email];
    header('Location: ../admin/login.php');
    exit;
}

// Include DB connection (returns a mysqli instance)
$db_path = __DIR__ . '/../config/db.php';
if (!file_exists($db_path)) {
    $_SESSION['error'] = 'Database configuration not found.';
    header('Location: ../admin/login.php');
    exit;
}

require_once $db_path;
if (!isset($conn) || ! ($conn instanceof mysqli)) {
    $_SESSION['error'] = 'Database connection failed.';
    header('Location: ../admin/login.php');
    exit;
}

// Created users are stored in `users`; the original admin account is stored in
// `admin`. Check both so every provisioned portal account can sign in.
$stmt = $conn->prepare("
    SELECT id, email, password, 'admin' AS account_type
    FROM admin
    WHERE email = ?
    UNION ALL
    SELECT id, email, password, 'user' AS account_type
    FROM users
    WHERE email = ?
");
if (! $stmt) {
    $_SESSION['error'] = 'Database error.';
    header('Location: ../admin/login.php');
    exit;
}

// Initialize variables that will be populated by bind_result
$account_id = 0;
$account_email = '';
$stored_hash = '';
$account_type = '';

$stmt->bind_param('ss', $email, $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows !== 1) {
    $stmt->close();
    $conn->close();
    $_SESSION['error'] = 'Invalid email or password.';
    $_SESSION['old'] = ['email' => $email];
    header('Location: ../admin/login.php');
    exit;
}

$stmt->bind_result($account_id, $account_email, $stored_hash, $account_type);
$stmt->fetch();

// First try password_verify (supports password_hash). If that fails, fallback to MD5 for legacy accounts.
$is_valid = false;
if (password_verify($password, $stored_hash)) {
    $is_valid = true;
} else {
    // fallback: MD5 comparison (legacy)
    if (md5($password) === $stored_hash) {
        $is_valid = true;
        // migrate to password_hash for this user
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $table = $account_type === 'admin' ? 'admin' : 'users';
        $upd = $conn->prepare("UPDATE {$table} SET password = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param('si', $newHash, $account_id);
            $upd->execute();
            $upd->close();
        }
    }
}

$stmt->close();

if ($is_valid) {
    $_SESSION['admin_email'] = $account_email;
    $_SESSION['account_type'] = $account_type;
    $_SESSION['account_id'] = (int)$account_id;
    $conn->close();
    header('Location: ../admin/dashboard.php');
    exit;
} else {
    $conn->close();
    $_SESSION['error'] = 'Invalid email or password.';
    $_SESSION['old'] = ['email' => $email];
    header('Location: ../admin/login.php');
    exit;
}
