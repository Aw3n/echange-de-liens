<h1><i class="fas fa-list"></i> Mes liens</h1>
<?php if (empty($links)): ?>
<div class="card"><div class="card-body"><p class="text-center">Aucun lien. <a href="links/add">Ajoutez-en un !</a></p></div></div>
<?php else: ?>
<div class="card"><div class="card-body"><div class="link-list">
<?php foreach ($links as $link): ?>
<div class="link-item">
    <div class="link-info">
        <a href="<?= e($link['url']) ?>" target="_blank" class="link-url"><?= e(truncate($link['title'] ?? $link['url'], 50)) ?></a>
        <span class="link-meta">ID: #<?= $link['id'] ?> | <i class="fas fa-eye"></i> <?= (int) $link['total_visits'] ?> visites</span>
    </div>
    <div class="link-points"><span class="points-badge"><?= format_number((int) $link['points']) ?></span><span class="points-label">points</span></div>
    <div class="link-actions">
        <form method="POST" action="links/assign-points" class="inline-form"><?= \App\Core\View::csrfField() ?><input type="hidden" name="link_id" value="<?= $link['id'] ?>"><input type="number" name="points" min="1" value="10" class="form-control form-control-sm" style="width:60px"><button class="btn btn-success btn-sm"><i class="fas fa-arrow-up"></i></button></form>
        <form method="POST" action="links/delete/<?= $link['id'] ?>" class="inline-form" onsubmit="return confirm('Supprimer ?')"><?= \App\Core\View::csrfField() ?><button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form>
    </div>
</div>
<?php endforeach; ?>
</div></div></div>
<?php if ($pages > 1): ?><div class="pagination"><?php for ($p=1;$p<=$pages;$p++): ?><a href="links/my?page=<?= $p ?>" class="page-link <?= $p===$current_page?'active':'' ?>"><?= $p ?></a><?php endfor; ?></div><?php endif; ?>
<?php endif; ?>
