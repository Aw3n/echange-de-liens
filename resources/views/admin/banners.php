<h1><i class="fas fa-image"></i> Gestion des bannières</h1>

<!-- Ajout rapide d'une bannière -->
<div class="card">
    <div class="card-header"><h3><i class="fas fa-plus-circle"></i> Ajouter une bannière</h3></div>
    <div class="card-body">
        <form method="POST" action="admin/banners/add" class="form-vertical">
            <?= \App\Core\View::csrfField() ?>
            <div class="row">
                <div class="form-group">
                    <label for="title"><i class="fas fa-heading"></i> Titre *</label>
                    <input type="text" id="title" name="title" class="form-control" maxlength="255" required placeholder="Ma bannière">
                </div>
                <div class="form-group">
                    <label for="type"><i class="fas fa-tag"></i> Type *</label>
                    <select id="type" name="type" class="form-control">
                        <option value="banner">Image</option>
                        <option value="html">Code HTML</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="form-group">
                    <label for="image_url"><i class="fas fa-image"></i> URL de l'image (type image)</label>
                    <input type="url" id="image_url" name="image_url" class="form-control" placeholder="https://exemple.com/banniere.png">
                </div>
                <div class="form-group">
                    <label for="target_url"><i class="fas fa-globe"></i> URL cible HTTPS (type image)</label>
                    <input type="url" id="target_url" name="target_url" class="form-control" placeholder="https://www.monsite.com">
                </div>
            </div>
            <div class="form-group">
                <label for="html_code"><i class="fas fa-code"></i> Code HTML (type html)</label>
                <textarea id="html_code" name="html_code" class="form-control" rows="3" placeholder="<script>...</script> ou <a>...</a>"></textarea>
            </div>
            <div class="row">
                <div class="form-group">
                    <label for="position"><i class="fas fa-map-marker-alt"></i> Position *</label>
                    <select id="position" name="position" class="form-control">
                        <option value="home_top">Accueil — haut</option>
                        <option value="home_bottom">Accueil — bas</option>
                        <option value="top">Toutes pages — haut</option>
                        <option value="bottom">Toutes pages — bas</option>
                        <option value="viewer_bottom">Visionneuse — bas (gauche + droite aléatoires)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Utilisateur (optionnel)</label>
                    <select id="username" name="username" class="form-control">
                        <option value="">— Aucun (bannière admin) —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= e($u['username']) ?>"><?= e($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="form-group">
                    <label for="points"><i class="fas fa-coins"></i> Points attribués</label>
                    <input type="number" id="points" name="points" class="form-control" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="width"><i class="fas fa-arrows-alt-h"></i> Largeur (px)</label>
                    <input type="number" id="width" name="width" class="form-control" min="1" value="468">
                </div>
                <div class="form-group">
                    <label for="height"><i class="fas fa-arrows-alt-v"></i> Hauteur (px)</label>
                    <input type="number" id="height" name="height" class="form-control" min="1" value="60">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter et publier</button>
        </form>
    </div>
</div>

<!-- Liste des bannières -->
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table">
<thead><tr><th>ID</th><th>Aperçu</th><th>Utilisateur</th><th>Position</th><th>Points</th><th>Clics</th><th>Approuvée</th><th>Actions</th></tr></thead>
<tbody><?php foreach ($banners as $b): ?><tr><td>#<?= $b['id'] ?></td><td><?php if ($b['image_url']): ?><img src="<?= e($b['image_url']) ?>" width="200" height="25" loading="lazy"><?php else: ?><small class="text-muted">HTML</small><?php endif; ?><br><small class="text-muted"><?= e($b['title']) ?></small></td><td><?= e($b['username'] ?? '—') ?></td><td><span class="badge"><?= e($b['position']) ?></span></td><td><form method="POST" action="admin/banners/points" class="inline-form"><?= \App\Core\View::csrfField() ?><input type="hidden" name="ad_id" value="<?= $b['id'] ?>"><input type="number" name="points" min="0" step="1" value="<?= (int)$b['points_assigned'] ?>" class="form-control form-control-sm" style="width:110px;display:inline-block;" title="Points attribués"><button class="btn btn-primary btn-sm" title="Enregistrer les points"><i class="fas fa-save"></i></button></form></td><td><?= (int)$b['clicks'] ?></td><td><?= $b['is_approved'] ? '✅' : '⏳' ?></td>
<td>
    <?php if (!$b['is_approved']): ?><form method="POST" action="admin/banners/approve" class="inline-form"><?= \App\Core\View::csrfField() ?><input type="hidden" name="ad_id" value="<?= $b['id'] ?>"><button name="action" value="approve" class="btn btn-success btn-sm" title="Approuver"><i class="fas fa-check"></i></button><button name="action" value="reject" class="btn btn-danger btn-sm" title="Refuser"><i class="fas fa-times"></i></button></form><?php endif; ?>
    <form method="POST" action="admin/banners/delete" class="inline-form" onsubmit="return confirm('Supprimer cette bannière ?');"><?= \App\Core\View::csrfField() ?><input type="hidden" name="ad_id" value="<?= $b['id'] ?>"><button class="btn btn-danger btn-sm" title="Supprimer"><i class="fas fa-trash"></i></button></form>
</td></tr><?php endforeach; ?></tbody></table></div></div></div>
