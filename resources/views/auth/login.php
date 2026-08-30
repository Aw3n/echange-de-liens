<div class="auth-page">
    <div class="auth-card">
        <h1><i class="fas fa-sign-in-alt"></i> <?= tr('Connexion') ?></h1>
        <form method="POST" action="login" class="auth-form">
            <?= \App\Core\View::csrfField() ?>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> <?= tr('Email') ?></label>
                <input type="email" id="email" name="email" class="form-control" required autocomplete="email" placeholder="votre@email.com">
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> <?= tr('Mot de passe') ?></label>
                <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password" placeholder="<?= tr('Votre mot de passe') ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-sign-in-alt"></i> <?= tr('Se connecter') ?>
            </button>
        </form>

        <div class="auth-footer">
            <p><a href="forgot-password"><?= tr('Mot de passe oublié ?') ?></a></p>
            <p><?= tr('Pas encore de compte ?') ?> <a href="register"><?= tr('Inscrivez-vous') ?></a></p>
        </div>
    </div>
</div>
