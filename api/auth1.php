<?php

require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
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
            if (isLoggedIn()) {
                sendJSON([
                    'loggedIn' => true,
                    'user' => [
                        'id' => getCurrentUserId(),
                        'username' => getCurrentUsername()
                    ]
                ]);
            } else {
                sendJSON(['loggedIn' => false]);
            }
            break;
			
			 case 'POST':
            $data = getRequestBody();
            $action = $data['action'] ?? null;

            if (!$action) {
                sendJSON(['error' => 'Brak akcji'], 400);
            }

			if ($action === 'register') {

                if (empty($data['username']) || empty($data['password'])) {
                    sendJSON([
                        'error' => 'Wszystkie pola są wymagane',
                        'contact' => ADMIN_CONTACT_MESSAGE
                    ], 400);
                }

                $username = validateUsername($data['username']);
                if (!$username) {
                    sendJSON(['error' => 'Nieprawidłowa nazwa użytkownika'], 400);
                }

                if (!validatePassword($data['password'])) {
                    sendJSON(['error' => 'Hasło musi mieć co najmniej 6 znaków'], 400);
                }

				$stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    sendJSON(['error' => 'Użytkownik już istnieje'], 400);
                }

                $hash = password_hash($data['password'], PASSWORD_DEFAULT);

                $stmt = $db->prepare("
                    INSERT INTO users (username, password)
                    VALUES (?, ?)
                ");
                $stmt->execute([$username, $hash]);

                $_SESSION['user_id'] = $db->lastInsertId();
                $_SESSION['username'] = $username;

                sendJSON([
                    'message' => 'Rejestracja zakończona sukcesem',
                    'user' => [
                        'id' => $_SESSION['user_id'],
                        'username' => $username
                    ]
                ], 201);
            }
			
			 if ($action === 'login') {

                if (empty($data['username']) || empty($data['password'])) {
                    sendJSON([
                        'error' => 'Nazwa użytkownika i hasło są wymagane',
                        'contact' => ADMIN_CONTACT_MESSAGE
                    ], 400);
                }

                $username = sanitizeString($data['username']);

                $stmt = $db->prepare("
                    SELECT id, username, password
                    FROM users
                    WHERE username = ?
                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($data['password'], $user['password'])) {
                    sendJSON(['error' => 'Nieprawidłowe dane logowania'], 401);
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                sendJSON([
                    'message' => 'Zalogowano pomyślnie',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username']
                    ]
                ]);
            }

            sendJSON(['error' => 'Nieprawidłowa akcja'], 400);
            break;
			
			case 'DELETE':
            session_destroy();
            sendJSON(['message' => 'Wylogowano pomyślnie']);
            break;

        default:
            sendJSON(['error' => 'Method not allowed'], 405);
    }

} catch (PDOException $e) {
    sendServerError('Błąd bazy danych');
} catch (Exception $e) {
    sendServerError('Błąd serwera');
}