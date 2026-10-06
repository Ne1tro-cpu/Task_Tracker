<?php
// Kurā lapā esam - izvēlnes izcelšanai un lapas izkārtojumam
$route    = is_string($_GET['r'] ?? null) ? $_GET['r'] : 'tasks';
$loggedIn = !empty($_SESSION['user_id']);
$section  = explode('/', $route)[0];

// Vai pēc uzdevuma pabeigšanas jāpalaiž konfeti (uzstāda TaskController)
$celebrate = !empty($_SESSION['celebrate']);
unset($_SESSION['celebrate']);
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title ?? 'Uzdevumi') ?></title>
    <!-- Izvēlēto izskatu (gaišs/tumšs) uzliekam pirms lapas zīmēšanas, lai tā nemirgo -->
    <script>try { var t = localStorage.getItem('tema'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wdth,wght@12..96,75..100,200..800&display=swap">
    <link rel="stylesheet" href="assets/style.css">
    <?php if ($loggedIn): ?>
        <!-- CSRF kods JavaScript pieprasījumiem (dēlis, kalendārs) -->
        <meta name="csrf-token" content="<?= csrf_token() ?>">
    <?php endif; ?>
    <script src="assets/app.js" defer></script>
</head>
<body class="<?= $loggedIn ? 'app' : 'guest' ?> page-<?= e(str_replace('/', '-', $route)) ?>"<?= $celebrate ? ' data-celebrate' : '' ?>>

<!-- Ikonu komplekts, ko izmanto ar <svg><use href="#i-..."></svg> -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-tasks" viewBox="0 0 24 24"><path d="M4 6h2M4 12h2M4 18h2M9 6h11M9 12h11M9 18h7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-tags" viewBox="0 0 24 24"><path d="M3 12V4h8l10 10-8 8L3 12z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="7.5" cy="8.5" r="1.5" fill="currentColor"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></symbol>
    <symbol id="i-out" viewBox="0 0 24 24"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-trash" viewBox="0 0 24 24"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-edit" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 16V11a6 6 0 0112 0v5l2 2H4l2-2zM10 21h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-link" viewBox="0 0 24 24"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-share" viewBox="0 0 24 24"><circle cx="18" cy="5" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="6" cy="12" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="18" cy="19" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8.2 10.8l7.6-4.4M8.2 13.2l7.6 4.4" stroke="currentColor" stroke-width="2"/></symbol>
    <symbol id="i-board" viewBox="0 0 24 24"><rect x="3" y="4" width="5" height="16" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/><rect x="10" y="4" width="5" height="10" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/><rect x="17" y="4" width="4" height="13" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/></symbol>
    <symbol id="i-cal" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-clip" viewBox="0 0 24 24"><path d="M20 11.5l-8 8a5 5 0 01-7-7l8.5-8.5a3.3 3.3 0 014.7 4.7L9.7 17a1.7 1.7 0 01-2.4-2.4L15 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-repeat" viewBox="0 0 24 24"><path d="M4 12a7 7 0 0112-5l2 2M20 12a7 7 0 01-12 5l-2-2M18 4v5h-5M6 20v-5h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4V5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M6 3h8l5 5v13H6V3zM14 3v5h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></symbol>
    <symbol id="i-sound" viewBox="0 0 24 24"><path d="M4 9h4l5-4v14l-5-4H4V9zM16.5 8.5a5 5 0 010 7M19 6a8.5 8.5 0 010 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-mute" viewBox="0 0 24 24"><path d="M4 9h4l5-4v14l-5-4H4V9zM17 9l5 6M22 9l-5 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 019.5 4a8 8 0 1010.5 10.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></symbol>
    <symbol id="i-auto" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 4a8 8 0 010 16z" fill="currentColor"/></symbol>
    <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M11 12l8-8M16 7l3 3M14 9l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-db" viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="7" ry="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M5 6v12c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3" fill="none" stroke="currentColor" stroke-width="2"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></symbol>
</svg>

<?php if ($loggedIn): ?>
<nav class="rail" aria-label="Galvenā izvēlne">
    <a class="brand" href="index.php?r=tasks" aria-label="Uzdevumi - sākums">
        <span class="brand-mark"></span>
    </a>
    <a href="index.php?r=tasks" class="rail-link <?= $section === 'tasks' ? 'is-active' : '' ?>">
        <svg><use href="#i-tasks"/></svg><span>Uzdevumi</span>
    </a>
    <a href="index.php?r=board" class="rail-link <?= $section === 'board' ? 'is-active' : '' ?>">
        <svg><use href="#i-board"/></svg><span>Dēlis</span>
    </a>
    <a href="index.php?r=calendar" class="rail-link <?= $section === 'calendar' ? 'is-active' : '' ?>">
        <svg><use href="#i-cal"/></svg><span>Kalendārs</span>
    </a>
    <a href="index.php?r=categories" class="rail-link <?= $section === 'categories' ? 'is-active' : '' ?>">
        <svg><use href="#i-tags"/></svg><span>Kategorijas</span>
    </a>
    <button type="button" class="rail-link" data-open-palette>
        <svg><use href="#i-search"/></svg><span>Meklēt</span>
    </button>
    <a href="index.php?r=tasks/create" class="rail-link rail-new">
        <svg><use href="#i-plus"/></svg><span>Jauns</span>
    </a>
    <div class="rail-foot">
        <a href="index.php?r=profile" class="rail-link rail-profile <?= $section === 'profile' ? 'is-active' : '' ?>" title="<?= e($_SESSION['username']) ?>">
            <span class="avatar"><?= e(initial($_SESSION['username'])) ?></span><span>Profils</span>
        </a>
        <!-- Atteikšanās ar POST + CSRF, lai to nevarētu izraisīt ar svešu saiti -->
        <form method="post" action="index.php?r=logout" class="rail-logout" data-no-ajax>
            <?= csrf_field() ?>
            <button type="submit" class="rail-link" title="Iziet">
                <svg><use href="#i-out"/></svg><span>Iziet</span>
            </button>
        </form>
    </div>
</nav>
<?php endif; ?>

<main class="stage">
<?php foreach ($errors ?? [] as $err): ?>
    <p class="alert" role="alert"><?= e($err) ?></p>
<?php endforeach; ?>
