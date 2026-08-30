<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Service Firo - Intégration complète avec la blockchain Firo
 *
 * Firo (ex-Zcoin) est un fork Bitcoin avec fonctionnalités de confidentialité (Spark) :
 * - 1 FIRO = 100 000 000 satoshis
 * - UTXO-based (comme Bitcoin)
 * - Blocs en ~10 minutes
 * - API REST publique via Insight (explorer.firo.org)
 * - Adresses transparentes (préfixe 'a' en mainnet)
 *
 * Stratégie de paiement par UTXO matching :
 * - Chaque paiement a un montant UNIQUE (montant de base + nonce aléatoire en satoshis)
 * - On vérifie les UTXOs de l'adresse pour trouver celui qui correspond exactement
 * - Cela permet de distinguer des paiements simultanés vers la même adresse
 *
 * Note : Seules les transactions transparentes sont vérifiables via l'API.
 * Les transactions Spark (privées) nécessitent un nœud local.
 *
 * Configuration admin requise (settings) :
 * - firo_address : Adresse Firo de réception des paiements (validée : base58check + préfixe réseau)
 * - firo_api_url : URL de l'API Insight AVEC le préfixe /api (défaut: https://explorer.firo.org/insight-api-firo/api)
 * - firo_rate_eur : Taux EUR/FIRO manuel (0 = auto CoinGecko) ; le taux utilisé est FIGÉ à la création de la commande
 * - firo_payment_timeout : Délai expiration paiement en minutes, stocké en base à la création (défaut: 45)
 * - firo_min_confirmations : Confirmations minimales (défaut: 2, ~20 min) ; progression affichée à l'utilisateur (0/2, 1/2, ...)
 */
class FiroService
{
    private const SATOSHI_PER_FIRO = 100000000; // 1 FIRO = 100 000 000 satoshis
    private const COINGECKO_API = 'https://api.coingecko.com/api/v3/simple/price';
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
        $this->apiUrl = rtrim($this->getSetting('firo_api_url', 'https://explorer.firo.org/insight-api-firo/api'), '/');

        // L'API Insight publique exige le préfixe /api : sans lui, l'explorateur
        // renvoie sa page HTML au lieu du JSON (compatibilité avec les anciennes
        // valeurs de réglage). Compatible PHP 7.x : pas de str_contains().
        if ($this->apiUrl !== ''
            && strpos($this->apiUrl, 'explorer.firo.org') !== false
            && substr($this->apiUrl, -4) !== '/api') {
            $this->apiUrl .= '/api';
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    // ========================================
    // PRIX ET CONVERSION
    // ========================================

    /**
     * Récupère le prix FIRO/EUR via CoinGecko avec cache
     */
    public function getFiroPriceEur(): float
    {
        // Taux manuel admin
        $manualRate = $this->getSetting('firo_rate_eur', '0');
        if ((float) $manualRate > 0) {
            return (float) $manualRate;
        }

        // Cache
        $cached = $this->getCache('firo_price_eur');
        if ($cached !== null) {
            return (float) $cached;
        }

        // API CoinGecko
        $price = $this->fetchCoinGeckoPrice();
        if ($price > 0) {
            $this->setCache('firo_price_eur', (string) $price, self::CACHE_TTL);
        } else {
            $stalePrice = $this->getCache('firo_price_eur_stale');
            if ($stalePrice !== null) {
                $this->log('Prix CoinGecko indisponible, utilisation du dernier prix connu');
                return (float) $stalePrice;
            }
            $this->log('Impossible de récupérer le prix FIRO/EUR depuis CoinGecko');
        }

        return $price;
    }

    public function eurToFiro(float $eur): float
    {
        $price = $this->getFiroPriceEur();
        if ($price <= 0) return 0.0;
        return round($eur / $price, 8);
    }

    public function firoToEur(float $firo): float
    {
        $price = $this->getFiroPriceEur();
        return round($firo * $price, 2);
    }

    public function toSatoshi(float $firo): string
    {
        return (string) (int) round($firo * self::SATOSHI_PER_FIRO);
    }

    public function fromSatoshi(string $satoshi): float
    {
        return (int) $satoshi / self::SATOSHI_PER_FIRO;
    }

    // ========================================
    // PAIEMENTS
    // ========================================

    /**
     * Crée un paiement Firo pour un achat VIP
     */
    public function createPayment(int $userId, int $packId): ?array
    {
        // Protection anti-doublon
        $existingPending = $this->db->queryOne(
            "SELECT id, created_at FROM purchases
             WHERE user_id = ? AND payment_method = 'firo' AND status = 'pending'
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
                 WHERE user_id = ? AND payment_method = 'firo' AND status = 'pending'
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

        // Conversion EUR → FIRO : le taux est FIGÉ à la création de la commande.
        // La facture conserve ce taux et ce montant crypto, indépendamment des
        // variations de cours ultérieures.
        $rate = $this->getFiroPriceEur();
        $firoAmount = $rate > 0 ? round($priceEur / $rate, 8) : 0.0;
        if ($firoAmount <= 0) {
            $this->log("Conversion EUR→FIRO échouée pour {$priceEur}€");
            return null;
        }

        // Adresse de réception (validée selon les règles propres à Firo)
        $address = trim($this->getSetting('firo_address', ''));
        if ($address === '' || !$this->validateAddress($address)) {
            $this->log("Adresse Firo de réception absente ou invalide");
            return null;
        }

        // Nonce unique pour UTXO matching
        $nonceSatoshi = random_int(1, self::NONCE_MAX);
        $baseSatoshi = (int) round($firoAmount * self::SATOSHI_PER_FIRO);
        $totalSatoshi = $baseSatoshi + $nonceSatoshi;
        $exactFiro = $totalSatoshi / self::SATOSHI_PER_FIRO;

        // Timeout : stocké en base à la création, jamais recalculé à l'affichage
        $timeoutMinutes = (int) $this->getSetting('firo_payment_timeout', '45');
        $expiresAt = date('Y-m-d H:i:s', time() + ($timeoutMinutes * 60));

        // Créer l'achat
        $purchaseId = $this->db->insert('purchases', [
            'user_id' => $userId,
            'amount_eur' => $priceEur,
            'amount_xelis' => $exactFiro, // Réutilise le champ pour le montant crypto
            'payment_method' => 'firo',
            'points_purchased' => $points,
            'status' => 'pending',
            'payment_address' => $address,
            'user_xelis_address' => '',
            'expires_at' => $expiresAt,
        ]);

        // Taux EUR/FIRO figé au moment de la commande (colonne optionnelle,
        // voir database/migration_purchases_crypto_rate.sql)
        try {
            $this->db->execute(
                "UPDATE purchases SET crypto_rate_eur = ? WHERE id = ?",
                [$rate, $purchaseId]
            );
        } catch (\Throwable $e) {
            // Colonne absente sur les installations non migrées : non bloquant
        }

        $firoUri = $this->buildFiroUri($address, $exactFiro);

        return [
            'purchase_id' => $purchaseId,
            'payment_address' => $address,
            'firo_amount' => $exactFiro,
            'satoshi_amount' => (string) $totalSatoshi,
            'base_firo' => $firoAmount,
            'nonce_satoshi' => $nonceSatoshi,
            'eur_amount' => $priceEur,
            'points' => $points,
            'expires_at' => $expiresAt,
            'timeout_minutes' => $timeoutMinutes,
            'firo_uri' => $firoUri,
        ];
    }

    private function getExistingPaymentData(int $purchaseId): ?array
    {
        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'firo' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase || strtotime($purchase['expires_at']) < time()) {
            return null;
        }

        $firoUri = $this->buildFiroUri(
            $purchase['payment_address'] ?? '',
            (float) $purchase['amount_xelis']
        );

        return [
            'purchase_id' => (int) $purchase['id'],
            'payment_address' => $purchase['payment_address'],
            'firo_amount' => (float) $purchase['amount_xelis'],
            'satoshi_amount' => $this->toSatoshi((float) $purchase['amount_xelis']),
            'base_firo' => (float) $purchase['amount_xelis'],
            'nonce_satoshi' => 0,
            'eur_amount' => (float) $purchase['amount_eur'],
            'points' => (int) $purchase['points_purchased'],
            'expires_at' => $purchase['expires_at'],
            'timeout_minutes' => (int) (($purchase['expires_at'] ? strtotime($purchase['expires_at']) - time() : 0) / 60),
            'firo_uri' => $firoUri,
        ];
    }

    /**
     * Vérifie le statut d'un paiement
     */
    public function getPaymentStatus(int $purchaseId): array
    {
        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'firo'",
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

        // Vérifier via UTXO matching
        $expectedSatoshi = $this->toSatoshi((float) $purchase['amount_xelis']);
        $address = $purchase['payment_address'];
        $verification = $this->verifyPayment($address, $expectedSatoshi);

        if ($verification['found']) {
            // Vérifier les confirmations (progression affichée à l'utilisateur)
            $txId = (string) ($verification['tx_id'] ?? '');
            $minConfirmations = max(1, (int) $this->getSetting('firo_min_confirmations', '2'));
            if ($txId !== '') {
                $txConfirmations = (int) ($verification['confirmations'] ?? 0);
                if ($txConfirmations < $minConfirmations) {
                    return [
                        'status' => 'waiting',
                        'message' => "Paiement détecté — en attente de confirmations ({$txConfirmations}/{$minConfirmations})",
                        'remaining_seconds' => max(0, strtotime($purchase['expires_at']) - time()),
                        'firo_amount' => (float) $purchase['amount_xelis'],
                        'payment_address' => $address,
                        'confirmations' => $txConfirmations,
                        'required_confirmations' => $minConfirmations,
                    ];
                }
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
                $txConfirmations = (int) ($txDetails['confirmations'] ?? 0);
                $minConfirmations = (int) $this->getSetting('firo_min_confirmations', '2');
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
                    'firo_amount' => (float) $purchase['amount_xelis'],
                    'payment_address' => $address,
                    'confirmations' => $txConfirmations,
                    'required_confirmations' => $minConfirmations,
                ];
            }
        }

        $remainingSeconds = max(0, strtotime($purchase['expires_at']) - time());
        return [
            'status' => 'waiting',
            'message' => 'En attente du paiement Firo...',
            'remaining_seconds' => $remainingSeconds,
            'firo_amount' => (float) $purchase['amount_xelis'],
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
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'firo' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase) {
            return ['success' => false, 'message' => 'Achat non trouvé ou déjà traité.'];
        }

        // Vérifier TX non déjà utilisée
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
                $minConfirmations = (int) $this->getSetting('firo_min_confirmations', '2');
                $txConfirmations = (int) ($txDetails['confirmations'] ?? 0);
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
     */
    private function isTransactionValidForPurchase(array $txDetails, array $purchase): bool
    {
        $expectedAddress = $purchase['payment_address'] ?? '';
        $expectedSatoshi = (int) $this->toSatoshi((float) $purchase['amount_xelis']);

        if (empty($expectedAddress)) return false;

        // Vérifier les outputs (vout) de la transaction
        $outputs = $txDetails['vout'] ?? [];
        foreach ($outputs as $output) {
            $outputSatoshi = (int) round(($output['value'] ?? 0) * self::SATOSHI_PER_FIRO);
            $outputAddress = $output['scriptPubKey']['addresses'][0] ?? '';

            if (strtolower($outputAddress) === strtolower($expectedAddress)) {
                // Match exact
                if ($outputSatoshi === $expectedSatoshi) {
                    return true;
                }
                // Tolérance nonce (1-99999 satoshis)
                $diff = abs($outputSatoshi - $expectedSatoshi);
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
            'description' => "Achat VIP Firo confirmé - {$purchase['points_purchased']} points",
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
     * Vérifie un paiement via UTXO matching
     */
    public function verifyPayment(string $address, string $expectedSatoshi): array
    {
        $utxos = $this->getAddressUtxos($address);
        if ($utxos !== null) {
            foreach ($utxos as $utxo) {
                $utxoSatoshi = (string) ($utxo['satoshis'] ?? (int) round(($utxo['amount'] ?? 0) * self::SATOSHI_PER_FIRO));
                if ($utxoSatoshi === $expectedSatoshi) {
                    $txId = $utxo['txid'] ?? '';

                    // Vérifier que ce TX n'est pas déjà utilisé
                    $alreadyUsed = $this->db->queryOne(
                        "SELECT id FROM purchases WHERE transaction_id = ? AND status = 'completed'",
                        [$txId]
                    );
                    if ($alreadyUsed) continue;

                    $sender = $this->findSenderAddress($txId);
                    $confirmations = (int) ($utxo['confirmations'] ?? 0);

                    return [
                        'found' => true,
                        'tx_id' => $txId,
                        'sender' => $sender,
                        'amount_satoshi' => $utxoSatoshi,
                        'confirmations' => $confirmations,
                    ];
                }
            }
            return ['found' => false];
        }

        return ['found' => false];
    }

    /**
     * Récupère les UTXOs d'une adresse via l'API Insight Firo
     */
    public function getAddressUtxos(string $address): ?array
    {
        $result = $this->apiGetWithRetry("/addr/{$address}/utxo");
        if ($result !== null && is_array($result)) {
            return $result;
        }

        $this->log("Échec récupération UTXOs pour {$address}");
        return null;
    }

    /**
     * Récupère le solde d'une adresse (en satoshis)
     */
    public function getAddressBalance(string $address): ?string
    {
        $result = $this->apiGetWithRetry("/addr/{$address}/balance");
        if ($result !== null) {
            // L'API retourne le balance en satoshis (valeur numérique directe)
            if (is_numeric($result)) {
                return (string) $result;
            }
            if (is_array($result) && isset($result['balance'])) {
                return (string) $result['balance'];
            }
        }

        // Fallback: via /addr endpoint
        $addrInfo = $this->apiGetWithRetry("/addr/{$address}");
        if ($addrInfo && isset($addrInfo['balance'])) {
            return (string) $addrInfo['balance'];
        }

        return null;
    }

    /**
     * Récupère les détails d'une transaction
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
     * Récupère les infos d'une adresse
     */
    public function getAddressInfo(string $address): ?array
    {
        return $this->apiGetWithRetry("/addr/{$address}");
    }

    /**
     * Trouve l'adresse de l'expéditeur d'une transaction
     */
    private function findSenderAddress(string $txId): string
    {
        $tx = $this->getTransaction($txId);
        if (!$tx) return '';

        $inputs = $tx['vin'] ?? [];
        foreach ($inputs as $input) {
            $address = $input['addr'] ?? $input['address'] ?? '';
            if (!empty($address)) {
                return $address;
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
             WHERE payment_method = 'firo'
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

            // UTXO matching
            $expectedSatoshi = $this->toSatoshi((float) $purchase['amount_xelis']);
            $address = $purchase['payment_address'];
            $verification = $this->verifyPayment($address, $expectedSatoshi);

            if ($verification['found']) {
                $minConfirmations = (int) $this->getSetting('firo_min_confirmations', '2');
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
                    $minConfirmations = (int) $this->getSetting('firo_min_confirmations', '2');
                    $txConfirmations = (int) ($txDetails['confirmations'] ?? 0);
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
     * Construit un URI Firo pour QR code
     * Format: firo:address?amount=value
     */
    public function buildFiroUri(string $address, float $amount): string
    {
        return "firo:{$address}?amount=" . number_format($amount, 8, '.', '');
    }

    /**
     * Valide une adresse Firo selon les règles propres au réseau :
     * - Alphabet base58 (sans 0, O, I, l)
     * - Préfixe réseau : 'a' (P2PKH mainnet), '4' (P2SH mainnet), 'T' (testnet)
     * - Checksum base58check (double SHA-256) si bcmath est disponible
     */
    public function validateAddress(string $address): bool
    {
        $address = trim($address);

        // Alphabet base58 de Bitcoin (pas de 0, O, I, l)
        if (!preg_match('/^[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{26,36}$/', $address)) {
            return false;
        }

        // Préfixes spécifiques à Firo
        if (!preg_match('/^[a4T]/', $address)) {
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
        $explorerUrl = $this->getSetting('firo_explorer_url', 'https://explorer.firo.org');
        return rtrim($explorerUrl, '/') . '/tx/' . urlencode($txId);
    }

    public function getExplorerAddressUrl(string $address): string
    {
        $explorerUrl = $this->getSetting('firo_explorer_url', 'https://explorer.firo.org');
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
                usleep(300000 * ($i + 1)); // 300ms, 600ms (Firo ~10min/bloc, API plus lente)
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
            CURLOPT_USERAGENT => 'EchangeLien-FiroService/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            if ($httpCode === 429) {
                $this->log("Rate limit API Firo (429) sur GET {$path}");
            } elseif ($httpCode !== 0) {
                $this->log("API GET {$path} → HTTP {$httpCode}");
            }
            return null;
        }

        // Certains endpoints retournent des valeurs brutes (balance en satoshis)
        if (is_numeric(trim($response))) {
            return trim($response);
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
        // Appel groupé partagé entre tous les services (limite de débit CoinGecko).
        // CoinGecko expose Firo sous l'id « firo » ou l'ancien id « zcoin ».
        $price = coingecko_fetch_eur('firo');
        if ($price <= 0) {
            $price = coingecko_fetch_eur('zcoin');
        }

        if ($price > 0) {
            $this->setCache('firo_price_eur_stale', (string) $price, 86400);
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
                'message' => '[FiroService] ' . $message,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {}
    }
}
