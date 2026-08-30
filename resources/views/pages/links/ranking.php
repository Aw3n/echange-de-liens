<div class="card">
    <div class="card-header"><h2><i class="fas fa-trophy"></i> <?= tr('Classement des liens') ?></h2></div>
    <div class="card-body">
        <?php if (empty($ranking['links'])): ?>
            <p class="text-center text-muted"><?= tr('Aucun lien classé pour le moment.') ?></p>
        <?php else: ?>
            <ol class="ranking-compact">
                <?php foreach ($ranking['links'] as $i => $link): ?>
                <?php $rank = ($ranking['current_page'] - 1) * $ranking['per_page'] + $i + 1; ?>
                <?php // Jamais d'URL d'origine exposée : titre du membre s'il existe,
                // sinon la même URL interne que le bouton Visiter (go?id=…). ?>
                <?php $linkLabel = !empty($link['title']) ? truncate($link['title'], 90) : 'go?id=' . $link['id']; ?>
                <li>
                    <span class="rc-rank <?= $rank <= 3 ? 'rank-gold' : '' ?>"><?= $rank ?>.</span>
                    <a href="go?id=<?= $link['id'] ?>" target="_blank" rel="noopener" class="rc-url" title="<?= e($linkLabel) ?>">&raquo; <?= e($linkLabel) ?></a>
                    <span class="rc-points">(<?= format_number((int) $link['points']) ?> <?= tr('points') ?>)</span>
                </li>
                <?php endforeach; ?>
            </ol>

            <!-- Pagination -->
            <?php if ($ranking['pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $ranking['pages']; $p++): ?>
                    <a href="ranking?page=<?= $p ?>" class="page-link <?= $p === $ranking['current_page'] ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
