<h1><i class="fas fa-flag"></i> Signalements</h1>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table">
<thead><tr><th>ID</th><th>Lien</th><th>Signalé par</th><th>Raison</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead>
<tbody><?php foreach ($reports as $r): ?><tr><td>#<?= $r['id'] ?></td><td><a href="<?= e($r['link_url'] ?? '') ?>" target="_blank"><?= e(truncate($r['link_url'] ?? '', 30)) ?></a></td><td><?= e($r['reporter_name'] ?? 'Anonyme') ?></td><td><?= e($r['reason']) ?></td><td><span class="badge badge-<?= $r['status']==='pending'?'warning':'success' ?>"><?= e($r['status']) ?></span></td><td><?= format_date($r['created_at']) ?></td>
<td><?php if ($r['status']==='pending'): ?>
<form method="POST" action="admin/reports/resolve" class="inline-form"><?= \App\Core\View::csrfField() ?><input type="hidden" name="report_id" value="<?= $r['id'] ?>"><button name="action" value="blacklist" class="btn btn-danger btn-sm"><i class="fas fa-ban"></i></button><button name="action" value="remove_link" class="btn btn-warning btn-sm"><i class="fas fa-unlink"></i></button><button name="action" value="dismiss" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></button></form>
<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
