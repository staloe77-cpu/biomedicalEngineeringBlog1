<?php

require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

requireAuth();

$UPLOAD_DIR = __DIR__ . '/uploads/';
$MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 MB
$ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

if (!isset($_FILES['image'])) {
    sendJSON([
        'error' => 'Brak pliku do przesłania',
        'contact' => ADMIN_CONTACT_MESSAGE
    ], 400);
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    sendJSON([
        'error' => 'Błąd przesyłania pliku',
        'contact' => ADMIN_CONTACT_MESSAGE
    ], 400);
}

if ($file['size'] > $MAX_FILE_SIZE) {
    sendJSON([
        'error' => 'Plik jest za duży (max 2MB)'
    ], 400);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($ALLOWED_MIME[$mime])) {
    sendJSON([
        'error' => 'Niedozwolony typ pliku'
    ], 400);
}

$extension = $ALLOWED_MIME[$mime];
$filename = uniqid('post_', true) . '.' . $extension;
$destination = $UPLOAD_DIR . $filename;

if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    sendJSON([
        'error' => 'Nie udało się zapisać pliku',
        'contact' => ADMIN_CONTACT_MESSAGE
    ], 500);
}

sendJSON([
    'message' => 'Plik przesłany pomyślnie',
    'filename' => $filename,
    'url' => '/uploads/' . $filename
], 201);







