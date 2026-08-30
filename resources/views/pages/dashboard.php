<!-- Tableau de bord -->
<h1><i class="fas fa-tachometer-alt"></i> Tableau de bord</h1>
<p class="page-subtitle">Bienvenue, <strong><?= e($user_data['username'] ?? '') ?></strong></p>

<!-- Cards de stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-primary"><i class="fas fa-star"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?= format_number((int) ($user_data['points'] ?? 0)) ?></span>
            <span class="stat-label">Points disponibles</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success"><i class="fas fa-link"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?= $total_links ?></span>
            <span class="stat-label">Mes liens</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-info"><i class="fas fa-eye"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?= format_number((int) ($visit_stats['total_visits'] ?? 0)) ?></span>
            <span class="stat-label">Visites effectuées</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <span class="stat-number"><?= $referral_count ?></span>
            <span class="stat-label">Filleuls</span>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="quick-actions">
    <a href="links/add" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter un lien</a>
    <a href="vip" class="btn btn-warning"><i class="fas fa-crown"></i> Acheter des points</a>
    <a href="bonus" class="btn btn-success"><i class="fas fa-gift"></i> Page Bonus (+5 pts)</a>
</div>

<!-- Parrainage -->
<div class="card">
    <div class="card-header"><h3><i class="fas fa-user-friends"></i> Parrainage</h3></div>
    <div class="card-body">
        <p>Partagez votre lien de parrainage et gagnez <strong>1 point par visite IP unique</strong> (1 visite par IP par 24h) + <strong>1000 points bonus</strong> par filleul inscrit !</p>
        <p class="text-muted"><i class="fas fa-chart-line"></i> <strong><?= format_number($referral_visit_count ?? 0) ?></strong> visites de parrainage enregistrées | <strong><?= $referral_count ?></strong> filleuls inscrits</p>
        <div class="input-group">
            <input type="text" class="form-control" value="<?= e($referral_link) ?>" id="referralLink" readonly>
            <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('referralLink').value).then(()=>alert('Lien copié !'))">
                <i class="fas fa-copy"></i> <?= tr('Copier') ?>
            </button>
        </div>
        <p class="text-muted" style="margin:0.75rem 0 0.25rem;"><i class="fas fa-user-plus"></i> <?= tr('Lien direct vers l\'inscription (même principe : 1 point par visite IP unique + bonus à l\'inscription)') ?> :</p>
        <div class="input-group">
            <input type="text" class="form-control" value="<?= e($referral_register_link) ?>" id="referralRegisterLink" readonly>
            <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('referralRegisterLink').value).then(()=>alert('Lien copié !'))">
                <i class="fas fa-copy"></i> <?= tr('Copier') ?>
            </button>
        </div>
        <p class="text-muted" style="margin:0.75rem 0 0.25rem;"><i class="fas fa-question-circle"></i> <?= tr('Liens de parrainage vers la FAQ (même principe : 1 point par visite IP unique + bonus à l\'inscription)') ?> :</p>
        <div class="input-group">
            <input type="text" class="form-control" value="<?= e($referral_faq_link_fr ?? '') ?>" id="referralFaqFr" readonly>
            <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('referralFaqFr').value).then(()=>alert('Lien copié !'))">
                <i class="fas fa-copy"></i> <?= tr('Copier') ?>
            </button>
        </div>
        <div class="input-group">
            <input type="text" class="form-control" value="<?= e($referral_faq_link_en ?? '') ?>" id="referralFaqEn" readonly>
            <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('referralFaqEn').value).then(()=>alert('Lien copié !'))">
                <i class="fas fa-copy"></i> <?= tr('Copier') ?>
            </button>
        </div>
    </div>
</div>

<?php if (!empty($affiliate_enabled)): ?>
<!-- Affiliation : mes gains -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-hand-holding-usd"></i> <?= tr('Affiliation — mes gains') ?></h3>
        <a href="account" class="btn btn-outline btn-sm"><i class="fas fa-wallet"></i> <?= tr('Mes adresses de paiement') ?></a>
    </div>
    <div class="card-body">
        <p><?= tr('Chaque commande validée (PayPal ou crypto) passée par un filleul recruté via votre lien vous rapporte une commission.') ?></p>
        <?php if (!empty($affiliate_tiers)): ?>
            <?php
            $tiersSorted = $affiliate_tiers;
            usort($tiersSorted, fn($a, $b) => $a['min'] <=> $b['min']);
            $tierParts = [];
            foreach ($tiersSorted as $tier) {
                $tierParts[] = tr('commande ≥') . ' ' . number_format((float) $tier['min'], 0, ',', ' ') . ' € → '
                    . rtrim(rtrim(number_format((float) $tier['pct'], 2, ',', ' '), '0'), ',') . ' %';
            }
            ?>
            <p class="text-muted"><i class="fas fa-percentage"></i> <?= tr('Barème :') ?> <?= e(implode(' · ', $tierParts)) ?> | <?= tr('Paiement possible à partir de') ?> <?= e(number_format((float) $affiliate_min_payout, 2, ',', ' ')) ?> €.</p>
        <?php endif; ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-info"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <span class="stat-number"><?= (int) $affiliate_stats['referrals'] ?></span>
                    <span class="stat-label"><?= tr('Filleuls') ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-primary"><i class="fas fa-coins"></i></div>
                <div class="stat-info">
                    <span class="stat-number"><?= e(number_format((float) $affiliate_stats['earned'], 2, ',', ' ')) ?> €</span>
                    <span class="stat-label"><?= tr('Total gagné') ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-warning"><i class="fas fa-paper-plane"></i></div>
                <div class="stat-info">
                    <span class="stat-number"><?= e(number_format((float) $affiliate_stats['paid'], 2, ',', ' ')) ?> €</span>
                    <span class="stat-label"><?= tr('Déjà payé') ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-success"><i class="fas fa-wallet"></i></div>
                <div class="stat-info">
                    <span class="stat-number"><?= e(number_format((float) $affiliate_stats['available'], 2, ',', ' ')) ?> €</span>
                    <span class="stat-label"><?= tr('Disponible') ?></span>
                </div>
            </div>
        </div>
        <?php if ((float) $affiliate_stats['available'] >= (float) $affiliate_min_payout): ?>
            <p class="text-success" style="margin-bottom:0;"><i class="fas fa-check-circle"></i> <?= tr('Votre solde atteint le minimum de paiement : renseignez une adresse de paiement sur la page') ?> <a href="account"><?= tr('Mon compte') ?></a><?= tr(', l\'admin effectuera le versement.') ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Mes liens -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-link"></i> Mes liens</h3>
        <a href="links/add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Ajouter</a>
    </div>
    <div class="card-body">
        <?php if (empty($links)): ?>
            <p class="text-center text-muted">Vous n'avez pas encore de liens. <a href="links/add">Ajoutez-en un !</a></p>
        <?php else: ?>
            <div class="link-list">
                <?php foreach ($links as $link): ?>
                <div class="link-item">
                    <div class="link-info">
                        <a href="<?= e($link['url']) ?>" target="_blank" class="link-url"><?= e(truncate($link['title'] ?? $link['url'], 50)) ?></a>
                        <span class="link-meta">
                            ID: #<?= $link['id'] ?> |
                            <i class="fas fa-eye"></i> <?= (int) $link['total_visits'] ?> visites |
                            Statut: <?= $link['is_active'] ? '<span class="text-success">Actif</span>' : '<span class="text-danger">Inactif</span>' ?>
                        </span>
                    </div>
                    <div class="link-points">
                        <span class="points-badge"><?= format_number((int) $link['points']) ?></span>
                        <span class="points-label">points</span>
                    </div>
                    <div class="link-actions">
                        <form method="POST" action="links/assign-points" class="inline-form">
                            <?= \App\Core\View::csrfField() ?>
                            <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                            <input type="number" name="points" min="1" max="100" value="10" class="form-control form-control-sm" style="width:70px">
                            <button type="submit" class="btn btn-success btn-sm" title="Attribuer des points"><i class="fas fa-arrow-up"></i></button>
                        </form>
                        <form method="POST" action="links/delete/<?= $link['id'] ?>" class="inline-form" onsubmit="return confirm('Supprimer ce lien ? Les points restants seront remboursés.')">
                            <?= \App\Core\View::csrfField() ?>
                            <button type="submit" class="btn btn-danger btn-sm" title="Supprimer"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Historique des points -->
<div class="card">
    <div class="card-header"><h3><i class="fas fa-history"></i> Historique récent</h3></div>
    <div class="card-body">
        <?php if (empty($points_history)): ?>
            <p class="text-center text-muted">Aucun historique.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" id="historyTable">
                    <thead><tr><th>Date</th><th>Type</th><th>Points</th><th>Description</th></tr></thead>
                    <tbody>
                    <?php \App\Core\View::include('partials.history_rows', ['points_history' => $points_history]); ?>
                    </tbody>
                </table>
            </div>
            <?php if (($history_total ?? 0) > count($points_history)): ?>
            <div class="text-center" style="margin-top:0.75rem;">
                <button type="button" class="btn btn-outline" id="historyMoreBtn" data-offset="<?= count($points_history) ?>">
                    <i class="fas fa-chevron-down"></i> <?= tr('Charger plus') ?>
                </button>
            </div>
            <script>
            (function () {
                var btn = document.getElementById('historyMoreBtn');
                if (!btn) { return; }
                var labelMore = '<i class="fas fa-chevron-down"></i> <?= tr('Charger plus') ?>';
                var labelLoading = '<i class="fas fa-spinner fa-spin"></i> <?= tr('Chargement...') ?>';
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    btn.innerHTML = labelLoading;
                    fetch('<?= e(base_path('dashboard/history')) ?>?offset=' + btn.getAttribute('data-offset'))
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data && data.success) {
                                document.querySelector('#historyTable tbody').insertAdjacentHTML('beforeend', data.html);
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
        <?php endif; ?>
    </div>
</div>
