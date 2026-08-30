<?php
/** @var array $user_data */
/** @var array $wallet_methods */
/** @var bool $affiliate_enabled */
/** @var array $affiliate_stats */
/** @var array $affiliate_tiers */
/** @var float $affiliate_min_payout */
?>
<h1><i class="fas fa-wallet"></i> <?= tr('Mon compte') ?></h1>
<p class="page-subtitle"><?= tr('Vos informations de paiement et votre activité d\'affiliation') ?></p>

<!-- Adresses de paiement -->
<div class="card">
    <div class="card-header"><h3><i class="fas fa-coins"></i> <?= tr('Mes adresses de paiement') ?></h3></div>
    <div class="card-body">
        <p class="text-muted">
            <?= tr('Renseignez optionnellement vos adresses de réception. Elles servent au paiement de vos gains d\'affiliation. Ces adresses sont privées : visibles uniquement par vous et l\'administrateur. Chaque adresse est vérifiée (format strict de sa crypto) avant enregistrement.') ?>
        </p>
        <form method="POST" action="account">
            <?= \App\Core\View::csrfField() ?>
            <div class="table-responsive"><table class="table">
                <thead><tr><th style="width:25%"><?= tr('Moyen de paiement') ?></th><th><?= tr('Adresse de réception') ?></th></tr></thead>
                <tbody>
                <?php foreach ($wallet_methods as $method => $meta): ?>
                    <tr>
                        <td>
                            <i class="<?= e($meta['icon']) ?>" style="color:<?= e($meta['color']) ?>;"></i>
                            <strong><?= e($meta['label']) ?></strong>
                            <?php if ($method === 'paypal'): ?>
                                <div class="text-muted" style="font-size:.75rem;"><?= tr('Adresse email PayPal') ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="text" name="<?= e($meta['column']) ?>" class="form-control form-control-sm"
                                   value="<?= e((string) ($user_data[$meta['column']] ?? '')) ?>"
                                   placeholder="<?= tr('Optionnel') ?>" autocomplete="off" spellcheck="false"
                                   style="font-family:monospace;font-size:.85rem;">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= tr('Enregistrer mes adresses') ?></button>
        </form>
    </div>
</div>

<!-- Affiliation -->
<?php if ($affiliate_enabled): ?>
<div class="card mt-2">
    <div class="card-header"><h3><i class="fas fa-hand-holding-usd"></i> <?= tr('Affiliation — mes gains') ?></h3></div>
    <div class="card-body">
        <p>
            <?= tr('Partagez votre lien de parrainage : quand un filleul inscrit via votre lien passe une commande validée (PayPal ou cryptomonnaie), vous gagnez un pourcentage du montant.') ?>
        </p>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-success"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <span class="stat-number"><?= (int) $affiliate_stats['referrals'] ?></span>
                    <span class="stat-label"><?= tr('Filleuls') ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-info"><i class="fas fa-euro-sign"></i></div>
                <div class="stat-info">
                    <span class="stat-number"><?= e(number_format($affiliate_stats['earned'], 2, ',', ' ')) ?> €</span>
                    <span class="stat-label"><?= tr('Total gagné') ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-warning"><i class="fas fa-check-double"></i></div>
                <div class="stat-info">
                    <span class="stat-number"><?= e(number_format($affiliate_stats['paid'], 2, ',', ' ')) ?> €</span>
                    <span class="stat-label"><?= tr('Déjà payé') ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-primary"><i class="fas fa-piggy-bank"></i></div>
                <div class="stat-info">
                    <span class="stat-number"><?= e(number_format($affiliate_stats['available'], 2, ',', ' ')) ?> €</span>
                    <span class="stat-label"><?= tr('Disponible') ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($affiliate_tiers)): ?>
        <p class="text-muted" style="margin-top:1rem;">
            <i class="fas fa-percentage"></i> <strong><?= tr('Barème :') ?></strong>
            <?php
            $tierParts = [];
            $tiersSorted = $affiliate_tiers;
            usort($tiersSorted, fn($a, $b) => $a['min'] <=> $b['min']);
            foreach ($tiersSorted as $tier) {
                $tierParts[] = tr('dès') . ' ' . number_format($tier['min'], 0, ',', ' ') . ' ' . tr('€ de commande →') . ' '
                    . rtrim(rtrim(number_format($tier['pct'], 2, ',', ' '), '0'), ',') . ' %';
            }
            ?>
            <?= e(implode(' · ', $tierParts)) ?>
        </p>
        <?php endif; ?>

        <?php if ($affiliate_stats['available'] < $affiliate_min_payout): ?>
            <p class="text-muted" style="margin-top:.5rem;">
                <i class="fas fa-info-circle"></i>
                <?= tr('Le paiement de vos gains est possible à partir de') ?>
                <strong><?= e(number_format($affiliate_min_payout, 2, ',', ' ')) ?> €</strong>
                <?= tr('de solde disponible') ?> <?= tr('(l\'administrateur vous règle via l\'adresse renseignée ci-dessus).') ?>
            </p>
        <?php else: ?>
            <div class="alert alert-success" style="margin-top:1rem;">
                <i class="fas fa-check-circle"></i>
                <?= tr('Votre solde disponible atteint le minimum de paiement') ?>
                (<?= e(number_format($affiliate_min_payout, 2, ',', ' ')) ?> €) :
                <?= tr('l\'administrateur peut procéder à votre règlement.') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($affiliate_stats['commissions'])): ?>
        <h4 style="margin-top:1.5rem;"><i class="fas fa-history"></i> <?= tr('Mes dernières commissions') ?></h4>
        <div class="table-responsive"><table class="table">
            <thead><tr><th><?= tr('Date') ?></th><th><?= tr('Filleul') ?></th><th><?= tr('Commande') ?></th><th>%</th><th><?= tr('Commission') ?></th><th><?= tr('Statut') ?></th></tr></thead>
            <tbody>
            <?php foreach ($affiliate_stats['commissions'] as $c): ?>
                <tr>
                    <td><?= format_date($c['created_at']) ?></td>
                    <td><?= e($c['referred_username'] ?? '') ?></td>
                    <td><?= e(number_format((float) $c['amount_eur'], 2, ',', ' ')) ?> €</td>
                    <td><?= e(rtrim(rtrim(number_format((float) $c['pct'], 2, ',', ' '), '0'), ',')) ?> %</td>
                    <td class="text-success"><strong>+<?= e(number_format((float) $c['commission_eur'], 2, ',', ' ')) ?> €</strong></td>
                    <td>
                        <?php if ($c['status'] === 'paid'): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> <?= tr('Payée') ?></span>
                        <?php else: ?>
                            <span class="badge badge-warning"><i class="fas fa-clock"></i> <?= tr('Acquise') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
