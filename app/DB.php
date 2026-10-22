<?php
declare(strict_types=1);

/**
 * لایه دیتابیس (PDO / MySQL + MariaDB)
 * تمام کوئری‌ها Prepared هستند؛ هیچ ورودی کاربر داخل SQL الحاق نمی‌شود.
 */
final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $c   = config('db', []);
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'] ?? 'localhost',
            (int)($c['port'] ?? 3306),
            $c['name'] ?? '',
            $c['charset'] ?? 'utf8mb4'
        );
        self::$pdo = new PDO($dsn, (string)($c['user'] ?? ''), (string)($c['pass'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
        ]);
        return self::$pdo;
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function val(string $sql, array $params = [], $default = null)
    {
        $v = self::q($sql, $params)->fetchColumn();
        return $v === false ? $default : $v;
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::q($sql, $params)->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::q($sql, $params);
        return (int)self::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function upsert(string $table, array $data, array $uniqueKeys): int
    {
        $cols = array_keys($data);
        $ph   = implode(',', array_map(fn($c) => ':' . $c, $cols));
        $upd  = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", $cols));
        $sql  = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES ($ph)
                 ON DUPLICATE KEY UPDATE $upd";
        self::q($sql, $data);
        return (int)self::pdo()->lastInsertId();
    }

    public static function tx(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn($pdo);
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function tableExists(string $table): bool
    {
        try {
            self::q("SELECT 1 FROM `$table` LIMIT 1");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
