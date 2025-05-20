<?php
namespace entitys\bd;

use PDO;
use PDOException;

class ConnectionOnBD
{
    public static function getConnection(): PDO
    {
        $host = $_ENV["HOST_BD"] ?? 'localhost';
        $user = $_ENV["USER_BD"] ?? 'root';
        $pass = $_ENV["PASS_BD"] ?? '';
        $db = $_ENV["DB_BD"] ?? '';
        $charset = $_ENV["CHARSET_BD"] ?? "utf8mb4";
        $port = $_ENV["PORT_BD"] ?? "3306";

        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // lança exceções em erros
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // retorna resultados como arrays associativos
                PDO::ATTR_EMULATE_PREPARES => false, // usa prepared statements reais
            ]);
            return $pdo;
        } catch (PDOException $e) {
            throw new \RuntimeException("Erro ao conectar ao banco de dados: " . $e->getMessage());
        }
    }
}
