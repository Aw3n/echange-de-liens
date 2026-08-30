<div class="card">
    <div class="card-header"><h2><i class="fas fa-image"></i> Ajouter une bannière</h2></div>
    <div class="card-body">
        <div class="alert alert-info"><i class="fas fa-info-circle"></i> Votre bannière sera approuvée par l'administrateur avant d'être visible. Les points attribués seront débités et remboursés si la bannière est refusée.</div>
        <form method="POST" action="bonus/add" class="form-vertical">
            <?= \App\Core\View::csrfField() ?>
            <div class="form-group">
                <label for="title">Titre</label>
                <input type="text" id="title" name="title" class="form-control" required placeholder="Titre de votre bannière">
            </div>
            <div class="form-group">
                <label for="target_url">URL cible (HTTPS)</label>
                <input type="url" id="target_url" name="target_url" class="form-control" required placeholder="https://www.monsite.com" pattern="https://.*">
            </div>
            <div class="form-group">
                <label for="image_url">URL de l'image (GIF, JPEG, PNG - 468x60)</label>
                <input type="url" id="image_url" name="image_url" class="form-control" required placeholder="https://www.monsite.com/banniere.gif">
            </div>
            <div class="form-group">
                <label for="points">Points à attribuer</label>
                <input type="number" id="points" name="points" class="form-control" min="1" required value="10">
                <small class="form-text">Vos points disponibles: <?= format_number(auth()['points']) ?></small>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Soumettre la bannière</button>
        </form>
    </div>
</div>
