<h1><i class="fas fa-shopping-cart"></i> Achats VIP</h1>

<?php if (!empty($purchases)): ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
    <form method="POST" action="admin/purchases/clear" onsubmit="return confirm('Vider tout l\'historique des achats ?\nLes achats encore en attente seront conservés.');">
        <?= \App\Core\View::csrfField() ?>
        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Vider l'historique</button>
    </form>
</div>
<?php endif; ?>

<?php
$pendingCount = 0;
$xelisPending = [];
$kaspaPending = [];
$firoPending = [];
$vergePending = [];
$pepecoinPending = [];
$vertcoinPending = [];
$dragonxPending = [];
$moneroPending = [];
foreach ($purchases as $p) {
    if ($p['status'] === 'pending') $pendingCount++;
    if ($p['payment_method'] === 'xelis' && $p['status'] === 'pending') $xelisPending[] = $p;
    if ($p['payment_method'] === 'kaspa' && $p['status'] === 'pending') $kaspaPending[] = $p;
    if ($p['payment_method'] === 'firo' && $p['status'] === 'pending') $firoPending[] = $p;
    if ($p['payment_method'] === 'verge' && $p['status'] === 'pending') $vergePending[] = $p;
    if ($p['payment_method'] === 'pepecoin' && $p['status'] === 'pending') $pepecoinPending[] = $p;
    if ($p['payment_method'] === 'vertcoin' && $p['status'] === 'pending') $vertcoinPending[] = $p;
    if ($p['payment_method'] === 'dragonx' && $p['status'] === 'pending') $dragonxPending[] = $p;
    if ($p['payment_method'] === 'monero' && $p['status'] === 'pending') $moneroPending[] = $p;
}
?>

<?php if ($pendingCount > 0): ?>
<div class="alert alert-warning" style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
    <span><i class="fas fa-exclamation-triangle"></i> <strong><?= $pendingCount ?></strong> achat(s) en attente de confirmation.</span>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <?php if (!empty($xelisPending)): ?>
        <a href="/admin/xelis/check" class="btn btn-sm btn-primary" title="Vérifier automatiquement les paiements XELIS sur la blockchain">
            <i class="fas fa-sync-alt"></i> XELIS (<?= count($xelisPending) ?>)
        </a>
        <?php endif; ?>
        <?php if (!empty($kaspaPending)): ?>
        <a href="/admin/kaspa/check" class="btn btn-sm" style="background:#4fc3f7;color:#000;" title="Vérifier automatiquement les paiements Kaspa sur la blockchain">
            <i class="fas fa-sync-alt"></i> Kaspa (<?= count($kaspaPending) ?>)
        </a>
        <?php endif; ?>
        <?php if (!empty($firoPending)): ?>
        <a href="/admin/firo/check" class="btn btn-sm" style="background:#e65100;color:#fff;" title="Vérifier automatiquement les paiements Firo sur la blockchain">
            <i class="fas fa-sync-alt"></i> Firo (<?= count($firoPending) ?>)
        </a>
        <?php endif; ?>
        <?php if (!empty($vergePending)): ?>
        <a href="/admin/verge/check" class="btn btn-sm" style="background:#2b1f6b;color:#fff;" title="Vérifier automatiquement les paiements Verge sur la blockchain">
            <i class="fas fa-sync-alt"></i> Verge (<?= count($vergePending) ?>)
        </a>
        <?php endif; ?>
        <?php if (!empty($pepecoinPending)): ?>
        <a href="/admin/pepecoin/check" class="btn btn-sm" style="background:#2f9e44;color:#fff;" title="Vérifier automatiquement les paiements Pepecoin sur la blockchain">
            <i class="fas fa-sync-alt"></i> Pepecoin (<?= count($pepecoinPending) ?>)
        </a>
        <?php endif; ?>
        <?php if (!empty($vertcoinPending)): ?>
        <a href="/admin/vertcoin/check" class="btn btn-sm" style="background:#048657;color:#fff;" title="Vérifier automatiquement les paiements Vertcoin sur la blockchain">
            <i class="fas fa-sync-alt"></i> Vertcoin (<?= count($vertcoinPending) ?>)
        </a>
        <?php endif; ?>
        <?php if (!empty($dragonxPending)): ?>
        <a href="/admin/dragonx/check" class="btn btn-sm" style="background:#e8590c;color:#fff;" title="Vérifier automatiquement les paiements DragonX sur la blockchain">
            <i class="fas fa-sync-alt"></i> DragonX (<?= count($dragonxPending) ?>)
        </a>
        <?php endif; ?>
        <?php if (!empty($moneroPending)): ?>
        <a href="/admin/monero/check" class="btn btn-sm" style="background:#ff6600;color:#fff;" title="Vérifier automatiquement les paiements Monero sur la blockchain">
            <i class="fas fa-sync-alt"></i> Monero (<?= count($moneroPending) ?>)
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card"><div class="card-body"><div class="table-responsive"><table class="table">
<thead><tr>
    <th>ID</th>
    <th>Utilisateur</th>
    <th>Points</th>
    <th>Montant</th>
    <th>Méthode</th>
    <th>Statut</th>
    <th>Détails TX</th>
    <th>Date</th>
    <th>Actions</th>
</tr></thead>
<tbody>
<?php foreach ($purchases as $p): ?>
<tr>
    <td>#<?= $p['id'] ?></td>
    <td><?= e($p['username'] ?? '') ?><br><small style="color:var(--text-muted);"><?= e($p['email'] ?? '') ?></small></td>
    <td><strong><?= format_number((int)$p['points_purchased']) ?></strong></td>
    <td>
        <?= e($p['amount_eur']) ?>€
        <?php if (!empty($p['amount_xelis']) && $p['payment_method'] === 'xelis'): ?>
            <br><small style="color:var(--warning);"><?= e(number_format((float)$p['amount_xelis'], 4, '.', ' ')) ?> XEL</small>
        <?php elseif (!empty($p['amount_xelis']) && $p['payment_method'] === 'kaspa'): ?>
            <br><small style="color:#4fc3f7;"><?= e(number_format((float)$p['amount_xelis'], 2, '.', ' ')) ?> KAS</small>
        <?php elseif (!empty($p['amount_xelis']) && $p['payment_method'] === 'firo'): ?>
            <br><small style="color:#e65100;"><?= e(number_format((float)$p['amount_xelis'], 4, '.', ' ')) ?> FIRO</small>
        <?php elseif (!empty($p['amount_xelis']) && $p['payment_method'] === 'verge'): ?>
            <br><small style="color:#2b1f6b;"><?= e(number_format((float)$p['amount_xelis'], 2, '.', ' ')) ?> XVG</small>
        <?php elseif (!empty($p['amount_xelis']) && $p['payment_method'] === 'pepecoin'): ?>
            <br><small style="color:#2f9e44;"><?= e(number_format((float)$p['amount_xelis'], 2, '.', ' ')) ?> PEPE</small>
        <?php elseif (!empty($p['amount_xelis']) && $p['payment_method'] === 'vertcoin'): ?>
            <br><small style="color:#048657;"><?= e(number_format((float)$p['amount_xelis'], 2, '.', ' ')) ?> VTC</small>
        <?php elseif (!empty($p['amount_xelis']) && $p['payment_method'] === 'dragonx'): ?>
            <br><small style="color:#e8590c;"><?= e(number_format((float)$p['amount_xelis'], 2, '.', ' ')) ?> DRGX</small>
        <?php elseif (!empty($p['amount_xelis']) && $p['payment_method'] === 'monero'): ?>
            <br><small style="color:#ff6600;"><?= e(number_format((float)$p['amount_xelis'], 8, '.', ' ')) ?> XMR</small>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($p['payment_method'] === 'xelis'): ?>
            <span class="badge" style="background:var(--warning);color:#000;"><i class="fas fa-coins"></i> XELIS</span>
        <?php elseif ($p['payment_method'] === 'kaspa'): ?>
            <span class="badge" style="background:#4fc3f7;color:#000;"><i class="fas fa-gem"></i> KASPA</span>
        <?php elseif ($p['payment_method'] === 'firo'): ?>
            <span class="badge" style="background:#e65100;color:#fff;"><i class="fas fa-shield-alt"></i> FIRO</span>
        <?php elseif ($p['payment_method'] === 'verge'): ?>
            <span class="badge" style="background:#2b1f6b;color:#fff;"><i class="fas fa-lock"></i> VERGE</span>
        <?php elseif ($p['payment_method'] === 'pepecoin'): ?>
            <span class="badge" style="background:#2f9e44;color:#fff;"><i class="fas fa-frog"></i> PEPECOIN</span>
        <?php elseif ($p['payment_method'] === 'vertcoin'): ?>
            <span class="badge" style="background:#048657;color:#fff;"><i class="fas fa-leaf"></i> VERTCOIN</span>
        <?php elseif ($p['payment_method'] === 'dragonx'): ?>
            <span class="badge" style="background:#e8590c;color:#fff;"><i class="fas fa-dragon"></i> DRAGONX</span>
        <?php elseif ($p['payment_method'] === 'monero'): ?>
            <span class="badge" style="background:#ff6600;color:#fff;"><i class="fas fa-user-secret"></i> MONERO</span>
        <?php else: ?>
            <span class="badge" style="background:#0070ba;color:#fff;"><i class="fab fa-paypal"></i> PayPal</span>
        <?php endif; ?>
    </td>
    <td>
        <?php
        $badgeClass = match($p['status']) {
            'pending' => 'badge-warning',
            'completed', 'confirmed' => 'badge-success',
            'cancelled' => 'badge-danger',
            'refunded' => 'badge-info',
            default => '',
        };
        ?>
        <span class="badge <?= $badgeClass ?>"><?= e($p['status']) ?></span>
        <?php if (!empty($p['confirmed_at'])): ?>
            <br><small style="color:var(--text-muted);"><?= format_date($p['confirmed_at']) ?></small>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($p['payment_method'] === 'xelis'): ?>
            <?php if (!empty($p['transaction_id'])): ?>
                <div style="font-family:monospace;font-size:.8rem;word-break:break-all;max-width:200px;">
                    <strong>TX:</strong> <?= e(substr($p['transaction_id'], 0, 12)) ?>...
                </div>
                <a href="https://explorer.xelis.io/transaction/<?= e($p['transaction_id']) ?>"
                   target="_blank" rel="noopener" style="font-size:.8rem;">
                    <i class="fas fa-external-link-alt"></i> Explorer
                </a>
            <?php else: ?>
                <small style="color:var(--text-muted);">Aucune TX soumise</small>
            <?php endif; ?>
            <?php if (!empty($p['payment_address'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;" title="<?= e($p['payment_address']) ?>">
                    Addr: <?= e(substr($p['payment_address'], 0, 20)) ?>...
                </small>
            <?php endif; ?>
            <?php if (!empty($p['expires_at'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;">
                    Expire: <?= format_date($p['expires_at']) ?>
                </small>
            <?php endif; ?>
        <?php elseif ($p['payment_method'] === 'kaspa'): ?>
            <?php if (!empty($p['transaction_id'])): ?>
                <div style="font-family:monospace;font-size:.8rem;word-break:break-all;max-width:200px;">
                    <strong>TX:</strong> <?= e(substr($p['transaction_id'], 0, 12)) ?>...
                </div>
                <a href="https://explorer.kaspa.org/txs/<?= e($p['transaction_id']) ?>"
                   target="_blank" rel="noopener" style="font-size:.8rem;">
                    <i class="fas fa-external-link-alt"></i> Explorer
                </a>
            <?php else: ?>
                <small style="color:var(--text-muted);">Aucune TX soumise</small>
            <?php endif; ?>
            <?php if (!empty($p['payment_address'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;" title="<?= e($p['payment_address']) ?>">
                    Addr: <?= e(substr($p['payment_address'], 0, 20)) ?>...
                </small>
            <?php endif; ?>
            <?php if (!empty($p['expires_at'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;">
                    Expire: <?= format_date($p['expires_at']) ?>
                </small>
            <?php endif; ?>
        <?php elseif ($p['payment_method'] === 'firo'): ?>
            <?php if (!empty($p['transaction_id'])): ?>
                <div style="font-family:monospace;font-size:.8rem;word-break:break-all;max-width:200px;">
                    <strong>TX:</strong> <?= e(substr($p['transaction_id'], 0, 12)) ?>...
                </div>
                <a href="https://explorer.firo.org/tx/<?= e($p['transaction_id']) ?>"
                   target="_blank" rel="noopener" style="font-size:.8rem;">
                    <i class="fas fa-external-link-alt"></i> Explorer
                </a>
            <?php else: ?>
                <small style="color:var(--text-muted);">Aucune TX soumise</small>
            <?php endif; ?>
            <?php if (!empty($p['payment_address'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;" title="<?= e($p['payment_address']) ?>">
                    Addr: <?= e(substr($p['payment_address'], 0, 20)) ?>...
                </small>
            <?php endif; ?>
            <?php if (!empty($p['expires_at'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;">
                    Expire: <?= format_date($p['expires_at']) ?>
                </small>
            <?php endif; ?>
        <?php elseif ($p['payment_method'] === 'verge'): ?>
            <?php if (!empty($p['transaction_id'])): ?>
                <div style="font-family:monospace;font-size:.8rem;word-break:break-all;max-width:200px;">
                    <strong>TX:</strong> <?= e(substr($p['transaction_id'], 0, 12)) ?>...
                </div>
                <a href="https://xvg-blockbook.nownodes.io/tx/<?= e($p['transaction_id']) ?>"
                   target="_blank" rel="noopener" style="font-size:.8rem;">
                    <i class="fas fa-external-link-alt"></i> Explorer
                </a>
            <?php else: ?>
                <small style="color:var(--text-muted);">Aucune TX soumise</small>
            <?php endif; ?>
            <?php if (!empty($p['payment_address'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;" title="<?= e($p['payment_address']) ?>">
                    Addr: <?= e(substr($p['payment_address'], 0, 20)) ?>...
                </small>
            <?php endif; ?>
            <?php if (!empty($p['expires_at'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;">
                    Expire: <?= format_date($p['expires_at']) ?>
                </small>
            <?php endif; ?>
        <?php elseif ($p['payment_method'] === 'pepecoin'): ?>
            <?php if (!empty($p['transaction_id'])): ?>
                <div style="font-family:monospace;font-size:.8rem;word-break:break-all;max-width:200px;">
                    <strong>TX:</strong> <?= e(substr($p['transaction_id'], 0, 12)) ?>...
                </div>
                <a href="https://www.pepeblocks.com/tx/<?= e($p['transaction_id']) ?>"
                   target="_blank" rel="noopener" style="font-size:.8rem;">
                    <i class="fas fa-external-link-alt"></i> Explorer
                </a>
            <?php else: ?>
                <small style="color:var(--text-muted);">Aucune TX soumise</small>
            <?php endif; ?>
            <?php if (!empty($p['payment_address'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;" title="<?= e($p['payment_address']) ?>">
                    Addr: <?= e(substr($p['payment_address'], 0, 20)) ?>...
                </small>
            <?php endif; ?>
            <?php if (!empty($p['expires_at'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;">
                    Expire: <?= format_date($p['expires_at']) ?>
                </small>
            <?php endif; ?>
        <?php elseif ($p['payment_method'] === 'vertcoin'): ?>
            <?php if (!empty($p['transaction_id'])): ?>
                <div style="font-family:monospace;font-size:.8rem;word-break:break-all;max-width:200px;">
                    <strong>TX:</strong> <?= e(substr($p['transaction_id'], 0, 12)) ?>...
                </div>
                <a href="https://blockbook.vertcoin.io/tx/<?= e($p['transaction_id']) ?>"
                   target="_blank" rel="noopener" style="font-size:.8rem;">
                    <i class="fas fa-external-link-alt"></i> Explorer
                </a>
            <?php else: ?>
                <small style="color:var(--text-muted);">Aucune TX soumise</small>
            <?php endif; ?>
            <?php if (!empty($p['payment_address'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;" title="<?= e($p['payment_address']) ?>">
                    Addr: <?= e(substr($p['payment_address'], 0, 20)) ?>...
                </small>
            <?php endif; ?>
            <?php if (!empty($p['expires_at'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;">
                    Expire: <?= format_date($p['expires_at']) ?>
                </small>
            <?php endif; ?>
        <?php elseif ($p['payment_method'] === 'dragonx'): ?>
            <?php if (!empty($p['transaction_id'])): ?>
                <div style="font-family:monospace;font-size:.8rem;word-break:break-all;max-width:200px;">
                    <strong>TX:</strong> <?= e(substr($p['transaction_id'], 0, 12)) ?>...
                </div>
                <a href="https://explorer.dragonx.is/tx/<?= e($p['transaction_id']) ?>"
                   target="_blank" rel="noopener" style="font-size:.8rem;">
                    <i class="fas fa-external-link-alt"></i> Explorer
                </a>
            <?php else: ?>
                <small style="color:var(--text-muted);">Aucune TX soumise</small>
            <?php endif; ?>
            <?php if (!empty($p['payment_address'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;" title="<?= e($p['payment_address']) ?>">
                    Addr: <?= e(substr($p['payment_address'], 0, 20)) ?>...
                </small>
            <?php endif; ?>
            <?php if (!empty($p['expires_at'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;">
                    Expire: <?= format_date($p['expires_at']) ?>
                </small>
            <?php endif; ?>
        <?php elseif ($p['payment_method'] === 'monero'): ?>
            <?php if (!empty($p['transaction_id'])): ?>
                <div style="font-family:monospace;font-size:.8rem;word-break:break-all;max-width:200px;">
                    <strong>TX:</strong> <?= e(substr($p['transaction_id'], 0, 12)) ?>...
                </div>
                <a href="<?= e($monero_explorer_url) ?>/tx/<?= e($p['transaction_id']) ?>"
                   target="_blank" rel="noopener" style="font-size:.8rem;">
                    <i class="fas fa-external-link-alt"></i> Explorer
                </a>
            <?php else: ?>
                <small style="color:var(--text-muted);">Aucune TX soumise</small>
            <?php endif; ?>
            <?php if (!empty($p['payment_address'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;" title="<?= e($p['payment_address']) ?>">
                    Addr: <?= e(substr($p['payment_address'], 0, 16)) ?>...
                </small>
            <?php endif; ?>
            <?php if (!empty($p['admin_notes']) && preg_match('/payment_id:([a-f0-9]+)/', $p['admin_notes'], $m)): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;">
                    Payment ID: <code><?= e($m[1]) ?></code>
                </small>
            <?php endif; ?>
            <?php if (!empty($p['expires_at'])): ?>
                <br><small style="color:var(--text-muted);font-size:.75rem;">
                    Expire: <?= format_date($p['expires_at']) ?>
                </small>
            <?php endif; ?>
        <?php else: ?>
            <span style="color:var(--text-muted);">—</span>
        <?php endif; ?>
    </td>
    <td><?= format_date($p['created_at']) ?></td>
    <td>
        <?php if ($p['status'] === 'pending'): ?>
            <?php if (!empty($p['user_xelis_address'])): ?>
                <small style="display:block;margin-bottom:.25rem;">Adresse: <?= e($p['user_xelis_address']) ?></small>
            <?php endif; ?>
            <form method="POST" action="admin/purchases/confirm" class="inline-form">
                <?= \App\Core\View::csrfField() ?>
                <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">
                <textarea name="notes" placeholder="Notes admin..." rows="1" style="width:100%;font-size:.8rem;padding:.25rem;margin-bottom:.25rem;border:1px solid var(--border);border-radius:4px;"></textarea>
                <button name="action" value="confirm" class="btn btn-success btn-sm" title="Confirmer et créditer les points">
                    <i class="fas fa-check"></i> Confirmer
                </button>
                <button name="action" value="cancel" class="btn btn-danger btn-sm" title="Annuler l'achat">
                    <i class="fas fa-times"></i>
                </button>
            </form>
        <?php elseif ($p['status'] === 'completed'): ?>
            <span style="color:var(--success);font-size:.85rem;"><i class="fas fa-check-circle"></i> Crédité</span>
        <?php endif; ?>
        <?php if ($p['status'] !== 'pending'): ?>
            <form method="POST" action="admin/purchases/delete" class="inline-form" style="margin-top:.25rem;" onsubmit="return confirm('Supprimer l\'achat #<?= $p['id'] ?> de l\'historique ?');">
                <?= \App\Core\View::csrfField() ?>
                <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" title="Supprimer de l'historique">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div></div>
