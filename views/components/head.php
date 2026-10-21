<?php /** @var string|null $title @var string|null $expect id of an element the first paint waits for (view transitions) */
use App\Services\Palette;

$theme = auth()?->theme() ?? 'auto';
$color = auth()?->color() ?? Palette::DEFAULT; ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<title><?= e(isset($title) ? $title . ' · ' . env('APP_NAME') : env('APP_NAME')) ?></title>
<meta name="description" content="<?= e(__((string) env('APP_DESCRIPTION', ''))) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="<?= e(Palette::secondary($color)) ?>">
<?php if ($theme === 'auto'): /* follow the system, before the first paint */ ?>
<script>document.documentElement.dataset.bsTheme = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';</script>
<?php endif ?>
<link rel="icon" href="<?= asset('assets/icons/logo.svg') ?>" type="image/svg+xml">
<link rel="icon" href="<?= asset('assets/icons/logo-64.png') ?>" type="image/png" sizes="64x64">
<link rel="apple-touch-icon" href="<?= asset('assets/icons/logo-180.png') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<?php if (filter_var(env('VIEW_TRANSITIONS', true), FILTER_VALIDATE_BOOL) && ($vt = (float) env('VIEW_TRANSITION_SECONDS', 0.2)) > 0): ?>
<style>@view-transition { navigation: auto; } :root { --vt: <?= round(min($vt, 3), 3) ?>s; } @media (prefers-reduced-motion: reduce) { @view-transition { navigation: none; } }</style>
<script src="<?= asset('assets/js/transitions.js') ?>"></script>
<?php endif ?>
<?php if (!empty($expect)): ?>
<link rel="expect" href="#<?= e($expect) ?>" blocking="render">
<?php endif ?>
<style><?= Palette::style($color) ?></style>
