<div class="card"><div class="card-header"><h2><i class="fas fa-question-circle"></i> FAQ</h2></div>
<div class="card-body"><?= $page_content ?></div></div>

<?php if (!empty($affiliate_enabled)): ?>
<!-- Bloc affiliation : affiché uniquement quand le module est activé par l'admin -->
<div class="card mt-2">
    <div class="card-header"><h2><i class="fas fa-hand-holding-usd"></i> <?= tr('Affiliation — Questions fréquentes') ?></h2></div>
    <div class="card-body">
        <?php
        // Q/R du module d'affiliation : libellés bilingues via tr(),
        // montants dynamiques (barème et minimum configurés par l'admin)
        $faqTierParts = [];
        $faqTiersSorted = $affiliate_tiers;
        usort($faqTiersSorted, fn($a, $b) => $a['min'] <=> $b['min']);
        foreach ($faqTiersSorted as $tier) {
            $faqTierParts[] = tr('commande ≥') . ' ' . number_format((float) $tier['min'], 0, ',', ' ') . ' € → '
                . rtrim(rtrim(number_format((float) $tier['pct'], 2, ',', ' '), '0'), ',') . ' %';
        }
        $faqRates = implode(' · ', $faqTierParts);
        $faqMin = number_format((float) $affiliate_min_payout, 2, ',', ' ');
        ?>
        <details style="margin-bottom:.75rem;">
            <summary style="cursor:pointer;font-weight:600;"><i class="fas fa-chevron-right text-muted"></i> <?= tr('Comment fonctionne l\'affiliation ?') ?></summary>
            <p style="margin:.5rem 0 0 1rem;"><?= tr('Partagez votre lien de parrainage : quand un filleul inscrit via votre lien passe une commande validée (PayPal ou cryptomonnaie), vous gagnez un pourcentage du montant.') ?></p>
        </details>
        <details style="margin-bottom:.75rem;">
            <summary style="cursor:pointer;font-weight:600;"><i class="fas fa-chevron-right text-muted"></i> <?= tr('Quel pourcentage puis-je gagner ?') ?></summary>
            <?php if ($faqRates !== ''): ?>
                <p style="margin:.5rem 0 0 1rem;"><?= tr('Barème :') ?> <?= e($faqRates) ?></p>
            <?php endif; ?>
            <p style="margin:.25rem 0 0 1rem;"><?= tr('La commission est calculée sur le montant de la commande une fois celle-ci validée.') ?></p>
        </details>
        <details style="margin-bottom:.75rem;">
            <summary style="cursor:pointer;font-weight:600;"><i class="fas fa-chevron-right text-muted"></i> <?= tr('Quand et comment suis-je payé ?') ?></summary>
            <p style="margin:.5rem 0 0 1rem;"><?= tr('Dès que votre solde disponible atteint le minimum de paiement, l\'administrateur peut procéder à votre règlement.') ?>
                <strong><?= tr('Minimum de paiement :') ?></strong> <?= e($faqMin) ?> €</p>
        </details>
        <details style="margin-bottom:.75rem;">
            <summary style="cursor:pointer;font-weight:600;"><i class="fas fa-chevron-right text-muted"></i> <?= tr('Où renseigner mon adresse de paiement ?') ?></summary>
            <p style="margin:.5rem 0 0 1rem;"><?= tr('Sur la page « Mon compte » : PayPal et adresses crypto (Xelis, Kaspa, Firo, Verge, Pepecoin, Vertcoin, DragonX, Monero).') ?>
                <?= tr('Chaque adresse est vérifiée et reste privée : visible uniquement par vous et l\'administrateur.') ?>
                <a href="<?= e(base_path('account')) ?>"><?= tr('Mes adresses de paiement') ?></a></p>
        </details>
        <details style="margin-bottom:.75rem;">
            <summary style="cursor:pointer;font-weight:600;"><i class="fas fa-chevron-right text-muted"></i> <?= tr('Qu\'est-ce qu\'une commande validée ?') ?></summary>
            <p style="margin:.5rem 0 0 1rem;"><?= tr('Un paiement confirmé sur la blockchain (seuil de confirmations atteint) ou confirmé par l\'administrateur pour PayPal.') ?>
                <?= tr('Un paiement simplement détecté ne génère jamais de commission.') ?></p>
        </details>
    </div>
</div>
<?php endif; ?>
