<?php
// Sākumlapa: virsraksts, logrīki, aktivitātes karte un uzdevumu saraksts.
// Visi skaitļi jau aprēķināti modelī (Task::summary un Task::heatmap) - skats tikai attēlo.
$today = new DateTimeImmutable('today');
$s = $summary;
$hm = $heatmap;
?>

<header class="hero">
    <div class="sun" aria-hidden="true"></div>
    <p class="hero-hello">Sveiks, <?= e($_SESSION['username']) ?></p>
    <h1 class="hero-date" aria-label="<?= e(lv_weekday((int)$today->format('w')) . ', ' . lv_date($today->format('Y-m-d'))) ?>">
        <span class="hero-day"><?= e(lv_weekday((int)$today->format('w'))) ?></span>
        <span class="hero-num"><?= e(lv_date($today->format('Y-m-d'))) ?></span>
    </h1>
    <p class="hero-summary" data-region="summary">
        <?php if (!$s['total']): ?>
            Saraksts ir tukšs. Sāc ar pirmo uzdevumu.
        <?php elseif ($s['overdue'] || $s['dueToday']): ?>
            <?= $s['dueToday'] ? "Šodien jāpabeidz {$s['dueToday']}." : '' ?>
            <?= $s['overdue'] ? "Nokavēti {$s['overdue']}." : '' ?>
        <?php elseif ($s['done'] === $s['total']): ?>
            Viss izdarīts. Laiks atpūtai.
        <?php else: ?>
            Palikuši <?= $s['total'] - $s['done'] ?> no <?= $s['total'] ?>. Šodien nekas nedeg.
        <?php endif; ?>
    </p>
    <div class="hero-actions">
        <a class="btn btn-primary" href="index.php?r=tasks/create" data-magnetic title="Jauns uzdevums (taustiņš N)">
            <svg><use href="#i-plus"/></svg> Jauns uzdevums
        </a>
        <button type="button" class="btn btn-ghost" data-open-palette>
            <svg><use href="#i-search"/></svg> Meklēt <kbd>Ctrl K</kbd>
        </button>
    </div>
</header>

<div data-region="widgets">
<?php if ($s['total']): ?>
<section class="widgets" aria-label="Pārskats">
    <article class="widget w-progress" data-tilt>
        <svg class="ring" viewBox="0 0 120 120" aria-hidden="true">
            <circle cx="60" cy="60" r="52" class="ring-track"/>
            <circle cx="60" cy="60" r="52" class="ring-fill" style="--p: <?= $s['percent'] ?>"/>
        </svg>
        <div class="ring-label">
            <strong data-count="<?= $s['percent'] ?>">0</strong><span>%</span>
        </div>
        <p>pabeigti <?= $s['done'] ?> no <?= $s['total'] ?></p>
    </article>

    <article class="widget w-due">
        <h2><svg><use href="#i-bell"/></svg> Tuvākie termiņi</h2>
        <?php if (!$s['upcoming']): ?>
            <p class="muted">Nav uzdevumu ar termiņu.</p>
        <?php else: ?>
        <ol>
            <?php foreach ($s['upcoming'] as $u): ?>
                <li class="<?= $u['days'] < 0 ? 'is-late' : ($u['days'] <= 1 ? 'is-soon' : '') ?>">
                    <a href="index.php?r=tasks/edit&amp;id=<?= (int)$u['id'] ?>"><?= e($u['title']) ?></a>
                    <span><?= e(due_label($u['days'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </article>

    <article class="widget w-status">
        <h2>Statusi</h2>
        <div class="stack-bar" role="img"
             aria-label="<?= e("jauni {$s['counts']['jauns']}, procesā {$s['counts']['procesā']}, pabeigti {$s['counts']['pabeigts']}") ?>">
            <?php foreach (STATUSES as $st): ?>
                <span class="seg seg-<?= status_class($st) ?>" style="--w: <?= $s['counts'][$st] / $s['total'] * 100 ?>%"></span>
            <?php endforeach; ?>
        </div>
        <ul class="legend">
            <?php foreach (STATUSES as $st): ?>
                <li><i class="dot dot-<?= status_class($st) ?>"></i><?= e($st) ?> <b><?= $s['counts'][$st] ?></b></li>
            <?php endforeach; ?>
        </ul>
    </article>

    <article class="widget w-cats">
        <h2>Kategorijas</h2>
        <ul>
            <?php foreach ($s['byCategory'] as $name => $n): ?>
                <li>
                    <span><?= e($name) ?></span>
                    <i class="cat-bar" style="--w: <?= $n / $s['maxCat'] * 100 ?>%"></i>
                    <b><?= $n ?></b>
                </li>
            <?php endforeach; ?>
        </ul>
    </article>
</section>
<?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/streak.php'; ?>

<section class="board">
    <div class="board-head">
        <h2>Uzdevumi</h2>

        <!-- Meklēšana serverī (GET, jo tikai nolasa datus) -->
        <form method="get" action="index.php" class="search-box" role="search">
            <input type="hidden" name="r" value="tasks">
            <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <svg><use href="#i-search"/></svg>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Meklēt nosaukumā vai aprakstā" aria-label="Meklēt uzdevumus">
            <?php if ($search !== ''): ?>
                <a href="index.php?r=tasks<?= $status ? '&amp;status=' . urlencode($status) : '' ?>" class="icon-btn" aria-label="Notīrīt meklēšanu"><svg><use href="#i-x"/></svg></a>
            <?php endif; ?>
        </form>

        <!-- Filtrs ar GET. Ja lapā ir visi uzdevumi, JS filtrē uzreiz, bez pārlādes. -->
        <?php $q = $search !== '' ? '&amp;q=' . urlencode($search) : ''; ?>
        <nav class="segments" aria-label="Filtrēt pēc statusa" <?= $status === '' && $search === '' ? 'data-live' : '' ?>>
            <a href="index.php?r=tasks<?= $q ?>" data-filter="" class="<?= $status === '' ? 'is-on' : '' ?>">Visi</a>
            <?php foreach (STATUSES as $st): ?>
                <a href="index.php?r=tasks&amp;status=<?= urlencode($st) . $q ?>" data-filter="<?= e($st) ?>"
                   class="<?= $status === $st ? 'is-on' : '' ?>"><?= e($st) ?></a>
            <?php endforeach; ?>
            <span class="segments-pill" aria-hidden="true"></span>
        </nav>
    </div>

<div data-region="task-list">
    <?php if ($search !== ''): ?>
        <p class="search-note">Atrasti <?= $s['total'] ?> uzdevumi pēc “<?= e($search) ?>”.</p>
    <?php endif; ?>

    <?php if (!$tasks): ?>
        <div class="empty">
            <div class="empty-art" aria-hidden="true"><i></i><i></i><i></i></div>
            <p><?= $search !== '' ? 'Nekas netika atrasts. Pamēģini citu vārdu.'
                : ($status ? 'Šajā statusā nav neviena uzdevuma.' : 'Pievieno pirmo uzdevumu, un tas parādīsies šeit.') ?></p>
            <a class="btn btn-primary" href="index.php?r=tasks/create"><svg><use href="#i-plus"/></svg> Jauns uzdevums</a>
        </div>
    <?php else: ?>
    <ul class="tasks">
        <?php foreach ($tasks as $t):
            $days = days_left($t['due_date']);
            $open = $t['status'] !== 'pabeigts';
            $editUrl = 'index.php?r=tasks/edit&id=' . (int)$t['id'];
            $isOwner = empty($t['owner']);   // kopīgotiem uzdevumiem owner = īpašnieka vārds
        ?>
        <li class="task status-<?= status_class($t['status']) ?> prio-<?= prio_class($t['priority']) ?>"
            data-task
            data-status="<?= e($t['status']) ?>"
            data-title="<?= e($t['title']) ?>"
            data-desc="<?= e($t['description'] ?? '') ?>"
            data-cat="<?= e($t['category'] ?? '') ?>"
            data-due="<?= e($t['due_date'] ?? '') ?>"
            data-href="<?= e($editUrl) ?>"
            style="view-transition-name: task-<?= (int)$t['id'] ?>">

            <?php if ($isOwner): ?>
                <!-- Ātrā atzīmēšana: nosūta tos pašus datus uz tasks/edit, mainot tikai statusu -->
                <form method="post" action="<?= e($editUrl) ?>" class="tick-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="list">
                    <?php foreach (['title', 'description', 'due_date', 'category_id', 'priority', 'repeat_rule'] as $f): ?>
                        <input type="hidden" name="<?= $f ?>" value="<?= e($t[$f] ?? '') ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="status" value="<?= $open ? 'pabeigts' : 'jauns' ?>">
                    <button type="submit" class="tick" data-focus-id="tick-<?= (int)$t['id'] ?>"
                            aria-label="<?= $open ? 'Atzīmēt kā pabeigtu' : 'Atzīmēt kā nepabeigtu' ?>: <?= e($t['title']) ?>">
                        <svg><use href="#i-check"/></svg>
                    </button>
                </form>
            <?php else: ?>
                <span class="tick is-static" aria-hidden="true"><svg><use href="#i-check"/></svg></span>
            <?php endif; ?>

            <a class="task-main" href="<?= e($editUrl) ?>">
                <span class="task-title"><?= e($t['title']) ?></span>
                <?php if ($t['description']): ?>
                    <span class="task-desc"><?= e($t['description']) ?></span>
                <?php endif; ?>

                <span class="task-meta">
                    <span class="chip chip-prio"><i></i><?= e($t['priority']) ?></span>
                    <span class="chip chip-status"><?= e($t['status']) ?></span>
                    <?php if ($t['category']): ?>
                        <span class="chip"><svg><use href="#i-tags"/></svg><?= e($t['category']) ?></span>
                    <?php endif; ?>
                    <?php if ($t['subtasks_total'] > 0): ?>
                        <span class="chip chip-steps" style="--p: <?= $t['subtasks_done'] / $t['subtasks_total'] * 100 ?>%">
                            <i></i><?= (int)$t['subtasks_done'] ?>/<?= (int)$t['subtasks_total'] ?> soļi
                        </span>
                    <?php endif; ?>
                    <?php if (isset(REPEATS[$t['repeat_rule'] ?? ''])): ?>
                        <span class="chip"><svg><use href="#i-repeat"/></svg><?= e(REPEATS[$t['repeat_rule']]) ?></span>
                    <?php endif; ?>
                    <?php if ($t['files_count'] > 0): ?>
                        <span class="chip"><svg><use href="#i-clip"/></svg><?= (int)$t['files_count'] ?></span>
                    <?php endif; ?>
                    <?php if ($t['links_count'] > 0): ?>
                        <span class="chip"><svg><use href="#i-link"/></svg><?= (int)$t['links_count'] ?></span>
                    <?php endif; ?>
                    <?php if (!$isOwner): ?>
                        <span class="chip"><svg><use href="#i-share"/></svg><?= e($t['owner']) ?></span>
                    <?php endif; ?>
                </span>
            </a>

            <?php if ($t['due_date']): $due = new DateTimeImmutable($t['due_date']); ?>
                <time class="due <?= $open && $days < 0 ? 'is-late' : ($open && $days <= 1 ? 'is-soon' : '') ?>"
                      datetime="<?= e($t['due_date']) ?>" title="<?= e(due_label($days)) ?>">
                    <b><?= $due->format('j') ?></b>
                    <span><?= e(lv_month((int)$due->format('n'), true)) ?></span>
                </time>
            <?php endif; ?>

            <?php if ($isOwner): ?>
            <!-- Dzēšana ar POST. JS pārvērš pogu par "Dzēst?" apstiprinājumu. -->
            <form method="post" action="index.php?r=tasks/delete" class="del-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="del" aria-label="Dzēst uzdevumu: <?= e($t['title']) ?>">
                    <svg><use href="#i-trash"/></svg><span>Dzēst?</span>
                </button>
            </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <p class="no-match" hidden>Neviens uzdevums neatbilst filtram.</p>
    <?php endif; ?>
    <?php if ($hiddenDone): ?>
        <p class="search-note older-note"><a href="index.php?r=tasks&amp;status=pabeigts">Rādīt vēl <?= $hiddenDone ?> sen pabeigtus uzdevumus</a></p>
    <?php endif; ?>
</div>
</section>
