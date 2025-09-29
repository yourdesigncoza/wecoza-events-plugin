<?php
namespace WeCozaEvents\Database;

use PDO;
use PDOException;
use RuntimeException;

use function function_exists;
use function getenv;
use function get_option;
use function is_string;
use function trim;

class Connection
{
    private static ?PDO $pdo = null;
    /**
     * @var array{host:string,port:int,dbname:string,user:string,password:string,schema:string}|null
     */
    private static ?array $config = null;
    private static bool $wordpressAttempted = false;

    public static function getPdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = self::getConfig();

        if ($config['password'] === '') {
            throw new RuntimeException('PostgreSQL password is not configured.');
        }

        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['dbname']);

        try {
            self::$pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to connect to PostgreSQL: ' . $exception->getMessage(), 0, $exception);
        }

        return self::$pdo;
    }

    public static function getSchema(): string
    {
        $config = self::getConfig();
        return $config['schema'];
    }

    /**
     * Clear cached connection and config (useful for tests/CLI reruns).
     */
    public static function reset(): void
    {
        self::$pdo = null;
        self::$config = null;
        self::$wordpressAttempted = false;
    }

    /**
     * @return array{host:string,port:int,dbname:string,user:string,password:string,schema:string}
     */
    private static function getConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $host = self::resolve('PGHOST', 'wecoza_postgres_host', 'db-wecoza-3-do-user-17263152-0.m.db.ondigitalocean.com');
        $portValue = self::resolve('PGPORT', 'wecoza_postgres_port', '25060');
        $dbname = self::resolve('PGDATABASE', 'wecoza_postgres_dbname', 'defaultdb');
        $user = self::resolve('PGUSER', 'wecoza_postgres_user', 'doadmin');
        $password = self::resolve('PGPASSWORD', 'wecoza_postgres_password', '');
        $schema = self::resolve('PGSCHEMA', 'wecoza_postgres_schema', 'public');

        $port = (int) $portValue;
        if ($port <= 0) {
            $port = 5432;
        }

        self::$config = [
            'host' => $host,
            'port' => $port,
            'dbname' => $dbname,
            'user' => $user,
            'password' => $password,
            'schema' => $schema !== '' ? $schema : 'public',
        ];

        return self::$config;
    }

    private static function resolve(string $envKey, string $optionKey, string $default): string
    {
        $env = getenv($envKey);
        if ($env !== false) {
            $env = trim($env);
            if ($env !== '') {
                return $env;
            }
        }

        if (!function_exists('get_option')) {
            self::bootstrapWordPress();
        }

        if (function_exists('get_option')) {
            $option = get_option($optionKey, $default);
            if (is_string($option)) {
                $option = trim($option);
                if ($option !== '') {
                    return $option;
                }
            }
        }

        return $default;
    }

    private static function bootstrapWordPress(): void
    {
        if (self::$wordpressAttempted) {
            return;
        }
        self::$wordpressAttempted = true;

        $root = dirname(__DIR__, 4);
        $wpLoadPath = $root . '/wp-load.php';
        if (is_readable($wpLoadPath)) {
            require_once $wpLoadPath;
        }
    }
}
