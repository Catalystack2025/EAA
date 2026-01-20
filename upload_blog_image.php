<?php
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/config/db.php';

start_session();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if ($userId === null) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid token']);
    exit;
}

if (empty($_FILES['image']['name']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    echo json_encode(['ok' => false, 'error' => 'No image uploaded']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['image']['tmp_name']);

$allowed = [
  'image/jpeg' => 'jpg',
  'image/png'  => 'png',
  'image/webp' => 'webp',
];

if (!isset($allowed[$mimeType])) {
    echo json_encode(['ok' => false, 'error' => 'Only JPG/PNG/WebP allowed']);
    exit;
}

$uploadDir = __DIR__ . '/public/uploads/blogs';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$filename = sprintf('blog_inline_%d_%s.%s', $userId, bin2hex(random_bytes(6)), $allowed[$mimeType]);
$targetPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
    exit;
}

$filePath = 'public/uploads/blogs/' . $filename;

// optional record
try {
    $stmt = db()->prepare('INSERT INTO blog_assets (user_id, file_path, mime_type) VALUES (:user_id, :file_path, :mime_type)');
    $stmt->execute([
        'user_id' => $userId,
        'file_path' => $filePath,
        'mime_type' => $mimeType,
    ]);
} catch (Throwable $e) {}

echo json_encode([
  'ok' => true,
  'url' => asset($filePath),
]);
