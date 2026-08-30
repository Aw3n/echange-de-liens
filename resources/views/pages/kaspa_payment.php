<?php
/** @var array $purchase */
/** @var float $kas_price_eur */
/** @var string $kaspa_uri */
$purchaseId = (int) $purchase['id'];
$kasAmount = number_format((float) $purchase['amount_xelis'], 8, '.', '');
$paymentAddress = $purchase['payment_address'] ?? '';
$expiresAt = strtotime($purchase['expires_at'] ?? 'now');
$remainingSeconds = max(0, $expiresAt - time());
$txHash = $purchase['transaction_id'] ?? '';
$isCompleted = $purchase['status'] === 'completed';
$isCancelled = $purchase['status'] === 'cancelled';
$isExpired = $remainingSeconds <= 0 && !$isCompleted;
$explorerUrl = 'https://explorer.kaspa.org';
$timeoutMinutes = (int) (($expiresAt - strtotime($purchase['created_at'] ?? 'now')) / 60);
?>

<h1><i class="fas fa-gem"></i> Paiement Kaspa</h1>

<?php if ($isCompleted): ?>
    <div class="alert alert-success" style="text-align:center;padding:2rem;">
        <div style="font-size:3rem;margin-bottom:1rem;"><i class="fas fa-check-circle"></i></div>
        <h2 style="color:var(--success);margin-bottom:.5rem;">Paiement confirmé !</h2>
        <p style="font-size:1.1rem;">
            Vos <strong><?= number_format((int) $purchase['points_purchased'], 0, ',', ' ') ?></strong> points ont été crédités.
        </p>
        <?php if ($txHash): ?>
            <div style="margin-top:1rem;padding:.75rem;background:rgba(0,0,0,.05);border-radius:8px;display:inline-block;">
                <small>TX: <a href="<?= e($explorerUrl . '/txs/' . $txHash) ?>" target="_blank" rel="noopener"><code><?= e(substr($txHash, 0, 16)) ?>...</code></a></small>
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
        <span id="network-text">Vérification du réseau Kaspa...</span>
    </div>

    <div class="grid-2" style="gap:2rem;">
        <!-- Colonne gauche : Instructions de paiement -->
        <div>
            <div class="card">
                <div class="card-body">
                    <h3 style="text-align:center;margin-bottom:1.5rem;">
                        <i class="fas fa-paper-plane"></i> Envoyez exactement
                    </h3>

                    <!-- Montant KAS avec bouton copier -->
                    <div style="text-align:center;background:var(--bg);padding:1.5rem;border-radius:12px;margin-bottom:1.5rem;position:relative;">
                        <div style="font-size:2rem;font-weight:700;color:var(--primary);font-family:monospace;" id="kas-amount-display">
                            <?= e($kasAmount) ?> KAS
                        </div>
                        <button type="button" onclick="copyAmount()" class="btn btn-sm" id="copy-amount-btn"
                                style="margin-top:.5rem;padding:.3rem .8rem;font-size:.8rem;"
                                title="Copier le montant">
                            <i class="fas fa-copy"></i> Copier le montant
                        </button>
                        <div style="color:var(--text-muted);margin-top:.75rem;">
                            ≈ <?= e(number_format((float) $purchase['amount_eur'], 2, ',', ' ')) ?> €
                            <span style="font-size:.85rem;">(1 KAS = <?= e(number_format($kas_price_eur, 6, ',', ' ')) ?> €)</span>
                        </div>
                    </div>

                    <!-- Adresse de paiement -->
                    <div style="margin-bottom:1.5rem;">
                        <label style="font-weight:600;margin-bottom:.5rem;display:block;">
                            <i class="fas fa-wallet"></i> Adresse de destination :
                        </label>
                        <div style="position:relative;">
                            <input type="text" id="kaspa-address" value="<?= e($paymentAddress) ?>"
                                   readonly
                                   style="width:100%;padding:.75rem;padding-right:3rem;font-family:monospace;font-size:.8rem;border:2px solid var(--border);border-radius:8px;background:var(--bg);cursor:pointer;"
                                   onclick="this.select();">
                            <button type="button" onclick="copyAddress()" class="btn btn-sm" id="copy-address-btn"
                                    style="position:absolute;right:4px;top:50%;transform:translateY(-50%);padding:.4rem .6rem;"
                                    title="Copier l'adresse">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>

                    <!-- QR Code -->
                    <div style="text-align:center;margin-bottom:1.5rem;">
                        <div id="qrcode" style="display:inline-block;padding:12px;background:#fff;border-radius:12px;border:2px solid var(--border);cursor:pointer;"
                             onclick="copyAll()" title="Cliquer pour copier l'adresse">
                        </div>
                        <div style="font-size:.8rem;color:var(--text-muted);margin-top:.5rem;">
                            Scannez avec un wallet Kaspa compatible
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
                            <i class="fas fa-bolt"></i> Kaspa confirme en ~1 seconde
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonne droite : Soumission TX et statut -->
        <div>
            <!-- Étapes du paiement -->
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-body">
                    <h3><i class="fas fa-list-ol"></i> Étapes du paiement</h3>
                    <div id="payment-steps" style="margin-top:1rem;">
                        <div class="step" id="step-1" style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid var(--border);">
                            <span class="step-icon" style="width:28px;height:28px;border-radius:50%;background:var(--warning);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;">1</span>
                            <span>Envoyez <strong><?= e($kasAmount) ?> KAS</strong> à l'adresse ci-dessus</span>
                        </div>
                        <div class="step" id="step-2" style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid var(--border);opacity:.5;">
                            <span class="step-icon" style="width:28px;height:28px;border-radius:50%;background:var(--border);color:var(--text-muted);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;">2</span>
                            <span>Collez le hash de transaction ci-dessous</span>
                        </div>
                        <div class="step" id="step-3" style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;opacity:.5;">
                            <span class="step-icon" style="width:28px;height:28px;border-radius:50%;background:var(--border);color:var(--text-muted);display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;">3</span>
                            <span>Points crédités automatiquement (~1 seconde)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Soumission TX -->
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-body">
                    <h3><i class="fas fa-link"></i> Soumettre la transaction</h3>
                    <p style="color:var(--text-muted);font-size:.9rem;">
                        Après avoir envoyé les KAS, collez le hash de transaction pour vérification automatique.
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
                        <form method="POST" action="/kaspa/submit-tx">
                            <?= \App\Core\View::csrfField() ?>
                            <input type="hidden" name="purchase_id" value="<?= $purchaseId ?>">
                            <div style="margin-bottom:1rem;">
                                <label style="font-weight:600;margin-bottom:.5rem;display:block;">
                                    Hash de transaction
                                </label>
                                <input type="text" name="tx_hash" id="tx-hash-input" required
                                       pattern="[a-fA-F0-9]{64}"
                                       placeholder="Ex: a7177c55769381a56c126e458c8bb6046613b8a8d45c2f8d3ca5dc71a065279a"
                                       style="width:100%;padding:.75rem;font-family:monospace;font-size:.85rem;border:2px solid var(--border);border-radius:8px;"
                                       title="Le hash doit contenir exactement 64 caractères hexadécimaux">
                                <small style="color:var(--text-muted);">
                                    64 caractères hexadécimaux. Trouvable dans votre wallet Kaspa ou l'<a href="<?= e($explorerUrl) ?>" target="_blank" rel="noopener">explorateur</a>.
                                </small>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" id="submit-tx-btn">
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
                            <i class="fas fa-shield-alt"></i> Confirmations: <span id="conf-current">0</span>/<span id="conf-required">1</span>
                        </div>
                    </div>
                    <div style="margin-top:.75rem;font-size:.85rem;color:var(--text-muted);">
                        <i class="fas fa-info-circle"></i> La vérification est automatique via UTXO matching.
                        Dès que le paiement est détecté, vos points sont crédités instantanément.
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="card" style="margin-top:1.5rem;">
                <div class="card-body">
                    <h3><i class="fas fa-question-circle"></i> Comment payer ?</h3>
                    <ol style="padding-left:1.2rem;line-height:2;">
                        <li>Ouvrez votre <strong>wallet Kaspa</strong> (Zelcore, Kaspion, Web wallet...)</li>
                        <li>Créez une transaction vers l'adresse ci-dessus</li>
                        <li>Envoyez <strong>exactement</strong> <code><?= e($kasAmount) ?> KAS</code></li>
                        <li>Le montant inclut un nonce unique pour identifier votre paiement</li>
                        <li>Copiez le <strong>hash de transaction</strong> et soumettez-le</li>
                        <li>La confirmation est <strong>quasi-instantanée</strong> (~1 seconde)</li>
                    </ol>
                    <div style="margin-top:1rem;padding:.75rem;background:var(--bg);border-radius:8px;font-size:.85rem;">
                        <i class="fas fa-shield-alt" style="color:var(--success);"></i>
                        <strong>Kaspa :</strong> Frais quasi-nuls (&lt; $0.01), confirmations en ~1 seconde.
                        Pas de smart contract nécessaire - vérification par UTXO matching.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast notification -->
    <div id="toast" style="position:fixed;bottom:2rem;left:50%;transform:translateX(-50%) translateY(100px);background:var(--success);color:#fff;padding:.75rem 1.5rem;border-radius:8px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.2);transition:transform .3s ease;z-index:9999;display:none;">
        <i class="fas fa-check"></i> <span id="toast-text">Copié !</span>
    </div>

    <!-- JavaScript pour QR Code, countdown, polling et UX -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
    (function() {
        var kasAmount = <?= json_encode($kasAmount) ?>;
        var address = <?= json_encode($paymentAddress) ?>;
        var uri = <?= json_encode($kaspa_uri) ?>;

        // QR Code avec URI Kaspa
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
            navigator.clipboard.writeText(kasAmount).then(function() {
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
            var input = document.getElementById('kaspa-address');
            navigator.clipboard.writeText(input.value).then(function() {
                showToast('Adresse copiée !');
                var btn = document.getElementById('copy-address-btn');
                btn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(function() { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 2000);
            });
        };

        // Copier les deux (adresse + montant via QR)
        window.copyAll = function() {
            window.copyAddress();
        };

        // Countdown
        var remaining = <?= $remainingSeconds ?>;
        var countdownEl = document.getElementById('countdown');
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

        // Vérifier le réseau Kaspa (ping API)
        var networkDot = document.getElementById('network-dot');
        var networkText = document.getElementById('network-text');
        fetch('https://api.kaspa.org/info', { mode: 'cors' })
            .then(function(r) {
                if (r.ok) return r.json();
                throw new Error('API error');
            })
            .then(function(data) {
                networkDot.style.background = 'var(--success)';
                var daaScore = data.virtualDaaScore ? data.virtualDaaScore.toLocaleString() : '?';
                networkText.innerHTML = 'Réseau Kaspa connecté <span style="color:var(--text-muted);font-size:.8rem;">(DAA: ' + daaScore + ')</span>';
            })
            .catch(function() {
                networkDot.style.background = 'var(--danger)';
                networkText.textContent = 'Réseau Kaspa indisponible - vérification locale uniquement';
            });

        // Polling statut paiement (toutes les 5s car Kaspa est rapide)
        var purchaseId = <?= $purchaseId ?>;
        var pollInterval;
        var isVisible = true;

        // Page Visibility API : pause le polling quand l'onglet est masqué
        document.addEventListener('visibilitychange', function() {
            isVisible = !document.hidden;
        });

        function pollStatus() {
            if (!isVisible) return;

            fetch('/kaspa/status/' + purchaseId, {
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
                    // Activer l'étape 3
                    activateStep(3);
                    setTimeout(function() { location.reload(); }, 2000);
                } else if (data.status === 'expired' || data.status === 'cancelled') {
                    clearInterval(pollInterval);
                    clearInterval(countdownInterval);
                    location.reload();
                } else if (data.confirmations !== undefined) {
                    // Afficher les confirmations
                    var confInfo = document.getElementById('confirmations-info');
                    confInfo.style.display = 'block';
                    document.getElementById('conf-current').textContent = data.confirmations;
                    document.getElementById('conf-required').textContent = data.required_confirmations;
                    // Activer étape 2 (TX soumise détectée)
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
            // Activer l'étape suivante
            var nextStep = document.getElementById('step-' + (n + 1));
            if (nextStep) nextStep.style.opacity = '1';
        }

        // Démarrer le polling
        pollInterval = setInterval(pollStatus, 5000);

        // Auto-focus sur le champ TX hash si vide
        var txInput = document.getElementById('tx-hash-input');
        if (txInput && !txInput.value) {
            // Détecter le collage de hash
            txInput.addEventListener('paste', function(e) {
                setTimeout(function() {
                    var val = txInput.value.trim();
                    if (val.length === 64 && /^[a-fA-F0-9]+$/.test(val)) {
                        // Auto-submit après 1 seconde
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
