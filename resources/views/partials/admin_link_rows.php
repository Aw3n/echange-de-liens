<?php
// Lignes du tableau de gestion des liens (admin).
// Partial partagé entre le rendu initial de /admin/links et
// l'endpoint AJAX /admin/links/more (« Charger plus »).
// Chaque ligne est suivie d'une ligne d'édition complète (URL, titre,
// points, statuts) masquée par défaut, dépliée via le bouton crayon.
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
    <td style="white-space:nowrap;">
        <button type="button" class="btn btn-outline btn-sm" title="Modifier (URL, titre, points)" onclick="var r=document.getElementById('link-edit-<?= (int)$l['id'] ?>');r.style.display=r.style.display==='none'?'table-row':'none';"><i class="fas fa-pencil-alt"></i></button>
        <form method="POST" action="admin/links/delete" class="inline-form" onsubmit="return confirm('Supprimer le lien #<?= (int)$l['id'] ?> ? Les points restants seront remboursés au propriétaire.');"><?= \App\Core\View::csrfField() ?><input type="hidden" name="link_id" value="<?= $l['id'] ?>"><button class="btn btn-danger btn-sm" title="Supprimer"><i class="fas fa-trash"></i></button></form>
    </td>
</tr>
<tr id="link-edit-<?= (int)$l['id'] ?>" style="display:none;">
    <td colspan="9">
        <form method="POST" action="admin/links/edit" class="form-vertical">
            <?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="link_id" value="<?= $l['id'] ?>">
            <div class="row">
                <div class="form-group">
                    <label for="edit-url-<?= (int)$l['id'] ?>"><i class="fas fa-globe"></i> URL *</label>
                    <input type="url" id="edit-url-<?= (int)$l['id'] ?>" name="url" class="form-control" value="<?= e($l['url']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="edit-title-<?= (int)$l['id'] ?>"><i class="fas fa-heading"></i> Titre</label>
                    <input type="text" id="edit-title-<?= (int)$l['id'] ?>" name="title" class="form-control" maxlength="255" value="<?= e($l['title'] ?? '') ?>" placeholder="Optionnel">
                </div>
            </div>
            <div class="row">
                <div class="form-group">
                    <label for="edit-points-<?= (int)$l['id'] ?>"><i class="fas fa-coins"></i> Points</label>
                    <input type="number" id="edit-points-<?= (int)$l['id'] ?>" name="points" class="form-control" min="0" value="<?= (int)$l['points'] ?>">
                </div>
                <div class="form-group">
                    <label for="edit-active-<?= (int)$l['id'] ?>">Actif</label>
                    <select id="edit-active-<?= (int)$l['id'] ?>" name="is_active" class="form-control"><option value="1" <?= $l['is_active']?'selected':'' ?>>Oui</option><option value="0" <?= $l['is_active']?'':'selected' ?>>Non</option></select>
                </div>
                <div class="form-group">
                    <label for="edit-bl-<?= (int)$l['id'] ?>">Blacklist</label>
                    <select id="edit-bl-<?= (int)$l['id'] ?>" name="is_blacklisted" class="form-control"><option value="0" <?= $l['is_blacklisted']?'':'selected' ?>>Non</option><option value="1" <?= $l['is_blacklisted']?'selected':'' ?>>Oui</option></select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Enregistrer les modifications</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
