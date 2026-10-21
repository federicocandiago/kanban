<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private ?string $file = null;

    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    /**
     * Error of the API, as RFC 9457 "problem details": {type, title, status, detail, errors?}.
     */
    public static function problem(int $status, string $title, string $detail = '', array $errors = []): self
    {
        $body = ['type' => 'about:blank', 'title' => $title, 'status' => $status] + ($detail !== '' ? ['detail' => $detail] : []) + ($errors ? ['errors' => $errors] : []);

        return new self(
            (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8']
        );
    }

    /** $to is a site path ("/dashboard", base added) or a full URL. */
    public static function redirect(string $to, int $status = 302): self
    {
        if (str_starts_with($to, '/') && !str_starts_with($to, '//')) {
            $to = url($to);
        }

        return new self('', $status, ['Location' => $to]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public static function file(string $path, string $contentType = 'application/octet-stream'): self
    {
        $response = new self('', 200, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) filesize($path),
            'Cache-Control' => 'no-store',
        ]);
        $response->file = $path;

        return $response;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->file !== null) {
            readfile($this->file);

            return;
        }
        echo $this->body;
    }
}
