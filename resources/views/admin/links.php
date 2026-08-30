<h1><i class="fas fa-link"></i> Gestion des liens</h1>
<p>Total : <?= $total ?> liens</p>

<!-- Ajout rapide d'un lien avec points -->
<div class="card">
    <div class="card-header"><h3><i class="fas fa-plus-circle"></i> Ajouter un lien</h3></div>
    <div class="card-body">
        <form method="POST" action="admin/links/add" class="form-vertical">
            <?= \App\Core\View::csrfField() ?>
            <div class="row">
                <div class="form-group">
                    <label for="url"><i class="fas fa-globe"></i> URL *</label>
                    <input type="url" id="url" name="url" class="form-control" required placeholder="https://www.monsite.com">
                </div>
                <div class="form-group">
                    <label for="title"><i class="fas fa-heading"></i> Titre</label>
                    <input type="text" id="title" name="title" class="form-control" maxlength="255" placeholder="Optionnel">
                </div>
            </div>
            <div class="row">
                <div class="form-group">
                    <label for="points"><i class="fas fa-coins"></i> Points *</label>
                    <input type="number" id="points" name="points" class="form-control" min="0" value="100" required>
                </div>
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Utilisateur</label>
                    <select id="username" name="username" class="form-control">
                        <option value="">— Moi (admin) —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= e($u['username']) ?>"><?= e($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter le lien</button>
        </form>
    </div>
</div>

<!-- Liste des liens (édition inline) -->
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table">
<thead><tr><th>ID</th><th>URL</th><th>Utilisateur</th><th colspan="3">Points / Actif / Blacklist</th><th>Visites</th><th>Date</th></tr></thead>
<tbody id="adminLinksBody"><?php \App\Core\View::include('partials.admin_link_rows', ['links' => $links]); ?></tbody></table></div>
<?php if (($total ?? 0) > count($links)): ?>
<div class="text-center" style="margin-top:0.75rem;">
    <button type="button" class="btn btn-outline" id="adminLinksMoreBtn" data-offset="<?= count($links) ?>">
        <i class="fas fa-chevron-down"></i> <?= tr('Charger plus') ?>
    </button>
</div>
<script>
(function () {
    var btn = document.getElementById('adminLinksMoreBtn');
    if (!btn) { return; }
    var labelMore = '<i class="fas fa-chevron-down"></i> <?= tr('Charger plus') ?>';
    var labelLoading = '<i class="fas fa-spinner fa-spin"></i> <?= tr('Chargement...') ?>';
    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = labelLoading;
        fetch('<?= e(base_path('admin/links/more')) ?>?offset=' + btn.getAttribute('data-offset'))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success) {
                    document.getElementById('adminLinksBody').insertAdjacentHTML('beforeend', data.html);
                    btn.setAttribute('data-offset', data.next_offset);
                }
                if (data && data.has_more) {
                    btn.disabled = false;
                    btn.innerHTML = labelMore;
                } else {
                    btn.remove();
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = labelMore;
            });
    });
})();
</script>
<?php endif; ?>
</div></div>
