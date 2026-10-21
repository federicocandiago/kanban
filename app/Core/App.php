<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    public function run(): void
    {
        $request = null;

        try {
            $request = Request::capture();
            if (!$request->isApi()) {
                Session::start();
            }
            Translator::boot();

            $router = new Router();
            (require BASE_PATH . '/routes/web.php')($router);

            $response = $router->dispatch($request);
        } catch (DatabaseException $e) {
            $this->log($e);
            $response = $this->error($request, 503, __('Database unavailable'), $e->getMessage(), 'pages/errors/database');
        } catch (ValidationException $e) {
            $response = $request?->isApi()
                ? Response::problem(422, __('Please check the request.'), count($e->errors) === 1 ? (string) array_values($e->errors)[0] : __('Some fields are not valid: see "errors".'), $e->errors)
                : Response::json(['ok' => false, 'message' => $e->getMessage(), 'errors' => $e->errors], 422);
        } catch (HttpException $e) {
            $response = $this->error($request, $e->status, $this->title($e->status), $e->getMessage());
        } catch (\PDOException $e) {
            $this->log($e);
            $response = $this->schemaOutdated($e) && $this->autoMigrate()
                ? $this->retry($request)
                : $this->error($request, 500, $this->title(500), env('APP_DEBUG') ? $e->getMessage() : '');
        } catch (\Throwable $e) {
            $this->log($e);
            $detail = env('APP_DEBUG') ? get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : '';
            $response = $this->error($request, 500, $this->title(500), $detail);
        }

        $response->send();
    }

    /** 42S02 / 42S22: a table or a column is missing → the database is behind the code. */
    private function schemaOutdated(\PDOException $e): bool
    {
        return in_array($e->getCode(), ['42S02', '42S22'], true);
    }

    /**
     * First install or new migration files: apply them and create the admin from .env.
     * Returns false when there was nothing to run, so a broken schema never causes a redirect loop.
     */
    private function autoMigrate(): bool
    {
        try {
            return Migrator::install() !== [];
        } catch (\Throwable $e) {
            $this->log($e);
            return false;
        }
    }

    /** After migrating, run the request again (pages are reloaded; fetch calls are asked to retry). */
    private function retry(?Request $request): Response
    {
        if ($request?->isApi()) {
            return Response::problem(503, __('Service Unavailable'), __('The database has just been set up, please try again.'));
        }
        if ($request === null || $request->method !== 'GET' || $request->wantsJson()) {
            return Response::json(['ok' => false, 'message' => __('The database has just been set up, please try again.')], 503);
        }
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');

        return Response::redirect($request->path . ($query !== '' ? '?' . $query : ''));
    }

    private function error(?Request $request, int $status, string $title, string $detail, string $view = 'pages/errors/error'): Response
    {
        if ($request?->isApi()) {
            return Response::problem($status, $title, $detail);
        }
        if ($request?->wantsJson()) {
            return Response::json(['ok' => false, 'message' => $detail ?: $title], $status);
        }

        return Response::html(View::render($view, compact('status', 'title', 'detail'), 'layouts/minimal'), $status);
    }

    private function title(int $status): string
    {
        return match ($status) {
            401 => __('Unauthorized'),
            403 => __('Access denied'),
            429 => __('Too Many Requests'),
            404 => __('Page not found'),
            405 => __('Method not allowed'),
            419 => __('Session expired'),
            default => __('Something went wrong'),
        };
    }

    private function log(\Throwable $e): void
    {
        $line = sprintf("[%s] %s: %s in %s:%d\n", date('c'), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
        @file_put_contents(storage_path('logs/app.log'), $line, FILE_APPEND);
    }
}
