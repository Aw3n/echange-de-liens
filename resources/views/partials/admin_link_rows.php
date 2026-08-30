<?php
// Lignes du tableau de gestion des liens (admin).
// Partial partagé entre le rendu initial de /admin/links et
// l'endpoint AJAX /admin/links/more (« Charger plus »).
?>
<?php foreach ($links as $l): ?>
<tr>
    <td>#<?= $l['id'] ?></td>
    <td><a href="<?= e($l['url']) ?>" target="_blank"><?= e(truncate($l['url'], 40)) ?></a><?php if (!empty($l['title'])): ?><br><small class="text-muted"><?= e($l['title']) ?></small><?php endif; ?></td>
    <td><?= e($l['username'] ?? '') ?></td>
    <td colspan="3">
        <form method="POST" action="admin/links/edit" class="inline-form"><?= \App\Core\View::csrfField() ?><input type="hidden" name="link_id" value="<?= $l['id'] ?>"><input type="number" name="points" value="<?= (int)$l['points'] ?>" min="0" style="width:80px" class="form-control form-control-sm"><select name="is_active" class="form-control form-control-sm" style="width:55px"><option value="1" <?= $l['is_active']?'selected':'' ?>>Oui</option><option value="0" <?= $l['is_active']?'':'selected' ?>>Non</option></select><select name="is_blacklisted" class="form-control form-control-sm" style="width:55px"><option value="0" <?= $l['is_blacklisted']?'':'selected' ?>>Non</option><option value="1" <?= $l['is_blacklisted']?'selected':'' ?>>Oui</option></select><button class="btn btn-primary btn-sm" title="Enregistrer"><i class="fas fa-save"></i></button></form>
    </td>
    <td><?= (int)$l['total_visits'] ?></td>
    <td><?= format_date($l['created_at']) ?></td>
</tr>
<?php endforeach; ?>
