<div class="auth-page">
    <div class="auth-card">
        <h1><i class="fas fa-user-plus"></i> <?= tr('Inscription') ?></h1>
        <form method="POST" action="register" class="auth-form">
            <?= \App\Core\View::csrfField() ?>
            <?php if (!empty($ref)): ?>
                <input type="hidden" name="referrer_id" value="<?= e($ref) ?>">
                <div class="alert alert-info"><i class="fas fa-gift"></i> <?= tr('Vous avez été parrainé ! Votre parrain gagne 1 point par visite IP unique et 1000 points bonus à votre inscription.') ?></div>
            <?php endif; ?>

            <?php if (!empty($guest_points) && (int) $guest_points > 0): ?>
                <div class="alert alert-success">
                    <i class="fas fa-piggy-bank"></i>
                    <?= tr('Cagnotte visiteur détectée :') ?> <strong><?= format_number((int) $guest_points) ?> <?= tr('points') ?></strong>.
                    <?= tr('Ils seront automatiquement crédités sur votre compte à l\'inscription.') ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> <?= tr('Nom d\'utilisateur') ?></label>
                <input type="text" id="username" name="username" class="form-control" required minlength="3" maxlength="50" placeholder="<?= tr('Votre pseudo') ?>">
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> <?= tr('Email') ?></label>
                <input type="email" id="email" name="email" class="form-control" required placeholder="votre@email.com">
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> <?= tr('Mot de passe') ?></label>
                <input type="password" id="password" name="password" class="form-control" required minlength="8" placeholder="<?= tr('Minimum 8 caractères') ?>">
            </div>

            <div class="form-group">
                <label for="password_confirm"><i class="fas fa-lock"></i> <?= tr('Confirmer le mot de passe') ?></label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" required placeholder="<?= tr('Retapez le mot de passe') ?>">
            </div>

            <?php if (!empty($captcha_question)): ?>
            <div class="form-group">
                <label for="captcha"><i class="fas fa-robot"></i> <?= tr('Anti-robot :') ?> <?= e($captcha_question) ?></label>
                <input type="number" id="captcha" name="captcha" class="form-control" required autocomplete="off" placeholder="<?= tr('Votre réponse') ?>">
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-user-plus"></i> <?= tr('S\'inscrire') ?>
            </button>
        </form>

        <div class="auth-footer">
            <p><?= tr('Déjà un compte ?') ?> <a href="login"><?= tr('Connectez-vous') ?></a></p>
        </div>
    </div>
</div>
