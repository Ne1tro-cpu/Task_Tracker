<?php $max = $categories ? max(1, ...array_map(fn($c) => (int)$c['task_count'], $categories)) : 1; ?>

<header class="page-head">
    <h1>Kategorijas</h1>
    <p class="muted">Grupē uzdevumus. Klikšķini uz nosaukuma, lai to pārdēvētu.</p>
</header>

<form method="post" action="index.php?r=categories/create" class="add-row add-big">
    <?= csrf_field() ?>
    <input type="text" name="name" placeholder="Jauna kategorija, piem. Skola" required maxlength="50">
    <button type="submit" class="btn btn-primary" data-magnetic><svg><use href="#i-plus"/></svg> Pievienot</button>
</form>

<div data-region="cat-list">
<?php if (!$categories): ?>
    <div class="empty">
        <div class="empty-art" aria-hidden="true"><i></i><i></i><i></i></div>
        <p>Vēl nav nevienas kategorijas. Pievieno pirmo augstāk.</p>
    </div>
<?php else: ?>
<ul class="cat-list">
    <?php foreach ($categories as $i => $c): ?>
        <li style="--w: <?= (int)$c['task_count'] / $max * 100 ?>%; --i: <?= $i ?>">
            <form method="post" action="index.php?r=categories/edit" class="cat-rename">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <input type="text" name="name" value="<?= e($c['name']) ?>" required maxlength="50" aria-label="Kategorijas nosaukums">
                <button type="submit" class="icon-btn save" aria-label="Saglabāt nosaukumu"><svg><use href="#i-check"/></svg></button>
            </form>
            <span class="cat-count"><b><?= (int)$c['task_count'] ?></b> <?= (int)$c['task_count'] === 1 ? 'uzdevums' : 'uzdevumi' ?></span>
            <i class="cat-fill" aria-hidden="true"></i>
            <form method="post" action="index.php?r=categories/delete" class="del-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="del" aria-label="Dzēst kategoriju"><svg><use href="#i-trash"/></svg><span>Dzēst?</span></button>
            </form>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
