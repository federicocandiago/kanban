<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Translator;
use App\Core\View;

/**
 * Emails written in the recipient's language, from views/emails/{view}-text.php and -html.php.
 * $subject receives the view data and returns the translated subject (a closure, so that
 * the __() literal stays in the caller, where bin/i18n.php finds it).
 * $data['color'] (a Palette key) tints the header: the sender's colour, or the recipient's own.
 */
final class Mail
{
    public static function send(string $to, ?string $locale, \Closure $subject, string $view, array $data): void
    {
        $previous = Translator::locale();
        Translator::setLocale($locale ?: $previous);
        try {
            $data += ['app' => (string) env('APP_NAME', 'Kalban')];
            Mailer::send(
                $to,
                $subject($data),
                View::partial("emails/{$view}-text", $data),
                View::partial('emails/layout', [
                    'app' => $data['app'],
                    'header' => Palette::secondary($data['color'] ?? null),
                    'body' => View::partial("emails/{$view}-html", $data),
                ])
            );
        } finally {
            Translator::setLocale($previous);
        }
    }
}
