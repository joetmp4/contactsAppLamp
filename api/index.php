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

//Helpers
function run(PDO $db, string $sql, array $params = []): PDOStatement {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

//Just matching columns
function likeClause(array $cols, string $q): array {
    return ['(' . implode(' LIKE ? OR ', $cols) . ' LIKE ?)', array_fill(0, count($cols), "%$q%")];
}

//Admins can touch any row and normal users only their own
function ownerScope(array $user, string $col = 'UserID'): array {
    return $user['role'] === 'admin' ? ['', []] : [" AND $col = ?", [$user['id']]];
}

function contactFields(array $data): array {
    requireFields($data, ['firstName', 'lastName', 'email', 'phone']);
    return [trim($data['firstName']), trim($data['lastName']), trim($data['email']), trim($data['phone'])];
}

function checkPassword($password): void {
    if (strlen((string)$password) < 8) {
        jsonResponse(['error' => 'Password must be at least 8 characters.'], 400);
    }
}

function createUser(PDO $db, array $data, string $role): void {
    requireFields($data, ['login', 'password', 'firstName', 'lastName']);
    $login = trim($data['login']);

    if (strlen($login) < 3 || strlen($login) > 50) {
        jsonResponse(['error' => 'Login must be 3-50 characters.'], 400);
    }
    checkPassword($data['password']);

    if (run($db, 'SELECT ID FROM Users WHERE Username = ? LIMIT 1', [$login])->fetch()) {
        jsonResponse(['error' => 'That login is already in use.'], 409);
    }

    run($db,
        'INSERT INTO Users (FirstName, LastName, Username, Password, Role, Active) VALUES (?, ?, ?, ?, ?, 1)',
        [trim($data['firstName']), trim($data['lastName']), $login, password_hash($data['password'], PASSWORD_DEFAULT), $role]
    );

    jsonResponse([
        'message' => $role === 'admin' ? 'Administrator account created.' : 'Registration successful.',
        'id' => (int)$db->lastInsertId(),
        'login' => $login,
        'role' => $role
    ], 201);
}

//Request
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$q = trim($_GET['q'] ?? '');

$contactCols = 'ID AS id, UserID AS userId, FirstName AS firstName, LastName AS lastName, Email AS email, PhoneNumber AS phone';

try {
    $db = getDb();

    //GET
    if ($method === 'GET') {
        if (isset($_GET['ping'])) {
            jsonResponse(['status' => 'OK', 'timestamp' => time()]);
        }

        $user = requireAuth();

        //Admin: search users (Password column intentionally NOT selected)
        if ($action === 'users') {
            requireAdmin($user);
            $sql = 'SELECT ID AS id, Username AS login, FirstName AS firstName, LastName AS lastName,
                           Role AS role, Active AS active, DateCreated AS createdAt, DateUpdated AS updatedAt
                    FROM Users';
            $params = [];
            if ($q !== '') {
                [$where, $params] = likeClause(['Username', 'FirstName', 'LastName'], $q);
                $sql .= " WHERE $where";
            }
            jsonResponse(['users' => run($db, "$sql ORDER BY ID LIMIT 100", $params)->fetchAll()]);
        }

        //Admin: search all contacts
        if ($action === 'allContacts') {
            requireAdmin($user);
            $sql = 'SELECT c.ID AS id, c.UserID AS userId, c.FirstName AS firstName, c.LastName AS lastName,
                           c.Email AS email, c.PhoneNumber AS phone,
                           u.Username AS userLogin, u.FirstName AS userFirstName, u.LastName AS userLastName
                    FROM Contacts c INNER JOIN Users u ON u.ID = c.UserID';
            $params = [];
            if ($q !== '') {
                [$where, $params] = likeClause(
                    ['c.FirstName', 'c.LastName', 'c.Email', 'c.PhoneNumber', 'u.Username', 'u.FirstName', 'u.LastName'], $q
                );
                $sql .= " WHERE $where";
            }
            jsonResponse(['contacts' => run($db, "$sql ORDER BY c.ID LIMIT 100", $params)->fetchAll()]);
        }

        //Single contact (users: own only, admins: any)
        if ($id > 0) {
            [$scope, $scopeParams] = ownerScope($user, 'c.UserID');
            $contact = run($db,
                "SELECT c.ID AS id, c.UserID AS userId, c.FirstName AS firstName, c.LastName AS lastName,
                        c.Email AS email, c.PhoneNumber AS phone, u.Username AS userLogin
                 FROM Contacts c INNER JOIN Users u ON u.ID = c.UserID
                 WHERE c.ID = ?$scope LIMIT 1",
                [$id, ...$scopeParams]
            )->fetch();

            if (!$contact) {
                jsonResponse(['error' => 'Contact not found.'], 404);
            }
            jsonResponse(['contact' => $contact]);
        }

        //List / search own contacts
        $sql = "SELECT $contactCols FROM Contacts WHERE UserID = ?";
        $params = [$user['id']];
        if ($q !== '') {
            [$where, $like] = likeClause(['FirstName', 'LastName', 'Email', 'PhoneNumber'], $q);
            $sql .= " AND $where";
            $params = [...$params, ...$like];
        }
        jsonResponse(['contacts' => run($db, "$sql ORDER BY LastName, FirstName LIMIT 100", $params)->fetchAll()]);
    }

    //POST
    if ($method === 'POST') {
        $data = readJsonBody();

        //Login
        if ($action === 'login' || ($action === '' && isset($data['login'], $data['password']))) {
            requireFields($data, ['login', 'password']);

            $account = run($db,
                'SELECT ID AS id, Username AS login, Password AS password, FirstName AS firstName,
                        LastName AS lastName, Role AS role, Active AS active
                 FROM Users WHERE Username = ? LIMIT 1',
                [trim($data['login'])]
            )->fetch();

            if (!$account || !password_verify($data['password'], $account['password'])) {
                jsonResponse(['error' => 'Invalid username or password.'], 401);
            }
            if ((int)$account['active'] !== 1) {
                jsonResponse(['error' => 'This account is disabled.'], 403);
            }

            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + (SESSION_HOURS * 3600));
            run($db, 'INSERT INTO Sessions (UserID, Token, ExpiresAt) VALUES (?, ?, ?)', [$account['id'], $token, $expiresAt]);

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

        //Public registration (always a basic user)
        if ($action === 'register') {
            createUser($db, $data, 'user');
        }

        $user = requireAuth();

        //Create contact for current user
        if ($action === 'contact' || $action === 'createContact') {
            run($db,
                'INSERT INTO Contacts (UserID, FirstName, LastName, Email, PhoneNumber) VALUES (?, ?, ?, ?, ?)',
                [$user['id'], ...contactFields($data)]
            );
            jsonResponse(['message' => 'Contact created.', 'id' => (int)$db->lastInsertId()], 201);
        }

        //Admin creates another admin
        if ($action === 'createAdmin') {
            requireAdmin($user);
            createUser($db, $data, 'admin');
        }

        jsonResponse(['error' => 'Unknown POST action.'], 400);
    }

    //PUT
    if ($method === 'PUT') {
        $data = readJsonBody();
        $user = requireAuth();

        //Update contact (users: own only, admins: any)
        if ($action === 'contact' || $action === 'updateContact' || $id > 0) {
            if ($id <= 0) {
                jsonResponse(['error' => 'A valid contact id is required.'], 400);
            }

            [$scope, $scopeParams] = ownerScope($user);
            $stmt = run($db,
                "UPDATE Contacts SET FirstName = ?, LastName = ?, Email = ?, PhoneNumber = ? WHERE ID = ?$scope",
                [...contactFields($data), $id, ...$scopeParams]
            );

            if ($stmt->rowCount() === 0) {
                jsonResponse(['error' => 'Contact not found or no changes were made.'], 404);
            }
            jsonResponse(['message' => 'Contact updated.', 'id' => $id]);
        }

        //Admin-only actions below both need a target user
        if ($action === 'disableUser' || $action === 'changePassword') {
            requireAdmin($user);
            $targetUserId = (int)($data['userId'] ?? 0);

            if ($targetUserId <= 0) {
                jsonResponse(['error' => 'A valid userId is required.'], 400);
            }

            if ($action === 'disableUser') {
                if ($targetUserId === (int)$user['id']) {
                    jsonResponse(['error' => 'You cannot disable your own account.'], 400);
                }
                $stmt = run($db, 'UPDATE Users SET Active = 0 WHERE ID = ?', [$targetUserId]);
                $notFound = 'User not found or already disabled.';
                $message = 'User disabled.';
            } else {
                requireFields($data, ['password']);
                checkPassword($data['password']);
                $stmt = run($db, 'UPDATE Users SET Password = ? WHERE ID = ?',
                    [password_hash($data['password'], PASSWORD_DEFAULT), $targetUserId]);
                $notFound = 'User not found or password is unchanged.';
                $message = 'Password changed. Existing sessions were invalidated.';
            }

            if ($stmt->rowCount() === 0) {
                jsonResponse(['error' => $notFound], 404);
            }

            //Kick the user out of any active sessions
            run($db, 'DELETE FROM Sessions WHERE UserID = ?', [$targetUserId]);
            jsonResponse(['message' => $message, 'userId' => $targetUserId]);
        }

        jsonResponse(['error' => 'Unknown PUT action.'], 400);
    }

    //DELETE
    if ($method === 'DELETE') {
        $user = requireAuth();

        if ($id <= 0) {
            jsonResponse(['error' => 'A valid contact id is required.'], 400);
        }

        [$scope, $scopeParams] = ownerScope($user);
        $stmt = run($db, "DELETE FROM Contacts WHERE ID = ?$scope", [$id, ...$scopeParams]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Contact not found.'], 404);
        }
        jsonResponse(['message' => 'Contact deleted.', 'id' => $id]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);

} catch (PDOException $e) {
    error_log($e->getMessage());
    //Keep DB details out of the response so credentials/schema never leak
    jsonResponse(['error' => 'Database error. Check the PHP/MySQL configuration and server logs.'], 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonResponse(['error' => 'Server error.'], 500);
}
