<?php
/**
 * Simple, Secure Photo Upload Handler for vCard App
 * Saves uploaded avatars to /uploads directory and returns the relative URL.
 */

header('Content-Type: application/json; charset=utf-8');

// Ensure POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $errorMsg = 'No file uploaded or upload error occurred.';
    if (isset($_FILES['photo']['error'])) {
        switch ($_FILES['photo']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = 'File exceeds maximum upload size (max 5MB).';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = 'No file was selected.';
                break;
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

$file = $_FILES['photo'];

// Limit size to 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Image file is too large (max 5MB).']);
    exit;
}

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif'
];

if (!isset($allowedTypes[$mimeType])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, WEBP, GIF.']);
    exit;
}

$ext = $allowedTypes[$mimeType];

// Uploads directory
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$filename = 'avatar_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$targetPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
    exit;
}

// Return relative URL
echo json_encode([
    'success' => true,
    'url' => 'uploads/' . $filename,
    'message' => 'Image uploaded successfully.'
]);
