<?php
// Sērija + aktivitātes karte (GitHub stilā): kolonna = nedēļa, rinda = nedēļas diena.
// Sagaida mainīgo $heatmap (Task::heatmap). Izmanto sākumlapa un profils.
$hm = $heatmap;
$todayStr = date('Y-m-d');
$level = fn(int $n): int => match (true) { $n === 0 => 0, $n === 1 => 1, $n <= 3 => 2, $n <= 5 => 3, default => 4 };
?>
<section class="streak" aria-label="Aktivitāte" data-region="streak">
    <div class="streak-stats">
        <div class="flame <?= $hm['streak'] ? 'is-lit' : '' ?>" aria-hidden="true">🔥</div>
        <p class="streak-num"><b data-count="<?= $hm['streak'] ?>"><?= $hm['streak'] ?></b>
            <span><?= $hm['streak'] === 1 ? 'diena' : 'dienas' ?> pēc kārtas</span></p>
        <p class="muted">
            <?php if (!$hm['streak']): ?>Pabeidz vienu uzdevumu, lai sāktu sēriju.
            <?php elseif (!$hm['today']): ?>Pabeidz šodien vēl vienu, lai sērija neapstātos.
            <?php else: ?>Šodien pabeigti <?= $hm['today'] ?>. Turpini!<?php endif; ?>
        </p>
        <dl class="streak-facts">
            <div><dt>Garākā sērija</dt><dd><?= $hm['best'] ?> d.</dd></div>
            <div><dt>Pabeigti 6 mēnešos</dt><dd><?= $hm['total'] ?></dd></div>
        </dl>
    </div>

    <div class="heatmap-wrap">
        <div class="heatmap" role="img" aria-label="Pabeigto uzdevumu karte pēdējām <?= $hm['weeks'] ?> nedēļām">
            <div class="hm-days" aria-hidden="true">
                <?php foreach (['P', '', 'T', '', 'P', '', 'S'] as $dn): ?><span><?= $dn ?></span><?php endforeach; ?>
            </div>
            <div class="hm-grid">
                <?php for ($w = 0; $w < $hm['weeks']; $w++): ?>
                    <?php for ($dow = 0; $dow < 7; $dow++):
                        $date = $hm['start']->modify('+' . ($w * 7 + $dow) . ' days');
                        $ds = $date->format('Y-m-d');
                        $future = $ds > $todayStr;
                        $n = $hm['counts'][$ds] ?? 0;
                    ?>
                        <i class="hm-cell l<?= $future ? 'x' : $level($n) ?> <?= $ds === $todayStr ? 'is-today' : '' ?>"
                           style="--w: <?= $w ?>"
                           <?php if (!$future): ?>title="<?= e(lv_date($ds)) ?>: <?= $n ?> pabeigti"<?php endif; ?>></i>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>
        </div>
        <div class="hm-legend" aria-hidden="true">
            mazāk <i class="hm-cell l0"></i><i class="hm-cell l1"></i><i class="hm-cell l2"></i><i class="hm-cell l3"></i><i class="hm-cell l4"></i> vairāk
        </div>
    </div>
</section>
