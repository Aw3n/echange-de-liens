<h1><i class="fas fa-ban"></i> Blacklist</h1>
<div class="card"><div class="card-header"><h3>Ajouter à la blacklist</h3></div><div class="card-body">
<form method="POST" action="admin/blacklist" class="inline-form"><?= \App\Core\View::csrfField() ?>
<input type="text" name="url_pattern" class="form-control" placeholder="domaine.com" required>
<input type="text" name="reason" class="form-control" placeholder="Raison">
<button class="btn btn-danger"><i class="fas fa-plus"></i> Ajouter</button>
</form></div></div>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table">
<thead><tr><th>ID</th><th>Pattern</th><th>Raison</th><th>Ajouté par</th><th>Date</th><th>Actions</th></tr></thead>
<tbody><?php foreach ($blacklist as $b): ?><tr><td>#<?= $b['id'] ?></td><td><code><?= e($b['url_pattern']) ?></code></td><td><?= e($b['reason'] ?? '') ?></td><td><?= e($b['username'] ?? 'Système') ?></td><td><?= format_date($b['created_at']) ?></td><td>
<form method="POST" action="admin/blacklist/delete" class="inline-form" onsubmit="return confirm('Retirer ce domaine de la blacklist ?\nLes liens correspondants seront réhabilités.');"><?= \App\Core\View::csrfField() ?>
<input type="hidden" name="blacklist_id" value="<?= $b['id'] ?>">
<button type="submit" class="btn btn-success btn-sm" title="Déblacklister ce domaine"><i class="fas fa-check"></i> Déblacklister</button>
</form>
</td></tr><?php endforeach; ?></tbody></table></div></div></div>
