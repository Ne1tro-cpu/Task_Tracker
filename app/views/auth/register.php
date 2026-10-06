<div class="auth">
    <?php require __DIR__ . '/_scene.php'; ?>

    <div class="auth-panel">
        <h1>Izveido kontu</h1>
        <p class="muted">Tas aizņems pusminūti.</p>

        <form method="post" action="index.php?r=register" class="auth-form">
            <?= csrf_field() ?>
            <label class="float">
                <input type="text" name="username" value="<?= e($username) ?>" placeholder=" " required
                       minlength="3" maxlength="30" pattern="[a-zA-Z0-9_]+" autocomplete="username">
                <span>Lietotājvārds</span>
                <small>3–30 simboli: burti, cipari, _</small>
            </label>
            <label class="float">
                <input type="email" name="email" value="<?= e($email) ?>" placeholder=" " required maxlength="100" autocomplete="email">
                <span>E-pasts</span>
            </label>
            <label class="float">
                <input type="password" name="password" placeholder=" " required minlength="8" autocomplete="new-password" data-strength>
                <span>Parole</span>
                <i class="strength" aria-hidden="true"><b></b></i>
            </label>
            <label class="float">
                <input type="password" name="password2" placeholder=" " required minlength="8" autocomplete="new-password">
                <span>Parole atkārtoti</span>
            </label>
            <button type="submit" class="btn btn-primary btn-wide" data-magnetic>Reģistrēties</button>
        </form>
        <p class="auth-switch">Jau ir konts? <a href="index.php?r=login">Piesakies</a></p>
    </div>
</div>
