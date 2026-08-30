<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-gift"></i> <?= tr('Page Bonus - Gagnez 5 points par clic !') ?></h2>
        <?php if (is_logged_in()): ?>
            <a href="bonus/add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?= tr('Ajouter ma bannière') ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="alert alert-success">
            <i class="fas fa-star"></i> <?= tr('Cliquez sur une bannière et patientez 15 secondes pour gagner') ?> <strong>5 <?= tr('points') ?></strong> !
            <?= tr('C\'est l\'équivalent de 5 clics sur un lien classique.') ?>
        </div>

        <?php if (empty($banners)): ?>
            <p class="text-center text-muted"><?= tr('Aucune bannière bonus disponible pour le moment.') ?></p>
        <?php else: ?>
            <div class="banner-grid">
                <?php foreach ($banners as $banner): ?>
                <div class="banner-card">
                    <a href="bonus/viewer?id=<?= $banner['id'] ?>" target="_blank" class="banner-link">
                        <img src="<?= e($banner['image_url']) ?>" alt="<?= e($banner['title']) ?>" width="468" height="60" loading="lazy" class="banner-img">
                    </a>
                    <div class="banner-meta">
                        <?php // Le pseudo du propriétaire reste visible uniquement côté admin. ?>
                        <span><i class="fas fa-star"></i> <?= (int) $banner['points_assigned'] ?> pts</span>
                        <span><i class="fas fa-mouse-pointer"></i> <?= (int) $banner['clicks'] ?> <?= tr('clics') ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
