<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Service Kaspa - Intégration complète avec la blockchain Kaspa
 *
 * Kaspa est un blockDAG UTXO (comme Bitcoin) avec :
 * - 1 KAS = 100 000 000 SOMPI (toutes les validations se font en entiers SOMPI, jamais en float)
 * - 10 blocs/seconde (confirmations quasi-instantanées)
 * - Frais quasi-nuls
 * - API REST publique (pas besoin de daemon local)
 *
 * Stratégie de paiement par UTXO matching :
 * - Chaque paiement a un montant UNIQUE (montant de base + nonce aléatoire en sompi)
 * - On vérifie les UTXOs de l'adresse pour trouver celui qui correspond exactement
 * - Cela permet de distinguer des paiements simultanés vers la même adresse
 *
 * Vérification BlockDAG (flux de paiement sûr) :
 * 1. TX détectée (UTXO matching ou hash soumis manuellement)
 * 2. Montant (en SOMPI) et adresse de destination vérifiés
 * 3. TX acceptée par la chaîne virtuelle (POST /transactions/acceptance)
 * 4. Seuil de score DAA atteint (virtualDaaScore - DAA score de la TX)
 * 5. Commande marquée PAYÉE
 *
 * Configuration admin requise (settings) :
 * - kaspa_address : Adresse Kaspa de réception des paiements
 * - kaspa_api_url : URL de l'API REST / indexer (défaut: https://api.kaspa.org)
 * - kaspa_rpc_url : URL d'un nœud/indexer privé testé en secours (ex: http://127.0.0.1:16110), vide = désactivé
 * - kaspa_explorer_url : URL de l'explorateur (interface humaine, ex: https://explorer.kaspa.org)
 * - kaspa_rate_eur : Taux EUR/KAS manuel (0 = auto CoinGecko)
 * - kaspa_payment_timeout : Délai expiration paiement en minutes (défaut: 30)
 * - kaspa_min_confirmations : Seuil de score DAA après acceptation (défaut: 1, car ~10 blocs/s)
 */
class KaspaService
{
    private const SOMPI_PER_KAS = 100000000; // 1 KAS = 100 000 000 SOMPI
    private const COINGECKO_API = 'https://api.coingecko.com/api/v3/simple/price';
    private const CACHE_TTL = 300; // 5 minutes cache prix
    private const NONCE_MAX = 99999; // Nonce max pour différencier les paiements (en SOMPI)
    private const MAX_PENDING_PER_USER = 3; // Max paiements en attente par utilisateur
    private const MIN_PENDING_INTERVAL = 30; // Secondes minimum entre 2 créations de paiement
    private const API_TIMEOUT = 15; // Timeout requêtes API (secondes)
    private const API_CONNECT_TIMEOUT = 5; // Timeout connexion (secondes)
    private const MAX_API_RETRIES = 2; // Nombre de tentatives en cas d'échec API

    private Database $db;
    private string $apiUrl;
    private string $rpcUrl;
    /** @var string[] Historique des erreurs pour debug */
    private array $errors = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->apiUrl = rtrim($this->getSetting('kaspa_api_url', 'https://api.kaspa.org'), '/');
        // Mode pro : nœud/indexer privé testé en secours de l'API publique
        $this->rpcUrl = rtrim($this->getSetting('kaspa_rpc_url', ''), '/');
    }

    /**
     * Retourne les dernières erreurs rencontrées (pour debug/admin)
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    // ========================================
    // PRIX ET CONVERSION
    // ========================================

    /**
     * Récupère le prix KAS/EUR via CoinGecko avec cache
     */
    public function getKasPriceEur(): float
    {
        // Vérifier le taux manuel admin
        $manualRate = $this->getSetting('kaspa_rate_eur', '0');
        if ((float) $manualRate > 0) {
            return (float) $manualRate;
        }

        // Cache
        $cached = $this->getCache('kaspa_price_eur');
        if ($cached !== null) {
            return (float) $cached;
        }

        // API CoinGecko
        $price = $this->fetchCoinGeckoPrice();
        if ($price > 0) {
            $this->setCache('kaspa_price_eur', (string) $price, self::CACHE_TTL);
        } else {
            // Fallback : essayer un prix en cache même expiré (mieux que 0)
            $stalePrice = $this->getCache('kaspa_price_eur_stale');
            if ($stalePrice !== null) {
                $this->log('Prix CoinGecko indisponible, utilisation du dernier prix connu');
                return (float) $stalePrice;
            }
            $this->log('Impossible de récupérer le prix KAS/EUR depuis CoinGecko');
        }

        return $price;
    }

    /**
     * Convertit un montant EUR en KAS
     */
    public function eurToKas(float $eur): float
    {
        $price = $this->getKasPriceEur();
        if ($price <= 0) {
            return 0.0;
        }
        return round($eur / $price, 8);
    }

    /**
     * Convertit un montant KAS en EUR
     */
    public function kasToEur(float $kas): float
    {
        $price = $this->getKasPriceEur();
        return round($kas * $price, 2);
    }

    /**
     * Convertit KAS en SOMPI (atomic units)
     */
    public function toSompi(float $kas): string
    {
        return (string) (int) round($kas * self::SOMPI_PER_KAS);
    }

    /**
     * Convertit SOMPI en KAS
     */
    public function fromSompi(string $sompi): float
    {
        return (int) $sompi / self::SOMPI_PER_KAS;
    }

    // ========================================
    // PAIEMENTS
    // ========================================

    /**
     * Crée un paiement Kaspa pour un achat VIP
     *
     * Utilise un nonce aléatoire pour rendre le montant unique
     * et permettre l'UTXO matching (identifier le paiement parmi d'autres).
     *
     * @param int $userId ID utilisateur
     * @param int $packId ID du pack VIP
     * @return array|null Données du paiement ou null si erreur
     */
    public function createPayment(int $userId, int $packId): ?array
    {
        // Protection anti-doublon : vérifier les paiements en attente existants
        $existingPending = $this->db->queryOne(
            "SELECT id, created_at FROM purchases
             WHERE user_id = ? AND payment_method = 'kaspa' AND status = 'pending'
             ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );

        if ($existingPending) {
            // Vérifier l'intervalle minimum entre 2 créations
            $createdAt = strtotime($existingPending['created_at']);
            if ((time() - $createdAt) < self::MIN_PENDING_INTERVAL) {
                $this->log("Rate limit: utilisateur #{$userId} a déjà un paiement en attente récent");
                // Retourner le paiement existant plutôt qu'en créer un nouveau
                return $this->getExistingPaymentData((int) $existingPending['id']);
            }

            // Vérifier le nombre max de paiements en attente
            $pendingCount = $this->db->queryOne(
                "SELECT COUNT(*) as cnt FROM purchases
                 WHERE user_id = ? AND payment_method = 'kaspa' AND status = 'pending'
                 AND expires_at > NOW()",
                [$userId]
            );
            if ((int) ($pendingCount['cnt'] ?? 0) >= self::MAX_PENDING_PER_USER) {
                $this->log("Limite atteinte: utilisateur #{$userId} a {$pendingCount['cnt']} paiements en attente");
                return null;
            }
        }

        // Récupérer les infos du pack
        $points = (int) ($this->getSetting("vip_points_{$packId}", '0'));
        $priceEur = (float) $this->getSetting("vip_price_{$packId}", '0');

        if ($points <= 0 || $priceEur <= 0) {
            $this->log("Pack invalide: points={$points}, prix={$priceEur}");
            return null;
        }

        // Conversion EUR → KAS : le taux est FIGÉ à la création de la commande.
        // La facture conserve ce taux et ce montant crypto, indépendamment des
        // variations de cours ultérieures.
        $rate = $this->getKasPriceEur();
        $kasAmount = $rate > 0 ? round($priceEur / $rate, 8) : 0.0;
        if ($kasAmount <= 0) {
            $this->log("Conversion EUR→KAS échouée pour {$priceEur}€");
            return null;
        }

        // Adresse de réception
        $address = $this->getSetting('kaspa_address', '');
        if (empty($address)) {
            $this->log("Adresse Kaspa non configurée");
            return null;
        }

        // Générer un nonce unique pour ce paiement (1 à NONCE_MAX sompi)
        // Cela rend le montant unique et identifiable via UTXO matching
        $nonceSompi = random_int(1, self::NONCE_MAX);
        $baseSompi = (int) round($kasAmount * self::SOMPI_PER_KAS);
        $totalSompi = $baseSompi + $nonceSompi;
        $exactKas = $totalSompi / self::SOMPI_PER_KAS;

        // Timeout paiement
        $timeoutMinutes = (int) $this->getSetting('kaspa_payment_timeout', '30');
        $expiresAt = date('Y-m-d H:i:s', time() + ($timeoutMinutes * 60));

        // Créer l'achat en base
        $purchaseId = $this->db->insert('purchases', [
            'user_id' => $userId,
            'amount_eur' => $priceEur,
            'amount_xelis' => $exactKas, // Réutilise le champ pour le montant crypto
            'payment_method' => 'kaspa',
            'points_purchased' => $points,
            'status' => 'pending',
            'payment_address' => $address,
            'user_xelis_address' => '', // Réutilise pour l'adresse sender si détectée
            'expires_at' => $expiresAt,
        ]);

        // Taux EUR/KAS figé au moment de la commande (colonne optionnelle,
        // voir database/migration_purchases_crypto_rate.sql)
        try {
            $this->db->execute(
                "UPDATE purchases SET crypto_rate_eur = ? WHERE id = ?",
                [$rate, $purchaseId]
            );
        } catch (\Throwable $e) {
            // Colonne absente sur les installations non migrées : non bloquant
        }

        // Générer l'URI Kaspa pour QR code
        $kaspaUri = $this->buildKaspaUri($address, $exactKas);

        return [
            'purchase_id' => $purchaseId,
            'payment_address' => $address,
            'kas_amount' => $exactKas,
            'sompi_amount' => (string) $totalSompi,
            'base_kas' => $kasAmount,
            'nonce_sompi' => $nonceSompi,
            'eur_amount' => $priceEur,
            'points' => $points,
            'expires_at' => $expiresAt,
            'timeout_minutes' => $timeoutMinutes,
            'kaspa_uri' => $kaspaUri,
        ];
    }

    /**
     * Récupère les données d'un paiement existant pour le réutiliser
     */
    private function getExistingPaymentData(int $purchaseId): ?array
    {
        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'kaspa' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase || strtotime($purchase['expires_at']) < time()) {
            return null;
        }

        $kaspaUri = $this->buildKaspaUri(
            $purchase['payment_address'] ?? '',
            (float) $purchase['amount_xelis']
        );

        return [
            'purchase_id' => (int) $purchase['id'],
            'payment_address' => $purchase['payment_address'],
            'kas_amount' => (float) $purchase['amount_xelis'],
            'sompi_amount' => $this->toSompi((float) $purchase['amount_xelis']),
            'base_kas' => (float) $purchase['amount_xelis'],
            'nonce_sompi' => 0,
            'eur_amount' => (float) $purchase['amount_eur'],
            'points' => (int) $purchase['points_purchased'],
            'expires_at' => $purchase['expires_at'],
            'timeout_minutes' => (int) (($purchase['expires_at'] ? strtotime($purchase['expires_at']) - time() : 0) / 60),
            'kaspa_uri' => $kaspaUri,
        ];
    }

    /**
     * Vérifie le statut d'un paiement
     */
    public function getPaymentStatus(int $purchaseId): array
    {
        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'kaspa'",
            [$purchaseId]
        );

        if (!$purchase) {
            return ['status' => 'not_found', 'message' => 'Paiement non trouvé.'];
        }

        // Déjà confirmé
        if ($purchase['status'] === 'completed') {
            return [
                'status' => 'completed',
                'message' => 'Paiement confirmé ! Vos points ont été crédités.',
                'transaction_hash' => $purchase['transaction_id'],
            ];
        }

        // Annulé
        if ($purchase['status'] === 'cancelled') {
            return ['status' => 'cancelled', 'message' => 'Paiement annulé.'];
        }

        // Expiré
        if (strtotime($purchase['expires_at']) < time()) {
            $this->db->update('purchases', ['status' => 'cancelled'], "id = ? AND status = 'pending'", [$purchaseId]);
            return ['status' => 'expired', 'message' => 'Paiement expiré. Veuillez réessayer.'];
        }

        // Vérifier le paiement sur la blockchain via UTXO matching
        $expectedSompi = $this->toSompi((float) $purchase['amount_xelis']);
        $address = $purchase['payment_address'];
        $verification = $this->verifyPayment($address, $expectedSompi);

        if ($verification['found']) {
            // Vérifier l'acceptation par le BlockDAG + seuil de score DAA
            $txId = (string) ($verification['tx_id'] ?? '');
            if ($txId !== '' && !$this->isTxConfirmedForPurchase($txId)) {
                $txConfirmations = $this->getTransactionConfirmations($txId);
                $minConfirmations = max(1, (int) $this->getSetting('kaspa_min_confirmations', '1'));
                return [
                    'status' => 'waiting',
                    'message' => "Transaction détectée, en attente d'acceptation par le BlockDAG ({$txConfirmations}/{$minConfirmations})",
                    'remaining_seconds' => max(0, strtotime($purchase['expires_at']) - time()),
                    'kas_amount' => (float) $purchase['amount_xelis'],
                    'payment_address' => $address,
                    'confirmations' => $txConfirmations,
                    'required_confirmations' => $minConfirmations,
                ];
            }

            // Paiement accepté et confirmé !
            $this->confirmPurchase($purchaseId, $purchase, $txId);
            return [
                'status' => 'completed',
                'message' => 'Paiement accepté par le BlockDAG ! Points crédités.',
                'transaction_hash' => $txId,
                'sender_address' => $verification['sender'] ?? '',
            ];
        }

        // Vérifier aussi si un hash de TX a été soumis manuellement
        if (!empty($purchase['transaction_id'])) {
            $txDetails = $this->getTransaction($purchase['transaction_id']);
            if ($txDetails && $this->isTransactionValidForPurchase($txDetails, $purchase)) {
                if ($this->isTxConfirmedForPurchase($purchase['transaction_id'])) {
                    $this->confirmPurchase($purchaseId, $purchase, $purchase['transaction_id']);
                    return [
                        'status' => 'completed',
                        'message' => 'Paiement confirmé via soumission TX ! Points crédités.',
                        'transaction_hash' => $purchase['transaction_id'],
                    ];
                }
                $txConfirmations = $this->getTransactionConfirmations($purchase['transaction_id']);
                $minConfirmations = max(1, (int) $this->getSetting('kaspa_min_confirmations', '1'));
                return [
                    'status' => 'waiting',
                    'message' => "TX soumise détectée, en attente d'acceptation par le BlockDAG ({$txConfirmations}/{$minConfirmations})",
                    'remaining_seconds' => max(0, strtotime($purchase['expires_at']) - time()),
                    'kas_amount' => (float) $purchase['amount_xelis'],
                    'payment_address' => $address,
                    'confirmations' => $txConfirmations,
                    'required_confirmations' => $minConfirmations,
                ];
            }
        }

        // En attente de paiement
        $remainingSeconds = max(0, strtotime($purchase['expires_at']) - time());
        return [
            'status' => 'waiting',
            'message' => 'En attente du paiement Kaspa...',
            'remaining_seconds' => $remainingSeconds,
            'kas_amount' => (float) $purchase['amount_xelis'],
            'payment_address' => $address,
        ];
    }

    /**
     * Soumet un hash de transaction pour vérification
     *
     * Vérifie que la TX correspond bien au paiement attendu :
     * - Adresse de destination correcte
     * - Montant correct (avec tolerance pour les frais)
     */
    public function submitTransactionHash(int $purchaseId, string $txHash): array
    {
        // Valider le format du hash (64 caractères hex)
        if (!preg_match('/^[a-f0-9]{64}$/i', $txHash)) {
            return ['success' => false, 'message' => 'Format de hash invalide. 64 caractères hexadécimaux attendus.'];
        }
        $txHash = strtolower($txHash);

        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'kaspa' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase) {
            return ['success' => false, 'message' => 'Achat non trouvé ou déjà traité.'];
        }

        // Vérifier que la TX n'est pas déjà utilisée pour un autre achat
        $existing = $this->db->queryOne(
            "SELECT id, payment_method FROM purchases WHERE transaction_id = ? AND id != ?",
            [$txHash, $purchaseId]
        );
        if ($existing) {
            return ['success' => false, 'message' => 'Cette transaction a déjà été soumise pour un autre achat.'];
        }

        // Vérifier la transaction via l'API Kaspa
        $txDetails = $this->getTransaction($txHash);
        if ($txDetails) {
            // Vérifier que la TX envoie bien à la bonne adresse et pour le bon montant
            if ($this->isTransactionValidForPurchase($txDetails, $purchase)) {
                // Confirmer automatiquement si acceptée par le BlockDAG + seuil DAA atteint
                if ($this->isTxConfirmedForPurchase($txHash)) {
                    $this->confirmPurchase($purchaseId, $purchase, $txHash);
                    return [
                        'success' => true,
                        'message' => 'Transaction vérifiée et confirmée ! Points crédités.',
                        'auto_confirmed' => true,
                    ];
                }

                // TX valide mais pas encore acceptée : on garde le hash pour le polling
                $this->db->update('purchases', [
                    'transaction_id' => $txHash,
                ], 'id = ?', [$purchaseId]);
                return [
                    'success' => true,
                    'message' => 'Transaction valide, en attente d\'acceptation par le BlockDAG. Confirmation automatique sous peu.',
                    'auto_confirmed' => false,
                ];
            }

            // TX trouvée mais ne correspond pas exactement
            $this->log("TX {$txHash} soumise pour achat #{$purchaseId} mais montant/adresse incorrect(e)");
            return [
                'success' => false,
                'message' => 'La transaction ne correspond pas au paiement attendu. Vérifiez l\'adresse et le montant.',
            ];
        }

        // TX pas encore trouvée dans l'API (peut prendre quelques secondes)
        // Sauvegarder le hash pour vérification ultérieure par le polling
        $this->db->update('purchases', [
            'transaction_id' => $txHash,
        ], 'id = ?', [$purchaseId]);

        return [
            'success' => true,
            'message' => 'Hash enregistré. La vérification se fera automatiquement dans quelques instants.',
            'auto_confirmed' => false,
        ];
    }

    /**
     * Vérifie qu'une transaction correspond à un achat
     * (bonne adresse de destination + bon montant)
     */
    private function isTransactionValidForPurchase(array $txDetails, array $purchase): bool
    {
        $expectedAddress = $purchase['payment_address'] ?? '';
        $expectedSompi = $this->toSompi((float) $purchase['amount_xelis']);

        if (empty($expectedAddress)) {
            return false;
        }

        // Vérifier les outputs de la transaction
        $outputs = $txDetails['outputs'] ?? [];
        foreach ($outputs as $output) {
            $outputAddress = $output['script_public_key_address']
                ?? $output['address']
                ?? '';

            // Normaliser les adresses pour comparaison
            if (strtolower($outputAddress) === strtolower($expectedAddress)) {
                $outputAmount = (string) ($output['amount'] ?? '0');
                if ($outputAmount === $expectedSompi) {
                    return true;
                }
                // Tolérance : le montant peut différer du nonce (1-99999 sompi)
                // Si l'adresse correspond et le montant est >= au montant attendu, c'est OK
                $diff = abs((int) $outputAmount - (int) $expectedSompi);
                if ($diff <= self::NONCE_MAX && $diff >= 0) {
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

        // Créditer les points
        $this->db->execute(
            "UPDATE users SET points = points + ? WHERE id = ?",
            [(int) $purchase['points_purchased'], (int) $purchase['user_id']]
        );

        // Historique
        $this->db->insert('points_history', [
            'user_id' => (int) $purchase['user_id'],
            'amount' => (int) $purchase['points_purchased'],
            'type' => 'purchase',
            'description' => "Achat VIP Kaspa confirmé - {$purchase['points_purchased']} points",
            'reference_id' => $purchaseId,
            'reference_type' => 'purchase',
        ]);

        // Log
        $this->log("Achat #{$purchaseId} confirmé - TX: {$txHash}");

        return true;
    }

    // ========================================
    // VÉRIFICATION BLOCKDAG (ACCEPTANCE + SCORE DAA)
    // ========================================

    /**
     * Vérifie qu'une transaction peut déclencher le paiement :
     * 1. Acceptée par la chaîne virtuelle (POST /transactions/acceptance)
     * 2. Seuil de score DAA atteint (virtualDaaScore - DAA score de la TX)
     *
     * L'endpoint d'acceptance étant récent, tout échec de détection
     * (endpoint absent, réponse inattendue) est traité comme neutre :
     * on retombe alors sur le seuil DAA classique, jamais de blocage.
     */
    public function isTxConfirmedForPurchase(string $txId): bool
    {
        if ($txId === '') {
            return false;
        }

        // 1) Acceptance par le BlockDAG (si l'endpoint répond)
        if ($this->isTransactionAccepted($txId) === false) {
            $this->log("TX {$txId} non encore acceptée par le BlockDAG");
            return false;
        }

        // 2) Seuil de score DAA
        $minConfirmations = max(1, (int) $this->getSetting('kaspa_min_confirmations', '1'));
        return $this->getTransactionConfirmations($txId) >= $minConfirmations;
    }

    /**
     * Vérifie l'acceptation d'une transaction par la chaîne virtuelle
     * via l'endpoint d'acceptance de l'API REST Kaspa.
     *
     * @return bool|null true = acceptée, false = pas encore, null = indéterminé
     */
    public function isTransactionAccepted(string $txId): ?bool
    {
        // POST /transactions/acceptance : forme bulk de l'API officielle
        $result = $this->apiPostWithRetry('/transactions/acceptance', [
            'transactionIds' => [$txId],
        ]);

        // Variante GET par transaction si le bulk ne répond pas
        if ($result === null) {
            $result = $this->apiGetWithRetry("/transactions/{$txId}/acceptance");
        }

        if ($result === null) {
            return null; // Endpoint indisponible → le seuil DAA décidera
        }

        return $this->parseAcceptance($result, $txId);
    }

    /**
     * Interprète défensivement une réponse d'acceptance
     * (plusieurs formats possibles selon la version de l'API).
     */
    private function parseAcceptance($result, string $txId): ?bool
    {
        if (!is_array($result)) {
            return null;
        }

        // Drapeau booléen direct : {"accepted": true} / {"is_accepted": true}
        foreach (['accepted', 'is_accepted', 'isAccepted'] as $flag) {
            if (isset($result[$flag]) && is_bool($result[$flag])) {
                return $result[$flag];
            }
        }

        // Liste d'IDs acceptés : {"accepted": ["txid", ...]}
        if (isset($result['accepted']) && is_array($result['accepted'])) {
            foreach ($result['accepted'] as $id) {
                if (is_string($id) && strcasecmp($id, $txId) === 0) {
                    return true;
                }
            }
            return false;
        }

        // Liste d'entrées : [{"transactionId": "...", "accepted": true}, ...]
        $entries = $result['entries'] ?? $result['data'] ?? (isset($result[0]) ? $result : null);
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $entryId = (string) ($entry['transactionId'] ?? $entry['transaction_id'] ?? $entry['id'] ?? '');
                if ($entryId !== '' && strcasecmp($entryId, $txId) !== 0) {
                    continue;
                }
                foreach (['accepted', 'is_accepted', 'isAccepted'] as $flag) {
                    if (isset($entry[$flag])) {
                        return filter_var($entry[$flag], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                    }
                }
            }
        }

        return null;
    }

    // ========================================
    // VÉRIFICATION BLOCKCHAIN (UTXO MATCHING)
    // ========================================

    /**
     * Vérifie un paiement via UTXO matching
     *
     * Scanne les UTXOs de l'adresse pour trouver celui qui correspond
     * exactement au montant attendu (base + nonce).
     *
     * @param string $address Adresse Kaspa de réception
     * @param string $expectedSompi Montant attendu en SOMPI
     * @return array ['found' => bool, 'tx_id' => string, 'sender' => string]
     */
    public function verifyPayment(string $address, string $expectedSompi): array
    {
        // Méthode 1 : UTXO matching (le plus fiable)
        $utxos = $this->getAddressUtxos($address);
        if ($utxos !== null) {
            foreach ($utxos as $utxo) {
                $utxoAmount = (string) ($utxo['amount'] ?? $utxo['utxo']['amount'] ?? '0');
                if ($utxoAmount === $expectedSompi) {
                    // Match exact ! Récupérer les infos
                    $txId = $utxo['outpoint']['transactionId']
                        ?? $utxo['transaction_id']
                        ?? '';

                    // Vérifier que ce TX n'a pas déjà été utilisé pour un autre paiement
                    $alreadyUsed = $this->db->queryOne(
                        "SELECT id FROM purchases WHERE transaction_id = ? AND status = 'completed'",
                        [$txId]
                    );
                    if ($alreadyUsed) {
                        continue; // UTXO déjà utilisé pour un autre paiement, skip
                    }

                    $sender = $this->findSenderAddress($txId);
                    return [
                        'found' => true,
                        'tx_id' => $txId,
                        'sender' => $sender,
                        'amount_sompi' => $utxoAmount,
                    ];
                }
            }
            return ['found' => false];
        }

        // Méthode 2 : Vérification par balance (fallback)
        // Moins fiable car ne permet pas de distinguer les paiements
        return ['found' => false];
    }

    /**
     * Récupère les UTXOs d'une adresse via l'API REST Kaspa
     * Avec retry automatique en cas d'échec
     */
    public function getAddressUtxos(string $address): ?array
    {
        // Essayer l'endpoint principal
        $result = $this->apiGetWithRetry("/addresses/{$address}/utxos");
        if ($result !== null && is_array($result)) {
            return $result;
        }

        // Fallback: endpoint RPC
        $result = $this->apiPostWithRetry('/v1/rpc/getUtxosByAddresses', [
            'addresses' => [$address],
        ]);

        if ($result !== null && isset($result['entries'])) {
            return $result['entries'];
        }

        $this->log("Échec récupération UTXOs pour {$address}");
        return null;
    }

    /**
     * Récupère le solde d'une adresse
     */
    public function getAddressBalance(string $address): ?string
    {
        $result = $this->apiGetWithRetry("/v1/rpc/getBalanceByAddress/{$address}");
        if ($result && isset($result['balance'])) {
            return $result['balance'];
        }

        // Fallback
        $result = $this->apiGetWithRetry("/addresses/{$address}/balance");
        if ($result && isset($result['balance'])) {
            return (string) $result['balance'];
        }

        return null;
    }

    /**
     * Récupère les détails d'une transaction
     */
    public function getTransaction(string $txId): ?array
    {
        // Endpoint principal
        $result = $this->apiGetWithRetry("/transactions/{$txId}");
        if ($result && !empty($result['transaction_id'])) {
            return $result;
        }

        // Fallback: endpoint multi-transactions
        $result = $this->apiPostWithRetry('/transactions', [
            'transactionIds' => [$txId],
        ]);
        if ($result && !empty($result[0]['transaction_id'])) {
            return $result[0];
        }

        return null;
    }

    /**
     * Récupère le nombre de confirmations d'une transaction
     */
    public function getTransactionConfirmations(string $txId): int
    {
        $tx = $this->getTransaction($txId);
        if (!$tx) {
            return 0;
        }

        // Si la TX a un block_hash, elle est confirmée
        if (!empty($tx['block_hash'])) {
            // Kaspa ~1 bloc/seconde, donc le nombre de blocs depuis = confirmations
            $currentDaaScore = $this->getCurrentDaaScore();
            $txDaaScore = (int) ($tx['accepting_block_hash_daa_score'] ?? $tx['block_daa_score'] ?? 0);

            if ($currentDaaScore > 0 && $txDaaScore > 0) {
                return max(1, $currentDaaScore - $txDaaScore + 1);
            }
            return 1; // Au moins 1 confirmation si elle est dans un bloc
        }

        return 0; // Pas encore dans un bloc
    }

    /**
     * Récupère le DAA score actuel (numéro de bloc effectif)
     */
    private function getCurrentDaaScore(): int
    {
        $cached = $this->getCache('kaspa_current_daa_score');
        if ($cached !== null) {
            return (int) $cached;
        }

        $result = $this->apiGet('/info');
        if ($result && isset($result['virtualDaaScore'])) {
            $daaScore = (int) $result['virtualDaaScore'];
            // Cache 10 secondes (les blocs sont ~1s)
            $this->setCache('kaspa_current_daa_score', (string) $daaScore, 10);
            return $daaScore;
        }

        return 0;
    }

    /**
     * Trouve l'adresse de l'expéditeur d'une transaction
     */
    private function findSenderAddress(string $txId): string
    {
        $tx = $this->getTransaction($txId);
        if (!$tx) {
            return '';
        }

        // Les inputs contiennent les adresses des expéditeurs
        $inputs = $tx['inputs'] ?? [];
        $addresses = [];
        foreach ($inputs as $input) {
            $address = $input['previousOutpointAddress']
                ?? $input['address']
                ?? '';
            if (!empty($address) && !in_array($address, $addresses)) {
                $addresses[] = $address;
            }
        }

        // Retourner la première adresse (l'expéditeur principal)
        return $addresses[0] ?? '';
    }

    // ========================================
    // VÉRIFICATION AUTOMATIQUE (CRON)
    // ========================================

    /**
     * Vérifie tous les paiements Kaspa en attente
     */
    public function checkAllPendingPayments(): array
    {
        $pending = $this->db->query(
            "SELECT * FROM purchases
             WHERE payment_method = 'kaspa'
             AND status = 'pending'"
        );

        $results = ['checked' => 0, 'confirmed' => 0, 'expired' => 0];

        foreach ($pending as $purchase) {
            // Vérifier expiration
            if (strtotime($purchase['expires_at']) < time()) {
                $this->db->update('purchases', ['status' => 'cancelled'], "id = ? AND status = 'pending'", [(int) $purchase['id']]);
                $results['expired']++;
                continue;
            }

            $results['checked']++;

            // Méthode 1 : UTXO matching
            $expectedSompi = $this->toSompi((float) $purchase['amount_xelis']);
            $address = $purchase['payment_address'];
            $verification = $this->verifyPayment($address, $expectedSompi);

            if ($verification['found']) {
                // Vérifier l'acceptation par le BlockDAG + seuil de score DAA
                if (!empty($verification['tx_id']) && !$this->isTxConfirmedForPurchase($verification['tx_id'])) {
                    continue; // Pas encore acceptée / seuil DAA non atteint
                }

                $this->confirmPurchase(
                    (int) $purchase['id'],
                    $purchase,
                    $verification['tx_id'] ?? ''
                );
                $results['confirmed']++;
                continue;
            }

            // Méthode 2 : Vérifier par hash de TX soumis
            if (!empty($purchase['transaction_id'])) {
                $txDetails = $this->getTransaction($purchase['transaction_id']);
                if ($txDetails && $this->isTransactionValidForPurchase($txDetails, $purchase)) {
                    if ($this->isTxConfirmedForPurchase($purchase['transaction_id'])) {
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
     * Construit un URI Kaspa pour QR code
     * Format: kaspa:address?amount=value
     */
    public function buildKaspaUri(string $address, float $amount): string
    {
        return "kaspa:{$address}?amount=" . number_format($amount, 8, '.', '');
    }

    /**
     * Valide une adresse Kaspa (format Bech32)
     */
    public function validateAddress(string $address): bool
    {
        // Adresses mainnet: kaspa:...
        // Adresses testnet: kaspatest:...
        // Format: prefix:60+ caractères alphanumériques
        return (bool) preg_match('/^(kaspa|kaspatest):[qpzry9x8gf2tvdw0s3jn54khce6mua7l]{60,}$/i', $address);
    }

    /**
     * Récupère l'URL de l'explorateur pour une transaction
     */
    public function getExplorerTxUrl(string $txId): string
    {
        $explorerUrl = $this->getSetting('kaspa_explorer_url', 'https://explorer.kaspa.org');
        return rtrim($explorerUrl, '/') . '/txs/' . urlencode($txId);
    }

    /**
     * Récupère l'URL de l'explorateur pour une adresse
     */
    public function getExplorerAddressUrl(string $address): string
    {
        $explorerUrl = $this->getSetting('kaspa_explorer_url', 'https://explorer.kaspa.org');
        return rtrim($explorerUrl, '/') . '/addresses/' . urlencode($address);
    }

    // ========================================
    // API REST AVEC RETRY
    // ========================================

    /**
     * Bases API essayées dans l'ordre : API REST principale,
     * puis nœud/indexer privé (kaspa_rpc_url, mode pro) s'il est renseigné.
     */
    private function getApiBases(): array
    {
        return array_values(array_filter([$this->apiUrl, $this->rpcUrl], fn($u) => $u !== ''));
    }

    /**
     * Appel API GET avec retry automatique (sur chaque base disponible)
     */
    private function apiGetWithRetry(string $path): ?array
    {
        foreach ($this->getApiBases() as $base) {
            for ($i = 0; $i <= self::MAX_API_RETRIES; $i++) {
                $result = $this->apiGet($path, $base);
                if ($result !== null) {
                    return $result;
                }
                if ($i < self::MAX_API_RETRIES) {
                    usleep(200000 * ($i + 1)); // 200ms, 400ms backoff
                }
            }
        }
        return null;
    }

    /**
     * Appel API POST avec retry automatique (sur chaque base disponible)
     */
    private function apiPostWithRetry(string $path, array $data): ?array
    {
        foreach ($this->getApiBases() as $base) {
            for ($i = 0; $i <= self::MAX_API_RETRIES; $i++) {
                $result = $this->apiPost($path, $data, $base);
                if ($result !== null) {
                    return $result;
                }
                if ($i < self::MAX_API_RETRIES) {
                    usleep(200000 * ($i + 1)); // 200ms, 400ms backoff
                }
            }
        }
        return null;
    }

    /**
     * Appel API GET
     */
    private function apiGet(string $path, string $base = ''): ?array
    {
        if (!function_exists('curl_init')) {
            $this->log('cURL non disponible');
            return null;
        }

        $url = ($base !== '' ? $base : $this->apiUrl) . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::API_CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'EchangeLien-KaspaService/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            if ($httpCode === 429) {
                $this->log("Rate limit API Kaspa (429) sur GET {$path}");
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

    /**
     * Appel API POST
     */
    private function apiPost(string $path, array $data, string $base = ''): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $url = ($base !== '' ? $base : $this->apiUrl) . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::API_CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'EchangeLien-KaspaService/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            if ($httpCode === 429) {
                $this->log("Rate limit API Kaspa (429) sur POST {$path}");
            }
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    /**
     * Récupère le prix KAS/EUR depuis CoinGecko
     */
    private function fetchCoinGeckoPrice(): float
    {
        // Appel groupé partagé entre tous les services (limite de débit CoinGecko)
        $price = coingecko_fetch_eur('kaspa');

        // Sauvegarder un prix "stale" comme dernier recours
        if ($price > 0) {
            $this->setCache('kaspa_price_eur_stale', (string) $price, 86400); // 24h
        }

        return $price;
    }

    /**
     * Récupère un setting
     */
    private function getSetting(string $key, string $default = ''): string
    {
        $result = $this->db->queryOne(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            [$key]
        );
        return $result['setting_value'] ?? $default;
    }

    /**
     * Cache simple en base de données
     */
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
        } catch (\Throwable $e) {
            // Table cache peut ne pas exister
        }
        return null;
    }

    /**
     * Stocke en cache
     */
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
        } catch (\Throwable $e) {
            // Silencieux
        }
    }

    /**
     * Log une erreur/message pour debug admin
     */
    private function log(string $message): void
    {
        $this->errors[] = $message;

        // Logger aussi en base si possible
        try {
            $this->db->insert('logs', [
                'level' => 'warning',
                'message' => '[KaspaService] ' . $message,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Silencieux
        }
    }
}
