<?php
// actions/login_action.php
// Authenticate admin user from POSTed email and password (password stored as MD5)

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

// Fetch stored password for email
$stmt = $conn->prepare('SELECT password FROM admin WHERE email = ? LIMIT 1');
if (! $stmt) {
    $_SESSION['error'] = 'Database error.';
    header('Location: ../admin/login.php');
    exit;
}

$stmt->bind_param('s', $email);
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

$stmt->bind_result($stored_hash);
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
        $upd = $conn->prepare('UPDATE admin SET password = ? WHERE email = ?');
        if ($upd) {
            $upd->bind_param('ss', $newHash, $email);
            $upd->execute();
            $upd->close();
        }
    }
}

$stmt->close();

if ($is_valid) {
    $_SESSION['admin_email'] = $email;
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

/**
 * Helper: show a simple Bootstrap error and link back to login
 */
function output_error_and_back($message)
{
    // Ensure session is started so messages could be persisted if needed
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

        // Minimal HTML using Bootstrap CDN to display error and a back link
        http_response_code(401);
        $msg = htmlspecialchars($message);
        echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="alert alert-danger" role="alert">{$msg}</div>
            <div class="mb-3">
                <a href="../admin/login.php" class="btn btn-primary">Back to Login</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
        exit;
}
