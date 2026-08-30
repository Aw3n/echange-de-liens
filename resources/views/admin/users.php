<h1><i class="fas fa-users"></i> Gestion des utilisateurs</h1>
<p>Total : <?= $total ?> utilisateurs</p>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table">
<thead><tr><th>ID</th><th>Pseudo</th><th>Email</th><th>Rôle</th><th>Points</th><th>Actif</th><th>Inscrit le</th><th>Actions</th></tr></thead>
<tbody><?php foreach ($users as $u): ?>
<tr><td>#<?= $u['id'] ?></td><td><?= e($u['username']) ?></td><td><?= e($u['email']) ?></td><td><span class="badge"><?= e($u['role_slug'] ?? '') ?></span></td><td><?= format_number((int)$u['points']) ?></td><td><?= $u['is_active'] ? '<span class="text-success">Oui</span>' : '<span class="text-danger">Non</span>' ?></td><td><?= format_date($u['created_at']) ?></td>
<td><form method="POST" action="admin/users/edit" class="inline-form"><?= \App\Core\View::csrfField() ?><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="number" name="points" value="<?= (int)$u['points'] ?>" style="width:70px" class="form-control form-control-sm"><select name="role_id" class="form-control form-control-sm" style="width:60px"><option value="1" <?= ($u['role_id']??3)==1?'selected':'' ?>>Admin</option><option value="3" <?= ($u['role_id']??3)==3?'selected':'' ?>>Membre</option></select><select name="is_active" class="form-control form-control-sm" style="width:50px"><option value="1" <?= ($u['is_active']??1)==1?'selected':'' ?>>Oui</option><option value="0" <?= ($u['is_active']??1)==0?'selected':'' ?>>Non</option></select><button class="btn btn-primary btn-sm"><i class="fas fa-save"></i></button></form>
<form method="POST" action="admin/users/points" class="inline-form"><?= \App\Core\View::csrfField() ?><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="number" name="amount" value="1000" style="width:80px" class="form-control form-control-sm" title="Points à attribuer (négatif pour débiter)"><button class="btn btn-success btn-sm" title="Attribuer ces points"><i class="fas fa-coins"></i></button></form></td></tr>
<?php
// Adresses de paiement crypto/PayPal renseignées par l'utilisateur (visibles admin uniquement)
$walletCols = ['paypal_email' => 'PayPal', 'xelis_address' => 'Xelis', 'kaspa_address' => 'Kaspa', 'firo_address' => 'Firo', 'verge_address' => 'Verge', 'pepecoin_address' => 'Pepecoin', 'vertcoin_address' => 'Vertcoin', 'dragonx_address' => 'DragonX', 'monero_address' => 'Monero'];
$walletSet = [];
foreach ($walletCols as $col => $label) {
    if (!empty($u[$col])) {
        $walletSet[] = '<span title="' . e((string) $u[$col]) . '">' . e($label) . ' : <code style="font-size:.75rem;">' . e((string) $u[$col]) . '</code></span>';
    }
}
if (!empty($walletSet)):
?>
<tr><td colspan="8" style="padding:.25rem .75rem;background:var(--bg);font-size:.85rem;"><i class="fas fa-wallet text-muted"></i> <?= implode('<br>', $walletSet) ?></td></tr>
<?php endif; ?>
<?php endforeach; ?></tbody></table></div>
<?php if ($pages > 1): ?><div class="pagination"><?php for ($p=1;$p<=$pages;$p++): ?><a href="admin/users?page=<?= $p ?>" class="page-link <?= $p===$current_page?'active':'' ?>"><?= $p ?></a><?php endfor; ?></div><?php endif; ?>
</div></div>
