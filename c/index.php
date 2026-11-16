<?php
/**
 * Short Link Redirection & Scan Tracker
 * Tracks scan count, logs analytics, and renders digital card with latest data.
 */

require_once __DIR__ . '/../config.php';

$slug = $_GET['id'] ?? $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: ../index.html");
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM cards WHERE slug = :slug");
$stmt->execute([':slug' => $slug]);
$card = $stmt->fetch();

if (!$card) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Card Not Found</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-50 flex items-center justify-center min-h-screen p-4 text-center font-sans">
        <div class="bg-white p-8 rounded-3xl shadow-xl max-w-sm w-full border border-slate-200">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 mx-auto flex items-center justify-center text-xl font-bold mb-3">!</div>
            <h1 class="text-lg font-black text-slate-900">Card Not Found</h1>
            <p class="text-xs text-slate-500 mt-1">This digital card link may have expired or been removed.</p>
            <a href="../index.html" class="inline-block mt-5 px-5 py-2.5 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition">Create a Card</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 1. Increment live scan count
$update = $db->prepare("UPDATE cards SET scans = scans + 1, last_scanned_at = :now WHERE id = :id");
$update->execute([
    ':now' => date('Y-m-d H:i:s'),
    ':id' => $card['id']
]);

// 2. Log scan entry
try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $log = $db->prepare("INSERT INTO scan_logs (card_id, scanned_at, ip_address, user_agent) VALUES (:card_id, :now, :ip, :ua)");
    $log->execute([
        ':card_id' => $card['id'],
        ':now' => date('Y-m-d H:i:s'),
        ':ip' => $ip,
        ':ua' => $ua
    ]);
} catch (Exception $e) {
    // non-fatal
}

// 3. Render digital card directly with preloaded data (instant load, zero redirect lag, zero dummy flash)
$cardHtml = file_get_contents(__DIR__ . '/../card.html');

// Ensure base URL resolves to parent directory so relative CSS and JS assets load correctly
$cardHtml = str_replace('<head>', "<head>\n  <base href=\"../\">", $cardHtml);

// Inject preloaded card data directly so card.js needs 0 extra API round-trips
$jsonCard = json_encode($card, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$injectScript = "<script>\n  window.INITIAL_CARD_DATA = {$jsonCard};\n</script>";
$cardHtml = str_replace('</head>', "  {$injectScript}\n</head>", $cardHtml);

// Pre-fill theme CSS variables directly in HTML style so the banner has the exact gradient on the very first paint
$primary = htmlspecialchars($card['theme_color'] ?: '#2563eb', ENT_QUOTES, 'UTF-8');
$secondary = htmlspecialchars($card['secondary_color'] ?: $primary, ENT_QUOTES, 'UTF-8');
$cardHtml = str_replace(
    '--theme-primary: #3b82f6;',
    "--theme-primary: {$primary};",
    $cardHtml
);
$cardHtml = str_replace(
    '--theme-secondary: #1d4ed8;',
    "--theme-secondary: {$secondary};",
    $cardHtml
);

// Pre-render content fields server-side so they display immediately with zero JavaScript delay
$fullName = htmlspecialchars(trim(($card['first_name'] ?? '') . ' ' . ($card['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
$jobTitle = htmlspecialchars(trim($card['job_title'] ?? ''), ENT_QUOTES, 'UTF-8');
$company = htmlspecialchars(trim($card['company'] ?? ''), ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars(trim($card['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars(trim($card['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$website = htmlspecialchars(trim($card['website'] ?? ''), ENT_QUOTES, 'UTF-8');
$fullAddress = htmlspecialchars(implode(', ', array_filter([
    trim($card['address'] ?? ''),
    trim($card['city'] ?? ''),
    trim($card['state'] ?? ''),
    trim($card['postal_code'] ?? ''),
    trim($card['country'] ?? '')
])), ENT_QUOTES, 'UTF-8');
$initials = htmlspecialchars(strtoupper(substr($card['first_name'] ?? '', 0, 1) . substr($card['last_name'] ?? '', 0, 1)), ENT_QUOTES, 'UTF-8');

$cardHtml = str_replace('<span id="avatarInitials"></span>', "<span id=\"avatarInitials\">{$initials}</span>", $cardHtml);
$cardHtml = str_replace('<h1 id="cardFullName" class="text-2xl font-black text-slate-900 mt-3 tracking-tight min-h-[2rem]"></h1>', "<h1 id=\"cardFullName\" class=\"text-2xl font-black text-slate-900 mt-3 tracking-tight min-h-[2rem]\">{$fullName}</h1>", $cardHtml);

if (!empty($jobTitle)) {
    $cardHtml = str_replace('<p id="cardJobTitle" class="text-sm font-semibold text-slate-600 mt-0.5 hidden"></p>', "<p id=\"cardJobTitle\" class=\"text-sm font-semibold text-slate-600 mt-0.5\">{$jobTitle}</p>", $cardHtml);
}
if (!empty($company)) {
    $cardHtml = str_replace('<div id="cardCompanyBadge" class="hidden items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium mt-2">', '<div id="cardCompanyBadge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium mt-2">', $cardHtml);
    $cardHtml = str_replace('<span id="cardCompanyName"></span>', "<span id=\"cardCompanyName\">{$company}</span>", $cardHtml);
}

if (!empty($phone)) {
    $cardHtml = str_replace('<p id="cardPhoneText" class="text-sm font-semibold text-slate-800"></p>', "<p id=\"cardPhoneText\" class=\"text-sm font-semibold text-slate-800\">{$phone}</p>", $cardHtml);
} else {
    $cardHtml = str_replace('id="phoneSection" class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between"', 'id="phoneSection" class="hidden p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between"', $cardHtml);
}

if (!empty($email)) {
    $cardHtml = str_replace('<p id="cardEmailText" class="text-sm font-semibold text-slate-800 truncate max-w-[200px]"></p>', "<p id=\"cardEmailText\" class=\"text-sm font-semibold text-slate-800 truncate max-w-[200px]\">{$email}</p>", $cardHtml);
} else {
    $cardHtml = str_replace('id="emailSection" class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between"', 'id="emailSection" class="hidden p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between"', $cardHtml);
}

if (!empty($website)) {
    $cardHtml = str_replace('<p id="cardWebsiteText" class="text-sm font-semibold text-slate-800 truncate max-w-[200px]"></p>', "<p id=\"cardWebsiteText\" class=\"text-sm font-semibold text-slate-800 truncate max-w-[200px]\">{$website}</p>", $cardHtml);
} else {
    $cardHtml = str_replace('id="websiteSection" class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between"', 'id="websiteSection" class="hidden p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between"', $cardHtml);
}

if (!empty($fullAddress)) {
    $cardHtml = str_replace('<p id="cardAddressText" class="text-sm font-medium text-slate-800 leading-snug mt-0.5"></p>', "<p id=\"cardAddressText\" class=\"text-sm font-medium text-slate-800 leading-snug mt-0.5\">{$fullAddress}</p>", $cardHtml);
} else {
    $cardHtml = str_replace('id="addressSection" class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col gap-3"', 'id="addressSection" class="hidden p-4 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col gap-3"', $cardHtml);
}

echo $cardHtml;
exit;
