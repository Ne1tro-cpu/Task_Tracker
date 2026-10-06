<!-- Kļūdas lapa (404, 403, 500 ...) - skat. render_error() failā app/core/helpers.php -->
<section class="error-page">
    <p class="error-code" aria-hidden="true"><?= (int)http_response_code() ?></p>
    <h1><?= e($heading) ?></h1>
    <?php if ($message !== ''): ?>
        <p class="error-text"><?= e($message) ?></p>
    <?php endif; ?>
    <div class="error-actions">
        <a class="btn btn-primary" href="index.php"><svg><use href="#i-tasks"/></svg> Uz sākumu</a>
    </div>
</section>
