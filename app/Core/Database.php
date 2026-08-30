<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Gestionnaire de base de données (Singleton)
 * PDO avec prepared statements et transactions
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = Config::get('database');

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            (int) $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (\PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Récupère l'instance unique de la base de données
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Récupère l'objet PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Exécute une requête SELECT
     * @param string $sql Requête SQL
     * @param array $params Paramètres liés
     * @return array Résultats
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Récupère une seule ligne
     */
    public function queryOne(string $sql, array $params = []): array|false
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Exécute une requête INSERT/UPDATE/DELETE
     * @return int Nombre de lignes affectées
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Insère une ligne et retourne l'ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        $this->execute($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Met à jour des lignes
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $sql = "UPDATE `{$table}` SET {$set} WHERE {$where}";

        return $this->execute($sql, array_merge(array_values($data), $whereParams));
    }

    /**
     * Supprime des lignes
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->execute("DELETE FROM `{$table}` WHERE {$where}", $params);
    }

    /**
     * Démarre une transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Valide une transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Annule une transaction
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Compte les lignes
     */
    public function count(string $table, string $where = '1=1', array $params = []): int
    {
        $result = $this->queryOne("SELECT COUNT(*) as total FROM `{$table}` WHERE {$where}", $params);
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Vérifie si un enregistrement existe
     */
    public function exists(string $table, string $where, array $params = []): bool
    {
        return $this->count($table, $where, $params) > 0;
    }

    /** Empêche le clonage */
    private function __clone() {}
}
