<?php

require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

		case 'GET':

            $postId = sanitizeInteger($_GET['post_id'] ?? null);
            if (!$postId) {
                sendJSON(['error' => 'Brak ID posta'], 400);
            }

            $stmt = $db->prepare("
                SELECT 
                    c.id,
                    c.content,
                    c.created_at,
                    u.username
                FROM comments c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.post_id = ?
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$postId]);

            $comments = array_map(function ($comment) {
                $comment['content'] = sanitizeForDisplay($comment['content']);
                return $comment;
            }, $stmt->fetchAll());

            sendJSON($comments);
            break;
			
			case 'POST':

            requireAuth();

            $data = getRequestBody();

            if (empty($data['post_id']) || empty($data['content'])) {
                sendJSON([
                    'error' => 'Post ID i treść komentarza są wymagane',
                    'contact' => ADMIN_CONTACT_MESSAGE
                ], 400);
            }

            $postId = sanitizeInteger($data['post_id']);
            $content = sanitizeString($data['content']);

            if (!$postId || strlen($content) < 1) {
                sendJSON(['error' => 'Nieprawidłowe dane komentarza'], 400);
            }

			$stmt = $db->prepare("SELECT id FROM posts WHERE id = ?");
            $stmt->execute([$postId]);
            if (!$stmt->fetch()) {
                sendJSON(['error' => 'Post nie istnieje'], 404);
            }

            $stmt = $db->prepare("
                INSERT INTO comments (content, user_id, post_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $content,
                getCurrentUserId(),
                $postId
            ]);

            sendJSON([
                'message' => 'Komentarz dodany',
                'comment_id' => $db->lastInsertId()
            ], 201);
            break;
			
			case 'DELETE':

            requireAuth();

            parse_str($_SERVER['QUERY_STRING'], $params);
            $commentId = sanitizeInteger($params['id'] ?? null);

            if (!$commentId) {
                sendJSON(['error' => 'Brak ID komentarza'], 400);
            }
			
			$stmt = $db->prepare("
                SELECT user_id FROM comments WHERE id = ?
            ");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();

            if (!$comment) {
                sendJSON(['error' => 'Komentarz nie istnieje'], 404);
            }

            if ($comment['user_id'] !== getCurrentUserId()) {
                sendJSON(['error' => 'Brak uprawnień'], 403);
            }

            $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$commentId]);

            sendJSON(['message' => 'Komentarz usunięty']);
            break;

        default:
            sendJSON(['error' => 'Method not allowed'], 405);
    }

} catch (PDOException $e) {
    sendServerError('Błąd bazy danych');
} catch (Exception $e) {
    sendServerError('Błąd serwera');
}

