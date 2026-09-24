<?php

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$q = trim($_GET['q'] ?? '');

try {
    $db = getDb();

    /*
     * GET
     */
    if ($method === 'GET') {
        if (isset($_GET['ping'])) {
            jsonResponse([
                'status' => 'OK',
                'timestamp' => time()
            ]);
        }

        $user = requireAuth();

        // Admin-only user search.
        if ($action === 'users') {
            requireAdmin($user);

            $sql = 'SELECT ID AS id, Username AS login, FirstName AS firstName, LastName AS lastName,
                           Role AS role, Active AS active, DateCreated AS createdAt, DateUpdated AS updatedAt
                    FROM Users';
            $params = [];

            if ($q !== '') {
                $sql .= ' WHERE Username LIKE ?
                          OR FirstName LIKE ?
                          OR LastName LIKE ?';
                $like = '%' . $q . '%';
                $params = [$like, $like, $like];
            }

            $sql .= ' ORDER BY ID LIMIT 100';

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            jsonResponse(['users' => $stmt->fetchAll()]);
        }

        // Admin-only search across all contacts.
        if ($action === 'allContacts') {
            requireAdmin($user);

            $sql = 'SELECT c.ID AS id, c.UserID AS userId, c.FirstName AS firstName, c.LastName AS lastName,
                           c.Email AS email, c.PhoneNumber AS phone,
                           u.Username AS userLogin,
                           u.FirstName AS userFirstName,
                           u.LastName AS userLastName
                    FROM Contacts c
                    INNER JOIN Users u ON u.ID = c.UserID';
            $params = [];

            if ($q !== '') {
                $sql .= ' WHERE c.FirstName LIKE ?
                          OR c.LastName LIKE ?
                          OR c.Email LIKE ?
                          OR c.PhoneNumber LIKE ?
                          OR u.Username LIKE ?
                          OR u.FirstName LIKE ?
                          OR u.LastName LIKE ?';

                $like = '%' . $q . '%';
                $params = [$like, $like, $like, $like, $like, $like, $like];
            }

            $sql .= ' ORDER BY c.ID LIMIT 100';

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            jsonResponse(['contacts' => $stmt->fetchAll()]);
        }

        // Get one contact. Users can only get their own; admins can get any.
        if ($id > 0) {
            if ($user['role'] === 'admin') {
                $stmt = $db->prepare(
                    'SELECT c.ID AS id, c.UserID AS userId, c.FirstName AS firstName, c.LastName AS lastName,
                            c.Email AS email, c.PhoneNumber AS phone,
                            u.Username AS userLogin
                     FROM Contacts c
                     INNER JOIN Users u ON u.ID = c.UserID
                     WHERE c.ID = ?
                     LIMIT 1'
                );
                $stmt->execute([$id]);
            } else {
                $stmt = $db->prepare(
                    'SELECT ID AS id, UserID AS userId, FirstName AS firstName, LastName AS lastName,
                            Email AS email, PhoneNumber AS phone
                     FROM Contacts
                     WHERE ID = ? AND UserID = ?
                     LIMIT 1'
                );
                $stmt->execute([$id, $user['id']]);
            }

            $contact = $stmt->fetch();

            if (!$contact) {
                jsonResponse(['error' => 'Contact not found.'], 404);
            }

            jsonResponse(['contact' => $contact]);
        }

        // Default search: current user's contacts only.
        $sql = 'SELECT ID AS id, UserID AS userId, FirstName AS firstName, LastName AS lastName,
                       Email AS email, PhoneNumber AS phone
                FROM Contacts
                WHERE UserID = ?';
        $params = [$user['id']];

        if ($q !== '') {
            $sql .= ' AND (
                        FirstName LIKE ?
                        OR LastName LIKE ?
                        OR Email LIKE ?
                        OR PhoneNumber LIKE ?
                      )';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $sql .= ' ORDER BY LastName, FirstName LIMIT 100';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['contacts' => $stmt->fetchAll()]);
    }

    /*
     * POST
     */
    if ($method === 'POST') {
        $data = readJsonBody();

        // Public login.
        if ($action === 'login' || ($action === '' && isset($data['login']) && isset($data['password']))) {
            requireFields($data, ['login', 'password']);

            $stmt = $db->prepare(
                'SELECT ID AS id, Username AS login, Password AS password, FirstName AS firstName,
                        LastName AS lastName, Role AS role, Active AS active
                 FROM Users
                 WHERE Username = ?
                 LIMIT 1'
            );
            $stmt->execute([trim($data['login'])]);
            $account = $stmt->fetch();

            if (!$account || !password_verify($data['password'], $account['password'])) {
                jsonResponse(['error' => 'Invalid username or password.'], 401);
            }

            if ((int)$account['active'] !== 1) {
                jsonResponse(['error' => 'This account is disabled.'], 403);
            }

            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + (SESSION_HOURS * 3600));

            $stmt = $db->prepare(
                'INSERT INTO Sessions (UserID, Token, ExpiresAt)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([$account['id'], $token, $expiresAt]);

            jsonResponse([
                'id' => (int)$account['id'],
                'login' => $account['login'],
                'firstName' => $account['firstName'],
                'lastName' => $account['lastName'],
                'role' => $account['role'],
                'active' => (int)$account['active'],
                'token' => $token,
                'expiresAt' => $expiresAt
            ]);
        }

        // Public registration. New accounts are always normal users.
        if ($action === 'register') {
            requireFields($data, ['login', 'password', 'firstName', 'lastName']);

            $login = trim($data['login']);
            $password = (string)$data['password'];
            $firstName = trim($data['firstName']);
            $lastName = trim($data['lastName']);

            if (strlen($login) < 3 || strlen($login) > 50) {
                jsonResponse(['error' => 'Login must be 3-50 characters.'], 400);
            }

            if (strlen($password) < 8) {
                jsonResponse(['error' => 'Password must be at least 8 characters.'], 400);
            }

            $stmt = $db->prepare('SELECT ID FROM Users WHERE Username = ? LIMIT 1');
            $stmt->execute([$login]);

            if ($stmt->fetch()) {
                jsonResponse(['error' => 'That login is already in use.'], 409);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare(
                'INSERT INTO Users (FirstName, LastName, Username, Password, Role, Active)
                 VALUES (?, ?, ?, ?, "user", 1)'
            );
            $stmt->execute([$firstName, $lastName, $login, $hash]);

            jsonResponse([
                'message' => 'Registration successful.',
                'id' => (int)$db->lastInsertId(),
                'login' => $login,
                'role' => 'user'
            ], 201);
        }

        $user = requireAuth();

        // Create a contact for the currently logged-in user.
        if ($action === 'contact' || $action === 'createContact') {
            requireFields($data, ['firstName', 'lastName', 'email', 'phone']);

            $stmt = $db->prepare(
                'INSERT INTO Contacts (UserID, FirstName, LastName, Email, PhoneNumber)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['id'],
                trim($data['firstName']),
                trim($data['lastName']),
                trim($data['email']),
                trim($data['phone'])
            ]);

            jsonResponse([
                'message' => 'Contact created.',
                'id' => (int)$db->lastInsertId()
            ], 201);
        }

        // Admin-only creation of another admin account.
        if ($action === 'createAdmin') {
            requireAdmin($user);
            requireFields($data, ['login', 'password', 'firstName', 'lastName']);

            if (strlen((string)$data['password']) < 8) {
                jsonResponse(['error' => 'Password must be at least 8 characters.'], 400);
            }

            $login = trim($data['login']);

            $stmt = $db->prepare('SELECT ID FROM Users WHERE Username = ? LIMIT 1');
            $stmt->execute([$login]);

            if ($stmt->fetch()) {
                jsonResponse(['error' => 'That login is already in use.'], 409);
            }

            $hash = password_hash($data['password'], PASSWORD_DEFAULT);

            $stmt = $db->prepare(
                'INSERT INTO Users (FirstName, LastName, Username, Password, Role, Active)
                 VALUES (?, ?, ?, ?, "admin", 1)'
            );
            $stmt->execute([
                trim($data['firstName']),
                trim($data['lastName']),
                $login,
                $hash
            ]);

            jsonResponse([
                'message' => 'Administrator account created.',
                'id' => (int)$db->lastInsertId(),
                'login' => $login,
                'role' => 'admin'
            ], 201);
        }

        jsonResponse(['error' => 'Unknown POST action.'], 400);
    }

    /*
     * PUT
     */
    if ($method === 'PUT') {
        $data = readJsonBody();
        $user = requireAuth();

        // Update a contact. Users can update their own contacts; admins can update any.
        if ($action === 'contact' || $action === 'updateContact' || $id > 0) {
            if ($id <= 0) {
                jsonResponse(['error' => 'A valid contact id is required.'], 400);
            }

            requireFields($data, ['firstName', 'lastName', 'email', 'phone']);

            if ($user['role'] === 'admin') {
                $stmt = $db->prepare(
                    'UPDATE Contacts
                     SET FirstName = ?, LastName = ?, Email = ?, PhoneNumber = ?
                     WHERE ID = ?'
                );
                $stmt->execute([
                    trim($data['firstName']),
                    trim($data['lastName']),
                    trim($data['email']),
                    trim($data['phone']),
                    $id
                ]);
            } else {
                $stmt = $db->prepare(
                    'UPDATE Contacts
                     SET FirstName = ?, LastName = ?, Email = ?, PhoneNumber = ?
                     WHERE ID = ? AND UserID = ?'
                );
                $stmt->execute([
                    trim($data['firstName']),
                    trim($data['lastName']),
                    trim($data['email']),
                    trim($data['phone']),
                    $id,
                    $user['id']
                ]);
            }

            if ($stmt->rowCount() === 0) {
                jsonResponse(['error' => 'Contact not found or no changes were made.'], 404);
            }

            jsonResponse(['message' => 'Contact updated.', 'id' => $id]);
        }

        // Admin disables an account. The admin cannot disable the account currently in use.
        if ($action === 'disableUser') {
            requireAdmin($user);

            $targetUserId = isset($data['userId']) ? (int)$data['userId'] : 0;

            if ($targetUserId <= 0) {
                jsonResponse(['error' => 'A valid userId is required.'], 400);
            }

            if ($targetUserId === (int)$user['id']) {
                jsonResponse(['error' => 'You cannot disable your own account.'], 400);
            }

            $stmt = $db->prepare('UPDATE Users SET Active = 0 WHERE ID = ?');
            $stmt->execute([$targetUserId]);

            if ($stmt->rowCount() === 0) {
                jsonResponse(['error' => 'User not found or already disabled.'], 404);
            }

            // Invalidate existing sessions for the disabled user.
            $stmt = $db->prepare('DELETE FROM Sessions WHERE UserID = ?');
            $stmt->execute([$targetUserId]);

            jsonResponse([
                'message' => 'User disabled.',
                'userId' => $targetUserId
            ]);
        }

        // Admin changes another user's password.
        if ($action === 'changePassword') {
            requireAdmin($user);

            $targetUserId = isset($data['userId']) ? (int)$data['userId'] : 0;

            if ($targetUserId <= 0) {
                jsonResponse(['error' => 'A valid userId is required.'], 400);
            }

            requireFields($data, ['password']);

            if (strlen((string)$data['password']) < 8) {
                jsonResponse(['error' => 'Password must be at least 8 characters.'], 400);
            }

            $hash = password_hash($data['password'], PASSWORD_DEFAULT);

            $stmt = $db->prepare(
                'UPDATE Users SET Password = ? WHERE ID = ?'
            );
            $stmt->execute([$hash, $targetUserId]);

            if ($stmt->rowCount() === 0) {
                jsonResponse(['error' => 'User not found or password is unchanged.'], 404);
            }

            // Force all existing sessions for the target user to log out.
            $stmt = $db->prepare('DELETE FROM Sessions WHERE UserID = ?');
            $stmt->execute([$targetUserId]);

            jsonResponse([
                'message' => 'Password changed. Existing sessions were invalidated.',
                'userId' => $targetUserId
            ]);
        }

        jsonResponse(['error' => 'Unknown PUT action.'], 400);
    }

    /*
     * DELETE
     */
    if ($method === 'DELETE') {
        $user = requireAuth();

        if ($id <= 0) {
            jsonResponse(['error' => 'A valid contact id is required.'], 400);
        }

        if ($user['role'] === 'admin') {
            $stmt = $db->prepare('DELETE FROM Contacts WHERE ID = ?');
            $stmt->execute([$id]);
        } else {
            $stmt = $db->prepare(
                'DELETE FROM Contacts WHERE ID = ? AND UserID = ?'
            );
            $stmt->execute([$id, $user['id']]);
        }

        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Contact not found.'], 404);
        }

        jsonResponse([
            'message' => 'Contact deleted.',
            'id' => $id
        ]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);

} catch (PDOException $e) {
    error_log($e->getMessage());

    // Do not expose database credentials/details to the browser.
    jsonResponse([
        'error' => 'Database error. Check the PHP/MySQL configuration and server logs.'
    ], 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonResponse(['error' => 'Server error.'], 500);
}
