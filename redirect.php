<?php
require_once __DIR__ . '/config.php';

$slug = $_GET['id'] ?? $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: index.html");
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM cards WHERE slug = :slug");
$stmt->execute([':slug' => $slug]);
$card = $stmt->fetch();

if (!$card) {
    http_response_code(404);
    echo "<h1>Card Not Found</h1>";
    exit;
}

// 1. Increment live scan count
$update = $db->prepare("UPDATE cards SET scans = scans + 1, last_scanned_at = :now WHERE id = :id");
$update->execute([
    ':now' => date('Y-m-d H:i:s'),
    ':id' => $card['id']
]);

// 2. Redirect to card.html
header("Location: card.html?slug=" . urlencode($slug));
exit;
