<!-- Datubāzes sagatavošana: parādās, kad datubāzes vēl nav vai tā jāatjaunina (SetupController) -->
<div class="setup">
    <div class="setup-card">
        <span class="setup-icon" aria-hidden="true"><svg><use href="#i-db"/></svg></span>

        <?php if ($status === 'upgrade'): ?>
            <h1>Datubāze jāatjaunina</h1>
            <p>Datubāze <b><?= e($dbName) ?></b> izveidota ar vecāku lietotnes versiju.
                Atjaunināšana pievienos jaunās tabulas un kolonnas. Esošie dati paliks.</p>
            <ul class="setup-list">
                <?php foreach ($pending as $file): ?>
                    <li><svg><use href="#i-file"/></svg> database/<?= e($file) ?></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="index.php?r=setup/upgrade">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-wide">Atjaunināt datubāzi</button>
            </form>
        <?php else: ?>
            <h1>Sagatavosim datubāzi</h1>
            <p><?= $status === 'database'
                    ? 'Datubāze <b>' . e($dbName) . '</b> vēl nav izveidota.'
                    : 'Datubāzē <b>' . e($dbName) . '</b> vēl nav tabulu.' ?>
                Nospied pogu, un lietotne izveidos visu nepieciešamo no faila <code>database/schema.sql</code>.</p>
            <form method="post" action="index.php?r=setup/install" class="stack-form">
                <?= csrf_field() ?>
                <label class="check">
                    <input type="checkbox" name="demo" value="1">
                    <span>Pievienot demo datus
                        <small>Lietotāji <b>demo</b> un <b>anna</b> ar uzdevumiem, soļiem, kopīgošanu un 5 mēnešu vēsturi - ērti prezentācijai.</small>
                    </span>
                </label>
                <button type="submit" class="btn btn-primary btn-wide">Izveidot datubāzi</button>
            </form>
        <?php endif; ?>

        <p class="setup-hint">To pašu var izdarīt arī phpMyAdmin, importējot failu <code>database/schema.sql</code>.</p>
    </div>
</div>
