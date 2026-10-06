<?php
// Profils: statistika, sērija, izskata iestatījumi, paroles maiņa, konta dzēšana
$statTiles = [
    ['Uzdevumi', $stats['tasks'], 'i-tasks'],
    ['Pabeigti', $stats['done'], 'i-check'],
    ['Kategorijas', $stats['categories'], 'i-tags'],
    ['Kopīgoti ar mani', $stats['shared_with_me'], 'i-share'],
    ['Pielikumi', $stats['files'], 'i-clip'],
    ['Komentāri', $stats['comments'], 'i-chat'],
];
?>

<header class="profile-head">
    <span class="avatar avatar-xl" aria-hidden="true"><?= e(initial($user['username'])) ?></span>
    <div>
        <h1><?= e($user['username']) ?></h1>
        <p class="muted"><?= e($user['email']) ?></p>
        <p class="muted">Lietotājs kopš <?= e(date('d.m.Y.', strtotime($user['created_at']))) ?></p>
    </div>
</header>

<section class="profile-stats" aria-label="Statistika">
    <?php foreach ($statTiles as $i => [$label, $value, $icon]): ?>
        <div class="stat" style="--i: <?= $i ?>">
            <svg><use href="#<?= $icon ?>"/></svg>
            <b data-count="<?= (int)$value ?>"><?= (int)$value ?></b>
            <span><?= e($label) ?></span>
        </div>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/../partials/streak.php'; ?>

<div class="profile-grid">
    <!-- Izskats un skaņa glabājas tikai šajā pārlūkā (localStorage), serverim tie nav vajadzīgi -->
    <section class="panel prefs">
        <header class="panel-head"><h2>Izskats</h2></header>
        <fieldset class="field">
            <legend>Tēma</legend>
            <div class="radio-seg" data-pref="tema">
                <label><input type="radio" name="tema" value="auto"><span><svg><use href="#i-auto"/></svg> Kā sistēmā</span></label>
                <label><input type="radio" name="tema" value="light"><span><svg><use href="#i-sun"/></svg> Gaiša</span></label>
                <label><input type="radio" name="tema" value="dark"><span><svg><use href="#i-moon"/></svg> Tumša</span></label>
            </div>
        </fieldset>
        <fieldset class="field">
            <legend>Skaņa, pabeidzot uzdevumu</legend>
            <div class="radio-seg" data-pref="skana">
                <label><input type="radio" name="skana" value="on"><span><svg><use href="#i-sound"/></svg> Ieslēgta</span></label>
                <label><input type="radio" name="skana" value="off"><span><svg><use href="#i-mute"/></svg> Izslēgta</span></label>
            </div>
        </fieldset>
    </section>

    <section class="panel" data-region="password" data-region-self>
        <header class="panel-head"><h2>Mainīt paroli</h2></header>
        <form method="post" action="index.php?r=profile/password" class="stack-form">
            <?= csrf_field() ?>
            <label class="field"><span>Pašreizējā parole</span>
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label class="field"><span>Jaunā parole</span>
                <input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
            </label>
            <label class="field"><span>Jaunā parole atkārtoti</span>
                <input type="password" name="password2" required minlength="8" maxlength="72" autocomplete="new-password">
            </label>
            <button type="submit" class="btn btn-primary"><svg><use href="#i-key"/></svg> Mainīt paroli</button>
        </form>
    </section>

    <section class="panel danger-zone">
        <header class="panel-head"><h2>Dzēst kontu</h2></header>
        <p class="muted">Tiks neatgriezeniski izdzēsti visi tavi uzdevumi, kategorijas, soļi, pielikumi un komentāri.
            Ar tevi kopīgotie citu uzdevumi paliks to īpašniekiem.</p>
        <form method="post" action="index.php?r=profile/delete" class="stack-form">
            <?= csrf_field() ?>
            <label class="field"><span>Apstiprini ar paroli</span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="check">
                <input type="checkbox" required>
                <span>Saprotu, ka to nevar atsaukt</span>
            </label>
            <button type="submit" class="btn btn-danger"><svg><use href="#i-trash"/></svg> Dzēst kontu</button>
        </form>
    </section>

    <section class="panel profile-logout">
        <header class="panel-head"><h2>Iziet</h2></header>
        <p class="muted">Pēc iziešanas būs jāpiesakās vēlreiz.</p>
        <form method="post" action="index.php?r=logout" data-no-ajax>
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost"><svg><use href="#i-out"/></svg> Iziet no sistēmas</button>
        </form>
    </section>
</div>
