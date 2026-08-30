<?php
// Lignes du classement de l'accueil.
// Partial partagé entre le rendu initial et l'endpoint AJAX
// /ranking/more (« Charger plus »). $rank_offset = rang du 1er élément.
?>
<?php foreach ($ranking as $i => $link): ?>
<div class="link-item">
    <div class="link-rank">#<?= ($rank_offset ?? 0) + $i + 1 ?></div>
    <div class="link-info">
        <?php // Jamais d'URL d'origine exposée (survol ou clic) :
        // href interne go?id= identique au bouton Visiter,
        // libellé = titre du membre sinon go?id=. ?>
        <?php $homeLabel = !empty($link['title']) ? truncate($link['title'], 60) : 'go?id=' . $link['id']; ?>
        <a href="go?id=<?= $link['id'] ?>" target="_blank" rel="noopener" class="link-url" title="<?= e($homeLabel) ?>">
            <?= e($homeLabel) ?>
        </a>
        <span class="link-meta">
            <i class="fas fa-eye"></i> <?= format_number((int) $link['total_visits']) ?> <?= tr('visites') ?>
        </span>
    </div>
    <div class="link-points">
        <span class="points-badge"><?= format_number((int) $link['points']) ?></span>
        <span class="points-label"><?= tr('points') ?></span>
    </div>
    <a href="go?id=<?= $link['id'] ?>" class="btn btn-primary btn-sm" target="_blank">
        <i class="fas fa-external-link-alt"></i> <?= tr('Visiter') ?>
    </a>
</div>
<?php endforeach; ?>
