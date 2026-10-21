<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Small CalDAV client (RFC 4791, plus RFC 8607 managed attachments), on curl.
 * Works with iCloud (Apple ID + app-specific password) and standard servers
 * (Nextcloud, Fastmail, Radicale, Baïkal…). Errors throw CalDavException.
 */
final class CalDav
{
    public const ICLOUD = 'https://caldav.icloud.com/';

    private const NS = ['D' => 'DAV:', 'C' => 'urn:ietf:params:xml:ns:caldav', 'CS' => 'http://calendarserver.org/ns/', 'I' => 'http://apple.com/ns/ical/'];

    public function __construct(private readonly string $url, private readonly string $user, private readonly string $password)
    {
    }

    /**
     * Finds the calendar home: current-user-principal (on the URL or its /.well-known/caldav),
     * then the principal's calendar-home-set.
     * @return array{home: string, name: ?string}
     */
    public function discover(): array
    {
        $principal = null;
        foreach ([$this->url, $this->absolute('/.well-known/caldav', $this->url)] as $start) {
            try {
                $xpath = $this->propfind($start, '<D:current-user-principal/>', 0);
                $href = $xpath->evaluate('string(//D:current-user-principal/D:href)');
                if ($href !== '') {
                    $principal = $this->absolute($href, $start);
                    break;
                }
            } catch (CalDavException $e) {
                if ($e->status === 401) {
                    throw $e;
                }
            }
        }
        if ($principal === null) {
            throw new CalDavException('No CalDAV principal found at this address.');
        }

        $xpath = $this->propfind($principal, '<C:calendar-home-set/><D:displayname/>', 0);
        $home = $xpath->evaluate('string(//C:calendar-home-set/D:href)');
        if ($home === '') {
            throw new CalDavException('This account has no calendars.');
        }

        return ['home' => $this->absolute($home, $principal), 'name' => $xpath->evaluate('string(//D:displayname)') ?: null];
    }

    /**
     * Event calendars of a home, in one request (ctag included: it tells which ones changed).
     * @return array<int, array{url: string, name: string, color: ?string, ctag: string, writable: bool, owner: ?string}>
     */
    public function calendars(string $home): array
    {
        $xpath = $this->propfind($home, '<D:resourcetype/><D:displayname/><CS:getctag/><D:sync-token/><I:calendar-color/>'
            . '<D:current-user-privilege-set/><C:supported-calendar-component-set/><CS:invite/>', 1);

        $calendars = [];
        foreach ($xpath->query('//D:response') as $response) {
            $prop = static fn (string $path): string => $xpath->evaluate("string(D:propstat[contains(D:status, ' 200 ')]/D:prop/{$path})", $response);
            $isCalendar = $xpath->query("D:propstat/D:prop/D:resourcetype/C:calendar", $response)->length > 0;
            $components = $xpath->query("D:propstat/D:prop/C:supported-calendar-component-set/C:comp[@name='VEVENT']", $response)->length;
            $declared = $xpath->query('D:propstat/D:prop/C:supported-calendar-component-set/C:comp', $response)->length;
            if (!$isCalendar || ($declared > 0 && $components === 0)) {
                continue; // reminders (VTODO only), inbox, outbox, notifications…
            }
            $privileges = $xpath->query("D:propstat/D:prop/D:current-user-privilege-set/D:privilege/*", $response);
            $writable = false;
            foreach ($privileges as $privilege) {
                $writable = $writable || in_array($privilege->localName, ['write', 'write-content', 'all'], true);
            }
            $color = $prop('I:calendar-color');

            $calendars[] = [
                'url' => $this->absolute($xpath->evaluate('string(D:href)', $response), $home),
                'name' => $prop('D:displayname') ?: __('Calendar'),
                'color' => preg_match('/^#[0-9A-Fa-f]{6}/', $color) ? substr($color, 0, 7) : null,
                'ctag' => $prop('CS:getctag') ?: $prop('D:sync-token'),
                'writable' => $privileges->length === 0 || $writable,
                // shared with me: the sharer's name (iCloud / calendarserver-sharing)
                'owner' => $prop('CS:invite/CS:organizer/CS:common-name') ?: null,
            ];
        }

        return $calendars;
    }

    /**
     * Events between two moments, recurring ones expanded by the server (one VEVENT per occurrence).
     * @return array<int, array{url: string, etag: string, ics: string}>
     */
    public function events(string $calendar, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $utc = new DateTimeZone('UTC');
        $range = 'start="' . $from->setTimezone($utc)->format('Ymd\THis\Z') . '" end="' . $to->setTimezone($utc)->format('Ymd\THis\Z') . '"';
        $body = '<?xml version="1.0" encoding="utf-8"?><C:calendar-query ' . self::xmlns() . '>'
            . '<D:prop><D:getetag/><C:calendar-data><C:expand ' . $range . '/></C:calendar-data></D:prop>'
            . '<C:filter><C:comp-filter name="VCALENDAR"><C:comp-filter name="VEVENT"><C:time-range ' . $range . '/></C:comp-filter></C:comp-filter></C:filter>'
            . '</C:calendar-query>';
        $xpath = $this->xml($this->request('REPORT', $calendar, $body, ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'])['body']);

        $events = [];
        foreach ($xpath->query('//D:response') as $response) {
            $ics = $xpath->evaluate('string(D:propstat/D:prop/C:calendar-data)', $response);
            if ($ics !== '') {
                $events[] = [
                    'url' => $this->absolute($xpath->evaluate('string(D:href)', $response), $calendar),
                    'etag' => $xpath->evaluate('string(D:propstat/D:prop/D:getetag)', $response),
                    'ics' => $ics,
                ];
            }
        }

        return $events;
    }

    /** @return array{ics: string, etag: string} the stored object, as it is now */
    public function get(string $url): array
    {
        $response = $this->request('GET', $url);

        return ['ics' => $response['body'], 'etag' => $response['headers']['etag'] ?? ''];
    }

    /** Creates ($etag null) or replaces (only if still at $etag) an object. Returns the new ETag, '' if the server didn't say. */
    public function put(string $url, string $ics, ?string $etag): string
    {
        $headers = ['Content-Type: text/calendar; charset=utf-8', $etag === null ? 'If-None-Match: *' : 'If-Match: ' . $etag];

        return $this->request('PUT', $url, $ics, $headers)['headers']['etag'] ?? '';
    }

    public function delete(string $url, ?string $etag = null): void
    {
        $this->request('DELETE', $url, null, $etag ? ['If-Match: ' . $etag] : []);
    }

    /** RFC 8607: the server stores files attached to events (iCloud does). */
    public function supportsAttachments(string $calendar): bool
    {
        $dav = $this->request('OPTIONS', $calendar)['headers']['dav'] ?? '';

        return str_contains($dav, 'calendar-managed-attachments');
    }

    /** Attaches a file to an event; returns the new ETag of the event. */
    public function attach(string $event, string $etag, string $name, string $mime, string $data): string
    {
        $headers = [
            'Content-Type: ' . $mime,
            'Content-Disposition: attachment; filename="' . addcslashes($name, '"\\') . '"',
            'If-Match: ' . $etag,
        ];

        return $this->request('POST', $event . '?action=attachment-add', $data, $headers)['headers']['etag'] ?? '';
    }

    /**
     * Moves an object to another calendar of the same account (WebDAV MOVE). The UID stays the same,
     * which is why a copy + delete doesn't work: iCloud refuses two objects with one UID.
     */
    public function move(string $url, string $destination, ?string $etag): void
    {
        $this->request('MOVE', $url, null, ['Destination: ' . $destination, 'Overwrite: F', ...($etag ? ['If-Match: ' . $etag] : [])]);
    }

    /** @return array{body: string, type: string} an attachment's content (its URL needs the account's credentials) */
    public function download(string $url): array
    {
        // the URL comes from the event: only follow it to the calendar's own domain (e.g. gateway.icloud.com for
        // caldav.icloud.com), never elsewhere with the account's credentials
        $domain = static fn (string $u): string => implode('.', array_slice(explode('.', strtolower((string) parse_url($u, PHP_URL_HOST))), -2));
        if (!preg_match('#^https?://#i', $url) || $domain($url) !== $domain($this->url)) {
            throw new CalDavException('This attachment is not on the calendar server.', 404);
        }
        $response = $this->request('GET', $url);

        return ['body' => $response['body'], 'type' => $response['headers']['content-type'] ?? 'application/octet-stream'];
    }

    private function propfind(string $url, string $props, int $depth): DOMXPath
    {
        $body = '<?xml version="1.0" encoding="utf-8"?><D:propfind ' . self::xmlns() . '><D:prop>' . $props . '</D:prop></D:propfind>';

        return $this->xml($this->request('PROPFIND', $url, $body, ['Depth: ' . $depth, 'Content-Type: application/xml; charset=utf-8'])['body']);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    private function request(string $method, string $url, ?string $body = null, array $headers = []): array
    {
        $responseHeaders = [];
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->user . ':' . $this->password,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC | CURLAUTH_DIGEST,
            CURLOPT_HTTPHEADER => [...$headers, 'User-Agent: ' . env('APP_NAME', 'Kalban') . '/1.0'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $result = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);

        if ($result === false) {
            throw new CalDavException('Connection failed: ' . $error);
        }
        if ($status >= 300) {
            $retry = isset($responseHeaders['retry-after']) ? (int) $responseHeaders['retry-after'] : null;
            throw new CalDavException("{$method} answered HTTP {$status}", $status, $retry);
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => (string) $result];
    }

    private function xml(string $body): DOMXPath
    {
        $doc = new DOMDocument();
        if ($body === '' || !@$doc->loadXML($body, LIBXML_NONET)) {
            throw new CalDavException('The server did not answer with valid XML.');
        }
        $xpath = new DOMXPath($doc);
        foreach (self::NS as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        return $xpath;
    }

    private static function xmlns(): string
    {
        return implode(' ', array_map(static fn (string $p, string $u): string => "xmlns:{$p}=\"{$u}\"", array_keys(self::NS), self::NS));
    }

    /** hrefs can be paths ("/123/calendars/") or full URLs on another host (iCloud: p104-caldav.icloud.com). */
    private function absolute(string $href, string $base): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $origin = preg_replace('#^(https?://[^/]+).*$#i', '$1', $base);

        return $origin . '/' . ltrim($href, '/');
    }
}
