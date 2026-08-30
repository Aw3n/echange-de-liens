<!-- Page d'accueil -->
<section class="hero">
    <h1><i class="fas fa-exchange-alt"></i> <?= tr('Echange de Liens') ?></h1>
    <p class="hero-subtitle"><?= tr('Gagnez des visiteurs en échange de visites sur les liens des autres membres') ?></p>
    <div class="hero-badge"><i class="fas fa-wand-magic-sparkles"></i> <?= tr('Inscription 100 % gratuite — sans carte bancaire') ?></div>
    <div class="hero-actions">
        <a href="links/add" class="btn btn-primary btn-lg"><i class="fas fa-plus"></i> <?= tr('Ajouter un lien') ?></a>
        <?php if (!is_logged_in()): ?>
        <a href="register" class="btn btn-outline btn-lg"><i class="fas fa-user-plus"></i> <?= tr('Inscription gratuite') ?></a>
        <?php endif; ?>
    </div>
</section>

<!-- Publicités haut -->
<?php if (!empty($topAds)): ?>
<div class="ad-banner ad-top">
    <?php foreach ($topAds as $ad): ?>
        <div class="ad-slot">
        <?php if ($ad['type'] === 'banner' && $ad['image_url']): ?>
            <a href="<?= e($ad['target_url']) ?>" target="_blank" rel="noopener">
                <img src="<?= e($ad['image_url']) ?>" alt="<?= e($ad['title']) ?>" width="<?= $ad['width'] ?>" height="<?= $ad['height'] ?>" loading="lazy">
            </a>
        <?php elseif ($ad['html_code']): ?>
            <?= $ad['html_code'] ?>
        <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-link"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?= format_number($stats['total_links']) ?></span>
            <span class="stat-label"><?= tr('Liens inscrits') ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-eye"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?= format_number($stats['total_visits']) ?></span>
            <span class="stat-label"><?= tr('Visites totales') ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?= format_number($stats['visits_today']) ?></span>
            <span class="stat-label"><?= tr('Visites aujourd\'hui') ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?= format_number($stats['total_members']) ?></span>
            <span class="stat-label"><?= tr('Membres') ?></span>
        </div>
    </div>
</div>

<!-- Classement -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-trophy"></i> <?= tr('Classement des liens') ?></h2>
        <a href="ranking" class="btn btn-outline btn-sm"><?= tr('Voir tout') ?> <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="card-body">
        <?php if (empty($ranking)): ?>
            <p class="text-center text-muted"><?= tr('Aucun lien pour le moment. Soyez le premier à en ajouter !') ?></p>
        <?php else: ?>
            <div class="link-list" id="rankingList">
                <?php \App\Core\View::include('partials.ranking_rows', ['ranking' => $ranking, 'rank_offset' => 0]); ?>
            </div>
            <?php if (($ranking_total ?? 0) > count($ranking)): ?>
            <div class="text-center" style="margin-top:0.75rem;">
                <button type="button" class="btn btn-outline" id="rankingMoreBtn" data-offset="<?= count($ranking) ?>">
                    <i class="fas fa-chevron-down"></i> <?= tr('Charger plus') ?>
                </button>
            </div>
            <script>
            (function () {
                var btn = document.getElementById('rankingMoreBtn');
                if (!btn) { return; }
                var labelMore = '<i class="fas fa-chevron-down"></i> <?= tr('Charger plus') ?>';
                var labelLoading = '<i class="fas fa-spinner fa-spin"></i> <?= tr('Chargement...') ?>';
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    btn.innerHTML = labelLoading;
                    fetch('<?= e(base_path('ranking/more')) ?>?offset=' + btn.getAttribute('data-offset'))
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data && data.success) {
                                document.getElementById('rankingList').insertAdjacentHTML('beforeend', data.html);
                                btn.setAttribute('data-offset', data.next_offset);
                            }
                            if (data && data.has_more) {
                                btn.disabled = false;
                                btn.innerHTML = labelMore;
                            } else {
                                btn.remove();
                            }
                        })
                        .catch(function () {
                            btn.disabled = false;
                            btn.innerHTML = labelMore;
                        });
                });
            })();
            </script>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Publicités bas -->
<?php if (!empty($bottomAds)): ?>
<div class="ad-banner ad-bottom">
    <?php foreach ($bottomAds as $ad): ?>
        <div class="ad-slot">
        <?php if ($ad['type'] === 'banner' && $ad['image_url']): ?>
            <a href="<?= e($ad['target_url']) ?>" target="_blank" rel="noopener">
                <img src="<?= e($ad['image_url']) ?>" alt="<?= e($ad['title']) ?>" width="<?= $ad['width'] ?>" height="<?= $ad['height'] ?>" loading="lazy">
            </a>
        <?php elseif ($ad['html_code']): ?>
            <?= $ad['html_code'] ?>
        <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
