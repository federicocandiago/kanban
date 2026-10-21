<?php
/**
 * Search results, grouped: boards, lists, cards (with labels), attachments, calendar events.
 * Used by the preview in the search modal ($preview: a few per group) and by the results page.
 * @var string $query @var array $results @var bool|null $preview
 */
use App\Services\SearchService;

$preview ??= false;
$boards = $results['boardsById'] ?? [];
$mark = static fn (?string $text): string => SearchService::highlight((string) $text, $query);
$total = count($results['boards']) + count($results['lists']) + count($results['cards']) + count($results['attachments']) + count($results['events']);
$tz = auth()->timezone(); ?>
<div class="search-results">
    <?php if (mb_strlen($query) < SearchService::MIN_LENGTH): ?>
        <p class="search-empty"><?= e(__('Type at least :n characters: boards, lists, cards, labels, attachments and calendar events.', ['n' => SearchService::MIN_LENGTH])) ?></p>
    <?php elseif ($total === 0): ?>
        <p class="search-empty"><?= e(__('Nothing found for “:q”.', ['q' => $query])) ?></p>
    <?php endif ?>

    <?php if ($results['boards']): ?>
        <h3><?= e(__('Boards')) ?></h3>
        <?php foreach ($results['boards'] as $board): ?>
            <a class="search-item" href="<?= e($board->isArchived() ? url('/dashboard?archived=1') : $board->url()) ?>">
                <span class="search-thumb" style="<?= $board->backgroundStyle(true) ?>"></span>
                <span class="search-text"><strong><?= $mark($board->name) ?></strong>
                    <?php if ($board->isArchived()): ?><small><?= e(__('archived')) ?></small><?php endif ?></span>
            </a>
        <?php endforeach ?>
    <?php endif ?>

    <?php if ($results['cards']): ?>
        <h3><?= e(__('Cards')) ?></h3>
        <?php foreach ($results['cards'] as $card): $board = $boards[(int) $card->board_id] ?? null; if (!$board) continue; ?>
            <a class="search-item" href="<?= e($board->url('?card=' . $card->id)) ?>">
                <span class="search-icon"><?= component('icon', ['name' => 'card']) ?></span>
                <span class="search-text">
                    <?php if ($card->labels()): ?>
                        <span class="card-labels">
                            <?php foreach ($card->labels() as $label): ?>
                                <span class="label label-<?= e($label->color) ?> <?= $label->name === '' ? 'is-bar' : '' ?>"><?= $mark($label->name) ?></span>
                            <?php endforeach ?>
                        </span>
                    <?php endif ?>
                    <strong><?= $mark($card->title) ?></strong>
                    <?php if ($excerpt = SearchService::excerpt($card->description, $query)): ?><small class="search-excerpt"><?= $mark($excerpt) ?></small><?php endif ?>
                    <small><?= e($board->name . ' › ' . $card->list_name) ?>
                        <?php if ($card->attachment_count): ?> · <?= component('icon', ['name' => 'clip', 'size' => 12]) ?> <?= (int) $card->attachment_count ?><?php endif ?>
                        <?php if ($card->end_date): ?> · <?= component('icon', ['name' => 'clock', 'size' => 12]) ?> <?= e(format_day($card->end_date)) ?><?php endif ?>
                        <?php if ($card->isArchived()): ?> · <?= e(__('archived')) ?><?php endif ?></small>
                </span>
            </a>
        <?php endforeach ?>
    <?php endif ?>

    <?php if ($results['lists']): ?>
        <h3><?= e(__('Lists')) ?></h3>
        <?php foreach ($results['lists'] as $list): $board = $boards[(int) $list['board_id']] ?? null; if (!$board) continue; ?>
            <a class="search-item" href="<?= e($board->url('#list-' . $list['id'])) ?>">
                <span class="search-icon"><?= component('icon', ['name' => 'list']) ?></span>
                <span class="search-text"><strong><?= $mark($list['name']) ?></strong>
                    <small><?= e($board->name) ?><?= $list['archived_at'] ? ' · ' . e(__('archived')) : '' ?></small></span>
            </a>
        <?php endforeach ?>
    <?php endif ?>

    <?php if ($results['attachments']): ?>
        <h3><?= e(__('Attachments')) ?></h3>
        <?php foreach ($results['attachments'] as $file): $board = $boards[(int) $file['board_id']] ?? null; if (!$board) continue; ?>
            <a class="search-item" href="<?= e($board->url('?card=' . $file['card_id'])) ?>">
                <span class="search-icon search-file"><?= e(strtoupper(pathinfo((string) $file['name'], PATHINFO_EXTENSION) ?: '?')) ?></span>
                <span class="search-text"><strong><?= $mark($file['name']) ?></strong>
                    <small><?= e($board->name . ' › ' . $file['card_title']) ?> · <?= e(format_bytes((int) $file['size'])) ?></small></span>
            </a>
        <?php endforeach ?>
    <?php endif ?>

    <?php if ($results['events']): ?>
        <h3><?= e(__('Calendars')) ?> <small><?= e(__('in the months already opened')) ?></small></h3>
        <?php foreach ($results['events'] as $event): ?>
            <a class="search-item" href="<?= e(url('/calendars/' . (int) $event->account_id . '?month=' . substr((string) $event->start_day, 0, 7) . '&card=' . $event->id)) ?>">
                <span class="search-icon"><?= component('icon', ['name' => 'clock']) ?></span>
                <span class="search-text">
                    <span class="card-labels"><span class="label" style="--lc: <?= e($event->calendar_color ?: '#616a75') ?>"><?= e($event->calendar_name) ?></span></span>
                    <strong><?= $mark($event->summary ?: __('(no title)')) ?></strong>
                    <small><?= e(format_day((string) $event->start_day)) ?><?= $event->timeRange($tz) !== '' ? ' · ' . e($event->timeRange($tz)) : '' ?></small>
                </span>
            </a>
        <?php endforeach ?>
    <?php endif ?>

    <?php if ($preview && $total > 0): ?>
        <p class="search-more"><?= e(__('Press Enter to see all the results.')) ?></p>
    <?php endif ?>
</div>
