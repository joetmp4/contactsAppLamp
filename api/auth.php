<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';

function getBearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

function requireAuth(): array
{
    $token = getBearerToken();

    if ($token === null || $token === '') {
        jsonResponse(['error' => 'Authorization token is required.'], 401);
    }

    $db = getDb();

    $sql = 'SELECT u.ID AS id, u.Username AS login, u.FirstName AS firstName, u.LastName AS lastName,
                   u.Role AS role, u.Active AS active,
                   s.ID AS sessionId, s.ExpiresAt AS expiresAt
            FROM Sessions s
            INNER JOIN Users u ON u.ID = s.UserID
            WHERE s.Token = ?
              AND s.ExpiresAt > CURRENT_TIMESTAMP
            LIMIT 1';

    $stmt = $db->prepare($sql);
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['active'] !== 1) {
        jsonResponse(['error' => 'Invalid or expired authorization token.'], 401);
    }

    return $user;
}

function requireAdmin(array $user): void
{
    if ($user['role'] !== 'admin') {
        jsonResponse(['error' => 'Administrator access is required.'], 403);
    }
}
