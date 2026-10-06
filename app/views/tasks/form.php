<?php
// Viena forma gan izveidei ($id = null), gan rediģēšanai ($id = uzdevuma nr.).
// Lietotājam, ar kuru uzdevums kopīgots ($readonly), redaktora vietā rāda tikai skatu.
// Sadaļas (soļi, saites, kopīgošana, faili, aktivitāte) parādās tikai saglabātam uzdevumam,
// kad kontrolieris skatam padod $subtasks, $links, $shares, $files, $activity.
$action = $id ? 'tasks/edit&id=' . (int)$id : 'tasks/create';

// Saites veida noteikšana priekšskatījumam
$linkKind = function (string $url): array {
    if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]{11})~', $url, $m)) {
        return ['video', 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg'];
    }
    if (preg_match('~\.(png|jpe?g|gif|webp|svg)(\?.*)?$~i', $url)) {
        return ['image', $url];
    }
    return ['web', null];
};
?>

<a class="back" href="index.php?r=tasks"><svg><use href="#i-tasks"/></svg> Visi uzdevumi</a>

<?php if (!empty($readonly)): ?>
<!-- Ar mani kopīgots uzdevums: tikai skatīšanās, bet soļus un saites var papildināt -->
<article data-region="editor" class="editor task-view prio-<?= prio_class($task['priority']) ?>"
         style="view-transition-name: task-<?= (int)$id ?>">
    <div class="editor-main">
        <p class="shared-by"><span class="avatar"><?= e(initial($task['owner_name'])) ?></span>
            <?= e($task['owner_name']) ?> kopīgoja ar tevi</p>
        <h1 class="view-title"><?= e($task['title']) ?></h1>
        <?php if ($task['description']): ?>
            <p class="view-desc"><?= nl2br(e($task['description'])) ?></p>
        <?php endif; ?>
    </div>
    <aside class="editor-side">
        <dl class="facts">
            <div><dt>Statuss</dt><dd><?= e($task['status']) ?></dd></div>
            <div><dt>Prioritāte</dt><dd><?= e($task['priority']) ?></dd></div>
            <div><dt>Termiņš</dt><dd><?= e($task['due_date'] ? lv_date($task['due_date']) : 'nav') ?></dd></div>
            <div><dt>Kategorija</dt><dd><?= e($task['category'] ?? 'nav') ?></dd></div>
            <div><dt>Atkārtojas</dt><dd><?= e(REPEATS[$task['repeat_rule'] ?? ''] ?? 'nē') ?></dd></div>
        </dl>
    </aside>
</article>
<?php else: ?>
<form method="post" action="index.php?r=<?= e($action) ?>" class="editor" data-region="editor" data-region-self
      style="<?= $id ? 'view-transition-name: task-' . (int)$id : '' ?>">
    <?= csrf_field() ?>

    <div class="editor-main">
        <label class="sr" for="title">Nosaukums</label>
        <input id="title" class="title-input" type="text" name="title" value="<?= e($task['title']) ?>"
               placeholder="Ko vajag izdarīt?" required maxlength="100" <?= $id ? '' : 'autofocus' ?>>

        <label class="sr" for="description">Apraksts</label>
        <textarea id="description" class="desc-input" name="description" rows="5" maxlength="2000"
                  placeholder="Piezīmes, detaļas, saites…" data-autogrow><?= e($task['description']) ?></textarea>
    </div>

    <aside class="editor-side">
        <fieldset class="field">
            <legend>Statuss</legend>
            <div class="radio-seg">
                <?php foreach (STATUSES as $s): ?>
                    <label>
                        <input type="radio" name="status" value="<?= e($s) ?>" <?= $s === $task['status'] ? 'checked' : '' ?>>
                        <span><?= e($s) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <?php if (array_key_exists('priority', $task)): ?>
        <fieldset class="field">
            <legend>Prioritāte</legend>
            <div class="radio-seg prio-seg">
                <?php foreach (PRIORITIES as $p): ?>
                    <label class="p-<?= prio_class($p) ?>">
                        <input type="radio" name="priority" value="<?= e($p) ?>" <?= $p === ($task['priority'] ?? 'vidēja') ? 'checked' : '' ?>>
                        <span><?= e($p) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php endif; ?>

        <label class="field">
            <span>Termiņš</span>
            <input type="date" name="due_date" value="<?= e($task['due_date']) ?>">
        </label>

        <label class="field">
            <span>Atkārtot</span>
            <select name="repeat_rule">
                <option value="">Neatkārtot</option>
                <?php foreach (REPEATS as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $key === ($task['repeat_rule'] ?? '') ? 'selected' : '' ?>><?= e(ucfirst($label)) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="field-hint">Kad pabeigsi, automātiski tiks izveidots nākamais.</small>
        </label>

        <label class="field">
            <span>Kategorija</span>
            <select name="category_id">
                <option value="">Bez kategorijas</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (string)$c['id'] === (string)$task['category_id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="editor-actions">
            <button type="submit" class="btn btn-primary" data-magnetic>
                <svg><use href="#i-check"/></svg> <?= $id ? 'Saglabāt izmaiņas' : 'Izveidot uzdevumu' ?>
            </button>
            <a class="btn btn-ghost" href="index.php?r=tasks"><?= $id ? 'Atpakaļ' : 'Atcelt' ?></a>
        </div>
    </aside>
</form>
<?php endif; ?>

<?php if ($id && isset($subtasks)):
    $doneSteps = count(array_filter($subtasks, fn($s) => !empty($s['is_done'])));
?>
<section data-region="steps" class="panel steps" style="--p: <?= $subtasks ? $doneSteps / count($subtasks) * 100 : 0 ?>%">
    <header class="panel-head">
        <h2>Soļi</h2>
        <span class="steps-count"><?= $doneSteps ?>/<?= count($subtasks) ?></span>
        <i class="steps-bar" aria-hidden="true"></i>
    </header>
    <ul class="step-list">
        <?php foreach ($subtasks as $s): ?>
            <li class="<?= !empty($s['is_done']) ? 'is-done' : '' ?>">
                <form method="post" action="index.php?r=subtasks/toggle">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="tick" aria-label="Pārslēgt soli"><svg><use href="#i-check"/></svg></button>
                </form>
                <span class="step-title"><?= e($s['title']) ?></span>
                <form method="post" action="index.php?r=subtasks/delete">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="icon-btn" aria-label="Dzēst soli"><svg><use href="#i-x"/></svg></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
    <form method="post" action="index.php?r=subtasks/create" class="add-row">
        <?= csrf_field() ?>
        <input type="hidden" name="task_id" value="<?= (int)$id ?>">
        <input type="text" name="title" placeholder="Pievienot soli…" required maxlength="100">
        <button type="submit" class="icon-btn" aria-label="Pievienot soli"><svg><use href="#i-plus"/></svg></button>
    </form>
</section>
<?php endif; ?>

<?php if ($id && isset($links)): ?>
<section data-region="links" class="panel links">
    <header class="panel-head"><h2>Saites un faili</h2></header>
    <?php if ($links): ?>
    <ul class="link-grid">
        <?php foreach ($links as $l): [$kind, $thumb] = $linkKind($l['url']); ?>
            <li class="link-card kind-<?= $kind ?>">
                <a href="<?= e($l['url']) ?>" target="_blank" rel="noopener noreferrer">
                    <?php if ($thumb): ?>
                        <img src="<?= e($thumb) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span class="link-ico"><svg><use href="#i-link"/></svg></span>
                    <?php endif; ?>
                    <span class="link-text">
                        <b><?= e($l['title'] ?: parse_url($l['url'], PHP_URL_HOST)) ?></b>
                        <small><?= e(parse_url($l['url'], PHP_URL_HOST)) ?></small>
                    </span>
                </a>
                <form method="post" action="index.php?r=links/delete">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                    <button type="submit" class="icon-btn" aria-label="Noņemt saiti"><svg><use href="#i-x"/></svg></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <form method="post" action="index.php?r=links/create" class="add-row">
        <?= csrf_field() ?>
        <input type="hidden" name="task_id" value="<?= (int)$id ?>">
        <input type="url" name="url" placeholder="https://… (lapa, attēls, YouTube video)" required maxlength="500">
        <input type="text" name="title" placeholder="Nosaukums (nav obligāts)" maxlength="100">
        <button type="submit" class="icon-btn" aria-label="Pievienot saiti"><svg><use href="#i-plus"/></svg></button>
    </form>
</section>
<?php endif; ?>

<?php if ($id && isset($shares)): ?>
<section data-region="shares" class="panel shares">
    <header class="panel-head"><h2>Kopīgots ar</h2></header>
    <?php if (!$shares): ?>
        <p class="muted panel-hint">Ievadi cita lietotāja vārdu. Viņš redzēs šo uzdevumu un varēs papildināt soļus, saites, failus un komentēt.</p>
    <?php endif; ?>
    <ul class="share-list">
        <?php foreach ($shares as $sh): ?>
            <li>
                <span class="avatar"><?= e(initial($sh['username'])) ?></span>
                <span><?= e($sh['username']) ?></span>
                <form method="post" action="index.php?r=shares/delete">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$sh['id'] ?>">
                    <button type="submit" class="icon-btn" aria-label="Pārtraukt kopīgošanu"><svg><use href="#i-x"/></svg></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
    <form method="post" action="index.php?r=shares/create" class="add-row">
        <?= csrf_field() ?>
        <input type="hidden" name="task_id" value="<?= (int)$id ?>">
        <input type="text" name="username" placeholder="Lietotājvārds" required maxlength="30">
        <button type="submit" class="icon-btn" aria-label="Kopīgot"><svg><use href="#i-share"/></svg></button>
    </form>
</section>
<?php endif; ?>

<?php if ($id && isset($files)):
    $fmtSize = fn(int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
?>
<section data-region="files" class="panel files">
    <header class="panel-head"><h2>Pielikumi</h2><span class="steps-count"><?= count($files) ?></span></header>
    <?php if ($files): ?>
    <ul class="file-grid">
        <?php foreach ($files as $f):
            $url = 'index.php?r=files/download&id=' . (int)$f['id'];
            $isImage = str_starts_with($f['mime'], 'image/');
            $ext = strtoupper(pathinfo($f['original_name'], PATHINFO_EXTENSION));
        ?>
            <li class="file-card">
                <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="file-thumb">
                    <?php if ($isImage): ?>
                        <img src="<?= e($url) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span class="file-ico"><svg><use href="#i-file"/></svg><b><?= e($ext) ?></b></span>
                    <?php endif; ?>
                </a>
                <div class="file-text">
                    <a href="<?= e($url) ?>&amp;download=1" title="Lejupielādēt"><?= e($f['original_name']) ?></a>
                    <small><?= $fmtSize((int)$f['size']) ?> · <?= e($f['username']) ?></small>
                </div>
                <form method="post" action="index.php?r=files/delete">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                    <button type="submit" class="icon-btn" aria-label="Dzēst failu"><svg><use href="#i-x"/></svg></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <form method="post" action="index.php?r=files/upload&amp;task=<?= (int)$id ?>" enctype="multipart/form-data" class="dropzone" data-dropzone>
        <?= csrf_field() ?>
        <input type="hidden" name="task_id" value="<?= (int)$id ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_SIZE ?>">
        <label>
            <input type="file" name="file" required
                   accept="<?= e(implode(',', array_map(fn($x) => '.' . $x, array_keys(UPLOAD_TYPES)))) ?>">
            <svg><use href="#i-clip"/></svg>
            <span data-dropzone-label><b>Izvēlies failu</b> vai ievelc to šeit</span>
            <small>PDF, attēli, Word, Excel, PowerPoint, TXT, ZIP · līdz <?= UPLOAD_MAX_SIZE / 1024 / 1024 ?> MB</small>
        </label>
        <button type="submit" class="btn btn-primary">Augšupielādēt</button>
    </form>
</section>
<?php endif; ?>

<?php if ($id && isset($activity)):
    // "pirms 5 min" stila laiks
    $ago = function (string $dt): string {
        $s = time() - strtotime($dt);
        if ($s < 60) return 'tikko';
        if ($s < 3600) return 'pirms ' . floor($s / 60) . ' min';
        if ($s < 86400) return 'pirms ' . floor($s / 3600) . ' h';
        if ($s < 172800) return 'vakar';
        return date('j.m.Y', strtotime($dt));
    };
?>
<section data-region="activity" class="panel activity" id="aktivitate">
    <header class="panel-head"><h2>Aktivitāte un komentāri</h2></header>

    <form method="post" action="index.php?r=comments/create" class="comment-form">
        <?= csrf_field() ?>
        <input type="hidden" name="task_id" value="<?= (int)$id ?>">
        <span class="avatar"><?= e(initial($_SESSION['username'])) ?></span>
        <textarea name="body" rows="1" maxlength="1000" placeholder="Uzraksti komentāru…" required data-autogrow></textarea>
        <button type="submit" class="icon-btn" aria-label="Sūtīt komentāru"><svg><use href="#i-chat"/></svg></button>
    </form>

    <?php if (!$activity): ?>
        <p class="muted panel-hint">Te parādīsies visas izmaiņas un komentāri.</p>
    <?php else: ?>
    <ol class="timeline">
        <?php foreach ($activity as $a): ?>
            <li class="tl-<?= $a['type'] ?>">
                <?php if ($a['type'] === 'comment'): ?>
                    <span class="avatar"><?= e(initial($a['username'])) ?></span>
                    <div class="bubble">
                        <p class="tl-head"><b><?= e($a['username']) ?></b> <time datetime="<?= e($a['created_at']) ?>"><?= e($ago($a['created_at'])) ?></time></p>
                        <p><?= nl2br(e($a['body'])) ?></p>
                    </div>
                    <?php if ((int)$a['user_id'] === (int)$_SESSION['user_id']): ?>
                        <form method="post" action="index.php?r=comments/delete">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="icon-btn" aria-label="Dzēst komentāru"><svg><use href="#i-x"/></svg></button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <i class="tl-dot" aria-hidden="true"></i>
                    <p><b><?= e($a['username']) ?></b> <?= e($a['body']) ?>
                        <time datetime="<?= e($a['created_at']) ?>"><?= e($ago($a['created_at'])) ?></time></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
</section>
<?php endif; ?>
