<div class="auth">
    <?php require __DIR__ . '/_scene.php'; ?>

    <div class="auth-panel">
        <h1>Laipni lūgts atpakaļ</h1>
        <p class="muted">Piesakies, lai redzētu savus uzdevumus.</p>

        <form method="post" action="index.php?r=login" class="auth-form">
            <?= csrf_field() ?>
            <label class="float">
                <input type="text" name="username" value="<?= e($username) ?>" placeholder=" " required autocomplete="username">
                <span>Lietotājvārds</span>
            </label>
            <label class="float">
                <input type="password" name="password" placeholder=" " required autocomplete="current-password">
                <span>Parole</span>
            </label>
            <button type="submit" class="btn btn-primary btn-wide" data-magnetic>Pieteikties</button>
        </form>
        <p class="auth-switch">Nav konta? <a href="index.php?r=register">Izveido to</a></p>
    </div>
</div>
