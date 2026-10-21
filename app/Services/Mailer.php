<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Sends UTF-8 emails (text + HTML) without libraries. MAIL_DRIVER in .env:
 *  - mail: PHP mail(), fine on most shared hosting
 *  - smtp: MAIL_HOST / MAIL_PORT / MAIL_ENCRYPTION (tls = STARTTLS, ssl, none) / MAIL_USERNAME / MAIL_PASSWORD
 *  - log:  writes the message to storage/logs/mail.log (development)
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $text, ?string $html = null): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid recipient');
        }

        $from = (string) env('MAIL_FROM_ADDRESS', 'no-reply@' . (parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: 'localhost'));
        $fromName = (string) env('MAIL_FROM_NAME', env('APP_NAME', 'Kalban'));
        [$headers, $body] = self::build($from, $fromName, $subject, $text, $html);
        $encodedSubject = self::encode($subject);

        switch (strtolower((string) env('MAIL_DRIVER', 'mail'))) {
            case 'log':
                $message = "To: {$to}\r\nSubject: {$encodedSubject}\r\n{$headers}\r\n\r\n{$body}";
                file_put_contents(storage_path('logs/mail.log'), "==== " . date('c') . "\r\n{$message}\r\n", FILE_APPEND);
                return;
            case 'smtp':
                self::smtp($from, $to, "To: <{$to}>\r\nSubject: {$encodedSubject}\r\n{$headers}\r\n\r\n{$body}");
                return;
            default:
                if (!mail($to, $encodedSubject, $body, $headers, '-f' . $from)) {
                    throw new \RuntimeException('mail() could not send the message');
                }
        }
    }

    /** @return array{0: string, 1: string} headers (without To/Subject) and MIME body */
    private static function build(string $from, string $fromName, string $subject, string $text, ?string $html): array
    {
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        $headers = [
            'From: ' . self::encode($fromName) . " <{$from}>",
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . "@{$domain}>",
            'MIME-Version: 1.0',
        ];
        $part = static fn (string $type, string $content): string =>
            "Content-Type: {$type}; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . rtrim(chunk_split(base64_encode($content)));

        if ($html === null) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';

            return [implode("\r\n", $headers), rtrim(chunk_split(base64_encode($text)))];
        }

        $boundary = 'b' . bin2hex(random_bytes(12));
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $body = "--{$boundary}\r\n" . $part('text/plain', $text) . "\r\n"
            . "--{$boundary}\r\n" . $part('text/html', $html) . "\r\n"
            . "--{$boundary}--";

        return [implode("\r\n", $headers), $body];
    }

    private static function encode(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    private static function smtp(string $from, string $to, string $data): void
    {
        $host = (string) env('MAIL_HOST', 'localhost');
        $port = (int) env('MAIL_PORT', 587);
        $encryption = strtolower((string) env('MAIL_ENCRYPTION', 'tls'));
        $user = (string) env('MAIL_USERNAME', '');
        $password = (string) env('MAIL_PASSWORD', '');

        $context = stream_context_create(['ssl' => ['peer_name' => $host]]);
        $socket = @stream_socket_client(($encryption === 'ssl' ? 'ssl://' : 'tcp://') . "{$host}:{$port}", $errno, $error, 15, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new \RuntimeException("SMTP connection to {$host}:{$port} failed: {$error}");
        }
        stream_set_timeout($socket, 20);

        $reply = static function () use ($socket): string {
            $response = '';
            while (($line = fgets($socket, 515)) !== false) {
                $response .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $response;
        };
        $command = static function (?string $line, array $expected) use ($socket, $reply): void {
            if ($line !== null) {
                fwrite($socket, $line . "\r\n");
            }
            $response = $reply();
            if (!in_array((int) substr($response, 0, 3), $expected, true)) {
                throw new \RuntimeException('SMTP error: ' . trim($response ?: 'no answer'));
            }
        };

        try {
            $hello = 'EHLO ' . (parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: 'localhost');
            $command(null, [220]);
            $command($hello, [250]);
            if ($encryption === 'tls') {
                $command('STARTTLS', [220]);
                $method = STREAM_CRYPTO_METHOD_TLS_CLIENT
                    | (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT : 0)
                    | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
                if (!stream_socket_enable_crypto($socket, true, $method)) {
                    throw new \RuntimeException('SMTP: STARTTLS failed');
                }
                $command($hello, [250]);
            }
            if ($user !== '') {
                $command('AUTH LOGIN', [334]);
                $command(base64_encode($user), [334]);
                $command(base64_encode($password), [235]);
            }
            $command("MAIL FROM:<{$from}>", [250]);
            $command("RCPT TO:<{$to}>", [250, 251]);
            $command('DATA', [354]);
            $command(preg_replace('/^\./m', '..', $data) . "\r\n.", [250]);
            $command('QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }
}
