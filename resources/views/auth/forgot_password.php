<div class="auth-page">
    <div class="auth-card">
        <h1><i class="fas fa-key"></i> Mot de passe oublié</h1>
        <p style="color:#6b7280;font-size:.9rem;margin-bottom:1rem">Entrez votre email : si un compte existe, vous recevrez un lien de réinitialisation.</p>
        <form method="POST" action="forgot-password" class="auth-form">
            <?= \App\Core\View::csrfField() ?>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" class="form-control" required autocomplete="email" placeholder="votre@email.com">
            </div>

            <?php if (!empty($captcha_question)): ?>
            <div class="form-group">
                <label for="captcha"><i class="fas fa-robot"></i> Anti-robot : <?= e($captcha_question) ?></label>
                <input type="number" id="captcha" name="captcha" class="form-control" required autocomplete="off" placeholder="Votre réponse">
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-paper-plane"></i> Envoyer le lien
            </button>
        </form>

        <div class="auth-footer">
            <p><a href="login">&larr; Retour à la connexion</a></p>
        </div>
    </div>
</div>
