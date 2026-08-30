<?php
/** @var array $purchase */
/** @var float $xel_price_eur */
$purchaseId = (int) $purchase['id'];
$xelAmount = number_format((float) $purchase['amount_xelis'], 8, '.', '');
$paymentAddress = $purchase['payment_address'] ?: ($purchase['user_xelis_address'] ?? '');
$expiresAt = strtotime($purchase['expires_at'] ?? 'now');
$remainingSeconds = max(0, $expiresAt - time());
$txHash = $purchase['transaction_id'] ?? '';
$isCompleted = $purchase['status'] === 'completed';
$isCancelled = $purchase['status'] === 'cancelled';
$isExpired = $remainingSeconds <= 0 && !$isCompleted;
?>

<h1><i class="fas fa-coins"></i> Paiement XELIS</h1>

<?php if ($isCompleted): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <strong>Paiement confirmé !</strong>
        Vos <?= number_format((int) $purchase['points_purchased'], 0, ',', ' ') ?> points ont été crédités.
        <?php if ($txHash): ?>
            <br><small>TX: <code><?= e(substr($txHash, 0, 16)) ?>...</code></small>
        <?php endif; ?>
    </div>
    <a href="/dashboard" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Retour au tableau de bord</a>

<?php elseif ($isCancelled || $isExpired): ?>
    <div class="alert alert-danger">
        <i class="fas fa-times-circle"></i> <strong>Paiement <?= $isExpired ? 'expiré' : 'annulé' ?>.</strong>
        Veuillez créer un nouveau paiement.
    </div>
    <a href="/vip" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Retour aux packs VIP</a>

<?php else: ?>
    <div class="grid-2" style="gap:2rem;">
        <!-- Colonne gauche : Instructions de paiement -->
        <div>
            <div class="card">
                <div class="card-body">
                    <h3 style="text-align:center;margin-bottom:1.5rem;">
                        <i class="fas fa-paper-plane"></i> Envoyez exactement
                    </h3>

                    <!-- Montant XEL -->
                    <div style="text-align:center;background:var(--bg);padding:1.5rem;border-radius:12px;margin-bottom:1.5rem;">
                        <div style="font-size:2rem;font-weight:700;color:var(--primary);font-family:monospace;">
                            <?= e($xelAmount) ?> XEL
                        </div>
                        <div style="color:var(--text-muted);margin-top:.5rem;">
                            ≈ <?= e(number_format((float) $purchase['amount_eur'], 2, ',', ' ')) ?> €
                            <span style="font-size:.85rem;">(1 XEL = <?= e(number_format($xel_price_eur, 4, ',', ' ')) ?> €)</span>
                        </div>
                    </div>

                    <!-- Adresse de paiement -->
                    <div style="margin-bottom:1.5rem;">
                        <label style="font-weight:600;margin-bottom:.5rem;display:block;">
                            <i class="fas fa-wallet"></i> Adresse de destination :
                        </label>
                        <div style="position:relative;">
                            <input type="text" id="xelis-address" value="<?= e($paymentAddress) ?>"
                                   readonly
                                   style="width:100%;padding:.75rem;padding-right:3rem;font-family:monospace;font-size:.85rem;border:2px solid var(--border);border-radius:8px;background:var(--bg);cursor:pointer;"
                                   onclick="this.select();">
                            <button type="button" onclick="copyAddress()" class="btn btn-sm"
                                    style="position:absolute;right:4px;top:50%;transform:translateY(-50%);padding:.4rem .6rem;"
                                    title="Copier l'adresse">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>

                    <!-- QR Code -->
                    <div style="text-align:center;margin-bottom:1.5rem;">
                        <div id="qrcode" style="display:inline-block;padding:12px;background:#fff;border-radius:12px;border:2px solid var(--border);"></div>
                    </div>

                    <!-- Countdown -->
                    <div id="countdown-container" style="text-align:center;margin-bottom:1rem;">
                        <div style="color:var(--text-muted);font-size:.9rem;">
                            <i class="fas fa-clock"></i> Expire dans
                        </div>
                        <div id="countdown" style="font-size:1.5rem;font-weight:700;color:var(--warning);font-family:monospace;">
                            --:--
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonne droite : Soumission TX et statut -->
        <div>
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-body">
                    <h3><i class="fas fa-link"></i> Soumettre la transaction</h3>
                    <p style="color:var(--text-muted);font-size:.9rem;">
                        Après avoir envoyé les XEL, collez le hash de transaction pour vérification.
                    </p>

                    <?php if (!empty($txHash)): ?>
                        <div class="alert alert-info" style="margin-bottom:1rem;">
                            <i class="fas fa-info-circle"></i>
                            TX soumise : <code style="word-break:break-all;"><?= e($txHash) ?></code>
                            <div id="tx-status-badge" style="margin-top:.5rem;">
                                <span class="badge badge-warning">
                                    <i class="fas fa-spinner fa-spin"></i> Vérification en cours...
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="/xelis/submit-tx">
                            <?= \App\Core\View::csrfField() ?>
                            <input type="hidden" name="purchase_id" value="<?= $purchaseId ?>">
                            <div style="margin-bottom:1rem;">
                                <label style="font-weight:600;margin-bottom:.5rem;display:block;">
                                    Hash de transaction
                                </label>
                                <input type="text" name="tx_hash" required
                                       pattern="[a-fA-F0-9]{64}"
                                       placeholder="Ex: dd693bad09cb03ba0bf9a6fa7b787f918748db869c1463b7fa16e20b498dea88"
                                       style="width:100%;padding:.75rem;font-family:monospace;font-size:.85rem;border:2px solid var(--border);border-radius:8px;"
                                       title="Le hash doit contenir exactement 64 caractères hexadécimaux">
                                <small style="color:var(--text-muted);">
                                    64 caractères hexadécimaux. Trouvable dans votre wallet ou l'explorateur XELIS.
                                </small>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-check"></i> Soumettre la transaction
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statut du paiement -->
            <div class="card">
                <div class="card-body">
                    <h3><i class="fas fa-signal"></i> Statut du paiement</h3>
                    <div id="payment-status">
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <span class="badge badge-warning">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                            <span id="status-text">En attente du paiement...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="card" style="margin-top:1.5rem;">
                <div class="card-body">
                    <h3><i class="fas fa-question-circle"></i> Comment payer ?</h3>
                    <ol style="padding-left:1.2rem;line-height:2;">
                        <li>Ouvrez votre <strong>wallet XELIS</strong> (Genesix, CLI...)</li>
                        <li>Créez une transaction vers l'adresse ci-dessus</li>
                        <li>Envoyez <strong>exactement</strong> <code><?= e($xelAmount) ?> XEL</code></li>
                        <li>Copiez le <strong>hash de transaction</strong> depuis votre wallet</li>
                        <li>Collez-le dans le formulaire et soumettez</li>
                        <li>La confirmation est <strong>automatique</strong> si le RPC est configuré, sinon l'admin valide manuellement</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript pour QR Code, countdown et polling -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
    (function() {
        // QR Code
        var address = <?= json_encode($paymentAddress) ?>;
        if (address && typeof QRCode !== 'undefined') {
            new QRCode(document.getElementById('qrcode'), {
                text: address,
                width: 200,
                height: 200,
                colorDark: '#1a1a2e',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }

        // Countdown
        var remaining = <?= $remainingSeconds ?>;
        var countdownEl = document.getElementById('countdown');
        var countdownInterval = setInterval(function() {
            remaining--;
            if (remaining <= 0) {
                clearInterval(countdownInterval);
                countdownEl.textContent = 'EXPIRÉ';
                countdownEl.style.color = 'var(--danger)';
                setTimeout(function() { location.reload(); }, 3000);
                return;
            }
            var m = Math.floor(remaining / 60);
            var s = remaining % 60;
            countdownEl.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            if (remaining < 120) {
                countdownEl.style.color = 'var(--danger)';
            }
        }, 1000);

        // Polling statut paiement
        var purchaseId = <?= $purchaseId ?>;
        var pollInterval = setInterval(function() {
            fetch('/xelis/status/' + purchaseId, {
                headers: { 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var statusText = document.getElementById('status-text');
                if (!statusText) return;

                if (data.status === 'completed') {
                    clearInterval(pollInterval);
                    clearInterval(countdownInterval);
                    statusText.innerHTML = '<span class="badge badge-success"><i class="fas fa-check"></i> Paiement confirmé !</span>';
                    setTimeout(function() { location.reload(); }, 2000);
                } else if (data.status === 'pending_confirmation') {
                    statusText.innerHTML = '<span class="badge badge-info"><i class="fas fa-spinner fa-spin"></i> ' +
                        (data.confirmations || 0) + '/' + (data.required_confirmations || 5) + ' confirmations</span>';
                } else if (data.status === 'expired' || data.status === 'cancelled') {
                    clearInterval(pollInterval);
                    clearInterval(countdownInterval);
                    location.reload();
                }
            })
            .catch(function() {});
        }, 10000); // toutes les 10s
    })();

    function copyAddress() {
        var input = document.getElementById('xelis-address');
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(function() {
            alert('Adresse copiée !');
        });
    }
    </script>
<?php endif; ?>
