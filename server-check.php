<?php
/**
 * Server Configuration Checker
 * Displays which PHP functions are available/disabled
 * 
 * Access: yourdomain.com/server-check.php
 */

// Don't display this on production - for testing only
if ($_SERVER['REMOTE_ADDR'] !== '::1' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    if (!isset($_GET['key']) || $_GET['key'] !== 'admin123') {
        die('Access denied. Use: ?key=admin123');
    }
}

?><!DOCTYPE html>
<html>
<head>
    <title>Server Configuration Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        h2 { color: #667eea; margin-top: 30px; font-size: 18px; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 4px; font-weight: bold; margin-left: 10px; }
        .enabled { background: #4caf50; color: white; }
        .disabled { background: #f44336; color: white; }
        .warning { background: #ff9800; color: white; }
        .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 15px 0; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        tr:hover { background: #f9f9f9; }
        .code { background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Server Configuration Check</h1>
        <p>Check which PHP functions are available on your hosting server.</p>

        <div class="info-box">
            <strong>ℹ️ Info:</strong> This page checks critical functions needed for the testing portal.
            <br><strong>Delete this file after checking:</strong> rm server-check.php
        </div>

        <h2>PHP Version & Basic Info</h2>
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td><code><?php echo phpversion(); ?></code></td>
            </tr>
            <tr>
                <td>SAPI</td>
                <td><?php echo php_sapi_name(); ?></td>
            </tr>
            <tr>
                <td>OS</td>
                <td><?php echo php_uname(); ?></td>
            </tr>
            <tr>
                <td>User Running PHP</td>
                <td><?php echo function_exists('get_current_user') ? get_current_user() : 'Unknown'; ?></td>
            </tr>
        </table>

        <h2>Critical Functions Status</h2>
        <table>
            <tr>
                <th>Function</th>
                <th>Status</th>
                <th>Purpose</th>
            </tr>
            <?php
            $functions = [
                'exec' => [
                    'status' => 'critical',
                    'purpose' => 'Run shell commands (Node automation, screenshots, PDF)'
                ],
                'shell_exec' => [
                    'status' => 'critical',
                    'purpose' => 'Execute shell commands via system'
                ],
                'system' => [
                    'status' => 'high',
                    'purpose' => 'Execute external programs'
                ],
                'passthru' => [
                    'status' => 'high',
                    'purpose' => 'Execute external programs with output'
                ],
                'proc_open' => [
                    'status' => 'high',
                    'purpose' => 'Open process for communication'
                ],
                'popen' => [
                    'status' => 'high',
                    'purpose' => 'Open pipe for command execution'
                ],
                'curl_exec' => [
                    'status' => 'high',
                    'purpose' => 'HTTP requests (API calls, fetching URLs)'
                ],
                'file_get_contents' => [
                    'status' => 'high',
                    'purpose' => 'Read file contents'
                ],
                'file_put_contents' => [
                    'status' => 'high',
                    'purpose' => 'Write file contents'
                ],
            ];

            foreach ($functions as $func => $info):
                $exists = function_exists($func);
                $statusClass = $exists ? 'enabled' : 'disabled';
                $statusText = $exists ? 'ENABLED ✓' : 'DISABLED ✗';
                ?>
                <tr>
                    <td><code><?php echo $func; ?></code></td>
                    <td><span class="status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                    <td><?php echo $info['purpose']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h2>Disabled Functions</h2>
        <?php
        $disabled = ini_get('disable_functions');
        if ($disabled):
            ?>
            <div class="info-box" style="background: #ffebee; border-left-color: #f44336;">
                <strong>⚠️ Disabled Functions:</strong>
                <div class="code"><?php echo htmlspecialchars($disabled); ?></div>
                <p><strong>Action:</strong> Contact your hosting provider and request these functions be enabled.</p>
            </div>
            <?php
        else:
            ?>
            <div class="info-box" style="background: #e8f5e9; border-left-color: #4caf50;">
                <strong>✓ No functions are disabled in php.ini</strong><br>
                All standard PHP functions should be available.
            </div>
            <?php
        endif;
        ?>

        <h2>Suhosin Restrictions</h2>
        <?php
        if (extension_loaded('suhosin')):
            ?>
            <div class="info-box" style="background: #fff3e0; border-left-color: #ff9800;">
                <strong>⚠️ Suhosin extension is installed</strong><br>
                Suhosin may impose additional restrictions. Contact your host for details.
            </div>
            <?php
        else:
            ?>
            <div class="info-box" style="background: #e8f5e9; border-left-color: #4caf50;">
                <strong>✓ Suhosin extension is not installed</strong>
            </div>
            <?php
        endif;
        ?>

        <h2>Recommendations</h2>
        <h3>If exec() is DISABLED:</h3>
        <ul>
            <li>✓ Basic test reports will work (HTML generation)</li>
            <li>✗ Screenshots will NOT be generated</li>
            <li>✗ PDFs will NOT be generated</li>
            <li>✗ Node automation will NOT run</li>
            <li><strong>Action:</strong> Contact hosting and request exec() be enabled, or upgrade to a plan that allows it</li>
        </ul>

        <h3>If exec() is ENABLED:</h3>
        <ul>
            <li>✓ All features work normally</li>
            <li>✓ Screenshots, PDFs, and automation available</li>
            <li>Ensure wkhtmltoimage and wkhtmltopdf are installed on server</li>
        </ul>

        <h2>Contact Hosting Provider</h2>
        <p>Use this template to request function enablement:</p>
        <div class="code">
Subject: Enable PHP exec() Function<br>
<br>
Hello,<br>
<br>
I need to enable the exec() PHP function on my account for my web application.<br>
<br>
Domain: <?php echo $_SERVER['HTTP_HOST']; ?><br>
PHP Version: <?php echo phpversion(); ?><br>
<br>
The application uses exec() to:<br>
- Run automated testing scripts<br>
- Generate screenshots and PDFs<br>
- Execute Node.js scripts safely<br>
<br>
Could you please enable this function? All commands are properly escaped and validated for security.<br>
<br>
Thank you
        </div>

        <h2>Testing Command Execution</h2>
        <?php if (function_exists('exec')): ?>
            <div class="info-box" style="background: #e8f5e9; border-left-color: #4caf50;">
                <strong>✓ Testing exec()...</strong>
                <?php
                ob_start();
                @exec('echo "Test successful"', $output, $code);
                $result = ob_get_clean();
                if (!empty($output) || $code === 0):
                    ?>
                    <p style="color: green;">Result: <?php echo htmlspecialchars(implode(PHP_EOL, $output)); ?></p>
                    <?php
                else:
                    ?>
                    <p style="color: orange;">exec() exists but returned exit code: <?php echo $code; ?></p>
                    <?php
                endif;
                ?>
            </div>
        <?php else: ?>
            <div class="info-box" style="background: #ffebee; border-left-color: #f44336;">
                <strong>✗ exec() is not available</strong><br>
                Cannot test command execution.
            </div>
        <?php endif; ?>

        <hr style="margin: 30px 0;">
        <p style="color: #999; font-size: 12px;">
            Generated: <?php echo date('Y-m-d H:i:s'); ?><br>
            <strong>Delete this file after checking!</strong> It's only for diagnostic purposes.
        </p>
    </div>
</body>
</html>
