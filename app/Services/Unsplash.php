<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Board backgrounds from Unsplash (https://unsplash.com/documentation), with UNSPLASH_ACCESS_KEY in .env.
 * The key stays on the server: the browser asks us, we ask Unsplash. Answers are cached (see CACHE_*).
 * As the API guidelines require, photos are hotlinked, credited to their author, and the
 * download is "tracked" when a photo becomes a background.
 */
final class Unsplash
{
    private const API = 'https://api.unsplash.com';
    public const PER_PAGE = 12;

    /**
     * Only API calls count against the hourly limit (50 for a demo key; images never count), so answers
     * are kept: the default photos for a day, searches for six hours, each photo shown for a week.
     */
    private const CACHE_DEFAULT = 86400;
    private const CACHE_SEARCH = 21600;
    private const CACHE_PHOTO = 604800;

    public static function enabled(): bool
    {
        return (string) env('UNSPLASH_ACCESS_KEY', '') !== '';
    }

    /**
     * Twelve photos for $query (empty: the "Wallpapers" topic).
     * @return array<int, array{id: string, thumb: string, author: string, author_url: string, color: string}>
     */
    public static function search(string $query): array
    {
        $query = mb_substr(trim($query), 0, 60);
        $path = $query === ''
            ? '/topics/wallpapers/photos?' . http_build_query(['per_page' => self::PER_PAGE, 'orientation' => 'landscape'])
            : '/search/photos?' . http_build_query(['query' => $query, 'per_page' => self::PER_PAGE, 'orientation' => 'landscape', 'content_filter' => 'high']);

        $cache = storage_path('cache/unsplash-' . md5($path) . '.json');
        if (is_file($cache) && filemtime($cache) > time() - ($query === '' ? self::CACHE_DEFAULT : self::CACHE_SEARCH)) {
            return json_decode((string) file_get_contents($cache), true) ?: [];
        }

        $data = self::get($path);
        $photos = [];
        foreach ($data['results'] ?? $data as $item) {
            $photo = self::remember($item); // choosing it later costs no extra call
            $photos[] = ['id' => $photo['id'], 'thumb' => $photo['thumb'], 'author' => $photo['author'], 'author_url' => $photo['author_url'], 'color' => $photo['color']];
        }
        @file_put_contents($cache, json_encode($photos));

        return $photos;
    }

    /**
     * Board columns for a background chosen in the picker: "color:blue" or "photo:{unsplash id}".
     * Returns null when the value is not valid.
     */
    public static function background(string $value): ?array
    {
        [$type, $id] = array_pad(explode(':', $value, 2), 2, '');

        if ($type === 'color' && Palette::has($id)) {
            return ['bg_type' => 'color', 'bg_value' => $id, 'bg_thumb' => null, 'bg_tint' => null, 'bg_credit_name' => null, 'bg_credit_url' => null];
        }
        if ($type !== 'photo' || !self::enabled() || !preg_match('/^[A-Za-z0-9_-]{4,40}$/', $id)) {
            return null;
        }

        $file = self::photoCache($id);
        $photo = is_file($file) && filemtime($file) > time() - self::CACHE_PHOTO
            ? json_decode((string) file_get_contents($file), true)
            : self::remember(self::get('/photos/' . $id));

        // required by the API guidelines when a photo is used: the only call a choice costs
        if ($photo['download'] !== '') {
            try {
                self::get((string) parse_url($photo['download'], PHP_URL_PATH) . '?' . (string) parse_url($photo['download'], PHP_URL_QUERY));
            } catch (\RuntimeException) { // tracking is best effort
            }
        }

        return [
            'bg_type' => 'photo',
            'bg_value' => $photo['raw'] . (str_contains($photo['raw'], '?') ? '&' : '?') . 'w=2560&q=80&fm=jpg&fit=max',
            'bg_thumb' => $photo['thumb'],
            'bg_tint' => preg_match('/^#[0-9a-f]{6}$/i', $photo['color'] ?? '') ? strtolower($photo['color']) : null,
            'bg_credit_name' => mb_substr($photo['author'], 0, 100),
            'bg_credit_url' => $photo['author_url'],
        ];
    }

    /** What we need of a photo from the API, also kept on disk for when it is chosen. */
    private static function remember(array $item): array
    {
        $photo = [
            'id' => (string) $item['id'],
            'raw' => (string) $item['urls']['raw'],
            'thumb' => (string) $item['urls']['small'],
            'author' => (string) $item['user']['name'],
            'author_url' => self::referral((string) $item['user']['links']['html']),
            'download' => (string) ($item['links']['download_location'] ?? ''),
            'color' => (string) ($item['color'] ?? '#2c3e50'),
        ];
        @file_put_contents(self::photoCache($photo['id']), json_encode($photo));

        return $photo;
    }

    private static function photoCache(string $id): string
    {
        return storage_path('cache/unsplash-photo-' . preg_replace('/[^A-Za-z0-9_-]/', '', $id) . '.json');
    }

    /** Links back to Unsplash must say where the visitor comes from. */
    public static function referral(string $url): string
    {
        $app = preg_replace('/[^a-z0-9]+/', '_', strtolower((string) env('APP_NAME', 'kalban')));

        return $url . (str_contains($url, '?') ? '&' : '?') . 'utm_source=' . $app . '&utm_medium=referral';
    }

    private static function get(string $path): array
    {
        $headers = ['Authorization: Client-ID ' . env('UNSPLASH_ACCESS_KEY'), 'Accept-Version: v1'];

        if (function_exists('curl_init')) {
            $curl = curl_init(self::API . $path);
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 8]);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        } else {
            $context = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'timeout' => 8, 'ignore_errors' => true]]);
            $body = @file_get_contents(self::API . $path, false, $context);
            $status = (int) preg_replace('#^HTTP/\S+ (\d+).*#', '$1', $http_response_header[0] ?? '');
        }

        if (!is_string($body) || $status !== 200) {
            throw new \RuntimeException("Unsplash: HTTP {$status}");
        }

        return json_decode($body, true) ?: [];
    }
}
