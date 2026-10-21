<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thrown when MySQL cannot be reached; carries a human readable hint.
 */
final class DatabaseException extends \RuntimeException
{
    public static function unreachable(array $config, \PDOException $previous): self
    {
        $code = (int) ($previous->errorInfo[1] ?? $previous->getCode());

        $hint = match ($code) {
            1049 => "The database \"{$config['database']}\" does not exist. Create it or check DB_DATABASE in .env.",
            1045, 1044 => 'Access denied. Check DB_USERNAME and DB_PASSWORD in .env.',
            2002, 2003, 2006 => "The MySQL server is not responding on {$config['host']}:{$config['port']}. Is it running?",
            default => $previous->getMessage(),
        };

        return new self('Unable to connect to the database. ' . $hint, $code, $previous);
    }
}
