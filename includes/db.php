<?php
/** PDO connection (prepared statements everywhere = SQL injection safe). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            exit(APP_DEBUG
                ? 'Database error: ' . htmlspecialchars($e->getMessage()) . '<br>Did you run <a href="' . BASE_URL . '/install.php">install.php</a>?'
                : 'Service unavailable.');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function fetch_all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function fetch_one(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function fetch_val(string $sql, array $p = []) { return q($sql, $p)->fetchColumn(); }
