<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Plain-PHP templates in /views. A page is rendered first, then wrapped
 * in its layout (which receives it as $content). Components live in
 * /views/components and are rendered with component('name', [...]).
 */
final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::partial($view, $data);

        return $layout === null ? $content : self::partial($layout, [...$data, 'content' => $content]);
    }

    public static function partial(string $__view, array $__data = []): string
    {
        $__file = BASE_PATH . '/views/' . $__view . '.php';
        if (!is_file($__file)) {
            throw new \RuntimeException("View not found: {$__view}");
        }

        extract([...self::$shared, ...$__data], EXTR_SKIP);
        ob_start();
        try {
            include $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
