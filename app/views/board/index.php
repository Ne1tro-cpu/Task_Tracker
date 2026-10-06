<?php
// Kanban dēlis. Kartītes var pārvilkt starp kolonnām ar peli vai pirkstu (app.js -> POST tasks/status).
// Ar tastatūru (un bez JavaScript) strādā bultiņu pogas katrā kartītē.
$columnTitles = ['jauns' => 'Jādara', 'procesā' => 'Procesā', 'pabeigts' => 'Pabeigts'];
?>

<header class="page-head">
    <h1>Dēlis</h1>
    <p class="muted">Pārvelc kartīti uz citu kolonnu vai izmanto bultiņas uz kartītes.</p>
</header>

<div class="kanban" data-kanban data-region="kanban">
    <?php foreach ($columns as $status => $cards):
        $i = array_search($status, STATUSES, true);
    ?>
        <section class="kb-col kb-<?= status_class($status) ?>" data-status="<?= e($status) ?>" aria-label="<?= e($columnTitles[$status]) ?>">
            <header class="kb-head">
                <i class="dot dot-<?= status_class($status) ?>"></i>
                <h2><?= e($columnTitles[$status]) ?></h2>
                <span class="kb-count"><?= count($cards) ?></span>
            </header>

            <ul class="kb-list" data-drop>
                <?php foreach ($cards as $n => $t): ?>
                    <li class="kb-card prio-<?= prio_class($t['priority']) ?>" data-card data-id="<?= (int)$t['id'] ?>" style="--n: <?= $n ?>">
                        <a href="index.php?r=tasks/edit&amp;id=<?= (int)$t['id'] ?>" class="kb-title" draggable="false"
                           data-focus-id="kb-<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
                        <div class="task-meta">
                            <?php if ($t['due_date']): $days = days_left($t['due_date']); ?>
                                <span class="chip <?= $status !== 'pabeigts' && $days < 0 ? 'chip-late' : '' ?>" title="<?= e(due_label($days)) ?>">
                                    <svg><use href="#i-cal"/></svg><?= e(lv_date($t['due_date'])) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($t['subtasks_total'] > 0): ?>
                                <span class="chip chip-steps" style="--p: <?= $t['subtasks_done'] / $t['subtasks_total'] * 100 ?>%"><i></i><?= (int)$t['subtasks_done'] ?>/<?= (int)$t['subtasks_total'] ?></span>
                            <?php endif; ?>
                            <?php if (isset(REPEATS[$t['repeat_rule'] ?? ''])): ?>
                                <span class="chip" title="Atkārtojas <?= e(REPEATS[$t['repeat_rule']]) ?>"><svg><use href="#i-repeat"/></svg></span>
                            <?php endif; ?>
                            <?php if ($t['files_count'] > 0): ?>
                                <span class="chip"><svg><use href="#i-clip"/></svg><?= (int)$t['files_count'] ?></span>
                            <?php endif; ?>
                        </div>
                        <!-- Pārvietot uz kaimiņu kolonnu (tastatūrai un bez JavaScript) -->
                        <div class="kb-move">
                            <?php foreach ([-1 => '←', 1 => '→'] as $dir => $arrow):
                                $target = STATUSES[$i + $dir] ?? null;
                                if (!$target) continue; ?>
                                <form method="post" action="index.php?r=tasks/status">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <input type="hidden" name="status" value="<?= e($target) ?>">
                                    <button type="submit" class="icon-btn" data-focus-id="kb-<?= (int)$t['id'] ?>-<?= $dir > 0 ? 'right' : 'left' ?>"
                                            aria-label="Pārvietot “<?= e($t['title']) ?>” uz <?= e($columnTitles[$target]) ?>"><?= $arrow ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($status === 'jauns'): ?>
                <a href="index.php?r=tasks/create" class="kb-add"><svg><use href="#i-plus"/></svg> Jauns uzdevums</a>
            <?php elseif ($status === 'pabeigts' && $hiddenDone > 0): ?>
                <a href="index.php?r=tasks&amp;status=pabeigts" class="kb-add">Vēl <?= $hiddenDone ?> vecāki pabeigtie</a>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
