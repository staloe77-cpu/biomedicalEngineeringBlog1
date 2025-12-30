<?php

define('DB_PATH', __DIR__ . '/database.db');

define('ADMIN_EMAIL', 'admin@example.com');
define('ADMIN_CONTACT_MESSAGE', 'W razie problemów skontaktuj się z administracją: admin@example.com');

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

function getDB(): PDO {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $db;
    } catch (PDOException $e) {
        sendServerError('Błąd połączenia z bazą danych');
    }
}

function sendJSON(array $data, int $statusCode = 200): void {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sendServerError(string $publicMessage): void {
    sendJSON([
        'error' => $publicMessage,
        'contact' => ADMIN_CONTACT_MESSAGE
    ], 500);
}

function getRequestBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJSON([
            'error' => 'Nieprawidłowy format JSON',
            'contact' => ADMIN_CONTACT_MESSAGE
        ], 400);
    }

    return $data;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id'], $_SESSION['username']);
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        sendJSON([
            'error' => 'Brak autoryzacji',
            'contact' => ADMIN_CONTACT_MESSAGE
        ], 401);
    }
}

function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUsername(): ?string {
    return $_SESSION['username'] ?? null;
}

function sanitizeString(?string $value): ?string {
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    $value = stripslashes($value);
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function sanitizeInteger($value): ?int {
    return filter_var($value, FILTER_VALIDATE_INT) !== false
        ? (int)$value
        : null;
}

function validateUsername(string $username): ?string {
    $username = trim($username);

    if (strlen($username) < 3 || strlen($username) > 50) {
        return null;
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return null;
    }

    return $username;
}

function validatePassword(string $password): bool {
    return strlen($password) >= 6;
}

function handleImageUpload(string $inputName = 'image'): ?string {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; 
    }

    $file = $_FILES[$inputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendJSON([
            'error' => 'Błąd przesyłania pliku',
            'contact' => ADMIN_CONTACT_MESSAGE
        ], 400);
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        sendJSON([
            'error' => 'Plik jest za duży (max 2MB)',
            'contact' => ADMIN_CONTACT_MESSAGE
        ], 400);
    }

    $mimeType = mime_content_type($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        sendJSON([
            'error' => 'Niedozwolony typ pliku',
            'contact' => ADMIN_CONTACT_MESSAGE
        ], 400);
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('post_', true) . '.' . $extension;
    $destination = UPLOAD_DIR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        sendServerError('Nie udało się zapisać pliku');
    }

    return 'uploads/' . $fileName;
}






















