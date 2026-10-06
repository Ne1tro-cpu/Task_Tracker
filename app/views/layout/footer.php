</main>

<!-- Paziņojumi (flash + termiņu atgādinājumi) parādās šeit -->
<div class="toasts" aria-live="polite">
    <?php if ($flash = get_flash()):
        // 'info' - svarīga ziņa (piem., demo konti), kas nepazūd pati; pārējās pazūd pēc dažām sekundēm
        $icon = ['error' => 'x', 'info' => 'key'][$flash['type']] ?? 'check'; ?>
        <div class="toast toast-<?= $flash['type'] === 'error' ? 'late' : e($flash['type']) ?>"
             <?= $flash['type'] === 'info' ? '' : 'data-autohide' ?> role="status">
            <svg><use href="#i-<?= $icon ?>"/></svg>
            <p><?= e($flash['msg']) ?></p>
            <?php if ($flash['type'] === 'info'): ?>
                <button type="button" class="icon-btn" data-toast-close aria-label="Aizvērt"><svg><use href="#i-x"/></svg></button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($_SESSION['user_id'])): ?>
<!-- Meklēšanas logs (Ctrl+K vai /) -->
<div class="palette" hidden>
    <div class="palette-backdrop" data-close-palette></div>
    <div class="palette-box" role="dialog" aria-modal="true" aria-label="Meklēt">
        <label class="palette-input">
            <svg><use href="#i-search"/></svg>
            <input type="search" placeholder="Meklēt uzdevumus vai doties uz…" autocomplete="off">
            <kbd>Esc</kbd>
        </label>
        <ul class="palette-results" role="listbox"></ul>
    </div>
</div>
<?php endif; ?>
</body>
</html>
