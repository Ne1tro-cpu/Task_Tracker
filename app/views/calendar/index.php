<?php
// Mēneša kalendārs. Savus uzdevumus var pārvilkt uz citu dienu (app.js -> POST tasks/due).
$weekdays = ['Pirmd.', 'Otrd.', 'Trešd.', 'Ceturtd.', 'Piektd.', 'Sestd.', 'Svētd.'];

$todayStr = date('Y-m-d');
$monthNum = (int)$first->format('n');
?>

<header class="cal-head">
    <h1><span><?= e(lv_month($monthNum)) ?></span> <small><?= $first->format('Y') ?></small></h1>
    <nav class="cal-nav" aria-label="Mēneši">
        <a class="icon-btn" href="index.php?r=calendar&amp;month=<?= e($prev) ?>" aria-label="Iepriekšējais mēnesis">←</a>
        <a class="btn btn-ghost" href="index.php?r=calendar">Šodien</a>
        <a class="icon-btn" href="index.php?r=calendar&amp;month=<?= e($next) ?>" aria-label="Nākamais mēnesis">→</a>
    </nav>
</header>

<div class="calendar" data-calendar>
    <?php foreach ($weekdays as $wd): ?>
        <div class="cal-wd"><?= $wd ?></div>
    <?php endforeach; ?>

    <?php for ($d = $gridStart, $n = 0; $d <= $gridEnd; $d = $d->modify('+1 day'), $n++):
        $ds = $d->format('Y-m-d');
        $tasks = $byDay[$ds] ?? [];
        $classes = ['cal-day'];
        if ((int)$d->format('n') !== $monthNum) $classes[] = 'is-other';
        if ($ds === $todayStr) $classes[] = 'is-today';
        if ($ds < $todayStr) $classes[] = 'is-past';
        if ((int)$d->format('N') >= 6) $classes[] = 'is-weekend';
    ?>
        <div class="<?= implode(' ', $classes) ?>" data-date="<?= $ds ?>" style="--n: <?= $n ?>">
            <div class="cal-date">
                <span><?= $d->format('j') ?></span>
                <a href="index.php?r=tasks/create&amp;due=<?= $ds ?>" class="cal-add" aria-label="Jauns uzdevums <?= e(lv_date($ds)) ?>"><svg><use href="#i-plus"/></svg></a>
            </div>
            <ul class="cal-tasks" data-drop>
                <?php foreach ($tasks as $t):
                    $own = empty($t['owner']); ?>
                    <li class="cal-task prio-<?= prio_class($t['priority']) ?> <?= $t['status'] === 'pabeigts' ? 'is-done' : '' ?> <?= $own ? '' : 'is-shared' ?>"
                        <?= $own ? 'data-card' : '' ?> data-id="<?= (int)$t['id'] ?>"
                        title="<?= e($t['title'] . ($own ? '' : ' (kopīgoja ' . $t['owner'] . ')')) ?>">
                        <a href="index.php?r=tasks/edit&amp;id=<?= (int)$t['id'] ?>" draggable="false">
                            <?php if (!$own): ?><svg><use href="#i-share"/></svg><?php endif; ?>
                            <?= e($t['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endfor; ?>
</div>
