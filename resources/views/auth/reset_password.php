<div class="auth-page">
    <div class="auth-card">
        <h1><i class="fas fa-lock"></i> Nouveau mot de passe</h1>
        <p style="color:#6b7280;font-size:.9rem;margin-bottom:1rem">Choisissez un nouveau mot de passe pour votre compte.</p>
        <form method="POST" action="reset-password" class="auth-form">
            <?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Nouveau mot de passe</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="8" autocomplete="new-password" placeholder="Minimum 8 caractères">
            </div>

            <div class="form-group">
                <label for="password_confirm"><i class="fas fa-lock"></i> Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" required autocomplete="new-password" placeholder="Retapez le mot de passe">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-save"></i> Enregistrer le mot de passe
            </button>
        </form>

        <div class="auth-footer">
            <p><a href="login">&larr; Retour à la connexion</a></p>
        </div>
    </div>
</div>
