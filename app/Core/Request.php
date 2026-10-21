<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
    ) {
    }

    public static function capture(): self
    {
        $path = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
        $base = base_url();
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = '/' . ltrim(substr($path, strlen($base)), '/');
        }
        $body = $_POST;

        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        }

        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_GET, $body, $_SERVER);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /**
     * Uploaded files for a field, always as a list of
     * ['name', 'tmp_name', 'size', 'error'] (works for name="x" and name="x[]").
     */
    public function files(string $key): array
    {
        $field = $_FILES[$key] ?? null;
        if ($field === null) {
            return [];
        }

        $list = [];
        foreach ((array) $field['name'] as $i => $name) {
            $error = (int) ((array) $field['error'])[$i];
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $list[] = [
                'name' => (string) $name,
                'tmp_name' => (string) ((array) $field['tmp_name'])[$i],
                'size' => (int) ((array) $field['size'])[$i],
                'error' => $error,
            ];
        }

        return $list;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$key] ?? null;
    }

    public function contentLength(): int
    {
        return (int) ($this->server['CONTENT_LENGTH'] ?? 0);
    }

    /** Calls to /api/…: token authentication, JSON (problem+json for errors), no session. */
    public function isApi(): bool
    {
        return $this->path === '/api' || str_starts_with($this->path, '/api/');
    }

    public function wantsJson(): bool
    {
        return $this->isApi()
            || str_contains($this->header('Accept') ?? '', 'application/json')
            || $this->header('X-Requested-With') === 'fetch';
    }
}
