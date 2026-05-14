<?php
// actions/create_user_action.php
// Receive name, email, password from POST and insert into users table (password stored as MD5).

session_start();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/create_user.php');
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Server-side validation
$errors = [];
if ($name === '') { $errors[] = 'Name is required.'; }
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Valid email is required.'; }
if ($password === '' || strlen($password) < 8) { $errors[] = 'Password required (min 8 characters).'; }

if (!empty($errors)) {
    $_SESSION['error'] = implode(' ', $errors);
    $_SESSION['old'] = ['name' => $name, 'email' => $email];
    header('Location: ../admin/create_user.php');
    exit;
}

// Include DB connection (single source of truth)
require_once __DIR__ . '/../config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    $_SESSION['error'] = 'Database connection failed.';
    header('Location: ../admin/create_user.php');
    exit;
}

// Check existing email
$check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
if ($check) {
    $check->bind_param('s', $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $check->close();
        $conn->close();
        $_SESSION['error'] = 'A user with that email already exists.';
        $_SESSION['old'] = ['name' => $name, 'email' => $email];
        header('Location: ../admin/create_user.php');
        exit;
    }
    $check->close();
}

// Insert new user with password_hash
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT INTO users (`name`, `email`, `password`) VALUES (?, ?, ?)');
if (! $stmt) {
    $_SESSION['error'] = 'Database error.';
    $conn->close();
    header('Location: ../admin/create_user.php');
    exit;
}

$stmt->bind_param('sss', $name, $email, $hash);
if ($stmt->execute()) {
    $_SESSION['success'] = 'User created successfully.';
    $stmt->close();
    $conn->close();
    header('Location: ../admin/manage_users.php');
    exit;
} else {
    $err = $stmt->error ?: $conn->error;
    $stmt->close();
    $conn->close();
    $_SESSION['error'] = 'Database error: ' . $err;
    $_SESSION['old'] = ['name' => $name, 'email' => $email];
    header('Location: ../admin/create_user.php');
    exit;
}

/**
 * Output a minimal Bootstrap-styled error page with a link back to the create user form.
 */
function output_error($message)
{
        http_response_code(500);
        $msg = htmlspecialchars($message);
        echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create User Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="alert alert-danger" role="alert">{$msg}</div>
            <a href="../admin/create_user.php" class="btn btn-primary">Back to Create User</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
        exit;
}
