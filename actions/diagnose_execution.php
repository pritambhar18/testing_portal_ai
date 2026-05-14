<?php
/**
 * Quick Diagnostic: Check which PHP execution functions are available
 * 
 * Access: https://dev02.stagingit.net/testing_portal/actions/diagnose_execution.php
 * 
 * This will show you exactly which functions you can use as alternatives to exec()
 */

// Simple access control
if (!isset($_GET['key']) || $_GET['key'] !== 'diagnose') {
    die('Access denied. Add ?key=diagnose to URL');
}

header('Content-Type: application/json; charset=utf-8');

$functions = [
    'exec' => 'Run command and capture output in array',
    'shell_exec' => 'Run command and return output as string',
    'system' => 'Run command and output directly',
    'passthru' => 'Run command and pass raw output',
    'proc_open' => 'Open process with pipes for I/O',
    'popen' => 'Open pipe to process',
    'pcntl_exec' => 'Execute program (Unix only)',
    'eval' => 'Execute PHP code dynamically',
];

$disabled = ini_get('disable_functions');
$disabledFunctions = $disabled ? array_map('trim', explode(',', $disabled)) : [];

$result = [
    'php_version' => phpversion(),
    'sapi' => php_sapi_name(),
    'disabled_functions_config' => $disabled ?: 'none',
    'available_functions' => [],
    'disabled_functions' => [],
    'recommendations' => [],
];

foreach ($functions as $func => $description) {
    if (function_exists($func)) {
        $result['available_functions'][$func] = $description;
    } else {
        $result['disabled_functions'][$func] = $description;
    }
}

// Test each available function
foreach ($result['available_functions'] as $func => $desc) {
    $testCommand = 'echo "Test from ' . $func . '"';
    
    switch ($func) {
        case 'exec':
            @exec($testCommand, $output, $code);
            $result['test_' . $func] = ['success' => $code === 0, 'output' => implode("\n", $output)];
            break;
        case 'shell_exec':
            @$output = shell_exec($testCommand);
            $result['test_' . $func] = ['success' => !empty($output), 'output' => trim($output)];
            break;
        case 'system':
            ob_start();
            @system($testCommand);
            $output = ob_get_clean();
            $result['test_' . $func] = ['success' => !empty($output), 'output' => trim($output)];
            break;
        case 'passthru':
            ob_start();
            @passthru($testCommand);
            $output = ob_get_clean();
            $result['test_' . $func] = ['success' => !empty($output), 'output' => trim($output)];
            break;
        case 'proc_open':
            $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = @proc_open($testCommand, $spec, $pipes, null);
            if (is_resource($proc)) {
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                $result['test_' . $func] = ['success' => true, 'output' => trim($output)];
            } else {
                $result['test_' . $func] = ['success' => false, 'output' => 'proc_open exists but failed'];
            }
            break;
        case 'popen':
            $handle = @popen($testCommand, 'r');
            if ($handle) {
                $output = fread($handle, 1024);
                pclose($handle);
                $result['test_' . $func] = ['success' => true, 'output' => trim($output)];
            } else {
                $result['test_' . $func] = ['success' => false, 'output' => 'popen exists but failed'];
            }
            break;
    }
}

// Provide recommendations
if (count($result['available_functions']) === 0) {
    $result['recommendations'][] = '❌ CRITICAL: No command execution functions available. Contact hosting provider immediately.';
    $result['recommendations'][] = 'Request at least one of: exec, shell_exec, system, passthru, or proc_open';
} elseif (isset($result['available_functions']['exec'])) {
    $result['recommendations'][] = '✅ exec() is available - your code should work fine';
} elseif (isset($result['available_functions']['proc_open'])) {
    $result['recommendations'][] = '⚠️  proc_open() is available - can use as fallback for exec()';
} elseif (isset($result['available_functions']['shell_exec'])) {
    $result['recommendations'][] = '⚠️  shell_exec() is available - can use as fallback for exec()';
} else {
    $result['recommendations'][] = '⚠️  Limited execution functions. May need to upgrade hosting plan.';
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
