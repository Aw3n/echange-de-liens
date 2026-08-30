<div class="card">
    <div class="card-header"><h2><i class="fas fa-plus-circle"></i> Ajouter un lien</h2></div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Ajoutez votre lien HTTPS. Il sera vérifié automatiquement avant d'être ajouté au classement.
            Plus votre lien a de points, plus il apparaît en haut de la liste !
        </div>
        <form method="POST" action="links/add" class="form-vertical">
            <?= \App\Core\View::csrfField() ?>
            <div class="form-group">
                <label for="url"><i class="fas fa-globe"></i> URL du site (HTTPS obligatoire)</label>
                <input type="url" id="url" name="url" class="form-control" required placeholder="https://www.monsite.com" pattern="https://.*">
            </div>
            <div class="form-group">
                <label for="title"><i class="fas fa-heading"></i> Titre (optionnel)</label>
                <input type="text" id="title" name="title" class="form-control" maxlength="255" placeholder="Mon super site">
            </div>
            <div class="form-group">
                <label for="points"><i class="fas fa-coins"></i> Points à attribuer au lien (optionnel)</label>
                <input type="number" id="points" name="points" class="form-control" min="0" max="<?= (int) ($user_points ?? 0) ?>" value="0">
                <small style="display:block;margin-top:.35rem;color:var(--text-muted);">
                    Votre solde : <strong><?= format_number((int) ($user_points ?? 0)) ?> points</strong>.
                    Les points attribués sont déduits de votre solde et boostent la position de votre lien dans le classement.
                </small>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-check"></i> Ajouter le lien
            </button>
        </form>
    </div>
</div>
