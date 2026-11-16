<?php
/**
 * Global Configuration & Database Initialization
 * For vCard Generator & Dynamic Card Tracking
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DATA_DIR', __DIR__ . '/data');
define('DEFAULT_ADMIN_PIN', '1234');
define('DEFAULT_ADMIN_EMAIL', 'admin@example.com');

// =========================================================================
// EMAIL API CONFIGURATION (Transactional Email Service)
// =========================================================================
// Service: 'resend' (https://resend.com) or 'brevo' (https://brevo.com)
define('EMAIL_SERVICE', 'resend');

// Paste your API key below (leave blank to fallback to standard PHP mail()):
define('EMAIL_API_KEY', '');

// Sender email
define('EMAIL_FROM', 'vCard Studio <onboarding@resend.dev>');

// Ensure data directory exists
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// =========================================================================
// DATABASE CONFIGURATION (MySQL / MariaDB)
// =========================================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'vcard_app');      // Create this database in phpMyAdmin and import schema.sql
define('DB_USER', 'root');           // Your MySQL username
define('DB_PASS', '');               // Your MySQL password
define('DB_CHARSET', 'utf8mb4');

function getDB() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Seed default admin settings if not already present
        $pdo->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('admin_pin', '" . DEFAULT_ADMIN_PIN . "')");
        $pdo->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('admin_email', '" . DEFAULT_ADMIN_EMAIL . "')");

        return $pdo;
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
    }
}

/**
 * Get current Admin PIN
 */
function getAdminPIN() {
    $db = getDB();
    $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `key` = 'admin_pin'");
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ? $row['value'] : DEFAULT_ADMIN_PIN;
}

/**
 * Update Admin PIN
 */
function setAdminPIN($newPin) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE `settings` SET `value` = :pin WHERE `key` = 'admin_pin'");
    return $stmt->execute([':pin' => $newPin]);
}

/**
 * Get Admin Email
 */
function getAdminEmail() {
    $db = getDB();
    $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `key` = 'admin_email'");
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ? $row['value'] : DEFAULT_ADMIN_EMAIL;
}

/**
 * Update Admin Email
 */
function setAdminEmail($email) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE `settings` SET `value` = :email WHERE `key` = 'admin_email'");
    return $stmt->execute([':email' => $email]);
}

/**
 * Check if current session is authenticated as Admin
 */
function isAdminAuthenticated() {
    return isset($_SESSION['vcard_admin_auth']) && $_SESSION['vcard_admin_auth'] === true;
}

/**
 * Set admin authentication in session
 */
function setAdminAuthenticated($status = true) {
    if ($status) {
        $_SESSION['vcard_admin_auth'] = true;
    } else {
        unset($_SESSION['vcard_admin_auth']);
    }
}

/**
 * Send Transactional Email via Free Cloud API (Resend or Brevo)
 */
function sendTransactionalEmail($toEmail, $subject, $htmlBody, $textBody = '') {
    $apiKey = trim(EMAIL_API_KEY);
    $service = strtolower(EMAIL_SERVICE);

    // If no API key is provided, fallback to PHP mail()
    if (empty($apiKey)) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . EMAIL_FROM . "\r\n";
        $sent = @mail($toEmail, $subject, $htmlBody, $headers);
        return [
            'sent' => $sent,
            'service' => 'php_mail',
            'api_key_configured' => false
        ];
    }

    if ($service === 'resend') {
        $url = 'https://api.resend.com/emails';
        $payload = [
            'from' => EMAIL_FROM,
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $textBody ?: strip_tags($htmlBody)
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['sent' => true, 'service' => 'resend', 'response' => json_decode($response, true)];
        } else {
            return ['sent' => false, 'service' => 'resend', 'error' => "HTTP $httpCode: " . ($response ?: $error)];
        }
    } elseif ($service === 'brevo') {
        $url = 'https://api.brevo.com/v3/smtp/email';
        $payload = [
            'sender' => ['name' => 'vCard Studio', 'email' => 'onboarding@resend.dev'],
            'to' => [['email' => $toEmail]],
            'subject' => $subject,
            'htmlContent' => $htmlBody,
            'textContent' => $textBody ?: strip_tags($htmlBody)
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'api-key: ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['sent' => true, 'service' => 'brevo', 'response' => json_decode($response, true)];
        } else {
            return ['sent' => false, 'service' => 'brevo', 'error' => "HTTP $httpCode: " . ($response ?: $error)];
        }
    }

    return ['sent' => false, 'error' => 'Unknown EMAIL_SERVICE configured.'];
}

// Auto-initialize DB on load
getDB();
