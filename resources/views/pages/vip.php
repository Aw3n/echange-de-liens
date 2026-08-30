<?php
/** @var array $vip_packs */
/** @var float $xel_price_eur */
/** @var float $kas_price_eur */
/** @var float $firo_price_eur */
/** @var float $xvg_price_eur */
/** @var float $pepe_price_eur */
/** @var float $vtc_price_eur */
/** @var float $drgx_price_eur */
/** @var float $xmr_price_eur */
$hasXelisPrice = $xel_price_eur > 0;
$hasKaspaPrice = $kas_price_eur > 0;
$hasFiroPrice = $firo_price_eur > 0;
$hasVergePrice = $xvg_price_eur > 0;
$hasPepecoinPrice = $pepe_price_eur > 0;
$hasVertcoinPrice = $vtc_price_eur > 0;
$hasDragonxPrice = $drgx_price_eur > 0;
$hasMoneroPrice = $xmr_price_eur > 0;
?>

<h1><i class="fas fa-crown"></i> VIP - Acheter des points</h1>
<p>Boostez vos liens en achetant des points supplémentaires !</p>

<?php if ($hasXelisPrice || $hasKaspaPrice || $hasFiroPrice || $hasVergePrice || $hasPepecoinPrice || $hasVertcoinPrice || $hasDragonxPrice || $hasMoneroPrice): ?>
<div style="text-align:center;margin-bottom:1.5rem;padding:.75rem 1rem;background:var(--bg);border-radius:8px;display:flex;justify-content:center;gap:2rem;flex-wrap:wrap;align-items:center;">
    <?php if ($hasXelisPrice): ?>
    <span>
        <i class="fas fa-chart-line"></i>
        <strong>XELIS :</strong>
        1 XEL = <?= e(number_format($xel_price_eur, 4, ',', ' ')) ?> €
    </span>
    <?php endif; ?>
    <?php if ($hasKaspaPrice): ?>
    <span>
        <i class="fas fa-gem" style="color:#4fc3f7;"></i>
        <strong>Kaspa :</strong>
        1 KAS = <?= e(number_format($kas_price_eur, 6, ',', ' ')) ?> €
    </span>
    <?php endif; ?>
    <?php if ($hasFiroPrice): ?>
    <span>
        <i class="fas fa-shield-alt" style="color:#e65100;"></i>
        <strong>Firo :</strong>
        1 FIRO = <?= e(number_format($firo_price_eur, 4, ',', ' ')) ?> €
    </span>
    <?php endif; ?>
    <?php if ($hasVergePrice): ?>
    <span>
        <i class="fas fa-lock" style="color:#2b1f6b;"></i>
        <strong>Verge :</strong>
        1 XVG = <?= e(number_format($xvg_price_eur, 6, ',', ' ')) ?> €
    </span>
    <?php endif; ?>
    <?php if ($hasPepecoinPrice): ?>
    <span>
        <i class="fas fa-frog" style="color:#2f9e44;"></i>
        <strong>Pepecoin :</strong>
        1 PEPE = <?= e(number_format($pepe_price_eur, 8, ',', ' ')) ?> €
    </span>
    <?php endif; ?>
    <?php if ($hasVertcoinPrice): ?>
    <span>
        <i class="fas fa-leaf" style="color:#048657;"></i>
        <strong>Vertcoin :</strong>
        1 VTC = <?= e(number_format($vtc_price_eur, 4, ',', ' ')) ?> €
    </span>
    <?php endif; ?>
    <?php if ($hasDragonxPrice): ?>
    <span>
        <i class="fas fa-dragon" style="color:#e8590c;"></i>
        <strong>DragonX :</strong>
        1 DRGX = <?= e(number_format($drgx_price_eur, 4, ',', ' ')) ?> €
    </span>
    <?php endif; ?>
    <?php if ($hasMoneroPrice): ?>
    <span>
        <i class="fas fa-user-secret" style="color:#ff6600;"></i>
        <strong>Monero :</strong>
        1 XMR = <?= e(number_format($xmr_price_eur, 2, ',', ' ')) ?> €
    </span>
    <?php endif; ?>
    <span style="color:var(--text-muted);font-size:.8rem;">
        <i class="fas fa-sync-alt"></i> Prix temps réel
        <?php $cacheTime = date('H:i:s'); ?>
        (<?= e($cacheTime) ?>)
    </span>
</div>
<?php endif; ?>

<div class="vip-grid">
<?php foreach ($vip_packs as $id => $pack): ?>
<div class="vip-card">
    <div class="vip-badge">Pack <?= $id ?></div>
    <h3><?= format_number($pack['points'] ?? 0) ?> points</h3>
    <div class="vip-price"><?= e($pack['price'] ?? '0') ?>€</div>
    <div style="color:var(--text-muted);font-size:.85rem;margin-bottom:.5rem;">
        <?php if ($hasXelisPrice && !empty($pack['price_xel'])): ?>
            ≈ <?= e(number_format($pack['price_xel'], 4, '.', ' ')) ?> XEL
        <?php endif; ?>
        <?php if (($hasKaspaPrice || $hasFiroPrice) && !empty($pack['price_kas'])): ?>
            &nbsp;|&nbsp;
        <?php endif; ?>
        <?php if ($hasKaspaPrice && !empty($pack['price_kas'])): ?>
            ≈ <?= e(number_format($pack['price_kas'], 2, '.', ' ')) ?> KAS
        <?php endif; ?>
        <?php if ($hasFiroPrice && !empty($pack['price_firo'])): ?>
            &nbsp;|&nbsp;≈ <?= e(number_format($pack['price_firo'], 2, '.', ' ')) ?> FIRO
        <?php endif; ?>
        <?php if ($hasVergePrice && !empty($pack['price_xvg'])): ?>
            &nbsp;|&nbsp;≈ <?= e(number_format($pack['price_xvg'], 0, '.', ' ')) ?> XVG
        <?php endif; ?>
        <?php if ($hasPepecoinPrice && !empty($pack['price_pepe'])): ?>
            &nbsp;|&nbsp;≈ <?= e(number_format($pack['price_pepe'], 2, '.', ' ')) ?> PEPE
        <?php endif; ?>
        <?php if ($hasVertcoinPrice && !empty($pack['price_vtc'])): ?>
            &nbsp;|&nbsp;≈ <?= e(number_format($pack['price_vtc'], 2, '.', ' ')) ?> VTC
        <?php endif; ?>
        <?php if ($hasDragonxPrice && !empty($pack['price_drgx'])): ?>
            &nbsp;|&nbsp;≈ <?= e(number_format($pack['price_drgx'], 2, '.', ' ')) ?> DRGX
        <?php endif; ?>
        <?php if ($hasMoneroPrice && !empty($pack['price_xmr'])): ?>
            &nbsp;|&nbsp;≈ <?= e(number_format($pack['price_xmr'], 6, '.', ' ')) ?> XMR
        <?php endif; ?>
    </div>
    <div class="vip-actions">
        <form method="POST" action="vip/paypal"><?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="pack_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-primary btn-block"><i class="fab fa-paypal"></i> PayPal</button>
        </form>
        <form method="POST" action="vip/xelis" class="mt-1"><?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="pack_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-warning btn-block">
                <i class="fas fa-coins"></i> Xelis
                <?php if ($hasXelisPrice && !empty($pack['price_xel'])): ?>
                    (<?= e(number_format($pack['price_xel'], 2, '.', '')) ?> XEL)
                <?php endif; ?>
            </button>
        </form>
        <form method="POST" action="vip/kaspa" class="mt-1"><?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="pack_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-block" style="background:#4fc3f7;color:#000;">
                <i class="fas fa-gem"></i> Kaspa
                <?php if ($hasKaspaPrice && !empty($pack['price_kas'])): ?>
                    (<?= e(number_format($pack['price_kas'], 0, '.', ' ')) ?> KAS)
                <?php endif; ?>
            </button>
        </form>
        <form method="POST" action="vip/firo" class="mt-1"><?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="pack_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-block" style="background:#e65100;color:#fff;">
                <i class="fas fa-shield-alt"></i> Firo
                <?php if ($hasFiroPrice && !empty($pack['price_firo'])): ?>
                    (<?= e(number_format($pack['price_firo'], 2, '.', ' ')) ?> FIRO)
                <?php endif; ?>
            </button>
        </form>
        <form method="POST" action="vip/verge" class="mt-1"><?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="pack_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-block" style="background:#2b1f6b;color:#fff;">
                <i class="fas fa-lock"></i> Verge
                <?php if ($hasVergePrice && !empty($pack['price_xvg'])): ?>
                    (<?= e(number_format($pack['price_xvg'], 0, '.', ' ')) ?> XVG)
                <?php endif; ?>
            </button>
        </form>
        <form method="POST" action="vip/pepecoin" class="mt-1"><?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="pack_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-block" style="background:#2f9e44;color:#fff;">
                <i class="fas fa-frog"></i> Pepecoin
                <?php if ($hasPepecoinPrice && !empty($pack['price_pepe'])): ?>
                    (<?= e(number_format($pack['price_pepe'], 2, '.', ' ')) ?> PEPE)
                <?php endif; ?>
            </button>
        </form>
        <form method="POST" action="vip/vertcoin" class="mt-1"><?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="pack_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-block" style="background:#048657;color:#fff;">
                <i class="fas fa-leaf"></i> Vertcoin
                <?php if ($hasVertcoinPrice && !empty($pack['price_vtc'])): ?>
                    (<?= e(number_format($pack['price_vtc'], 2, '.', ' ')) ?> VTC)
                <?php endif; ?>
            </button>
        </form>
        <form method="POST" action="vip/dragonx" class="mt-1"><?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="pack_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-block" style="background:#e8590c;color:#fff;">
                <i class="fas fa-dragon"></i> DragonX
                <?php if ($hasDragonxPrice && !empty($pack['price_drgx'])): ?>
                    (<?= e(number_format($pack['price_drgx'], 2, '.', ' ')) ?> DRGX)
                <?php endif; ?>
            </button>
        </form>
        <form method="POST" action="vip/monero" class="mt-1"><?= \App\Core\View::csrfField() ?>
            <input type="hidden" name="pack_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-block" style="background:#ff6600;color:#fff;">
                <i class="fas fa-user-secret"></i> Monero
                <?php if ($hasMoneroPrice && !empty($pack['price_xmr'])): ?>
                    (<?= e(number_format($pack['price_xmr'], 4, '.', ' ')) ?> XMR)
                <?php endif; ?>
            </button>
        </form>
    </div>
</div>
<?php endforeach; ?>
</div>

<div class="card mt-2"><div class="card-body">
    <h3><i class="fas fa-info-circle"></i> Comment ça marche ?</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem;">
        <div>
            <h4><i class="fab fa-paypal" style="color:#0070ba;"></i> PayPal</h4>
            <ul>
                <li>Cliquez sur le bouton PayPal du pack choisi</li>
                <li>Envoyez le montant à l'adresse PayPal de l'admin</li>
                <li>Crédité après confirmation manuelle</li>
            </ul>
        </div>
        <div>
            <h4><i class="fas fa-coins" style="color:var(--warning);"></i> Xelis (XEL)</h4>
            <ul>
                <li>Page de paiement avec QR code</li>
                <li>Envoyez le montant <strong>exact</strong> en XEL</li>
                <li>Collez le hash de transaction</li>
                <li>Confirmation auto (si RPC) ou manuelle</li>
            </ul>
        </div>
        <div>
            <h4><i class="fas fa-gem" style="color:#4fc3f7;"></i> Kaspa (KAS)</h4>
            <ul>
                <li>Page de paiement avec QR code</li>
                <li>Envoyez le montant <strong>exact</strong> en KAS</li>
                <li>Vérification automatique via UTXO matching</li>
                <li>Confirmation <strong>quasi-instantanée</strong> (~1s)</li>
            </ul>
            <?php if ($hasKaspaPrice): ?>
            <p style="font-size:.85rem;color:var(--text-muted);">
                <i class="fas fa-bolt"></i> Frais quasi-nuls (&lt; $0.01), prix temps réel CoinGecko.
            </p>
            <?php endif; ?>
        </div>
        <div>
            <h4><i class="fas fa-shield-alt" style="color:#e65100;"></i> Firo (FIRO)</h4>
            <ul>
                <li>Page de paiement avec QR code</li>
                <li>Envoyez le montant <strong>exact</strong> en FIRO</li>
                <li>Vérification automatique via UTXO matching</li>
                <li>Confirmation après <strong>2 blocs</strong> (~20 min)</li>
            </ul>
            <?php if ($hasFiroPrice): ?>
            <p style="font-size:.85rem;color:var(--text-muted);">
                <i class="fas fa-user-shield"></i> Cryptomonnaie axée sur la confidentialité (Spark protocol).
            </p>
            <?php endif; ?>
        </div>
        <div>
            <h4><i class="fas fa-lock" style="color:#2b1f6b;"></i> Verge (XVG)</h4>
            <ul>
                <li>Page de paiement avec QR code</li>
                <li>Envoyez le montant <strong>exact</strong> en XVG</li>
                <li>Vérification automatique via la blockchain</li>
                <li>Confirmation <strong>rapide</strong> (~3 min, blocs 30s)</li>
            </ul>
            <?php if ($hasVergePrice): ?>
            <p style="font-size:.85rem;color:var(--text-muted);">
                <i class="fas fa-bolt"></i> Frais quasi-nuls, confirmation rapide, vie privée.
            </p>
            <?php endif; ?>
        </div>
        <div>
            <h4><i class="fas fa-frog" style="color:#2f9e44;"></i> Pepecoin (PEPE)</h4>
            <ul>
                <li>Page de paiement avec QR code</li>
                <li>Envoyez le montant <strong>exact</strong> en PEPE</li>
                <li>Vérification automatique via la blockchain</li>
                <li>Confirmation <strong>rapide</strong> (~6 min, blocs 1 min)</li>
            </ul>
            <?php if ($hasPepecoinPrice): ?>
            <p style="font-size:.85rem;color:var(--text-muted);">
                <i class="fas fa-bolt"></i> Fork Dogecoin (Scrypt PoW), frais très faibles.
            </p>
            <?php endif; ?>
        </div>
        <div>
            <h4><i class="fas fa-leaf" style="color:#048657;"></i> Vertcoin (VTC)</h4>
            <ul>
                <li>Page de paiement avec QR code</li>
                <li>Envoyez le montant <strong>exact</strong> en VTC</li>
                <li>Vérification automatique via la blockchain</li>
                <li>Confirmation après <strong>6 blocs</strong> (~15 min, blocs 2,5 min)</li>
            </ul>
            <?php if ($hasVertcoinPrice): ?>
            <p style="font-size:.85rem;color:var(--text-muted);">
                <i class="fas fa-bolt"></i> Fork Bitcoin (Lyra2REv3 PoW), décentralisé depuis 2014.
            </p>
            <?php endif; ?>
        </div>
        <div>
            <h4><i class="fas fa-dragon" style="color:#e8590c;"></i> DragonX (DRGX)</h4>
            <ul>
                <li>Page de paiement avec QR code</li>
                <li>Envoyez le montant <strong>exact</strong> en DRGX</li>
                <li>Vérification automatique via la blockchain</li>
                <li>Confirmation après <strong>10 blocs</strong> (~6 min, blocs ~34 s)</li>
            </ul>
            <?php if ($hasDragonxPrice): ?>
            <p style="font-size:.85rem;color:var(--text-muted);">
                <i class="fas fa-bolt"></i> Protocole Zcash (RandomX PoW CPU), paiements rapides et privés.
            </p>
            <?php endif; ?>
        </div>
        <div>
            <h4><i class="fas fa-user-secret" style="color:#ff6600;"></i> Monero (XMR)</h4>
            <ul>
                <li>Page de paiement avec QR code</li>
                <li>Envoyez les <strong>XMR</strong> à l'adresse indiquée</li>
                <li>Collez le hash de transaction pour vérification</li>
                <li>Confirmation <strong>manuelle</strong> par l'admin (ou auto via wallet RPC)</li>
            </ul>
            <?php if ($hasMoneroPrice): ?>
            <p style="font-size:.85rem;color:var(--text-muted);">
                <i class="fas fa-shield-alt"></i> La référence en matière de confidentialité. 10 confirmations requises (~20 min).
            </p>
            <?php endif; ?>
        </div>
    </div>
</div></div>
