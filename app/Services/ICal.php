<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Minimal iCalendar (RFC 5545) reader/writer. A file becomes a tree of components:
 *   ['name' => 'VEVENT', 'props' => [['name', params[], 'value'], ...], 'children' => [...]]
 * Writing gives back the same lines for everything that was not touched (alarms, attendees,
 * X- properties…), so editing an event from here never loses what other apps put in it.
 */
final class ICal
{
    public static function parse(string $text): array
    {
        // unfold: a line starting with a space or a tab continues the previous one
        $lines = preg_split('/\r?\n/', preg_replace('/\r?\n[ \t]/', '', $text) ?? '') ?: [];
        $root = ['name' => '', 'props' => [], 'children' => []];
        $stack = [&$root];

        foreach ($lines as $line) {
            if ($line === '' || !preg_match('/^([A-Za-z0-9-]+)((?:;[^:"]*(?:"[^"]*"[^:"]*)*)*):(.*)$/s', $line, $m)) {
                continue;
            }
            [, $name, $paramText, $value] = $m;
            $name = strtoupper($name);
            $current = &$stack[count($stack) - 1];

            if ($name === 'BEGIN') {
                $current['children'][] = ['name' => strtoupper($value), 'props' => [], 'children' => []];
                $stack[] = &$current['children'][array_key_last($current['children'])];
            } elseif ($name === 'END') {
                if (count($stack) > 1) {
                    array_pop($stack);
                }
            } else {
                $current['props'][] = [$name, self::params($paramText), $value];
            }
            unset($current);
        }

        return $root['children'][0] ?? ['name' => 'VCALENDAR', 'props' => [], 'children' => []];
    }

    public static function serialize(array $component): string
    {
        $out = 'BEGIN:' . $component['name'] . "\r\n";
        foreach ($component['props'] as [$name, $params, $value]) {
            $line = $name;
            foreach ($params as $key => $param) {
                $line .= ';' . $key . '=' . (preg_match('/[;:,]/', $param) ? '"' . $param . '"' : $param);
            }
            $out .= self::fold($line . ':' . $value);
        }
        foreach ($component['children'] as $child) {
            $out .= self::serialize($child);
        }

        return $out . 'END:' . $component['name'] . "\r\n";
    }

    /** @return array[] the VEVENT components of a calendar */
    public static function events(array $calendar): array
    {
        return array_values(array_filter($calendar['children'], static fn (array $c): bool => $c['name'] === 'VEVENT'));
    }

    public static function prop(array $component, string $name): ?array
    {
        foreach ($component['props'] as $prop) {
            if ($prop[0] === $name) {
                return $prop;
            }
        }

        return null;
    }

    /** @return array[] every property with that name (e.g. all the ATTACH lines) */
    public static function props(array $component, string $name): array
    {
        return array_values(array_filter($component['props'], static fn (array $p): bool => $p[0] === $name));
    }

    public static function text(array $component, string $name): ?string
    {
        $prop = self::prop($component, $name);

        return $prop ? self::unescape($prop[2]) : null;
    }

    /** Replaces (or adds, or with null removes) a property, keeping its position. */
    public static function set(array $component, string $name, ?string $value, array $params = []): array
    {
        $kept = [];
        $done = false;
        foreach ($component['props'] as $prop) {
            if ($prop[0] !== $name) {
                $kept[] = $prop;
            } elseif (!$done && $value !== null) {
                $kept[] = [$name, $params, $value];
                $done = true;
            }
        }
        if (!$done && $value !== null) {
            $kept[] = [$name, $params, $value];
        }
        $component['props'] = $kept;

        return $component;
    }

    public static function setText(array $component, string $name, ?string $text): array
    {
        return self::set($component, $name, $text === null || $text === '' ? null : self::escape($text));
    }

    /**
     * A DTSTART / DTEND / RECURRENCE-ID value as a date in $tz.
     * Handles UTC ("…Z"), TZID (IANA names; unknown ones fall back to $tz), floating times and dates.
     * @return array{0: DateTimeImmutable, 1: bool}|null the moment, and whether it is a whole day
     */
    public static function date(?array $prop, DateTimeZone $tz): ?array
    {
        if ($prop === null) {
            return null;
        }
        [, $params, $value] = $prop;
        $value = trim($value);

        if (($params['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('!Ymd', substr($value, 0, 8), $tz);

            return $date ? [$date, true] : null;
        }
        if (str_ends_with($value, 'Z')) {
            $date = DateTimeImmutable::createFromFormat('Ymd\THis\Z', $value, new DateTimeZone('UTC'));
        } else {
            $date = DateTimeImmutable::createFromFormat('Ymd\THis', $value, self::zone($params['TZID'] ?? null, $tz));
        }

        return $date ? [$date->setTimezone($tz), false] : null;
    }

    /** Value for a DTSTART/DTEND we write: whole days as DATE, times in UTC (no VTIMEZONE needed). */
    public static function formatDate(DateTimeImmutable $date, bool $allDay): array
    {
        return $allDay
            ? [['VALUE' => 'DATE'], $date->format('Ymd')]
            : [[], $date->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z')];
    }

    public static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    public static function unescape(string $text): string
    {
        return preg_replace_callback('/\\\\([\\\\;,nN])/', static fn (array $m): string => in_array($m[1], ['n', 'N'], true) ? "\n" : $m[1], $text) ?? $text;
    }

    private static function zone(?string $tzid, DateTimeZone $fallback): DateTimeZone
    {
        if ($tzid) {
            try {
                return new DateTimeZone(trim($tzid, '"'));
            } catch (\Exception) { // e.g. Outlook names ("W. Europe Standard Time")
            }
        }

        return $fallback;
    }

    private static function params(string $text): array
    {
        $params = [];
        preg_match_all('/;([A-Za-z0-9-]+)=("[^"]*"|[^;]*)/', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as [, $key, $value]) {
            $params[strtoupper($key)] = trim($value, '"');
        }

        return $params;
    }

    /** Lines longer than 75 octets continue on the next line after a space, without cutting UTF-8 characters. */
    private static function fold(string $line): string
    {
        $out = '';
        $max = 75; // continuation lines: 74 + the leading space
        while (strlen($line) > $max) {
            $cut = $max;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut) . "\r\n ";
            $line = substr($line, $cut);
            $max = 74;
        }

        return $out . $line . "\r\n";
    }
}
