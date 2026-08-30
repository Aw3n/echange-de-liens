<?php
/** @var array $purchase */
/** @var float $xmr_price_eur */
/** @var string $monero_uri */
/** @var string $explorer_url */
$purchaseId = (int) $purchase['id'];
$xmrAmountDisplay = number_format((float) $purchase['amount_xelis'], 8, '.', '');
$paymentAddress = $purchase['payment_address'] ?? '';
$expiresAt = strtotime($purchase['expires_at'] ?? 'now');
$remainingSeconds = max(0, $expiresAt - time());
$txHash = $purchase['transaction_id'] ?? '';
$isCompleted = $purchase['status'] === 'completed';
$isCancelled = $purchase['status'] === 'cancelled';
$isExpired = $remainingSeconds <= 0 && !$isCompleted;
$explorerUrl = $explorer_url ?? 'https://xmrchain.net';
$moneroColor = '#ff6600'; // Orange Monero

// Extraire le payment_id depuis admin_notes
$paymentId = '';
if (preg_match('/payment_id:([a-f0-9]+)/', $purchase['admin_notes'] ?? '', $m)) {
    $paymentId = $m[1];
}
?>

<h1><i class="fas fa-user-secret" style="color:<?= e($moneroColor) ?>;"></i> Paiement Monero (XMR)</h1>

<?php if ($isCompleted): ?>
    <div class="alert alert-success" style="text-align:center;padding:2rem;">
        <div style="font-size:3rem;margin-bottom:1rem;"><i class="fas fa-check-circle"></i></div>
        <h2 style="color:var(--success);margin-bottom:.5rem;">Paiement confirmé !</h2>
        <p style="font-size:1.1rem;">
            Vos <strong><?= number_format((int) $purchase['points_purchased'], 0, ',', ' ') ?></strong> points ont été crédités.
        </p>
        <?php if ($txHash): ?>
            <div style="margin-top:1rem;padding:.75rem;background:rgba(0,0,0,.05);border-radius:8px;display:inline-block;">
                <small>TX: <a href="<?= e($explorerUrl . '/tx/' . $txHash) ?>" target="_blank" rel="noopener"><code><?= e(substr($txHash, 0, 16)) ?>...</code></a></small>
            </div>
        <?php endif; ?>
        <div style="margin-top:1.5rem;">
            <a href="/dashboard" class="btn btn-primary btn-lg"><i class="fas fa-arrow-left"></i> Retour au tableau de bord</a>
        </div>
    </div>

<?php elseif ($isCancelled || $isExpired): ?>
    <div class="alert alert-danger" style="text-align:center;padding:2rem;">
        <div style="font-size:3rem;margin-bottom:1rem;"><i class="fas fa-times-circle"></i></div>
        <h2 style="color:var(--danger);margin-bottom:.5rem;">Paiement <?= $isExpired ? 'expiré' : 'annulé' ?></h2>
        <p>Veuillez créer un nouveau paiement depuis la page VIP.</p>
        <div style="margin-top:1.5rem;">
            <a href="/vip" class="btn btn-primary btn-lg"><i class="fas fa-arrow-left"></i> Retour aux packs VIP</a>
        </div>
    </div>

<?php else: ?>
    <!-- Barre de statut réseau -->
    <div id="network-status" style="text-align:center;margin-bottom:1rem;padding:.5rem;background:var(--bg);border-radius:8px;font-size:.85rem;">
        <span id="network-dot" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--warning);margin-right:.5rem;vertical-align:middle;"></span>
        <span id="network-text">Vérification du réseau Monero...</span>
    </div>

    <div class="grid-2" style="gap:2rem;">
        <!-- Colonne gauche : Instructions de paiement -->
        <div>
            <div class="card">
                <div class="card-body">
                    <h3 style="text-align:center;margin-bottom:1.5rem;">
                        <i class="fas fa-paper-plane"></i> Envoyez
                    </h3>

                    <!-- Montant XMR avec bouton copier -->
                    <div style="text-align:center;background:var(--bg);padding:1.5rem;border-radius:12px;margin-bottom:1.5rem;">
                        <div style="font-size:2rem;font-weight:700;color:<?= e($moneroColor) ?>;font-family:monospace;" id="xmr-amount-display">
                            <?= e($xmrAmountDisplay) ?> XMR
                        </div>
                        <button type="button" onclick="copyAmount()" class="btn btn-sm" id="copy-amount-btn"
                                style="margin-top:.5rem;padding:.3rem .8rem;font-size:.8rem;"
                                title="Copier le montant">
                            <i class="fas fa-copy"></i> Copier le montant
                        </button>
                        <div style="color:var(--text-muted);margin-top:.75rem;">
                            ≈ <?= e(number_format((float) $purchase['amount_eur'], 2, ',', ' ')) ?> €
                            <span style="font-size:.85rem;">(1 XMR = <?= e(number_format($xmr_price_eur, 2, ',', ' ')) ?> €)</span>
                        </div>
                    </div>

                    <!-- Adresse de paiement -->
                    <div style="margin-bottom:1.5rem;">
                        <label style="font-weight:600;margin-bottom:.5rem;display:block;">
                            <i class="fas fa-wallet"></i> Adresse de destination :
                        </label>
                        <div style="position:relative;">
                            <input type="text" id="monero-address" value="<?= e($paymentAddress) ?>"
                                   readonly
                                   style="width:100%;padding:.75rem;padding-right:3rem;font-family:monospace;font-size:.85rem;border:2px solid var(--border);border-radius:8px;background:var(--bg);cursor:pointer;"
                                   onclick="this.select();">
                            <button type="button" onclick="copyAddress()" class="btn btn-sm" id="copy-address-btn"
                                    style="position:absolute;right:4px;top:50%;transform:translateY(-50%);padding:.4rem .6rem;"
                                    title="Copier l'adresse">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Payment ID (si disponible) -->
                    <?php if (!empty($paymentId)): ?>
                    <div style="margin-bottom:1.5rem;">
                        <label style="font-weight:600;margin-bottom:.5rem;display:block;">
                            <i class="fas fa-key"></i> Payment ID :
                        </label>
                        <div style="position:relative;">
                            <input type="text" id="payment-id" value="<?= e($paymentId) ?>"
                                   readonly
                                   style="width:100%;padding:.75rem;padding-right:3rem;font-family:monospace;font-size:.85rem;border:2px solid var(--border);border-radius:8px;background:var(--bg);cursor:pointer;"
                                   onclick="this.select();">
                            <button type="button" onclick="copyPaymentId()" class="btn btn-sm" id="copy-paymentid-btn"
                                    style="position:absolute;right:4px;top:50%;transform:translateY(-50%);padding:.4rem .6rem;"
                                    title="Copier le Payment ID">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <small style="color:var(--text-muted);display:block;margin-top:.25rem;">
                            <i class="fas fa-info-circle"></i> Important : incluez ce Payment ID dans votre transaction pour identifier votre paiement.
                        </small>
                    </div>
                    <?php endif; ?>

                    <!-- QR Code -->
                    <div style="text-align:center;margin-bottom:1.5rem;">
                        <div id="qrcode" style="display:inline-block;padding:12px;background:#fff;border-radius:12px;border:2px solid var(--border);cursor:pointer;"
                             onclick="copyAddress()" title="Cliquer pour copier l'adresse">
                        </div>
                        <div style="font-size:.8rem;color:var(--text-muted);margin-top:.5rem;">
                            Scannez avec un wallet Monero (Official GUI, Cake Wallet, Feather...)
                        </div>
                    </div>

                    <!-- Countdown -->
                    <div id="countdown-container" style="text-align:center;margin-bottom:1rem;">
                        <div style="color:var(--text-muted);font-size:.9rem;">
                            <i class="fas fa-clock"></i> Expire dans
                        </div>
                        <div id="countdown" style="font-size:1.5rem;font-weight:700;color:var(--warning);font-family:monospace;">
                            --:--
                        </div>
                        <div style="font-size:.75rem;color:var(--text-muted);">
                            <i class="fas fa-cube"></i> Monero confirme en ~2 min/bloc (10 confirmations requises, ~20 min)
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonne droite : Soumission TX et statut -->
        <div>
            <!-- Note importante sur la confidentialité -->
            <div class="card" style="margin-bottom:1.5rem;border-left:4px solid <?= e($moneroColor) ?>;">
                <div class="card-body" style="padding:1rem;">
                    <h4 style="margin-bottom:.5rem;"><i class="fas fa-shield-alt" style="color:<?= e($moneroColor) ?>;"></i> Confidentialité Monero</h4>
                    <p style="font-size:.9rem;color:var(--text-muted);margin:0;">
                        Monero est une cryptomonnaie axée sur la vie privée. Les montants et adresses sont chiffrés sur la blockchain.
                        La vérification du paiement nécessite de soumettre le hash de transaction. L'administrateur confirmera manuellement
                        ou automatiquement si un <strong>wallet RPC</strong> est configuré.
                    </p>
                </div>
            </div>

            <!-- Étapes du paiement -->
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-body">
                    <h3><i class="fas fa-list-ol"></i> Étapes du paiement</h3>
                    <div id="payment-steps" style="margin-top:1rem;">
                        <div class="step" id="step-1" style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid var(--border);">
                            <span class="step-icon" style="width:28px;height:28px;border-radius:50%;background:var(--warning);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;">1</span>
                            <span>Envoyez <strong><?= e($xmrAmountDisplay) ?> XMR</strong> à l'adresse ci-dessus</span>
                        </div>
                        <?php if (!empty($paymentId)): ?>
                        <div class="step" id="step-1b" style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid var(--border);opacity:.5;">
                            <span class="step-icon" style="width:28px;height:28px;border-radius:50%;background:var(--border);color:var(--text-muted);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;">+</span>
                            <span>Incluez le <strong>Payment ID</strong> dans la transaction</span>
                        </div>
                        <?php endif; ?>
                        <div class="step" id="step-2" style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid var(--border);opacity:.5;">
                            <span class="step-icon" style="width:28px;height:28px;border-radius:50%;background:var(--border);color:var(--text-muted);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;">2</span>
                            <span>Collez le hash de transaction ci-dessous</span>
                        </div>
                        <div class="step" id="step-3" style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;opacity:.5;">
                            <span class="step-icon" style="width:28px;height:28px;border-radius:50%;background:var(--border);color:var(--text-muted);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;">3</span>
                            <span>Points crédités après confirmation (10 blocs ~20 min)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Soumission TX -->
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-body">
                    <h3><i class="fas fa-link"></i> Soumettre la transaction</h3>
                    <p style="color:var(--text-muted);font-size:.9rem;">
                        Après avoir envoyé les XMR, collez le hash de transaction pour vérification.
                    </p>

                    <?php if (!empty($txHash)): ?>
                        <div class="alert alert-info" style="margin-bottom:1rem;">
                            <i class="fas fa-info-circle"></i>
                            TX soumise : <code style="word-break:break-all;font-size:.8rem;"><?= e($txHash) ?></code>
                            <div id="tx-status-badge" style="margin-top:.5rem;">
                                <span class="badge badge-warning">
                                    <i class="fas fa-spinner fa-spin"></i> Vérification en cours...
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="/monero/submit-tx">
                            <?= \App\Core\View::csrfField() ?>
                            <input type="hidden" name="purchase_id" value="<?= $purchaseId ?>">
                            <div style="margin-bottom:1rem;">
                                <label style="font-weight:600;margin-bottom:.5rem;display:block;">
                                    Hash de transaction
                                </label>
                                <input type="text" name="tx_hash" id="tx-hash-input" required
                                       pattern="[a-fA-F0-9]{64}"
                                       placeholder="Ex: 7d8a5e3f1b9c2d4e6f8a0b1c3d5e7f9a2b4c6d8e0f1a3b5c7d9e1f2a4b6c8d"
                                       style="width:100%;padding:.75rem;font-family:monospace;font-size:.85rem;border:2px solid var(--border);border-radius:8px;"
                                       title="Le hash doit contenir exactement 64 caractères hexadécimaux">
                                <small style="color:var(--text-muted);">
                                    64 caractères hexadécimaux. Trouvable dans votre wallet Monero ou l'<a href="<?= e($explorerUrl) ?>" target="_blank" rel="noopener">explorateur</a>.
                                </small>
                            </div>
                            <button type="submit" class="btn btn-block" style="background:<?= e($moneroColor) ?>;color:#fff;" id="submit-tx-btn">
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
                            <span class="badge badge-warning" id="status-badge">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                            <span id="status-text">En attente du paiement...</span>
                        </div>
                        <div id="confirmations-info" style="display:none;margin-top:.5rem;font-size:.85rem;color:var(--text-muted);">
                            <i class="fas fa-lock"></i> Confirmations: <span id="conf-current">0</span>/<span id="conf-required">10</span>
                        </div>
                    </div>
                    <div style="margin-top:.75rem;font-size:.85rem;color:var(--text-muted);">
                        <i class="fas fa-info-circle"></i>
                        Après soumission du hash, la TX est vérifiée via l'explorateur.
                        L'administrateur confirmera manuellement (ou automatiquement via wallet RPC).
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="card" style="margin-top:1.5rem;">
                <div class="card-body">
                    <h3><i class="fas fa-question-circle"></i> Comment payer ?</h3>
                    <ol style="padding-left:1.2rem;line-height:2;">
                        <li>Ouvrez votre <strong>wallet Monero</strong> (GUI officielle, Cake Wallet, Feather...)</li>
                        <li>Créez une transaction vers l'adresse ci-dessus</li>
                        <li>Envoyez <strong><?= e($xmrAmountDisplay) ?> XMR</strong></li>
                        <?php if (!empty($paymentId)): ?>
                        <li><strong>Important :</strong> Incluez le Payment ID dans votre transaction</li>
                        <?php endif; ?>
                        <li>Copiez le <strong>hash de transaction</strong> et soumettez-le</li>
                        <li>Attendez <strong>10 confirmations</strong> (~20 minutes)</li>
                    </ol>
                    <div style="margin-top:1rem;padding:.75rem;background:var(--bg);border-radius:8px;font-size:.85rem;">
                        <i class="fas fa-user-secret" style="color:<?= e($moneroColor) ?>;"></i>
                        <strong>Monero (XMR) :</strong> La cryptomonnaie de référence pour la vie privée.
                        Transactions furtives avec montants et adresses chiffrés sur la blockchain.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast notification -->
    <div id="toast" style="position:fixed;bottom:2rem;left:50%;transform:translateX(-50%) translateY(100px);background:var(--success);color:#fff;padding:.75rem 1.5rem;border-radius:8px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.2);transition:transform .3s ease;z-index:9999;display:none;">
        <i class="fas fa-check"></i> <span id="toast-text">Copié !</span>
    </div>

    <!-- JavaScript pour QR Code, countdown et polling -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
    (function() {
        var xmrAmountDisplay = <?= json_encode($xmrAmountDisplay) ?>;
        var uri = <?= json_encode($monero_uri) ?>;

        // QR Code avec URI Monero
        if (uri && typeof QRCode !== 'undefined') {
            new QRCode(document.getElementById('qrcode'), {
                text: uri,
                width: 200,
                height: 200,
                colorDark: '#1a1a2e',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }

        // Toast notification
        function showToast(msg, type) {
            var toast = document.getElementById('toast');
            var toastText = document.getElementById('toast-text');
            toastText.textContent = msg;
            toast.style.background = type === 'error' ? 'var(--danger)' : 'var(--success)';
            toast.style.display = 'block';
            toast.style.transform = 'translateX(-50%) translateY(0)';
            setTimeout(function() {
                toast.style.transform = 'translateX(-50%) translateY(100px)';
                setTimeout(function() { toast.style.display = 'none'; }, 300);
            }, 2000);
        }

        // Copier le montant
        window.copyAmount = function() {
            navigator.clipboard.writeText(xmrAmountDisplay).then(function() {
                showToast('Montant copié !');
                var btn = document.getElementById('copy-amount-btn');
                btn.innerHTML = '<i class="fas fa-check"></i> Copié !';
                btn.style.background = 'var(--success)';
                btn.style.color = '#fff';
                setTimeout(function() {
                    btn.innerHTML = '<i class="fas fa-copy"></i> Copier le montant';
                    btn.style.background = '';
                    btn.style.color = '';
                }, 2000);
            });
        };

        // Copier l'adresse
        window.copyAddress = function() {
            var input = document.getElementById('monero-address');
            navigator.clipboard.writeText(input.value).then(function() {
                showToast('Adresse copiée !');
                var btn = document.getElementById('copy-address-btn');
                btn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(function() { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 2000);
            });
        };

        // Copier le Payment ID
        window.copyPaymentId = function() {
            var input = document.getElementById('payment-id');
            if (!input) return;
            navigator.clipboard.writeText(input.value).then(function() {
                showToast('Payment ID copié !');
                var btn = document.getElementById('copy-paymentid-btn');
                btn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(function() { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 2000);
            });
        };

        // Countdown
        var remaining = <?= $remainingSeconds ?>;
        var countdownEl = document.getElementById('countdown');
        var purchaseId = <?= $purchaseId ?>;
        var pollInterval;
        var isVisible = true;

        var countdownInterval = setInterval(function() {
            remaining--;
            if (remaining <= 0) {
                clearInterval(countdownInterval);
                clearInterval(pollInterval);
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

        // Vérifier le réseau Monero via notre backend (évite CORS)
        var networkDot = document.getElementById('network-dot');
        var networkText = document.getElementById('network-text');
        fetch('/monero/status/' + purchaseId, { headers: { 'Accept': 'application/json' } })
            .then(function(r) {
                if (r.ok) {
                    networkDot.style.background = 'var(--success)';
                    networkText.textContent = 'Réseau Monero connecté';
                } else {
                    throw new Error('Backend error');
                }
            })
            .catch(function() {
                networkDot.style.background = 'var(--danger)';
                networkText.textContent = 'Vérification réseau indisponible';
            });

        // Polling statut paiement (toutes les 15s car Monero ~2min/bloc)
        document.addEventListener('visibilitychange', function() {
            isVisible = !document.hidden;
        });

        function pollStatus() {
            if (!isVisible) return;

            fetch('/monero/status/' + purchaseId, {
                headers: { 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var statusText = document.getElementById('status-text');
                var statusBadge = document.getElementById('status-badge');
                if (!statusText) return;

                if (data.status === 'completed') {
                    clearInterval(pollInterval);
                    clearInterval(countdownInterval);
                    statusBadge.className = 'badge badge-success';
                    statusBadge.innerHTML = '<i class="fas fa-check"></i>';
                    statusText.innerHTML = '<strong style="color:var(--success);">Paiement confirmé ! Redirection...</strong>';
                    activateStep(3);
                    setTimeout(function() { location.reload(); }, 2000);
                } else if (data.status === 'expired' || data.status === 'cancelled') {
                    clearInterval(pollInterval);
                    clearInterval(countdownInterval);
                    location.reload();
                } else if (data.status === 'waiting_admin') {
                    statusBadge.className = 'badge badge-info';
                    statusBadge.innerHTML = '<i class="fas fa-hourglass-half"></i>';
                    statusText.innerHTML = '<strong style="color:var(--primary);">TX confirmée. En attente de validation par l\'administrateur.</strong>';
                    activateStep(3);
                } else if (data.status === 'partial') {
                    statusBadge.className = 'badge badge-warning';
                    statusBadge.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                    statusText.innerHTML = '<strong style="color:var(--warning);">' + (data.message || 'Paiement partiel détecté.') + '</strong>';
                } else if (data.confirmations !== undefined) {
                    var confInfo = document.getElementById('confirmations-info');
                    confInfo.style.display = 'block';
                    document.getElementById('conf-current').textContent = data.confirmations;
                    document.getElementById('conf-required').textContent = data.required_confirmations;
                    activateStep(2);
                }
            })
            .catch(function() {});
        }

        function activateStep(n) {
            var step = document.getElementById('step-' + n);
            if (!step) return;
            step.style.opacity = '1';
            var icon = step.querySelector('.step-icon');
            if (icon) {
                icon.style.background = 'var(--success)';
                icon.innerHTML = '<i class="fas fa-check" style="font-size:.7rem;"></i>';
            }
            var nextStep = document.getElementById('step-' + (n + 1));
            if (nextStep) nextStep.style.opacity = '1';
        }

        // Démarrer le polling (15s car Monero ~2min/bloc)
        pollInterval = setInterval(pollStatus, 15000);
        // Premier check immédiat après chargement
        setTimeout(pollStatus, 2000);

        // Auto-focus sur le champ TX hash si vide
        var txInput = document.getElementById('tx-hash-input');
        if (txInput && !txInput.value) {
            txInput.addEventListener('paste', function(e) {
                setTimeout(function() {
                    var val = txInput.value.trim();
                    if (val.length === 64 && /^[a-fA-F0-9]+$/.test(val)) {
                        var btn = document.getElementById('submit-tx-btn');
                        if (btn) {
                            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Vérification...';
                            btn.disabled = true;
                            setTimeout(function() { btn.closest('form').submit(); }, 1000);
                        }
                    }
                }, 100);
            });
        }
    })();
    </script>
<?php endif; ?>
