<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;

/**
 * Base of the API controllers: answers {"data": …} with the right status. Access to boards,
 * lists and cards reuses the checks of the site (member / owner); the rules are in the services.
 */
abstract class ApiController extends Controller
{
    protected function data(mixed $data, int $status = 200): Response
    {
        return Response::json(['data' => $data], $status);
    }

    protected function created(mixed $data): Response
    {
        return $this->data($data, 201);
    }

    protected function noContent(): Response
    {
        return Response::noContent();
    }

    /** Only the fields named, as sent in the JSON (or form) body. */
    protected function only(Request $request, array $fields): array
    {
        return array_intersect_key($request->all(), array_flip($fields));
    }

    /** An optional non-negative integer field (position). */
    protected function position(array $input): ?int
    {
        if (!array_key_exists('position', $input) || $input['position'] === null) {
            return null;
        }
        $position = filter_var($input['position'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($position === false) {
            throw ValidationException::field('position', __('The position must be a number from 0.'));
        }

        return $position;
    }

    /** An optional boolean field (archived, favorite). */
    protected function flag(array $input, string $field): ?bool
    {
        if (!array_key_exists($field, $input)) {
            return null;
        }
        $value = filter_var($input[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === null) {
            throw ValidationException::field($field, __('Use true or false.'));
        }

        return $value;
    }
}
