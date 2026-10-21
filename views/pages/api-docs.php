<?php
/**
 * API documentation drawn from the OpenAPI document, laid out like Swagger: operations grouped
 * by tag, each one opens with its parameters, body, answers, a curl example and "Try it".
 * @var array $doc
 */
use App\Services\ApiSpec;

$base = $doc['servers'][0]['url'];
$pretty = static fn (mixed $v): string => (string) json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$byTag = [];
foreach ($doc['paths'] as $path => $operations) {
    foreach ($operations as $method => $op) {
        $byTag[$op['tags'][0]][] = [$method, $path, $op];
    }
} ?>
<div class="docs">
    <header class="docs-head">
        <div>
            <h1><?= e($doc['info']['title']) ?> <span class="docs-version">v<?= e($doc['info']['version']) ?> · OpenAPI <?= e($doc['openapi']) ?></span></h1>
            <p class="mono docs-base"><?= e($base) ?></p>
        </div>
        <a class="btn btn-secondary" href="<?= e(url('/api/v1/openapi.json')) ?>" download="kalban-openapi.json"><?= component('icon', ['name' => 'download', 'size' => 14]) ?> openapi.json</a>
    </header>

    <section class="panel docs-intro">
        <?php foreach (explode("\n\n", $doc['info']['description']) as $paragraph): ?>
            <p><?= e($paragraph) ?></p>
        <?php endforeach ?>
        <label class="form-label" for="docs-token"><?= e(__('Token for "Try it"')) ?></label>
        <input class="form-control mono" id="docs-token" type="password" autocomplete="off" placeholder="kalban_…">
        <p class="form-text"><?= e(__('It stays in this page only: it is not saved anywhere. "Try it" sends real requests to your boards.')) ?></p>
    </section>

    <?php foreach ($doc['tags'] as $tag): if (empty($byTag[$tag['name']])) continue; ?>
        <section class="docs-tag">
            <h2><?= e($tag['name']) ?> <small><?= e($tag['description']) ?></small></h2>
            <?php foreach ($byTag[$tag['name']] as [$method, $path, $op]):
                $id = strtolower($method) . '-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($path)), '-');
                $params = $op['parameters'] ?? [];
                $bodyType = isset($op['requestBody']) ? array_key_first($op['requestBody']['content']) : null;
                $bodyExample = $bodyType && $bodyType !== 'multipart/form-data' ? ApiSpec::example($op['requestBody']['content'][$bodyType]['schema'], $doc) : null;
                $examplePath = preg_replace_callback('/\{(\w+)\}/', static function (array $m) use ($params): string {
                    foreach ($params as $p) { if ($p['name'] === $m[1]) return (string) $p['example']; }
                    return $m[0];
                }, $path);
                $curl = 'curl -X ' . strtoupper($method) . " '" . $base . $examplePath . "' \\\n  -H 'Authorization: Bearer kalban_…'"
                    . ($bodyType === 'multipart/form-data' ? " \\\n  -F 'file=@preventivo.pdf'" : '')
                    . ($bodyExample !== null ? " \\\n  -H 'Content-Type: application/json' \\\n  -d '" . json_encode($bodyExample, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "'" : ''); ?>
                <details class="op op-<?= e($method) ?>" id="<?= e($id) ?>">
                    <summary>
                        <span class="op-method"><?= e(strtoupper($method)) ?></span>
                        <code class="op-path"><?= e($path) ?></code>
                        <span class="op-summary"><?= e($op['summary']) ?></span>
                        <span class="op-scope is-<?= e($op['x-scope']) ?>"><?= e($op['x-scope'] === 'write' ? __('read and change') : __('read')) ?></span>
                    </summary>
                    <div class="op-body">
                        <?php if (!empty($op['description'])): ?><p><?= e($op['description']) ?></p><?php endif ?>

                        <?php if ($params): ?>
                            <h3><?= e(__('Parameters')) ?></h3>
                            <table class="table table-sm docs-table">
                                <thead><tr><th><?= e(__('Name')) ?></th><th><?= e(__('In')) ?></th><th><?= e(__('Type')) ?></th><th><?= e(__('Description')) ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($params as $p): ?>
                                    <tr><td class="mono"><?= e($p['name']) ?><?= !empty($p['required']) ? ' *' : '' ?></td><td><?= e($p['in']) ?></td><td class="mono"><?= e($p['schema']['type']) ?></td><td><?= e($p['description'] ?? '') ?></td></tr>
                                <?php endforeach ?>
                                </tbody>
                            </table>
                        <?php endif ?>

                        <?php if ($bodyType): ?>
                            <h3><?= e(__('Request body')) ?> <code><?= e($bodyType) ?></code></h3>
                            <?php if ($bodyExample !== null): ?><pre class="docs-code"><?= e($pretty($bodyExample)) ?></pre><?php else: ?><p class="mono">file: <?= e(__('the file to attach')) ?></p><?php endif ?>
                        <?php endif ?>

                        <h3><?= e(__('Responses')) ?></h3>
                        <ul class="docs-responses">
                            <?php foreach ($op['responses'] as $code => $response):
                                $response = isset($response['$ref']) ? $doc['components']['responses'][basename($response['$ref'])] : $response;
                                $json = $response['content']['application/json']['schema'] ?? null; ?>
                                <li>
                                    <span class="docs-status is-<?= e(substr((string) $code, 0, 1)) ?>xx"><?= e($code) ?></span> <?= e($response['description']) ?>
                                    <?php if ($json): ?><pre class="docs-code"><?= e($pretty(ApiSpec::example($json, $doc))) ?></pre><?php endif ?>
                                </li>
                            <?php endforeach ?>
                        </ul>

                        <h3>curl</h3>
                        <pre class="docs-code"><?= e($curl) ?></pre>

                        <form class="docs-try" data-try data-method="<?= e(strtoupper($method)) ?>" data-path="<?= e($base . $path) ?>" data-body="<?= e((string) $bodyType) ?>">
                            <h3><?= e(__('Try it')) ?></h3>
                            <?php foreach ($params as $p): ?>
                                <label class="docs-try-field"><span class="mono"><?= e($p['name']) ?></span>
                                    <input class="form-control form-control-sm" name="<?= e($p['in'] . ':' . $p['name']) ?>" value="<?= $p['in'] === 'path' ? e((string) $p['example']) : '' ?>">
                                </label>
                            <?php endforeach ?>
                            <?php if ($bodyType === 'multipart/form-data'): ?>
                                <input class="form-control form-control-sm mb-2" type="file" name="file">
                            <?php elseif ($bodyType): ?>
                                <textarea class="form-control form-control-sm mono mb-2" name="body" rows="5"><?= e($pretty($bodyExample)) ?></textarea>
                            <?php endif ?>
                            <button class="btn btn-sm <?= $method === 'delete' ? 'btn-danger' : 'btn-primary' ?>" type="submit"><?= e(__('Send')) ?></button>
                            <pre class="docs-code docs-result" hidden></pre>
                        </form>
                    </div>
                </details>
            <?php endforeach ?>
        </section>
    <?php endforeach ?>

    <section class="docs-tag">
        <h2><?= e(__('Schemas')) ?></h2>
        <?php foreach ($doc['components']['schemas'] as $name => $schema): ?>
            <details class="op op-schema">
                <summary><code class="op-path"><?= e($name) ?></code></summary>
                <div class="op-body"><pre class="docs-code"><?= e($pretty(ApiSpec::example($schema, $doc))) ?></pre></div>
            </details>
        <?php endforeach ?>
    </section>
</div>
<script src="<?= asset('assets/js/api-docs.js') ?>"></script>
