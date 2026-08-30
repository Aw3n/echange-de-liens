<h1><i class="fas fa-chart-bar"></i> Statistiques</h1>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-eye"></i></div><div class="stat-info"><span class="stat-number"><?= format_number((int) ($visit_stats['total_visits'] ?? 0)) ?></span><span class="stat-label">Visites totales</span></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-check"></i></div><div class="stat-info"><span class="stat-number"><?= format_number((int) ($visit_stats['validated_visits'] ?? 0)) ?></span><span class="stat-label">Visites validées</span></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-arrow-up"></i></div><div class="stat-info"><span class="stat-number"><?= format_number((int) ($visit_stats['total_points_earned'] ?? 0)) ?></span><span class="stat-label">Points gagnés</span></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-arrow-down"></i></div><div class="stat-info"><span class="stat-number"><?= format_number((int) ($visit_stats['total_points_spent'] ?? 0)) ?></span><span class="stat-label">Points dépensés</span></div></div>
</div>
<div class="card"><div class="card-header"><h3>Résumé par type</h3></div><div class="card-body">
<?php if (empty($points_summary)): ?><p class="text-muted">Aucune donnée.</p>
<?php else: ?>
<table class="table"><thead><tr><th>Type</th><th>Total</th><th>Occurrences</th></tr></thead><tbody>
<?php foreach ($points_summary as $s): ?><tr><td><?= e($s['type']) ?></td><td><?= format_number((int) $s['total']) ?></td><td><?= (int) $s['count'] ?></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?>
</div></div>
