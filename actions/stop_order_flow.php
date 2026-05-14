<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

function send_stop_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    send_stop_json(['success' => false, 'error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_stop_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload ?: '{}', true);
if (!is_array($payload)) {
    $payload = [];
}

$clientRunId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($payload['client_run_id'] ?? $_POST['client_run_id'] ?? ''));
if ($clientRunId === '') {
    send_stop_json(['success' => false, 'error' => 'Missing run identifier.'], 422);
}

$stopRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'order_flow_stops';
if (!is_dir($stopRoot) && !mkdir($stopRoot, 0777, true) && !is_dir($stopRoot)) {
    send_stop_json(['success' => false, 'error' => 'Unable to create stop directory.'], 500);
}

$stopPath = $stopRoot . DIRECTORY_SEPARATOR . $clientRunId . '.stop';
$stopPayload = [
    'requested_by' => (string)$_SESSION['admin_email'],
    'requested_at' => date('c'),
];

if (file_put_contents($stopPath, json_encode($stopPayload, JSON_PRETTY_PRINT), LOCK_EX) === false) {
    send_stop_json(['success' => false, 'error' => 'Unable to write stop signal.'], 500);
}

send_stop_json(['success' => true, 'message' => 'Stop signal sent.']);
