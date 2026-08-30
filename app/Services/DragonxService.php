<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Service DragonX (DRGX) - Intégration complète avec la blockchain DragonX
 *
 * DragonX est un fork du protocole Zcash (zk-SNARKs, RandomX PoW CPU,
 * Hush Arrakis Chain) :
 * - 1 DRGX = 100 000 000 satoshis (zatoshi)
 * - UTXO transparent + pool shielded (on ne gère que le transparent)
 * - Blocs en ~34 secondes
 * - API REST publique de l'explorateur officiel (explorer.dragonx.is/api)
 * - Adresses transparentes mainnet commençant par R
 * - URI de paiement : dragonx:adresse?amount=...
 *
 * Stratégie de paiement par UTXO matching :
 * - Chaque paiement a un montant UNIQUE (montant de base + nonce aléatoire en satoshis)
 * - On vérifie les UTXO de l'adresse pour trouver celui qui correspond exactement
 * - Cela permet de distinguer des paiements simultanés vers la même adresse
 *
 * Endpoints API utilisés (explorateur officiel) :
 * - GET /api/status                 → hauteur du tip ({"blocks": N})
 * - GET /api/address/{addr}/utxos   → UTXO [{txid, vout_index, value, height, time}]
 * - GET /api/tx/{txid}              → détail TX (outputs[].value, outputs[].addresses, block_height)
 *
 * Configuration admin requise (settings) :
 * - dragonx_address : Adresse DragonX transparente de réception des paiements
 * - dragonx_api_url : URL de l'API de l'explorateur (défaut: https://explorer.dragonx.is/api)
 * - dragonx_rate_eur : Taux EUR/DRGX manuel (0 = auto CoinGecko)
 * - dragonx_payment_timeout : Délai expiration paiement en minutes (défaut: 30)
 * - dragonx_min_confirmations : Confirmations minimales (défaut: 10, ~6 min)
 */
class DragonxService
{
    private const SATOSHI_PER_DRGX = 100000000; // 1 DRGX = 100 000 000 satoshis
    private const COINGECKO_API = 'https://api.coingecko.com/api/v3/simple/price';
    private const COINGECKO_ID = 'dragonx-2';
    private const CACHE_TTL = 300; // 5 minutes cache prix
    private const NONCE_MAX = 99999; // Nonce max pour différencier les paiements (en satoshis)
    private const MAX_PENDING_PER_USER = 3;
    private const MIN_PENDING_INTERVAL = 30; // Secondes minimum entre 2 créations
    private const API_TIMEOUT = 15;
    private const API_CONNECT_TIMEOUT = 5;
    private const MAX_API_RETRIES = 2;

    private Database $db;
    private string $apiUrl;
    /** @var string[] */
    private array $errors = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->apiUrl = rtrim($this->getSetting('dragonx_api_url', 'https://explorer.dragonx.is/api'), '/');
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    // ========================================
    // PRIX ET CONVERSION
    // ========================================

    /**
     * Récupère le prix DRGX/EUR via CoinGecko avec cache
     */
    public function getDrgxPriceEur(): float
    {
        // Taux manuel admin
        $manualRate = $this->getSetting('dragonx_rate_eur', '0');
        if ((float) $manualRate > 0) {
            return (float) $manualRate;
        }

        // Cache
        $cached = $this->getCache('dragonx_price_eur');
        if ($cached !== null) {
            return (float) $cached;
        }

        // API CoinGecko
        $price = $this->fetchCoinGeckoPrice();
        if ($price > 0) {
            $this->setCache('dragonx_price_eur', (string) $price, self::CACHE_TTL);
        } else {
            $stalePrice = $this->getCache('dragonx_price_eur_stale');
            if ($stalePrice !== null) {
                $this->log('Prix CoinGecko indisponible, utilisation du dernier prix connu');
                return (float) $stalePrice;
            }
            $this->log('Impossible de récupérer le prix DRGX/EUR depuis CoinGecko');
        }

        return $price;
    }

    public function eurToDrgx(float $eur): float
    {
        $price = $this->getDrgxPriceEur();
        if ($price <= 0) return 0.0;
        return round($eur / $price, 8);
    }

    public function drgxToEur(float $drgx): float
    {
        $price = $this->getDrgxPriceEur();
        return round($drgx * $price, 2);
    }

    public function toSatoshi(float $drgx): string
    {
        return (string) (int) round($drgx * self::SATOSHI_PER_DRGX);
    }

    /**
     * Convertit un montant décimal API (string "3.00030000") en satoshis
     */
    public function valueToSatoshi(string $value): string
    {
        return (string) (int) round((float) $value * self::SATOSHI_PER_DRGX);
    }

    public function fromSatoshi(string $satoshi): float
    {
        return (int) $satoshi / self::SATOSHI_PER_DRGX;
    }

    // ========================================
    // PAIEMENTS
    // ========================================

    /**
     * Crée un paiement DragonX pour un achat VIP
     */
    public function createPayment(int $userId, int $packId): ?array
    {
        // Protection anti-doublon
        $existingPending = $this->db->queryOne(
            "SELECT id, created_at FROM purchases
             WHERE user_id = ? AND payment_method = 'dragonx' AND status = 'pending'
             ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );

        if ($existingPending) {
            $createdAt = strtotime($existingPending['created_at']);
            if ((time() - $createdAt) < self::MIN_PENDING_INTERVAL) {
                $this->log("Rate limit: utilisateur #{$userId} a déjà un paiement en attente récent");
                return $this->getExistingPaymentData((int) $existingPending['id']);
            }

            $pendingCount = $this->db->queryOne(
                "SELECT COUNT(*) as cnt FROM purchases
                 WHERE user_id = ? AND payment_method = 'dragonx' AND status = 'pending'
                 AND expires_at > NOW()",
                [$userId]
            );
            if ((int) ($pendingCount['cnt'] ?? 0) >= self::MAX_PENDING_PER_USER) {
                $this->log("Limite atteinte: utilisateur #{$userId} a {$pendingCount['cnt']} paiements en attente");
                return null;
            }
        }

        // Infos du pack
        $points = (int) ($this->getSetting("vip_points_{$packId}", '0'));
        $priceEur = (float) $this->getSetting("vip_price_{$packId}", '0');

        if ($points <= 0 || $priceEur <= 0) {
            $this->log("Pack invalide: points={$points}, prix={$priceEur}");
            return null;
        }

        // Conversion EUR → DRGX : le taux est FIGÉ à la création de la
        // commande et mémorisé dans purchases.crypto_rate_eur ; les
        // fluctuations ultérieures du marché n'impactent jamais la commande.
        $rate = $this->getDrgxPriceEur();
        if ($rate <= 0) {
            $this->log("Taux EUR/DRGX indisponible pour {$priceEur}€");
            return null;
        }
        $drgxAmount = round($priceEur / $rate, 8);
        if ($drgxAmount <= 0) {
            $this->log("Conversion EUR→DRGX échouée pour {$priceEur}€");
            return null;
        }

        // Adresse de réception : mainnet stricte (base58check + préfixe R).
        // Une adresse invalide ou de testnet est refusée AVANT toute création
        // de commande.
        $address = $this->getSetting('dragonx_address', '');
        if ($address === '' || !$this->validateAddress($address)) {
            $this->log("Adresse DragonX non configurée ou invalide (mainnet attendu, préfixe R)");
            return null;
        }

        // Nonce unique pour UTXO matching
        $nonceSatoshi = random_int(1, self::NONCE_MAX);
        $baseSatoshi = (int) round($drgxAmount * self::SATOSHI_PER_DRGX);
        $totalSatoshi = $baseSatoshi + $nonceSatoshi;
        $exactDrgx = $totalSatoshi / self::SATOSHI_PER_DRGX;

        // Timeout
        $timeoutMinutes = (int) $this->getSetting('dragonx_payment_timeout', '30');
        $expiresAt = date('Y-m-d H:i:s', time() + ($timeoutMinutes * 60));

        // Créer l'achat
        $purchaseId = $this->db->insert('purchases', [
            'user_id' => $userId,
            'amount_eur' => $priceEur,
            'amount_xelis' => $exactDrgx, // Réutilise le champ pour le montant crypto
            'payment_method' => 'dragonx',
            'points_purchased' => $points,
            'status' => 'pending',
            'payment_address' => $address,
            'user_xelis_address' => '',
            'expires_at' => $expiresAt,
        ]);

        // Mémorise le taux EUR/DRGX appliqué à la commande (colonne optionnelle
        // sur les installations antérieures à la migration crypto_rate_eur)
        try {
            $this->db->execute(
                "UPDATE purchases SET crypto_rate_eur = ? WHERE id = ?",
                [number_format($rate, 8, '.', ''), $purchaseId]
            );
        } catch (\Throwable $e) {}

        $dragonxUri = $this->buildDragonxUri($address, $exactDrgx);

        return [
            'purchase_id' => $purchaseId,
            'payment_address' => $address,
            'drgx_amount' => $exactDrgx,
            'satoshi_amount' => (string) $totalSatoshi,
            'base_drgx' => $drgxAmount,
            'nonce_satoshi' => $nonceSatoshi,
            'eur_amount' => $priceEur,
            'points' => $points,
            'expires_at' => $expiresAt,
            'timeout_minutes' => $timeoutMinutes,
            'dragonx_uri' => $dragonxUri,
        ];
    }

    private function getExistingPaymentData(int $purchaseId): ?array
    {
        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'dragonx' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase || strtotime($purchase['expires_at']) < time()) {
            return null;
        }

        $dragonxUri = $this->buildDragonxUri(
            $purchase['payment_address'] ?? '',
            (float) $purchase['amount_xelis']
        );

        return [
            'purchase_id' => (int) $purchase['id'],
            'payment_address' => $purchase['payment_address'],
            'drgx_amount' => (float) $purchase['amount_xelis'],
            'satoshi_amount' => $this->toSatoshi((float) $purchase['amount_xelis']),
            'base_drgx' => (float) $purchase['amount_xelis'],
            'nonce_satoshi' => 0,
            'eur_amount' => (float) $purchase['amount_eur'],
            'points' => (int) $purchase['points_purchased'],
            'expires_at' => $purchase['expires_at'],
            'timeout_minutes' => (int) (($purchase['expires_at'] ? strtotime($purchase['expires_at']) - time() : 0) / 60),
            'dragonx_uri' => $dragonxUri,
        ];
    }

    /**
     * Vérifie le statut d'un paiement
     */
    public function getPaymentStatus(int $purchaseId): array
    {
        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'dragonx'",
            [$purchaseId]
        );

        if (!$purchase) {
            return ['status' => 'not_found', 'message' => 'Paiement non trouvé.'];
        }

        if ($purchase['status'] === 'completed') {
            return [
                'status' => 'completed',
                'message' => 'Paiement confirmé ! Vos points ont été crédités.',
                'transaction_hash' => $purchase['transaction_id'],
            ];
        }

        if ($purchase['status'] === 'cancelled') {
            return ['status' => 'cancelled', 'message' => 'Paiement annulé.'];
        }

        if (strtotime($purchase['expires_at']) < time()) {
            $this->db->update('purchases', ['status' => 'cancelled'], "id = ? AND status = 'pending'", [$purchaseId]);
            return ['status' => 'expired', 'message' => 'Paiement expiré. Veuillez réessayer.'];
        }

        // Vérifier via les UTXO de l'adresse
        $expectedSatoshi = $this->toSatoshi((float) $purchase['amount_xelis']);
        $address = $purchase['payment_address'];
        $verification = $this->verifyPayment($address, $expectedSatoshi);

        if ($verification['found']) {
            // Détecté ≠ confirmé : une transaction vue par l'API n'est validée
            // qu'après un hash exploitable ET le seuil de confirmations.
            $minConfirmations = max(1, (int) $this->getSetting('dragonx_min_confirmations', '10'));
            $txId = (string) ($verification['tx_id'] ?? '');
            $txConfirmations = (int) ($verification['confirmations'] ?? 0);
            if ($txId === '' || $txConfirmations < $minConfirmations) {
                return [
                    'status' => 'waiting',
                    'message' => "Paiement détecté — en attente de confirmations ({$txConfirmations}/{$minConfirmations})",
                    'remaining_seconds' => max(0, strtotime($purchase['expires_at']) - time()),
                    'drgx_amount' => (float) $purchase['amount_xelis'],
                    'payment_address' => $address,
                    'confirmations' => $txConfirmations,
                    'required_confirmations' => $minConfirmations,
                ];
            }

            $this->confirmPurchase($purchaseId, $purchase, $txId);
            return [
                'status' => 'completed',
                'message' => 'Paiement confirmé sur la blockchain ! Points crédités.',
                'transaction_hash' => $txId,
                'sender_address' => $verification['sender'] ?? '',
            ];
        }

        // Vérifier si un hash de TX a été soumis
        if (!empty($purchase['transaction_id'])) {
            $txDetails = $this->getTransaction($purchase['transaction_id']);
            if ($txDetails && $this->isTransactionValidForPurchase($txDetails, $purchase)) {
                $txConfirmations = $this->getTransactionConfirmations($txDetails);
                $minConfirmations = (int) $this->getSetting('dragonx_min_confirmations', '10');
                if ($txConfirmations >= $minConfirmations) {
                    $this->confirmPurchase($purchaseId, $purchase, $purchase['transaction_id']);
                    return [
                        'status' => 'completed',
                        'message' => 'Paiement confirmé via soumission TX ! Points crédités.',
                        'transaction_hash' => $purchase['transaction_id'],
                    ];
                }
                return [
                    'status' => 'waiting',
                    'message' => "Paiement détecté — en attente de confirmations ({$txConfirmations}/{$minConfirmations})",
                    'remaining_seconds' => max(0, strtotime($purchase['expires_at']) - time()),
                    'drgx_amount' => (float) $purchase['amount_xelis'],
                    'payment_address' => $address,
                    'confirmations' => $txConfirmations,
                    'required_confirmations' => $minConfirmations,
                ];
            }
        }

        $remainingSeconds = max(0, strtotime($purchase['expires_at']) - time());
        return [
            'status' => 'waiting',
            'message' => 'En attente du paiement DragonX...',
            'remaining_seconds' => $remainingSeconds,
            'drgx_amount' => (float) $purchase['amount_xelis'],
            'payment_address' => $address,
        ];
    }

    /**
     * Soumet un hash de transaction pour vérification
     */
    public function submitTransactionHash(int $purchaseId, string $txHash): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $txHash)) {
            return ['success' => false, 'message' => 'Format de hash invalide. 64 caractères hexadécimaux attendus.'];
        }
        $txHash = strtolower($txHash);

        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'dragonx' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase) {
            return ['success' => false, 'message' => 'Achat non trouvé ou déjà traité.'];
        }

        // Une TX déjà enregistrée sur un autre achat (quel que soit son
        // statut) n'est jamais réutilisable
        $existing = $this->db->queryOne(
            "SELECT id FROM purchases WHERE transaction_id = ? AND id != ?",
            [$txHash, $purchaseId]
        );
        if ($existing) {
            return ['success' => false, 'message' => 'Cette transaction a déjà été soumise pour un autre achat.'];
        }

        // Vérifier via l'API
        $txDetails = $this->getTransaction($txHash);
        if ($txDetails) {
            if ($this->isTransactionValidForPurchase($txDetails, $purchase)) {
                $minConfirmations = (int) $this->getSetting('dragonx_min_confirmations', '10');
                $txConfirmations = $this->getTransactionConfirmations($txDetails);
                if ($txConfirmations >= $minConfirmations) {
                    $this->confirmPurchase($purchaseId, $purchase, $txHash);
                    return [
                        'success' => true,
                        'message' => 'Transaction vérifiée et confirmée ! Points crédités.',
                        'auto_confirmed' => true,
                    ];
                }
                // TX valide mais pas assez de confirmations
                $this->db->update('purchases', ['transaction_id' => $txHash], 'id = ?', [$purchaseId]);
                return [
                    'success' => true,
                    'message' => "Paiement détecté — en attente de confirmations ({$txConfirmations}/{$minConfirmations})",
                    'auto_confirmed' => false,
                    'confirmations' => $txConfirmations,
                ];
            }

            $this->log("TX {$txHash} soumise pour achat #{$purchaseId} mais montant/adresse incorrect(e)");
            return [
                'success' => false,
                'message' => 'La transaction ne correspond pas au paiement attendu. Vérifiez l\'adresse et le montant.',
            ];
        }

        // TX pas encore dans l'API
        $this->db->update('purchases', ['transaction_id' => $txHash], 'id = ?', [$purchaseId]);

        return [
            'success' => true,
            'message' => 'Hash enregistré. La vérification se fera automatiquement dans quelques instants.',
            'auto_confirmed' => false,
        ];
    }

    /**
     * Vérifie qu'une transaction correspond à un achat
     * Format explorateur DragonX : outputs[].value = string décimale DRGX,
     * outputs[].addresses[0] = adresse
     */
    private function isTransactionValidForPurchase(array $txDetails, array $purchase): bool
    {
        $expectedAddress = $purchase['payment_address'] ?? '';
        $expectedSatoshi = $this->toSatoshi((float) $purchase['amount_xelis']);

        if (empty($expectedAddress)) return false;

        // Vérifier les outputs de la transaction
        $outputs = $txDetails['outputs'] ?? [];
        foreach ($outputs as $output) {
            $outputSatoshi = $this->valueToSatoshi((string) ($output['value'] ?? '0'));
            $outputAddresses = $output['addresses'] ?? [];
            $outputAddress = $outputAddresses[0] ?? '';

            if ($outputAddress === $expectedAddress) {
                // Match exact
                if ($outputSatoshi === $expectedSatoshi) {
                    return true;
                }
                // Tolérance nonce (1-99999 satoshis)
                $diff = abs((int) $outputSatoshi - (int) $expectedSatoshi);
                if ($diff >= 0 && $diff <= self::NONCE_MAX) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Confirme un achat et crédite les points
     */
    public function confirmPurchase(int $purchaseId, array $purchase, string $txHash = ''): bool
    {
        // Garde atomique : seule une commande encore « pending » peut être
        // confirmée. Une commande annulée ou expirée n'est jamais créditée
        // (pas de remboursement : rien n'a été débité à la création).
        $updated = $this->db->execute(
            "UPDATE purchases SET status = 'completed', confirmed_at = ?, transaction_id = ?
             WHERE id = ? AND status = 'pending'",
            [date('Y-m-d H:i:s'), $txHash ?: ($purchase['transaction_id'] ?? ''), $purchaseId]
        );
        if ($updated === 0) {
            $this->log("confirmPurchase ignoré pour achat #{$purchaseId} : déjà traité ou annulé");
            return false;
        }

        $this->db->execute(
            "UPDATE users SET points = points + ? WHERE id = ?",
            [(int) $purchase['points_purchased'], (int) $purchase['user_id']]
        );

        $this->db->insert('points_history', [
            'user_id' => (int) $purchase['user_id'],
            'amount' => (int) $purchase['points_purchased'],
            'type' => 'purchase',
            'description' => "Achat VIP DragonX confirmé - {$purchase['points_purchased']} points",
            'reference_id' => $purchaseId,
            'reference_type' => 'purchase',
        ]);

        $this->log("Achat #{$purchaseId} confirmé - TX: {$txHash}");
        return true;
    }

    // ========================================
    // VÉRIFICATION BLOCKCHAIN (UTXO MATCHING)
    // ========================================

    /**
     * Vérifie un paiement via les UTXO de l'adresse
     * Utilise l'API de l'explorateur pour récupérer les UTXO non dépensés
     * et la hauteur du tip pour calculer les confirmations.
     */
    public function verifyPayment(string $address, string $expectedSatoshi): array
    {
        $utxoData = $this->getAddressUtxos($address);
        if ($utxoData === null) {
            return ['found' => false];
        }

        $tipHeight = $this->getTipHeight();

        $utxos = $utxoData['utxos'] ?? [];
        foreach ($utxos as $utxo) {
            $utxoSatoshi = $this->valueToSatoshi((string) ($utxo['value'] ?? '0'));

            // Match exact ou tolérance nonce (1-99999 satoshis)
            $diff = abs((int) $utxoSatoshi - (int) $expectedSatoshi);
            if ($diff > self::NONCE_MAX) {
                continue;
            }

            $txId = (string) ($utxo['txid'] ?? '');
            if ($txId === '') continue;

            // Vérifier que ce TX n'est pas déjà utilisé
            $alreadyUsed = $this->db->queryOne(
                "SELECT id FROM purchases WHERE transaction_id = ? AND status = 'completed'",
                [$txId]
            );
            if ($alreadyUsed) continue;

            $confirmations = 0;
            if ($tipHeight !== null && !empty($utxo['height'])) {
                $confirmations = max(0, $tipHeight - (int) $utxo['height'] + 1);
            }

            return [
                'found' => true,
                'tx_id' => $txId,
                'sender' => $this->findSenderAddress($txId),
                'amount_satoshi' => $utxoSatoshi,
                'confirmations' => $confirmations,
            ];
        }

        return ['found' => false];
    }

    /**
     * Récupère les UTXO d'une adresse via l'explorateur
     * GET /api/address/{address}/utxos
     */
    public function getAddressUtxos(string $address): ?array
    {
        $result = $this->apiGetWithRetry("/address/{$address}/utxos");
        if ($result !== null && is_array($result)) {
            return $result;
        }

        $this->log("Échec récupération UTXO pour {$address}");
        return null;
    }

    /**
     * Récupère le solde d'une adresse (string décimale DRGX)
     */
    public function getAddressBalance(string $address): ?string
    {
        $result = $this->apiGetWithRetry("/address/{$address}?page=1&limit=1");
        if ($result !== null && is_array($result)) {
            return $result['address']['balance'] ?? '0';
        }

        return null;
    }

    /**
     * Récupère les détails d'une transaction via l'explorateur
     * GET /api/tx/{txid}
     */
    public function getTransaction(string $txId): ?array
    {
        $result = $this->apiGetWithRetry("/tx/{$txId}");
        if ($result && !empty($result['txid'])) {
            return $result;
        }
        return null;
    }

    /**
     * Récupère le statut du réseau DragonX (hauteur du tip)
     * GET /api/status
     */
    public function getNetworkStatus(): ?array
    {
        return $this->apiGetWithRetry('/status');
    }

    /**
     * Hauteur du dernier bloc (tip), null si l'API est injoignable
     */
    public function getTipHeight(): ?int
    {
        $status = $this->getNetworkStatus();
        if ($status && isset($status['blocks'])) {
            return (int) $status['blocks'];
        }
        return null;
    }

    /**
     * Confirmations d'une TX détaillée : tip - hauteur TX + 1
     * (0 si la TX n'est pas encore minée ou le tip inconnu)
     */
    private function getTransactionConfirmations(array $txDetails): int
    {
        $blockHeight = (int) ($txDetails['block_height'] ?? 0);
        if ($blockHeight <= 0) {
            return 0;
        }
        $tipHeight = $this->getTipHeight();
        if ($tipHeight === null) {
            return 0;
        }
        return max(0, $tipHeight - $blockHeight + 1);
    }

    /**
     * Trouve l'adresse de l'expéditeur d'une transaction
     */
    private function findSenderAddress(string $txId): string
    {
        $tx = $this->getTransaction($txId);
        if (!$tx) return '';

        $inputs = $tx['inputs'] ?? [];
        foreach ($inputs as $input) {
            if (!empty($input['address'])) {
                return (string) $input['address'];
            }
        }

        return '';
    }

    // ========================================
    // VÉRIFICATION AUTOMATIQUE (CRON)
    // ========================================

    public function checkAllPendingPayments(): array
    {
        $pending = $this->db->query(
            "SELECT * FROM purchases
             WHERE payment_method = 'dragonx'
             AND status = 'pending'"
        );

        $results = ['checked' => 0, 'confirmed' => 0, 'expired' => 0];

        foreach ($pending as $purchase) {
            if (strtotime($purchase['expires_at']) < time()) {
                $this->db->update('purchases', ['status' => 'cancelled'], "id = ? AND status = 'pending'", [(int) $purchase['id']]);
                $results['expired']++;
                continue;
            }

            $results['checked']++;

            // UTXO matching via les UTXO de l'adresse
            $expectedSatoshi = $this->toSatoshi((float) $purchase['amount_xelis']);
            $address = $purchase['payment_address'];
            $verification = $this->verifyPayment($address, $expectedSatoshi);

            if ($verification['found']) {
                $minConfirmations = (int) $this->getSetting('dragonx_min_confirmations', '10');
                $txConfirmations = $verification['confirmations'] ?? 0;
                if ($txConfirmations >= $minConfirmations) {
                    $this->confirmPurchase(
                        (int) $purchase['id'],
                        $purchase,
                        $verification['tx_id'] ?? ''
                    );
                    $results['confirmed']++;
                }
                continue;
            }

            // Vérifier par hash soumis
            if (!empty($purchase['transaction_id'])) {
                $txDetails = $this->getTransaction($purchase['transaction_id']);
                if ($txDetails && $this->isTransactionValidForPurchase($txDetails, $purchase)) {
                    $minConfirmations = (int) $this->getSetting('dragonx_min_confirmations', '10');
                    $txConfirmations = $this->getTransactionConfirmations($txDetails);
                    if ($txConfirmations >= $minConfirmations) {
                        $this->confirmPurchase(
                            (int) $purchase['id'],
                            $purchase,
                            $purchase['transaction_id']
                        );
                        $results['confirmed']++;
                    }
                }
            }
        }

        $this->log("Vérification cron: {$results['checked']} vérifiés, {$results['confirmed']} confirmés, {$results['expired']} expirés");
        return $results;
    }

    // ========================================
    // UTILITAIRES
    // ========================================

    /**
     * Construit un URI DragonX pour QR code
     * Format: dragonx:address?amount=value
     */
    public function buildDragonxUri(string $address, float $amount): string
    {
        return "dragonx:{$address}?amount=" . number_format($amount, 8, '.', '');
    }

    /**
     * Valide une adresse DragonX selon les règles propres au réseau :
     * mainnet transparent uniquement (préfixe « R »), alphabet base58
     * puis checksum base58check complet (25 octets : version + hash +
     * checksum, comme les adresses de type Zcash/Hush).
     * Les adresses shielded (zs...) sont refusées : le module ne vérifie
     * que les paiements transparents.
     */
    public function validateAddress(string $address): bool
    {
        $address = trim($address);

        // Alphabet base58 (sans 0, O, I, l), longueur réaliste
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{26,36}$/', $address)) {
            return false;
        }

        // Préfixe mainnet transparent obligatoire
        if (!preg_match('/^R/', $address)) {
            return false;
        }

        // Checksum base58check complet (validation définitive)
        return $this->isValidBase58Check($address);
    }

    /**
     * Vérifie le checksum base58check (25 octets : version + hash + checksum)
     */
    private function isValidBase58Check(string $address): bool
    {
        if (!function_exists('bcadd')) {
            // bcmath absent : on s'arrête au contrôle de format + préfixe
            return true;
        }

        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $num = '0';
        for ($i = 0, $n = strlen($address); $i < $n; $i++) {
            $idx = strpos($alphabet, $address[$i]);
            if ($idx === false) {
                return false;
            }
            $num = bcadd(bcmul($num, '58'), (string) $idx);
        }

        // Conversion grand entier → octets
        $hex = '';
        while (bccomp($num, '0') > 0) {
            $rem = (int) bcmod($num, '256');
            $hex = sprintf('%02x', $rem) . $hex;
            $num = bcdiv($num, '256', 0);
        }
        // Les '1' de tête représentent des octets nuls
        for ($i = 0; $i < strlen($address) && $address[$i] === '1'; $i++) {
            $hex = '00' . $hex;
        }

        if (strlen($hex) !== 50) {
            return false; // 25 octets attendus
        }

        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return false;
        }

        $payload = substr($bytes, 0, 21);
        $checksum = substr($bytes, 21, 4);
        $hash = hash('sha256', hash('sha256', $payload, true), true);

        return hash_equals(substr($hash, 0, 4), $checksum);
    }

    public function getExplorerTxUrl(string $txId): string
    {
        $explorerUrl = $this->getSetting('dragonx_explorer_url', 'https://explorer.dragonx.is');
        return rtrim($explorerUrl, '/') . '/tx/' . urlencode($txId);
    }

    public function getExplorerAddressUrl(string $address): string
    {
        $explorerUrl = $this->getSetting('dragonx_explorer_url', 'https://explorer.dragonx.is');
        return rtrim($explorerUrl, '/') . '/address/' . urlencode($address);
    }

    // ========================================
    // API REST AVEC RETRY
    // ========================================

    private function apiGetWithRetry(string $path): mixed
    {
        for ($i = 0; $i <= self::MAX_API_RETRIES; $i++) {
            $result = $this->apiGet($path);
            if ($result !== null) return $result;
            if ($i < self::MAX_API_RETRIES) {
                usleep(300000 * ($i + 1)); // 300ms, 600ms
            }
        }
        return null;
    }

    private function apiGet(string $path): mixed
    {
        if (!function_exists('curl_init')) {
            $this->log('cURL non disponible');
            return null;
        }

        $url = $this->apiUrl . $path;
        $ch = curl_init($url);
        if ($ch === false) return null;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::API_CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'EchangeLien-DragonxService/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            if ($httpCode === 429) {
                $this->log("Rate limit API DragonX (429) sur GET {$path}");
            } elseif ($httpCode !== 0) {
                $this->log("API GET {$path} → HTTP {$httpCode}");
            }
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON invalide depuis GET {$path}: " . json_last_error_msg());
            return null;
        }

        return $decoded;
    }

    private function fetchCoinGeckoPrice(): float
    {
        // Appel groupé partagé entre tous les services (limite de débit CoinGecko)
        $price = coingecko_fetch_eur(self::COINGECKO_ID);

        if ($price > 0) {
            $this->setCache('dragonx_price_eur_stale', (string) $price, 86400);
        }

        return $price;
    }

    // ========================================
    // CACHE & SETTINGS
    // ========================================

    private function getSetting(string $key, string $default = ''): string
    {
        $result = $this->db->queryOne(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            [$key]
        );
        return $result['setting_value'] ?? $default;
    }

    private function getCache(string $key): ?string
    {
        try {
            $result = $this->db->queryOne(
                "SELECT cache_value, cache_expires FROM cache WHERE cache_key = ?",
                [$key]
            );
            if ($result && strtotime($result['cache_expires']) > time()) {
                return $result['cache_value'];
            }
        } catch (\Throwable $e) {}
        return null;
    }

    private function setCache(string $key, string $value, int $ttl): void
    {
        try {
            $expires = date('Y-m-d H:i:s', time() + $ttl);
            $existing = $this->db->queryOne("SELECT cache_key FROM cache WHERE cache_key = ?", [$key]);
            if ($existing) {
                $this->db->execute(
                    "UPDATE cache SET cache_value = ?, cache_expires = ? WHERE cache_key = ?",
                    [$value, $expires, $key]
                );
            } else {
                $this->db->insert('cache', [
                    'cache_key' => $key,
                    'cache_value' => $value,
                    'cache_expires' => $expires,
                ]);
            }
        } catch (\Throwable $e) {}
    }

    private function log(string $message): void
    {
        $this->errors[] = $message;
        try {
            $this->db->insert('logs', [
                'level' => 'warning',
                'message' => '[DragonxService] ' . $message,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {}
    }
}
