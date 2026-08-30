<?php
/** @var bool $affiliate_enabled */
/** @var array $referrers */
/** @var array $payouts */
/** @var array $tiers */
/** @var float $min_payout */
?>
<h1><i class="fas fa-hand-holding-usd"></i> Affiliation</h1>
<p>
    <?= tr('Module optionnel : un parrain gagne un pourcentage de chaque commande validée (PayPal ou crypto) passée par ses filleuls.') ?>
    <?php if ($affiliate_enabled): ?>
        <span class="badge badge-success"><i class="fas fa-check"></i> <?= tr('Module actif') ?></span>
    <?php else: ?>
        <span class="badge badge-danger"><i class="fas fa-times"></i> <?= tr('Module inactif') ?></span>
    <?php endif; ?>
    <a href="admin/settings" class="btn btn-outline btn-sm"><i class="fas fa-sliders-h"></i> <?= tr('Configurer (barème, activation)') ?></a>
</p>

<?php if (!empty($tiers)): ?>
<div class="card"><div class="card-body">
    <strong><i class="fas fa-percentage"></i> <?= tr('Barème actuel :') ?></strong>
    <?php
    $tiersSorted = $tiers;
    usort($tiersSorted, fn($a, $b) => $a['min'] <=> $b['min']);
    $tierParts = [];
    foreach ($tiersSorted as $tier) {
        $tierParts[] = tr('commande ≥') . ' ' . number_format($tier['min'], 0, ',', ' ') . ' € → '
            . rtrim(rtrim(number_format($tier['pct'], 2, ',', ' '), '0'), ',') . ' %';
    }
    ?>
    <?= e(implode(' · ', $tierParts)) ?>
    &nbsp;|&nbsp; <strong><?= tr('Minimum de paiement :') ?></strong> <?= e(number_format($min_payout, 2, ',', ' ')) ?> €
</div></div>
<?php endif; ?>

<!-- Parrains et soldes -->
<div class="card mt-1"><div class="card-body">
<h3><i class="fas fa-users"></i> <?= tr('Parrains') ?></h3>
<?php if (empty($referrers)): ?>
    <p class="text-muted"><?= tr('Aucun parrain avec filleul ou commission pour le moment.') ?></p>
<?php else: ?>
<div class="table-responsive"><table class="table">
<thead><tr>
    <th><?= tr('Parrain') ?></th><th><?= tr('Filleuls') ?></th><th><?= tr('Commandes') ?></th><th><?= tr('Gagné') ?></th><th><?= tr('Payé') ?></th>
    <th><?= tr('Disponible') ?></th><th><?= tr('Moyens renseignés') ?></th><th><?= tr('Paiement') ?></th>
</tr></thead>
<tbody>
<?php foreach ($referrers as $r): ?>
    <tr>
        <td><strong><?= e($r['username']) ?></strong><br><small class="text-muted">#<?= (int) $r['id'] ?> · <?= e($r['email']) ?></small></td>
        <td><?= (int) $r['referrals'] ?></td>
        <td><?= (int) $r['nb_commissions'] ?></td>
        <td><?= e(number_format((float) $r['earned'], 2, ',', ' ')) ?> €</td>
        <td><?= e(number_format((float) $r['paid'], 2, ',', ' ')) ?> €</td>
        <td><strong class="<?= (float) $r['available'] >= $min_payout ? 'text-success' : 'text-muted' ?>">
            <?= e(number_format((float) $r['available'], 2, ',', ' ')) ?> €</strong></td>
        <td style="font-size:.85rem;">
            <?php if (empty($r['payout_methods'])): ?>
                <span class="text-muted"><?= tr('Aucun') ?></span>
            <?php else: ?>
                <?php foreach ($r['payout_methods'] as $m => $pm): ?>
                    <div title="<?= e($pm['address']) ?>">
                        <i class="<?= e($pm['icon']) ?>" style="color:<?= e($pm['color']) ?>;"></i>
                        <?= e($pm['label']) ?> :
                        <code style="font-size:.75rem;"><?= e(strlen($pm['address']) > 14 ? substr($pm['address'], 0, 10) . '…' : $pm['address']) ?></code>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </td>
        <td>
            <?php if ((float) $r['available'] >= $min_payout && !empty($r['payout_methods'])): ?>
                <?php $confirmMsg = tr('Payer') . ' ' . number_format((float) $r['available'], 2, ',', ' ') . ' '
                    . tr('€ à') . ' ' . $r['username'] . " ?\n" . tr('Effectuez le transfert réel AVANT de valider.'); ?>
                <form method="POST" action="admin/affiliation/payout"
                      data-msg="<?= e($confirmMsg) ?>"
                      onsubmit="return confirm(this.dataset.msg);">
                    <?= \App\Core\View::csrfField() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $r['id'] ?>">
                    <select name="method" class="form-control form-control-sm" style="width:auto;display:inline-block;">
                        <?php foreach ($r['payout_methods'] as $m => $pm): ?>
                            <option value="<?= e($m) ?>"><?= e($pm['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-paper-plane"></i> <?= tr('Payer') ?></button>
                </form>
            <?php elseif ((float) $r['available'] < $min_payout): ?>
                <small class="text-muted"><?= tr('Solde <') ?> <?= e(number_format($min_payout, 0, ',', ' ')) ?> €</small>
            <?php else: ?>
                <small class="text-muted"><?= tr('Aucun moyen renseigné') ?></small>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div></div>

<!-- Historique des paiements -->
<div class="card mt-1"><div class="card-body">
<h3><i class="fas fa-history"></i> <?= tr('Paiements effectués') ?></h3>
<?php if (empty($payouts)): ?>
    <p class="text-muted"><?= tr('Aucun paiement d\'affiliation enregistré.') ?></p>
<?php else: ?>
<div class="table-responsive"><table class="table">
<thead><tr><th><?= tr('Date') ?></th><th><?= tr('Utilisateur') ?></th><th><?= tr('Montant') ?></th><th><?= tr('Moyen') ?></th><th><?= tr('Adresse') ?></th><th><?= tr('Note') ?></th></tr></thead>
<tbody>
<?php foreach ($payouts as $p): ?>
    <tr>
        <td><?= format_date($p['created_at']) ?></td>
        <td><?= e($p['username']) ?></td>
        <td><strong><?= e(number_format((float) $p['amount_eur'], 2, ',', ' ')) ?> €</strong></td>
        <td><span class="badge"><?= e($p['method']) ?></span></td>
        <td><code style="font-size:.75rem;"><?= e($p['address']) ?></code></td>
        <td class="text-muted" style="font-size:.85rem;"><?= e((string) ($p['admin_note'] ?? '')) ?></td>
    </tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div></div>
