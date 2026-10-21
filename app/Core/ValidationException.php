<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Invalid input found by a service: App answers 422 with the field errors
 * ({ok, message, errors} to the site, problem+json to the API).
 */
final class ValidationException extends \RuntimeException
{
    /** @param array<string, string> $errors field => message */
    public function __construct(public readonly array $errors, string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('Please check the form.'));
    }

    public static function field(string $field, string $message): self
    {
        return new self([$field => $message], $message);
    }
}
