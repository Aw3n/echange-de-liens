<!-- Admin Dashboard -->
<h1><i class="fas fa-shield-alt"></i> Administration</h1>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-info"><span class="stat-number"><?= $stats['total_users'] ?></span><span class="stat-label">Utilisateurs</span></div></div>
    <div class="stat-card"><div class="stat-icon bg-success"><i class="fas fa-link"></i></div><div class="stat-info"><span class="stat-number"><?= $stats['total_links'] ?></span><span class="stat-label">Liens</span></div></div>
    <div class="stat-card"><div class="stat-icon bg-info"><i class="fas fa-eye"></i></div><div class="stat-info"><span class="stat-number"><?= $stats['total_visits'] ?></span><span class="stat-label">Visites</span></div></div>
    <div class="stat-card"><div class="stat-icon bg-warning"><i class="fas fa-flag"></i></div><div class="stat-info"><span class="stat-number"><?= $stats['pending_reports'] ?></span><span class="stat-label">Signalements</span></div></div>
    <div class="stat-card"><div class="stat-icon bg-warning"><i class="fas fa-image"></i></div><div class="stat-info"><span class="stat-number"><?= $stats['pending_banners'] ?></span><span class="stat-label">Bannières en attente</span></div></div>
    <div class="stat-card"><div class="stat-icon bg-warning"><i class="fas fa-shopping-cart"></i></div><div class="stat-info"><span class="stat-number"><?= $stats['pending_purchases'] ?></span><span class="stat-label">Achats en attente</span></div></div>
</div>
<div class="quick-actions">
    <a href="admin/users" class="btn btn-primary"><i class="fas fa-users"></i> Utilisateurs</a>
    <a href="admin/links" class="btn btn-primary"><i class="fas fa-link"></i> Liens</a>
    <a href="admin/reports" class="btn btn-warning"><i class="fas fa-flag"></i> Signalements (<?= $stats['pending_reports'] ?>)</a>
    <a href="admin/banners" class="btn btn-warning"><i class="fas fa-image"></i> Bannières (<?= $stats['total_banners'] ?>)<?php if ($stats['pending_banners'] > 0): ?> · <?= $stats['pending_banners'] ?> en attente<?php endif; ?></a>
    <a href="admin/purchases" class="btn btn-warning"><i class="fas fa-shopping-cart"></i> Achats (<?= $stats['pending_purchases'] ?>)</a>
    <a href="admin/settings" class="btn btn-outline"><i class="fas fa-cog"></i> Configuration</a>
    <a href="admin/blacklist" class="btn btn-danger"><i class="fas fa-ban"></i> Blacklist</a>
</div>
<div class="card"><div class="card-header"><h3>Derniers inscrits</h3></div><div class="card-body">
<div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>Pseudo</th><th>Email</th><th>Points</th><th>Date</th></tr></thead><tbody>
<?php foreach ($recent_users as $u): ?><tr><td>#<?= $u['id'] ?></td><td><?= e($u['username']) ?></td><td><?= e($u['email']) ?></td><td><?= format_number((int)$u['points']) ?></td><td><?= format_date($u['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
