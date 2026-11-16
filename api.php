<?php
/**
 * API Handler for Dynamic vCards, Admin Auth, and Dashboard
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

// Helper to send JSON response
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Generate clean URL slug from name
function generateSlug($name, $db, $excludeId = null) {
    $baseSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name)));
    $baseSlug = trim($baseSlug, '-');
    if (empty($baseSlug)) {
        $baseSlug = 'card';
    }

    $slug = $baseSlug;
    $counter = 1;

    while (true) {
        $sql = "SELECT id FROM cards WHERE slug = :slug";
        if ($excludeId) {
            $sql .= " AND id != :id";
        }
        $stmt = $db->prepare($sql);
        $params = [':slug' => $slug];
        if ($excludeId) {
            $params[':id'] = $excludeId;
        }
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            break;
        }
        $slug = $baseSlug . '-' . (++$counter);
    }

    return $slug;
}

switch ($action) {

    // 1. Admin PIN Login
    case 'login':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $pin = trim($input['pin'] ?? '');
        $currentPin = getAdminPIN();

        if ($pin === $currentPin) {
            setAdminAuthenticated(true);
            jsonResponse(['success' => true, 'message' => 'Logged in successfully']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Invalid Admin PIN'], 401);
        }
        break;

    // 2. Admin Logout
    case 'logout':
        setAdminAuthenticated(false);
        jsonResponse(['success' => true, 'message' => 'Logged out']);
        break;

    // 3. Check Admin Auth Status
    case 'check_auth':
        jsonResponse([
            'authenticated' => isAdminAuthenticated()
        ]);
        break;

    // 3b. Get Server Local LAN IP (for phone testing over Wi-Fi)
    case 'get_server_ip':
        $hostname = gethostname();
        $ip = gethostbyname($hostname);
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";

        jsonResponse([
            'success' => true,
            'ip' => $ip,
            'hostname' => $hostname,
            'suggested_url' => "{$protocol}{$ip}{$scriptDir}/card.html"
        ]);
        break;

    // 4. Change Admin PIN
    case 'change_pin':
        if (!isAdminAuthenticated()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $oldPin = trim($input['old_pin'] ?? '');
        $newPin = trim($input['new_pin'] ?? '');

        if ($oldPin !== getAdminPIN()) {
            jsonResponse(['success' => false, 'message' => 'Current PIN is incorrect'], 400);
        }

        if (strlen($newPin) < 4) {
            jsonResponse(['success' => false, 'message' => 'New PIN must be at least 4 digits/characters'], 400);
        }

        setAdminPIN($newPin);
        jsonResponse(['success' => true, 'message' => 'Admin PIN updated successfully']);
        break;

    // 4b. Forgot Admin PIN (Sends Reset Link to Admin Email)
    case 'forgot_pin':
        $adminEmail = getAdminEmail();
        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 mins
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare("INSERT INTO pin_resets (token, email, expires_at, created_at) VALUES (:token, :email, :expires_at, :created_at)");
        $stmt->execute([
            ':token' => $token,
            ':email' => $adminEmail,
            ':expires_at' => $expiresAt,
            ':created_at' => $now
        ]);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // If host is localhost or 127.0.0.1, replace with server LAN IP for external access
        if (strpos($host, 'localhost') !== false || $host === '127.0.0.1') {
            $lanIp = gethostbyname(gethostname());
            if ($lanIp && $lanIp !== '127.0.0.1') {
                $host = $lanIp;
            }
        }
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
        $resetUrl = "{$protocol}{$host}{$scriptDir}/dashboard.php?action=reset_pin&token={$token}";

        $subject = "vCard Admin PIN Reset Request";
        $textBody = "Hello Admin,\n\nA request was made to reset the company vCard Admin Dashboard PIN.\n\nClick the link below to set your new PIN (valid for 30 minutes):\n{$resetUrl}\n\nIf you did not request this, you can safely ignore this email.\n";

        $htmlBody = '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 28px; border: 1px solid #e2e8f0; border-radius: 20px; background-color: #ffffff; color: #1e293b;">
            <div style="text-align: center; margin-bottom: 24px;">
                <div style="width: 50px; height: 50px; border-radius: 14px; background-color: #eff6ff; color: #2563eb; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold;">
                    🔐
                </div>
                <h2 style="margin: 12px 0 4px; font-size: 20px; font-weight: 800; color: #0f172a;">Admin PIN Reset</h2>
                <p style="margin: 0; font-size: 13px; color: #64748b;">vCard Studio Administration</p>
            </div>
            
            <p style="font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 16px;">Hello Admin,</p>
            <p style="font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 24px;">
                A request was made to reset the master PIN for your company vCard Admin Dashboard. Click the button below to set your new PIN:
            </p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . htmlspecialchars($resetUrl) . '" style="background-color: #2563eb; color: #ffffff; padding: 13px 32px; text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 14px; display: inline-block; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                    Set New Admin PIN &rarr;
                </a>
            </div>
            
            <p style="font-size: 12px; color: #64748b; line-height: 1.5; margin-top: 24px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                ⏳ This link is valid for <strong>30 minutes</strong>. If you did not request this PIN reset, please ignore this email.
            </p>
        </div>';

        //<p style="font-size: 11px; color: #94a3b8; word-break: break-all; margin-top: 12px;">
          //      Direct Link: <a href="' . htmlspecialchars($resetUrl) . '" style="color: #2563eb;">' . htmlspecialchars($resetUrl) . '</a>
        //</p>

        $emailResult = sendTransactionalEmail($adminEmail, $subject, $htmlBody, $textBody);

        // Server-side log for local environments
        $logDir = __DIR__ . '/data';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $logLine = "[{$now}] RESET LINK (valid 30 min): {$resetUrl} | API: " . json_encode($emailResult) . "\n";
        @file_put_contents($logDir . '/reset_log.txt', $logLine, FILE_APPEND);

        jsonResponse([
            'success' => true,
            'message' => "Reset instructions sent to {$adminEmail}. Please check your inbox.",
            'email' => $adminEmail
        ]);
        break;

    // 4c. Reset Admin PIN using Valid Token
    case 'reset_pin':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $token = trim($input['token'] ?? '');
        $newPin = trim($input['new_pin'] ?? '');

        if (empty($token) || empty($newPin)) {
            jsonResponse(['success' => false, 'message' => 'Token and new PIN are required.'], 400);
        }

        if (strlen($newPin) < 4) {
            jsonResponse(['success' => false, 'message' => 'New PIN must be at least 4 digits.'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM pin_resets WHERE token = :token ORDER BY id DESC LIMIT 1");
        $stmt->execute([':token' => $token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            jsonResponse(['success' => false, 'message' => 'Invalid or expired reset token.'], 400);
        }

        if (strtotime($reset['expires_at']) < time()) {
            jsonResponse(['success' => false, 'message' => 'This reset token has expired. Please request a new one.'], 400);
        }

        setAdminPIN($newPin);

        $del = $db->prepare("DELETE FROM pin_resets WHERE token = :token");
        $del->execute([':token' => $token]);

        // setAdminAuthenticated(true); // Removed to require manual login after reset

        jsonResponse([
            'success' => true,
            'message' => 'Admin PIN has been reset successfully! Redirecting...'
        ]);
        break;

    // 4d. Request OTP for Card Owner Self-Service Editing
    case 'request_otp':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = trim($input['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'Please provide a valid email address.'], 400);
        }

        // Find card with this email (case-insensitive)
        $stmt = $db->prepare("SELECT id, first_name, last_name, slug, email FROM cards WHERE LOWER(email) = LOWER(:email) ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([':email' => $email]);
        $card = $stmt->fetch();

        if (!$card) {
            jsonResponse([
                'success' => false,
                'message' => 'No digital vCard is registered under this email address. Please check your spelling.'
            ], 404);
        }

        // Generate 6-digit random code
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes validity
        $now = date('Y-m-d H:i:s');

        // Update database with OTP code and expiry
        $updateStmt = $db->prepare("UPDATE cards SET otp_code = :otp, otp_expires_at = :expires WHERE id = :id");
        $updateStmt->execute([
            ':otp' => $otp,
            ':expires' => $expiresAt,
            ':id' => $card['id']
        ]);

        $subject = "Your vCard Edit Verification Code: {$otp}";
        $textBody = "Hello {$card['first_name']},\n\nYour 6-digit verification code to edit your digital vCard is: {$otp}\n\nThis code will expire in 5 minutes.\n\nIf you did not request this code, you can safely ignore this email.\n";

        $htmlBody = '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 32px; border: 1px solid #e2e8f0; border-radius: 24px; background-color: #ffffff; color: #1e293b; text-align: center;">
            <div style="width: 56px; height: 56px; border-radius: 16px; background-color: #eff6ff; color: #2563eb; display: inline-flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 16px;">
                ✉️
            </div>
            <h2 style="margin: 0 0 6px; font-size: 22px; font-weight: 800; color: #0f172a;">Edit Verification Code</h2>
            <p style="margin: 0 0 24px; font-size: 13px; color: #64748b;">Hello ' . htmlspecialchars($card['first_name']) . ', use the 6-digit code below to edit your digital business card:</p>
            
            <div style="background-color: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 16px; padding: 20px; margin: 20px 0;">
                <span style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 38px; font-weight: 800; letter-spacing: 8px; color: #2563eb;">' . $otp . '</span>
            </div>

            <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 20px 0 0;">
                ⏳ This code is valid for <strong>5 minutes</strong>. Never share this code with anyone.
            </p>
        </div>';

        $emailResult = sendTransactionalEmail($card['email'], $subject, $htmlBody, $textBody);

        // Fallback local logging
        $logDir = __DIR__ . '/data';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $logLine = "[{$now}] CARD EDIT OTP for {$card['email']}: {$otp} (expires {$expiresAt}) | Result: " . json_encode($emailResult) . "\n";
        @file_put_contents($logDir . '/reset_log.txt', $logLine, FILE_APPEND);

        jsonResponse([
            'success' => true,
            'message' => 'Verification code sent to ' . htmlspecialchars($card['email']) . '.',
            'email' => $card['email']
        ]);
        break;

    // 4e. Verify OTP and Grant Temporary Edit Token
    case 'verify_otp':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = trim($input['email'] ?? '');
        $otp = trim($input['otp'] ?? $input['code'] ?? '');

        if (empty($email) || empty($otp)) {
            jsonResponse(['success' => false, 'message' => 'Email and 6-digit OTP code are required.'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM cards WHERE LOWER(email) = LOWER(:email) ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([':email' => $email]);
        $card = $stmt->fetch();

        if (!$card) {
            jsonResponse(['success' => false, 'message' => 'No card found matching this email.'], 404);
        }

        if (empty($card['otp_code']) || empty($card['otp_expires_at'])) {
            jsonResponse(['success' => false, 'message' => 'No pending verification code found. Please request a new code.'], 400);
        }

        if (trim($card['otp_code']) !== $otp) {
            jsonResponse(['success' => false, 'message' => 'Incorrect verification code. Please check and try again.'], 400);
        }

        if (strtotime($card['otp_expires_at']) < time()) {
            jsonResponse(['success' => false, 'message' => 'This verification code has expired. Please request a new one.'], 400);
        }

        // Generate temporary secure edit token
        $editToken = bin2hex(random_bytes(24));

        // Save edit token and clear OTP
        $saveStmt = $db->prepare("UPDATE cards SET edit_token = :edit_token, otp_code = NULL, otp_expires_at = NULL WHERE id = :id");
        $saveStmt->execute([
            ':edit_token' => $editToken,
            ':id' => $card['id']
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Verification successful! Loading your card editor...',
            'edit_token' => $editToken,
            'slug' => $card['slug']
        ]);
        break;

    // 5. Get List of Cards for Dashboard
    case 'list':
        if (!isAdminAuthenticated()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized. Please login with PIN.'], 401);
        }

        $stmt = $db->query("SELECT * FROM cards ORDER BY created_at DESC");
        $cards = $stmt->fetchAll();

        // Calculate summary metrics
        $totalCards = count($cards);
        $totalScans = 0;
        $topCard = null;
        $maxScans = -1;

        foreach ($cards as $c) {
            $totalScans += (int)$c['scans'];
            if ((int)$c['scans'] > $maxScans) {
                $maxScans = (int)$c['scans'];
                $topCard = $c;
            }
        }

        jsonResponse([
            'success' => true,
            'cards' => $cards,
            'stats' => [
                'total_cards' => $totalCards,
                'total_scans' => $totalScans,
                'top_card' => $topCard ? ($topCard['first_name'] . ' ' . $topCard['last_name']) : 'None'
            ]
        ]);
        break;

    // 6. Get Single Card for Editing or Display
    case 'get':
        $slug = $_GET['slug'] ?? '';
        $id = $_GET['id'] ?? '';

        if (!empty($slug)) {
            $stmt = $db->prepare("SELECT * FROM cards WHERE slug = :slug");
            $stmt->execute([':slug' => $slug]);
        } elseif (!empty($id)) {
            $stmt = $db->prepare("SELECT * FROM cards WHERE id = :id");
            $stmt->execute([':id' => $id]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Missing slug or id'], 400);
        }

        $card = $stmt->fetch();
        if (!$card) {
            jsonResponse(['success' => false, 'message' => 'Card not found'], 404);
        }

        jsonResponse(['success' => true, 'card' => $card]);
        break;

    // 7. Save / Update Dynamic Card
    case 'save':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $firstName = trim($input['firstName'] ?? $input['first_name'] ?? '');
        $lastName = trim($input['lastName'] ?? $input['last_name'] ?? '');

        if (empty($firstName) || empty($lastName)) {
            jsonResponse(['success' => false, 'message' => 'First and Last name are required.'], 400);
        }

        $existingId = !empty($input['id']) ? (int)$input['id'] : null;
        $customSlug = trim($input['slug'] ?? '');

        // If editing by slug
        if (!$existingId && !empty($customSlug)) {
            $findStmt = $db->prepare("SELECT id FROM cards WHERE slug = :slug");
            $findStmt->execute([':slug' => $customSlug]);
            $existingRow = $findStmt->fetch();
            if ($existingRow) {
                $existingId = (int)$existingRow['id'];
            }
        }

        // Security check: Verify that the save request includes either valid Admin Session OR a valid edit_token matching the card record
        if ($existingId) {
            $isAuthorized = false;
            if (isAdminAuthenticated()) {
                $isAuthorized = true;
            } else {
                $providedToken = trim($input['edit_token'] ?? $input['token'] ?? '');
                if (!empty($providedToken)) {
                    $chkStmt = $db->prepare("SELECT id, edit_token FROM cards WHERE id = :id");
                    $chkStmt->execute([':id' => $existingId]);
                    $chkRow = $chkStmt->fetch();
                    if ($chkRow && !empty($chkRow['edit_token']) && hash_equals($chkRow['edit_token'], $providedToken)) {
                        $isAuthorized = true;
                    }
                }
            }

            if (!$isAuthorized) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Unauthorized to edit this card. Please verify your email with a verification code to edit this card.'
                ], 403);
            }
        }

        // Determine Slug
        if (empty($customSlug)) {
            $slug = generateSlug($firstName . '-' . $lastName, $db, $existingId);
        } else {
            $slug = generateSlug($customSlug, $db, $existingId);
        }

        $editPin = !empty($providedPin) ? $providedPin : '1234';
        $isDynamic = isset($input['isDynamic']) ? (int)$input['isDynamic'] : (isset($input['is_dynamic']) ? (int)$input['is_dynamic'] : 1);
        $now = date('Y-m-d H:i:s');

        if ($existingId) {
            // Update
            $sql = "UPDATE cards SET
                slug = :slug,
                first_name = :first_name,
                last_name = :last_name,
                company = :company,
                job_title = :job_title,
                dob = :dob,
                phone_type = :phone_type,
                phone = :phone,
                email = :email,
                website = :website,
                address = :address,
                city = :city,
                state = :state,
                postal_code = :postal_code,
                country = :country,
                photo_url = :photo_url,
                theme_color = :theme_color,
                secondary_color = :secondary_color,
                qr_color = :qr_color,
                qr_pattern = :qr_pattern,
                qr_corner_style = :qr_corner_style,
                edit_pin = :edit_pin,
                is_dynamic = :is_dynamic,
                updated_at = :updated_at
                WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':slug' => $slug,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':company' => trim($input['company'] ?? ''),
                ':job_title' => trim($input['jobTitle'] ?? $input['job_title'] ?? ''),
                ':dob' => $input['dob'] ?? '',
                ':phone_type' => $input['phoneType'] ?? $input['phone_type'] ?? 'Mobile',
                ':phone' => trim($input['phone'] ?? ''),
                ':email' => trim($input['email'] ?? ''),
                ':website' => trim($input['website'] ?? ''),
                ':address' => trim($input['address'] ?? ''),
                ':city' => trim($input['city'] ?? ''),
                ':state' => trim($input['state'] ?? ''),
                ':postal_code' => trim($input['postalCode'] ?? $input['postal_code'] ?? ''),
                ':country' => trim($input['country'] ?? ''),
                ':photo_url' => trim($input['photoUrl'] ?? $input['photo_url'] ?? ''),
                ':theme_color' => $input['themeColor'] ?? $input['theme_color'] ?? '#2563eb',
                ':secondary_color' => $input['secondaryColor'] ?? $input['secondary_color'] ?? '#1d4ed8',
                ':qr_color' => $input['qrColor'] ?? $input['qr_color'] ?? '#000000',
                ':qr_pattern' => $input['qrPattern'] ?? $input['qr_pattern'] ?? 'square',
                ':qr_corner_style' => $input['qrCornerStyle'] ?? $input['qr_corner_style'] ?? 'extra-rounded',
                ':edit_pin' => $editPin,
                ':is_dynamic' => $isDynamic,
                ':updated_at' => $now,
                ':id' => $existingId
            ]);
            $cardId = $existingId;
        } else {
            // Insert
            $sql = "INSERT INTO cards (
                slug, first_name, last_name, company, job_title, dob, phone_type, phone,
                email, website, address, city, state, postal_code, country, photo_url,
                theme_color, secondary_color, qr_color, qr_pattern, qr_corner_style,
                edit_pin, is_dynamic, scans, created_at, updated_at
            ) VALUES (
                :slug, :first_name, :last_name, :company, :job_title, :dob, :phone_type, :phone,
                :email, :website, :address, :city, :state, :postal_code, :country, :photo_url,
                :theme_color, :secondary_color, :qr_color, :qr_pattern, :qr_corner_style,
                :edit_pin, :is_dynamic, 0, :created_at, :updated_at
            )";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':slug' => $slug,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':company' => trim($input['company'] ?? ''),
                ':job_title' => trim($input['jobTitle'] ?? $input['job_title'] ?? ''),
                ':dob' => $input['dob'] ?? '',
                ':phone_type' => $input['phoneType'] ?? $input['phone_type'] ?? 'Mobile',
                ':phone' => trim($input['phone'] ?? ''),
                ':email' => trim($input['email'] ?? ''),
                ':website' => trim($input['website'] ?? ''),
                ':address' => trim($input['address'] ?? ''),
                ':city' => trim($input['city'] ?? ''),
                ':state' => trim($input['state'] ?? ''),
                ':postal_code' => trim($input['postalCode'] ?? $input['postal_code'] ?? ''),
                ':country' => trim($input['country'] ?? ''),
                ':photo_url' => trim($input['photoUrl'] ?? $input['photo_url'] ?? ''),
                ':theme_color' => $input['themeColor'] ?? $input['theme_color'] ?? '#2563eb',
                ':secondary_color' => $input['secondaryColor'] ?? $input['secondary_color'] ?? '#1d4ed8',
                ':qr_color' => $input['qrColor'] ?? $input['qr_color'] ?? '#000000',
                ':qr_pattern' => $input['qrPattern'] ?? $input['qr_pattern'] ?? 'square',
                ':qr_corner_style' => $input['qrCornerStyle'] ?? $input['qr_corner_style'] ?? 'extra-rounded',
                ':edit_pin' => $editPin,
                ':is_dynamic' => $isDynamic,
                ':created_at' => $now,
                ':updated_at' => $now
            ]);
            $cardId = (int)$db->lastInsertId();
        }

        // Build permanent short URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (strpos($host, 'localhost') !== false || $host === '127.0.0.1') {
            $lanIp = gethostbyname(gethostname());
            if ($lanIp && $lanIp !== '127.0.0.1') {
                $host = $lanIp;
            }
        }
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
        $shortUrl = "{$protocol}{$host}{$scriptDir}/c/?id={$slug}";

        jsonResponse([
            'success' => true,
            'id' => $cardId,
            'slug' => $slug,
            'short_url' => $shortUrl,
            'message' => $existingId ? 'Card updated successfully' : 'Card created successfully'
        ]);
        break;

    // 8. Delete Card
    case 'delete':
        if (!isAdminAuthenticated()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int)($input['id'] ?? 0);

        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Card ID is required'], 400);
        }

        $stmt = $db->prepare("DELETE FROM cards WHERE id = :id");
        $stmt->execute([':id' => $id]);

        jsonResponse(['success' => true, 'message' => 'Card deleted successfully']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        break;
}
