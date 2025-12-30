<?php

require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id = sanitizeInteger($_GET['id'] ?? null);

try {
    switch ($method) {
        case 'GET':
            $myPosts = isset($_GET['my']) && $_GET['my'] === 'true';

            if ($id) {
                $stmt = $db->prepare("
                    SELECT p.*, u.username AS author
                    FROM posts p
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE p.id = ?
                ");
                $stmt->execute([$id]);
                $post = $stmt->fetch();

                if (!$post) {
                    sendJSON(['error' => 'Post nie znaleziony'], 404);
                }

                if (!isLoggedIn() && $post['status'] !== 'published') {
                    sendJSON(['error' => 'Post nie znaleziony'], 404);
                }

                if (!isLoggedIn() || ($post['user_id'] !== getCurrentUserId() && $post['status'] !== 'published')) {
                    $post['content'] = 'Zaloguj się, aby zobaczyć treść posta';
                }

                sendJSON($post);
            }

            if ($myPosts && isLoggedIn()) {
                $stmt = $db->prepare("
                    SELECT p.*, u.username AS author
                    FROM posts p
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE p.user_id = ?
                    ORDER BY p.created_at DESC
                ");
                $stmt->execute([getCurrentUserId()]);
            } else {
                $stmt = $db->query("
                    SELECT p.id, p.title, p.content, p.image_path, p.created_at, p.updated_at, u.username AS author
                    FROM posts p
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE p.status = 'published'
                    ORDER BY p.created_at DESC
                ");
            }

            sendJSON($stmt->fetchAll());
            break;

        case 'POST':
            requireAuth();

            $title = sanitizeString($_POST['title'] ?? null);
            $content = sanitizeString($_POST['content'] ?? null);
            $status = sanitizeString($_POST['status'] ?? 'draft');

            if (empty($title) || empty($content)) {
                sendJSON([
                    'error' => 'Tytuł i treść są wymagane',
                    'contact' => ADMIN_CONTACT_MESSAGE
                ], 400);
            }

            if (strlen($title) > 255) {
                sendJSON(['error' => 'Tytuł jest za długi'], 400);
            }

            if (!in_array($status, ['draft', 'published'], true)) {
                $status = 'draft';
            }

            $imagePath = handleImageUpload('image'); 

            $stmt = $db->prepare("
                INSERT INTO posts (title, content, image_path, user_id, status)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title,
                $content,
                $imagePath,
                getCurrentUserId(),
                $status
            ]);

            sendJSON(['message' => 'Post utworzony pomyślnie'], 201);
            break;

        case 'PUT':
            requireAuth();

            if (!$id) {
                sendJSON(['error' => 'ID posta jest wymagane'], 400);
            }

            $data = getRequestBody();

            $stmt = $db->prepare("SELECT user_id, image_path FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();

            if (!$post) {
                sendJSON(['error' => 'Post nie istnieje'], 404);
            }

            if ($post['user_id'] !== getCurrentUserId()) {
                sendJSON(['error' => 'Brak uprawnień'], 403);
            }

            $fields = [];
            $values = [];

            if (!empty($data['title'])) {
                $fields[] = 'title = ?';
                $values[] = sanitizeString($data['title']);
            }

            if (!empty($data['content'])) {
                $fields[] = 'content = ?';
                $values[] = sanitizeString($data['content']);
            }

            if (!empty($data['status']) && in_array($data['status'], ['draft', 'published'], true)) {
                $fields[] = 'status = ?';
                $values[] = $data['status'];
            }

            if (isset($_FILES['image'])) {
                $newImagePath = handleImageUpload('image');
                if ($newImagePath) {
                    if (!empty($post['image_path'])) {
                        $oldFile = __DIR__ . '/' . $post['image_path'];
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    $fields[] = 'image_path = ?';
                    $values[] = $newImagePath;
                }
            } elseif (isset($data['remove_image']) && $data['remove_image'] === true) {
                if (!empty($post['image_path'])) {
                    $oldFile = __DIR__ . '/' . $post['image_path'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                $fields[] = 'image_path = ?';
                $values[] = null;
            }

            if (empty($fields)) {
                sendJSON(['error' => 'Brak danych do aktualizacji'], 400);
            }

            $fields[] = 'updated_at = CURRENT_TIMESTAMP';
            $values[] = $id;

            $stmt = $db->prepare("
                UPDATE posts SET " . implode(', ', $fields) . " WHERE id = ?
            ");
            $stmt->execute($values);

            sendJSON(['message' => 'Post zaktualizowany']);
            break;

        case 'DELETE':
            requireAuth();

            if (!$id) {
                sendJSON(['error' => 'ID posta jest wymagane'], 400);
            }

            $stmt = $db->prepare("SELECT user_id, image_path FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();

            if (!$post) {
                sendJSON(['error' => 'Post nie istnieje'], 404);
            }

            if ($post['user_id'] !== getCurrentUserId()) {
                sendJSON(['error' => 'Brak uprawnień'], 403);
            }

            if (!empty($post['image_path'])) {
                $filePath = __DIR__ . '/' . $post['image_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
            $stmt->execute([$id]);

            sendJSON(['message' => 'Post usunięty']);
            break;

        default:
            sendJSON(['error' => 'Method not allowed'], 405);
    }

} catch (PDOException $e) {
    sendServerError('Błąd bazy danych');
} catch (Exception $e) {
    sendServerError('Błąd serwera');
}