<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The API described as OpenAPI 3.1: served as JSON at /api/v1/openapi.json (Postman, Insomnia,
 * code generators…) and drawn as a page at /api/docs. Keep it next to routes/web.php when the
 * API changes. "x-scope" says which token can call each operation.
 */
final class ApiSpec
{
    public static function document(): array
    {
        $app = (string) env('APP_NAME', 'Kalban');

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $app . ' API',
                'version' => '1.0.0',
                'description' => implode("\n\n", [
                    __('Read and change your boards, lists and cards. The calendar is not part of the API.'),
                    __('Authentication: turn on API access in Settings, generate a token and send it in every request as "Authorization: Bearer kalban_…". A read-only token can call the GET operations; a read and change token all of them. A token lasts :days days.', ['days' => \App\Models\ApiToken::days()]),
                    __('A token can do what its user can do on the site: it sees only the boards they are a member of, and only board owners delete for good. Changes appear in the card log as "Name (API)".'),
                    __('Requests and answers are JSON (attachments: multipart/form-data). Successful answers are {"data": …}; errors follow RFC 9457 (application/problem+json) with the field errors in "errors". Limit: :n requests a minute per token, then 429 with Retry-After.', ['n' => \App\Core\Throttle::limit('API_PER_MINUTE', 60)]),
                ]),
            ],
            'servers' => [['url' => absolute_url('/api/v1')]],
            'security' => [['bearer' => []]],
            'tags' => [
                ['name' => 'Account', 'description' => __('Who the token belongs to, and their labels.')],
                ['name' => 'Boards', 'description' => __('Boards the user is a member of.')],
                ['name' => 'Lists', 'description' => __('Lists of a board, left to right.')],
                ['name' => 'Cards', 'description' => __('Cards of a list, top to bottom, with labels and log.')],
                ['name' => 'Attachments', 'description' => __('Files attached to cards.')],
            ],
            'paths' => self::paths(),
            'components' => [
                'securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'kalban_…', 'description' => __('Personal token from Settings › API access.')]],
                'schemas' => self::schemas(),
                'responses' => self::responses(),
            ],
        ];
    }

    /** An example value for a schema of $doc: its "example", or one built from its properties. */
    public static function example(array $schema, array $doc, int $depth = 0): mixed
    {
        if ($depth > 6) {
            return null;
        }
        if (isset($schema['$ref'])) {
            [, , $section, $name] = explode('/', $schema['$ref']);
            return self::example($doc['components'][$section][$name] ?? [], $doc, $depth + 1);
        }
        if (isset($schema['allOf'])) {
            $merged = [];
            foreach ($schema['allOf'] as $part) {
                $merged = array_merge($merged, (array) self::example($part, $doc, $depth + 1));
            }
            return $merged;
        }
        if (array_key_exists('example', $schema)) {
            return $schema['example'];
        }
        $type = is_array($schema['type'] ?? null) ? $schema['type'][0] : ($schema['type'] ?? null);
        if ($type === 'object' || isset($schema['properties'])) {
            $out = [];
            foreach ($schema['properties'] ?? [] as $name => $property) {
                $out[$name] = self::example($property, $doc, $depth + 1);
            }
            return $out;
        }
        if ($type === 'array') {
            return [self::example($schema['items'] ?? [], $doc, $depth + 1)];
        }

        return null;
    }

    private static function paths(): array
    {
        $board = self::param('uuid', __('Board id (uuid).'), 'string', '94113c91-9f25-4426-8eb5-c40aa958a90a');
        $list = self::param('id', __('List id.'), 'integer', 2);
        $card = self::param('id', __('Card id.'), 'integer', 12);
        $archived = ['name' => 'archived', 'in' => 'query', 'required' => false, 'description' => __('true: the archived ones instead of the active ones.'), 'schema' => ['type' => 'boolean', 'default' => false]];

        return [
            '/me' => ['get' => self::op('Account', 'read', __('The user and the token'), '', [], null, ['200' => self::ok('Me')])],
            '/labels' => ['get' => self::op('Account', 'read', __('The user\'s labels'), __('The labels the user can put on cards. Labels of other members are visible on the cards, but each one uses their own.'), [], null, ['200' => self::ok(['type' => 'array', 'items' => self::ref('Label')])])],
            '/boards' => [
                'get' => self::op('Boards', 'read', __('List boards'), '', [$archived], null, ['200' => self::ok(['type' => 'array', 'items' => self::ref('Board')])]),
                'post' => self::op('Boards', 'write', __('Create a board'), __('The user becomes its owner.'), [], 'BoardCreate', ['201' => self::ok('Board', __('Created'))]),
            ],
            '/boards/{uuid}' => [
                'get' => self::op('Boards', 'read', __('A board with members, lists and cards'), __('Only the active lists and cards; archived boards can be read too.'), [$board], null, ['200' => self::ok('BoardDetail')]),
                'patch' => self::op('Boards', 'write', __('Change a board'), __('Rename it, change background, mark it as favourite. "archived": true archives it, false restores it (owner only).'), [$board], 'BoardUpdate', ['200' => self::ok('Board')]),
                'delete' => self::op('Boards', 'write', __('Delete a board for good'), __('Owner only, and only once archived: lists, cards and attachments go too.'), [$board], null, ['204' => ['description' => __('Deleted')]]),
            ],
            '/boards/{uuid}/lists' => [
                'get' => self::op('Lists', 'read', __('Lists of a board'), '', [$board, $archived], null, ['200' => self::ok(['type' => 'array', 'items' => self::ref('List')])]),
                'post' => self::op('Lists', 'write', __('Create a list'), __('At the end, or at "position" (0 = first).'), [$board], 'ListCreate', ['201' => self::ok('List', __('Created'))]),
            ],
            '/lists/{id}' => [
                'patch' => self::op('Lists', 'write', __('Change a list'), __('Rename it, move it to "position", archive it or restore it.'), [$list], 'ListUpdate', ['200' => self::ok('List')]),
                'delete' => self::op('Lists', 'write', __('Delete a list for good'), __('Board owner only: its cards and attachments go too.'), [$list], null, ['204' => ['description' => __('Deleted')]]),
            ],
            '/lists/{id}/cards' => [
                'get' => self::op('Cards', 'read', __('Cards of a list'), '', [$list, $archived], null, ['200' => self::ok(['type' => 'array', 'items' => self::ref('Card')])]),
                'post' => self::op('Cards', 'write', __('Create a card'), __('Only "title" is required. At the bottom, or at "position" (0 = top).'), [$list], 'CardCreate', ['201' => self::ok('Card', __('Created'))]),
            ],
            '/cards/{id}' => [
                'get' => self::op('Cards', 'read', __('A card with attachments and log'), '', [$card], null, ['200' => self::ok('CardDetail')]),
                'patch' => self::op('Cards', 'write', __('Change a card'), __('Only the fields sent change. "list_id" alone moves it to the bottom of that list; with "position" to that place. "archived": true / false archives or restores it. Every change goes to the card log.'), [$card], 'CardUpdate', ['200' => self::ok('Card')]),
                'delete' => self::op('Cards', 'write', __('Delete a card for good'), __('Board owner only.'), [$card], null, ['204' => ['description' => __('Deleted')]]),
            ],
            '/cards/{id}/labels/{label}' => [
                'put' => self::op('Cards', 'write', __('Put a label on a card'), __('One of the user\'s own labels (GET /labels). Doing it twice changes nothing.'), [$card, self::param('label', __('Label id.'), 'integer', 3)], null, ['200' => self::ok('Card')]),
                'delete' => self::op('Cards', 'write', __('Remove a label from a card'), '', [$card, self::param('label', __('Label id.'), 'integer', 3)], null, ['200' => self::ok('Card')]),
            ],
            '/cards/{id}/activity' => ['get' => self::op('Cards', 'read', __('Change log of a card'), __('Newest first.'), [$card], null, ['200' => self::ok(['type' => 'array', 'items' => self::ref('Activity')])])],
            '/cards/{id}/attachments' => [
                'get' => self::op('Attachments', 'read', __('Attachments of a card'), '', [$card], null, ['200' => self::ok(['type' => 'array', 'items' => self::ref('Attachment')])]),
                'post' => self::op('Attachments', 'write', __('Attach a file'), __('multipart/form-data with a "file" field, up to :size.', ['size' => format_bytes(AttachmentService::maxBytes())]), [$card], 'Upload', ['201' => self::ok('Attachment', __('Created'))]),
            ],
            '/attachments/{id}/download' => ['get' => self::op('Attachments', 'read', __('Download an attachment'), __('The file itself, as a download.'), [self::param('id', __('Attachment id.'), 'integer', 5)], null, ['200' => ['description' => __('The file'), 'content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]]]])],
            '/attachments/{id}' => ['delete' => self::op('Attachments', 'write', __('Delete an attachment'), '', [self::param('id', __('Attachment id.'), 'integer', 5)], null, ['204' => ['description' => __('Deleted')]])],
        ];
    }

    private static function schemas(): array
    {
        $time = ['type' => 'string', 'format' => 'date-time', 'example' => '2026-10-21T15:12:04+02:00'];
        $date = ['type' => ['string', 'null'], 'format' => 'date', 'example' => '2026-10-22'];
        $colors = array_keys(Palette::COLORS);

        return [
            'Me' => self::object([
                'id' => ['type' => 'integer', 'example' => 5], 'name' => ['type' => 'string', 'example' => 'Fede'], 'email' => ['type' => 'string', 'example' => 'fede@example.com'],
                'token' => self::object(['scope' => ['type' => 'string', 'enum' => ['read', 'write'], 'example' => 'write'], 'expires_at' => $time]),
            ]),
            'Member' => self::object([
                'id' => ['type' => 'integer', 'example' => 5], 'name' => ['type' => 'string', 'example' => 'Fede'], 'email' => ['type' => 'string', 'example' => 'fede@example.com'],
                'role' => ['type' => 'string', 'enum' => ['owner', 'member'], 'example' => 'owner'],
            ]),
            'Board' => self::object([
                'id' => ['type' => 'string', 'format' => 'uuid', 'example' => '94113c91-9f25-4426-8eb5-c40aa958a90a'],
                'name' => ['type' => 'string', 'example' => 'Progetto Alpha'],
                'background' => ['type' => 'object', 'description' => __('{"type": "color", "color", "hex"} or {"type": "photo", "url", "author", "author_url"}'), 'example' => ['type' => 'color', 'color' => 'green', 'hex' => Palette::primary('green')]],
                'role' => ['type' => 'string', 'enum' => ['owner', 'member'], 'example' => 'owner'],
                'favorite' => ['type' => 'boolean', 'example' => true],
                'archived' => ['type' => 'boolean', 'example' => false],
                'url' => ['type' => 'string', 'format' => 'uri', 'description' => __('The board on the site.'), 'example' => absolute_url('/boards/94113c91-9f25-4426-8eb5-c40aa958a90a')],
                'created_at' => $time, 'updated_at' => $time,
            ]),
            'BoardDetail' => ['allOf' => [self::ref('Board'), self::object([
                'members' => ['type' => 'array', 'items' => self::ref('Member')],
                'lists' => ['type' => 'array', 'items' => ['allOf' => [self::ref('List'), self::object(['cards' => ['type' => 'array', 'items' => self::ref('Card')]])]]],
            ])]],
            'List' => self::object([
                'id' => ['type' => 'integer', 'example' => 2], 'board_id' => ['type' => 'string', 'format' => 'uuid', 'example' => '94113c91-9f25-4426-8eb5-c40aa958a90a'],
                'name' => ['type' => 'string', 'example' => 'In corso'], 'position' => ['type' => 'integer', 'example' => 0],
                'archived' => ['type' => 'boolean', 'example' => false], 'created_at' => $time, 'updated_at' => $time,
            ]),
            'Card' => self::object([
                'id' => ['type' => 'integer', 'example' => 12], 'board_id' => ['type' => 'string', 'format' => 'uuid', 'example' => '94113c91-9f25-4426-8eb5-c40aa958a90a'],
                'list_id' => ['type' => 'integer', 'example' => 2], 'title' => ['type' => 'string', 'example' => 'Preparare la presentazione'],
                'description' => ['type' => ['string', 'null'], 'example' => 'Slide e demo'], 'start_date' => $date, 'end_date' => $date,
                'position' => ['type' => 'integer', 'example' => 0], 'labels' => ['type' => 'array', 'items' => self::ref('Label')],
                'attachment_count' => ['type' => 'integer', 'example' => 1], 'archived' => ['type' => 'boolean', 'example' => false],
                'created_at' => $time, 'updated_at' => $time,
            ]),
            'CardDetail' => ['allOf' => [self::ref('Card'), self::object([
                'attachments' => ['type' => 'array', 'items' => self::ref('Attachment')],
                'activity' => ['type' => 'array', 'items' => self::ref('Activity')],
            ])]],
            'Label' => self::object([
                'id' => ['type' => 'integer', 'example' => 3], 'name' => ['type' => 'string', 'example' => 'Design'],
                'color' => ['type' => 'string', 'enum' => $colors, 'example' => 'green'], 'hex' => ['type' => 'string', 'example' => Palette::secondary('green')],
                'mine' => ['type' => 'boolean', 'description' => __('One of the user\'s labels (they can put it on or take it off).'), 'example' => true],
            ]),
            'Attachment' => self::object([
                'id' => ['type' => 'integer', 'example' => 5], 'name' => ['type' => 'string', 'example' => 'preventivo.pdf'],
                'size' => ['type' => 'integer', 'example' => 48213], 'mime' => ['type' => 'string', 'example' => 'application/pdf'],
                'download_url' => ['type' => 'string', 'format' => 'uri', 'example' => absolute_url('/api/v1/attachments/5/download')], 'created_at' => $time,
            ]),
            'Activity' => self::object([
                'id' => ['type' => 'integer', 'example' => 40], 'user' => ['type' => 'string', 'example' => 'Fede (API)'],
                'field' => ['type' => 'string', 'enum' => array_keys(\App\Models\Activity::fields()), 'example' => 'title'],
                'old_value' => ['type' => ['string', 'null'], 'example' => 'Bozza'], 'new_value' => ['type' => ['string', 'null'], 'example' => 'Preparare la presentazione'],
                'created_at' => $time,
            ]),
            'BoardCreate' => self::object([
                'name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Progetto Alpha'],
                'background' => ['type' => 'string', 'description' => __('"color:{name}" (:colors) or "photo:{Unsplash id}". Default: the user\'s colour.', ['colors' => implode(', ', $colors)]), 'example' => 'color:green'],
            ], ['name']),
            'BoardUpdate' => self::object([
                'name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Progetto Beta'], 'background' => ['type' => 'string', 'example' => 'color:indigo'],
                'favorite' => ['type' => 'boolean', 'example' => true], 'archived' => ['type' => 'boolean', 'description' => __('Owner only.'), 'example' => false],
            ]),
            'ListCreate' => self::object(['name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Da fare'], 'position' => ['type' => 'integer', 'minimum' => 0, 'example' => 0]], ['name']),
            'ListUpdate' => self::object(['name' => ['type' => 'string', 'example' => 'Fatto'], 'position' => ['type' => 'integer', 'minimum' => 0, 'example' => 1], 'archived' => ['type' => 'boolean', 'example' => false]]),
            'CardCreate' => self::object([
                'title' => ['type' => 'string', 'maxLength' => 255, 'example' => 'Telefonare al fornitore'], 'description' => ['type' => 'string', 'example' => 'Chiedere i tempi di consegna'],
                'start_date' => $date, 'end_date' => $date, 'position' => ['type' => 'integer', 'minimum' => 0, 'example' => 0],
            ], ['title']),
            'CardUpdate' => self::object([
                'title' => ['type' => 'string', 'example' => 'Telefonare al fornitore (fatto)'], 'description' => ['type' => ['string', 'null'], 'example' => null],
                'start_date' => $date, 'end_date' => $date, 'list_id' => ['type' => 'integer', 'example' => 3],
                'position' => ['type' => 'integer', 'minimum' => 0, 'example' => 0], 'archived' => ['type' => 'boolean', 'example' => false],
            ]),
            'Upload' => self::object(['file' => ['type' => 'string', 'format' => 'binary']], ['file']),
            'Problem' => self::object([
                'type' => ['type' => 'string', 'example' => 'about:blank'], 'title' => ['type' => 'string', 'example' => 'Unauthorized'],
                'status' => ['type' => 'integer', 'example' => 401], 'detail' => ['type' => 'string', 'example' => __('Send a valid API token in the header "Authorization: Bearer …".')],
                'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'string'], 'description' => __('Only for 422: field → message.'), 'example' => ['title' => __('Give the card a name.')]],
            ]),
        ];
    }

    private static function responses(): array
    {
        $problem = static fn (string $description): array => ['description' => $description, 'content' => ['application/problem+json' => ['schema' => self::ref('Problem')]]];

        return [
            'Unauthorized' => $problem(__('No token, unknown, expired, or API access turned off by the user.')),
            'Forbidden' => $problem(__('Read-only token, or an action for the board owner only.')),
            'NotFound' => $problem(__('It does not exist, or the user is not a member of its board.')),
            'Conflict' => $problem(__('Not possible in this state (for example: the board is archived).')),
            'Invalid' => $problem(__('Invalid fields: see "errors".')),
            'TooMany' => $problem(__('Too many requests: wait as many seconds as Retry-After says.')),
        ];
    }

    /** One operation, with the errors every call can get. */
    private static function op(string $tag, string $scope, string $summary, string $description, array $parameters, ?string $body, array $responses): array
    {
        $op = ['tags' => [$tag], 'summary' => $summary, 'x-scope' => $scope];
        if ($description !== '') {
            $op['description'] = $description;
        }
        if ($parameters) {
            $op['parameters'] = $parameters;
        }
        if ($body !== null) {
            $type = $body === 'Upload' ? 'multipart/form-data' : 'application/json';
            $op['requestBody'] = ['required' => true, 'content' => [$type => ['schema' => self::ref($body)]]];
        }
        $op['responses'] = $responses + [
            '401' => self::ref('Unauthorized', 'responses'), '403' => self::ref('Forbidden', 'responses'), '404' => self::ref('NotFound', 'responses'),
        ] + ($scope === 'write' ? ['409' => self::ref('Conflict', 'responses'), '422' => self::ref('Invalid', 'responses')] : [])
            + ['429' => self::ref('TooMany', 'responses')];

        return $op;
    }

    private static function ok(string|array $schema, string $description = 'OK'): array
    {
        return ['description' => $description, 'content' => ['application/json' => ['schema' => self::object(['data' => is_string($schema) ? self::ref($schema) : $schema])]]];
    }

    private static function param(string $name, string $description, string $type, mixed $example): array
    {
        return ['name' => $name, 'in' => 'path', 'required' => true, 'description' => $description, 'schema' => ['type' => $type], 'example' => $example];
    }

    private static function object(array $properties, array $required = []): array
    {
        return ['type' => 'object', 'properties' => $properties] + ($required ? ['required' => $required] : []);
    }

    private static function ref(string $name, string $section = 'schemas'): array
    {
        return ['$ref' => "#/components/{$section}/{$name}"];
    }
}
