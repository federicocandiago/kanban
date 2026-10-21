<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Very small active-record base: attributes are read as properties ($user->email).
 */
abstract class Model
{
    protected static string $table;

    public function __construct(protected array $attributes = [])
    {
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    protected static function db(): Database
    {
        return Database::connection();
    }

    public static function find(int $id): ?static
    {
        return static::first('id = ?', [$id]);
    }

    public static function first(string $where, array $params = []): ?static
    {
        $row = static::db()->selectOne('SELECT * FROM `' . static::$table . "` WHERE {$where} LIMIT 1", $params);

        return $row ? new static($row) : null;
    }

    /** @return static[] */
    public static function where(string $where, array $params = [], string $order = 'id DESC'): array
    {
        $rows = static::db()->select('SELECT * FROM `' . static::$table . "` WHERE {$where} ORDER BY {$order}", $params);

        return array_map(static fn (array $row) => new static($row), $rows);
    }

    /** @return static[] */
    public static function all(string $order = 'id DESC'): array
    {
        return static::where('1 = 1', [], $order);
    }

    /** @return array{items: static[], page: int, pages: int, total: int} */
    public static function paginate(int $page, int $perPage = 10, string $order = 'id DESC', string $where = '1 = 1', array $params = []): array
    {
        $total = static::count($where, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $rows = static::db()->select(
            'SELECT * FROM `' . static::$table . "` WHERE {$where} ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['items' => array_map(static fn (array $row) => new static($row), $rows), 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    public static function count(string $where = '1 = 1', array $params = []): int
    {
        return (int) static::db()->scalar('SELECT COUNT(*) FROM `' . static::$table . "` WHERE {$where}", $params);
    }

    public static function create(array $data): static
    {
        $now = date('Y-m-d H:i:s');
        $data += ['created_at' => $now, 'updated_at' => $now];
        $id = static::db()->insert(static::$table, $data);

        return static::find($id);
    }

    public function update(array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        static::db()->update(static::$table, $data, 'id = ?', [$this->id]);
        $this->attributes = array_merge($this->attributes, $data);
    }

    public function delete(): void
    {
        static::db()->query('DELETE FROM `' . static::$table . '` WHERE id = ?', [$this->id]);
    }
}
