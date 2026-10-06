<!-- Dekoratīvā kreisā puse pieteikšanās un reģistrācijas lapām -->
<div class="scene" aria-hidden="true">
    <div class="sun"></div>
    <p class="scene-word"><?php foreach (preg_split('//u', 'Uzdevumi.', -1, PREG_SPLIT_NO_EMPTY) as $i => $ch): ?><span style="--i: <?= $i ?>"><?= e($ch) ?></span><?php endforeach; ?></p>
    <ul class="slips">
        <li style="--r: -6deg; --x: 8%;  --y: 14%; --d: 0s">
            <i class="tick-mini is-done"></i> Nodot praktisko darbu
        </li>
        <li style="--r: 4deg;  --x: 46%; --y: 30%; --d: -3s">
            <i class="tick-mini"></i> Nopirkt pienu <em>rīt</em>
        </li>
        <li style="--r: -2deg; --x: 18%; --y: 56%; --d: -6s">
            <i class="tick-mini"></i> Treniņš 18:00
        </li>
        <li style="--r: 7deg;  --x: 52%; --y: 72%; --d: -9s">
            <i class="tick-mini is-done"></i> Piezvanīt vecmāmiņai
        </li>
    </ul>
</div>
