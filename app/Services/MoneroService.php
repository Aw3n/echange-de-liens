<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Service Monero (XMR) - Intégration avec la blockchain Monero
 *
 * Monero est une cryptomonnaie axée sur la vie privée (CryptoNote) :
 * - 1 XMR = 1 000 000 000 000 (10^12) atomic units (picos)
 * - Blocs en ~2 minutes
 * - Adresses mainnet : commencent par '4', 95 caractères
 * - Transactions furtives : montants/adresses chiffrés sur la blockchain
 *
 * Stratégie de paiement :
 * - Mode MANUEL (défaut) : L'utilisateur soumet le TXID, l'admin confirme manuellement
 *   (Impossible de vérifier le montant/destinataire sans clé de vue)
 * - Mode AUTO (wallet RPC) : Si l'admin configure monero_wallet_rpc_url,
 *   vérification automatique via payment_id et l'API monero-wallet-rpc
 *
 * Vérification via explorateur xmrchain.net :
 * - Vérifie l'existence de la TX
 * - Vérifie les confirmations
 * - Vérifie que la TX n'est pas coinbase
 *
 * Configuration admin requise (settings) :
 * - monero_address : Adresse Monero de réception
 * - monero_explorer_url : URL explorateur (défaut: https://xmrchain.net)
 * - monero_rate_eur : Taux EUR/XMR manuel (0 = auto CoinGecko)
 * - monero_payment_timeout : Délai expiration en minutes (défaut: 60)
 * - monero_min_confirmations : Confirmations minimales (défaut: 10, ~20 min)
 * - monero_wallet_rpc_url : URL du wallet RPC (optionnel, ex: http://127.0.0.1:28088/json_rpc)
 * - monero_wallet_rpc_user : Utilisateur RPC (optionnel)
 * - monero_wallet_rpc_password : Mot de passe RPC (optionnel)
 */
class MoneroService
{
    private const ATOMIC_PER_XMR = '1000000000000'; // 10^12 atomic units (picos)
    private const COINGECKO_API = 'https://api.coingecko.com/api/v3/simple/price';
    private const CACHE_TTL = 300; // 5 minutes cache prix
    private const MAX_PENDING_PER_USER = 3;
    private const MIN_PENDING_INTERVAL = 30;
    private const API_TIMEOUT = 15;
    private const API_CONNECT_TIMEOUT = 5;
    private const MAX_API_RETRIES = 2;
    private const PAYMENT_ID_LENGTH = 16; // Short payment ID (hex)

    private Database $db;
    private string $explorerUrl;
    /** @var string[] */
    private array $errors = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->explorerUrl = rtrim($this->getSetting('monero_explorer_url', 'https://xmrchain.net'), '/');
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getExplorerUrl(): string
    {
        return $this->explorerUrl;
    }

    // ========================================
    // PRIX ET CONVERSION
    // ========================================

    /**
     * Récupère le prix XMR/EUR via CoinGecko avec cache
     */
    public function getXmrPriceEur(): float
    {
        // Taux manuel admin
        $manualRate = $this->getSetting('monero_rate_eur', '0');
        if ((float) $manualRate > 0) {
            return (float) $manualRate;
        }

        // Cache
        $cached = $this->getCache('monero_price_eur');
        if ($cached !== null) {
            return (float) $cached;
        }

        // API CoinGecko
        $price = $this->fetchCoinGeckoPrice();
        if ($price > 0) {
            $this->setCache('monero_price_eur', (string) $price, self::CACHE_TTL);
        } else {
            $stalePrice = $this->getCache('monero_price_eur_stale');
            if ($stalePrice !== null) {
                $this->log('Prix CoinGecko indisponible, utilisation du dernier prix connu');
                return (float) $stalePrice;
            }
            $this->log('Impossible de récupérer le prix XMR/EUR depuis CoinGecko');
        }

        return $price;
    }

    public function eurToXmr(float $eur): float
    {
        $price = $this->getXmrPriceEur();
        if ($price <= 0) return 0.0;
        return round($eur / $price, 12);
    }

    public function xmrToEur(float $xmr): float
    {
        $price = $this->getXmrPriceEur();
        return round($xmr * $price, 2);
    }

    /**
     * Convertit XMR en atomic units (string car très grand nombre)
     */
    public function toAtomic(float $xmr): string
    {
        return (string) (int) round($xmr * (float) self::ATOMIC_PER_XMR);
    }

    /**
     * Convertit atomic units en XMR
     */
    public function fromAtomic(string $atomic): float
    {
        return (float) $atomic / (float) self::ATOMIC_PER_XMR;
    }

    // ========================================
    // PAIEMENTS
    // ========================================

    /**
     * Crée un paiement Monero pour un achat VIP
     */
    public function createPayment(int $userId, int $packId): ?array
    {
        // Protection anti-doublon
        $existingPending = $this->db->queryOne(
            "SELECT id, created_at FROM purchases
             WHERE user_id = ? AND payment_method = 'monero' AND status = 'pending'
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
                 WHERE user_id = ? AND payment_method = 'monero' AND status = 'pending'
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

        // Conversion EUR → XMR : le taux est FIGÉ à la création de la
        // commande et mémorisé dans purchases.crypto_rate_eur ; les
        // fluctuations ultérieures du marché n'impactent jamais la commande.
        $rate = $this->getXmrPriceEur();
        if ($rate <= 0) {
            $this->log("Taux EUR/XMR indisponible pour {$priceEur}€");
            return null;
        }
        $xmrAmount = round($priceEur / $rate, 8);
        if ($xmrAmount <= 0) {
            $this->log("Conversion EUR→XMR échouée pour {$priceEur}€");
            return null;
        }

        // Adresse de réception
        $address = $this->getSetting('monero_address', '');
        if (empty($address)) {
            $this->log("Adresse Monero non configurée");
            return null;
        }

        // Valider le format de l'adresse Monero
        if (!$this->validateAddress($address)) {
            $this->log("Adresse Monero invalide: {$address}");
            return null;
        }

        // Générer un payment_id unique pour identifier ce paiement
        $paymentId = $this->generatePaymentId($userId, $packId);

        // Timeout (plus long car Monero ~2min/bloc)
        $timeoutMinutes = (int) $this->getSetting('monero_payment_timeout', '60');
        $expiresAt = date('Y-m-d H:i:s', time() + ($timeoutMinutes * 60));

        // Créer l'achat
        $purchaseId = $this->db->insert('purchases', [
            'user_id' => $userId,
            'amount_eur' => $priceEur,
            'amount_xelis' => $xmrAmount,
            'payment_method' => 'monero',
            'points_purchased' => $points,
            'status' => 'pending',
            'payment_address' => $address,
            'user_xelis_address' => '',
            'expires_at' => $expiresAt,
        ]);

        // Mémorise le taux EUR/XMR appliqué à la commande (colonne optionnelle
        // sur les installations antérieures à la migration crypto_rate_eur)
        try {
            $this->db->execute(
                "UPDATE purchases SET crypto_rate_eur = ? WHERE id = ?",
                [number_format($rate, 8, '.', ''), $purchaseId]
            );
        } catch (\Throwable $e) {}

        // Stocker le payment_id dans admin_notes (temporaire, pour référence)
        $this->db->update('purchases', ['admin_notes' => "payment_id:{$paymentId}"], 'id = ?', [$purchaseId]);

        $moneroUri = $this->buildMoneroUri($address, $xmrAmount, $paymentId);

        return [
            'purchase_id' => $purchaseId,
            'payment_address' => $address,
            'xmr_amount' => $xmrAmount,
            'atomic_amount' => $this->toAtomic($xmrAmount),
            'payment_id' => $paymentId,
            'eur_amount' => $priceEur,
            'points' => $points,
            'expires_at' => $expiresAt,
            'timeout_minutes' => $timeoutMinutes,
            'monero_uri' => $moneroUri,
        ];
    }

    private function getExistingPaymentData(int $purchaseId): ?array
    {
        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'monero' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase || strtotime($purchase['expires_at']) < time()) {
            return null;
        }

        $paymentId = $this->extractPaymentId($purchase);
        $moneroUri = $this->buildMoneroUri(
            $purchase['payment_address'] ?? '',
            (float) $purchase['amount_xelis'],
            $paymentId
        );

        return [
            'purchase_id' => (int) $purchase['id'],
            'payment_address' => $purchase['payment_address'],
            'xmr_amount' => (float) $purchase['amount_xelis'],
            'atomic_amount' => $this->toAtomic((float) $purchase['amount_xelis']),
            'payment_id' => $paymentId,
            'eur_amount' => (float) $purchase['amount_eur'],
            'points' => (int) $purchase['points_purchased'],
            'expires_at' => $purchase['expires_at'],
            'timeout_minutes' => (int) (($purchase['expires_at'] ? strtotime($purchase['expires_at']) - time() : 0) / 60),
            'monero_uri' => $moneroUri,
        ];
    }

    /**
     * Vérifie le statut d'un paiement
     */
    public function getPaymentStatus(int $purchaseId): array
    {
        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'monero'",
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

        // Mode wallet RPC : vérification automatique CÔTÉ SERVEUR.
        // Le payment_id est généré par le CMS et le montant réellement reçu
        // est constaté par le wallet : aucune donnée du navigateur n'est
        // utilisée pour valider la commande.
        $walletRpcUrl = $this->getSetting('monero_wallet_rpc_url', '');
        if (!empty($walletRpcUrl)) {
            $paymentId = $this->extractPaymentId($purchase);
            $rpcCheck = $this->checkPaymentViaWalletRpc($paymentId, (float) $purchase['amount_xelis']);
            if ($rpcCheck['found']) {
                $minConfirmations = max(1, (int) $this->getSetting('monero_min_confirmations', '10'));

                // Paiement partiel : jamais validé automatiquement
                if (!empty($rpcCheck['partial'])) {
                    $receivedXmr = $this->fromAtomic((string) ($rpcCheck['amount_atomic'] ?? '0'));
                    return [
                        'status' => 'partial',
                        'message' => sprintf(
                            'Paiement partiel détecté (%.8f XMR reçus sur %.8f attendus). La commande ne sera pas validée en l\'état.',
                            $receivedXmr,
                            (float) $purchase['amount_xelis']
                        ),
                        'remaining_seconds' => max(0, strtotime($purchase['expires_at']) - time()),
                        'xmr_amount' => (float) $purchase['amount_xelis'],
                        'payment_address' => $purchase['payment_address'],
                    ];
                }

                // Détecté ≠ confirmé : hash exploitable ET seuil de
                // confirmations exigés avant toute validation
                $txId = (string) ($rpcCheck['tx_id'] ?? '');
                $txConfirmations = (int) ($rpcCheck['confirmations'] ?? 0);
                if ($txId === '' || $txConfirmations < $minConfirmations) {
                    return [
                        'status' => 'waiting',
                        'message' => "Paiement détecté — en attente de confirmations ({$txConfirmations}/{$minConfirmations})",
                        'remaining_seconds' => max(0, strtotime($purchase['expires_at']) - time()),
                        'xmr_amount' => (float) $purchase['amount_xelis'],
                        'payment_address' => $purchase['payment_address'],
                        'confirmations' => $txConfirmations,
                        'required_confirmations' => $minConfirmations,
                    ];
                }

                $this->confirmPurchase($purchaseId, $purchase, $txId);
                return [
                    'status' => 'completed',
                    'message' => 'Paiement confirmé via wallet RPC ! Points crédités.',
                    'transaction_hash' => $txId,
                ];
            }
        }

        // Vérifier si un hash de TX a été soumis
        if (!empty($purchase['transaction_id'])) {
            $txDetails = $this->getTransactionFromExplorer($purchase['transaction_id']);
            if ($txDetails) {
                $minConfirmations = (int) $this->getSetting('monero_min_confirmations', '10');
                $txConfirmations = (int) ($txDetails['confirmations'] ?? 0);
                if ($txConfirmations >= $minConfirmations) {
                    // TX existe avec assez de confirmations → en attente confirmation admin
                    return [
                        'status' => 'waiting_admin',
                        'message' => "TX confirmée sur la blockchain ({$txConfirmations} conf). En attente de validation par l'administrateur.",
                        'remaining_seconds' => max(0, strtotime($purchase['expires_at']) - time()),
                        'xmr_amount' => (float) $purchase['amount_xelis'],
                        'payment_address' => $purchase['payment_address'],
                        'confirmations' => $txConfirmations,
                        'required_confirmations' => $minConfirmations,
                    ];
                }
                return [
                    'status' => 'waiting',
                    'message' => "Paiement détecté — en attente de confirmations ({$txConfirmations}/{$minConfirmations})",
                    'remaining_seconds' => max(0, strtotime($purchase['expires_at']) - time()),
                    'xmr_amount' => (float) $purchase['amount_xelis'],
                    'payment_address' => $purchase['payment_address'],
                    'confirmations' => $txConfirmations,
                    'required_confirmations' => $minConfirmations,
                ];
            }
        }

        $remainingSeconds = max(0, strtotime($purchase['expires_at']) - time());
        return [
            'status' => 'waiting',
            'message' => 'En attente du paiement Monero...',
            'remaining_seconds' => $remainingSeconds,
            'xmr_amount' => (float) $purchase['amount_xelis'],
            'payment_address' => $purchase['payment_address'],
        ];
    }

    /**
     * Soumet un hash de transaction pour vérification
     * Vérifie via l'explorateur que la TX existe et a des confirmations
     */
    public function submitTransactionHash(int $purchaseId, string $txHash): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $txHash)) {
            return ['success' => false, 'message' => 'Format de hash invalide. 64 caractères hexadécimaux attendus.'];
        }
        $txHash = strtolower($txHash);

        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'monero' AND status = 'pending'",
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

        // Vérifier via l'explorateur
        $txDetails = $this->getTransactionFromExplorer($txHash);
        if ($txDetails) {
            $minConfirmations = (int) $this->getSetting('monero_min_confirmations', '10');
            $txConfirmations = (int) ($txDetails['confirmations'] ?? 0);

            $this->db->update('purchases', ['transaction_id' => $txHash], 'id = ?', [$purchaseId]);

            if ($txConfirmations >= $minConfirmations) {
                return [
                    'success' => true,
                    'message' => "TX vérifiée sur la blockchain ({$txConfirmations} confirmations). En attente de validation par l'administrateur.",
                    'auto_confirmed' => false,
                    'needs_admin_confirmation' => true,
                    'confirmations' => $txConfirmations,
                ];
            }

            return [
                'success' => true,
                'message' => "Paiement détecté — en attente de confirmations ({$txConfirmations}/{$minConfirmations})",
                'auto_confirmed' => false,
                'needs_admin_confirmation' => true,
                'confirmations' => $txConfirmations,
            ];
        }

        // TX pas encore dans l'explorateur
        $this->db->update('purchases', ['transaction_id' => $txHash], 'id = ?', [$purchaseId]);

        return [
            'success' => true,
            'message' => 'Hash enregistré. La TX n\'est pas encore visible sur l\'explorateur. Réessayez dans quelques instants.',
            'auto_confirmed' => false,
        ];
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
            'description' => "Achat VIP Monero confirmé - {$purchase['points_purchased']} points",
            'reference_id' => $purchaseId,
            'reference_type' => 'purchase',
        ]);

        $this->log("Achat #{$purchaseId} confirmé - TX: {$txHash}");
        return true;
    }

    // ========================================
    // VÉRIFICATION BLOCKCHAIN
    // ========================================

    /**
     * Vérifie une transaction via l'explorateur xmrchain.net
     * Retourne les détails si trouvée, null sinon
     */
    public function getTransactionFromExplorer(string $txId): ?array
    {
        $result = $this->apiGetWithRetry("/api/transaction/{$txId}");
        if ($result && ($result['status'] ?? '') === 'success' && !empty($result['data'])) {
            return $result['data'];
        }
        return null;
    }

    /**
     * Récupère la hauteur actuelle de la blockchain
     */
    public function getCurrentHeight(): ?int
    {
        $result = $this->apiGetWithRetry('/api/networkinfo');
        if ($result && ($result['status'] ?? '') === 'success') {
            return (int) ($result['data']['height'] ?? 0);
        }
        return null;
    }

    /**
     * Vérification via monero-wallet-rpc (si configuré)
     * Utilise get_payments avec le payment_id (généré côté serveur) puis
     * SOMME les montants réellement reçus par le wallet :
     * - montant >= 99 % de l'attendu → paiement complet (validable)
     * - excédent → validé et logué
     * - montant < 99 % → paiement partiel, JAMAIS validé automatiquement
     */
    private function checkPaymentViaWalletRpc(string $paymentId, float $expectedAmount): array
    {
        $rpcUrl = $this->getSetting('monero_wallet_rpc_url', '');
        if (empty($rpcUrl)) return ['found' => false];

        $rpcUser = $this->getSetting('monero_wallet_rpc_user', '');
        $rpcPass = $this->getSetting('monero_wallet_rpc_password', '');

        // D'abord refresh le wallet
        $this->walletRpcCall('refresh', [], $rpcUrl, $rpcUser, $rpcPass);

        // Chercher les paiements par payment_id
        $candidates = [];
        $result = $this->walletRpcCall('get_payments', [
            'payment_id' => $paymentId,
        ], $rpcUrl, $rpcUser, $rpcPass);

        if ($result && !empty($result['payments'])) {
            foreach ($result['payments'] as $payment) {
                $candidates[] = [
                    'amount' => (int) ($payment['amount'] ?? 0),
                    'tx_hash' => (string) ($payment['tx_hash'] ?? ''),
                    'block_height' => (int) ($payment['block_height'] ?? 0),
                    'confirmations' => null,
                ];
            }
        }

        // Repli : get_transfers (entrées) si get_payments n'a rien donné
        if (empty($candidates)) {
            $transfers = $this->walletRpcCall('get_transfers', [
                'in' => true,
                'payment_id' => $paymentId,
            ], $rpcUrl, $rpcUser, $rpcPass);

            if ($transfers && !empty($transfers['in'])) {
                foreach ($transfers['in'] as $transfer) {
                    $candidates[] = [
                        'amount' => (int) ($transfer['amount'] ?? 0),
                        'tx_hash' => (string) ($transfer['txid'] ?? ''),
                        'block_height' => (int) ($transfer['height'] ?? 0),
                        'confirmations' => isset($transfer['confirmations']) ? (int) $transfer['confirmations'] : null,
                    ];
                }
            }
        }

        return $this->matchWalletPayments($candidates, $expectedAmount);
    }

    /**
     * Analyse les paiements reçus par le wallet pour un payment_id :
     * somme des montants, anti-réutilisation des TX, gestion partielle/
     * excédentaire. Toute erreur RPC laisse la liste vide → found=false,
     * aucune validation n'est possible sur une réponse absente.
     */
    private function matchWalletPayments(array $candidates, float $expectedAmount): array
    {
        $expectedAtomic = (int) $this->toAtomic($expectedAmount);
        // En dessous de 99 % du montant attendu : paiement partiel, la
        // commande n'est jamais validée automatiquement.
        $minAcceptable = (int) ($expectedAtomic * 0.99);

        $totalAtomic = 0;
        $bestTxHash = '';
        $bestConfirmations = 0;

        foreach ($candidates as $candidate) {
            $amount = $candidate['amount'];
            $txHash = $candidate['tx_hash'];
            if ($amount <= 0 || $txHash === '') continue;

            // Une même TX ne peut jamais valider deux commandes
            $alreadyUsed = $this->db->queryOne(
                "SELECT id FROM purchases WHERE transaction_id = ? AND status = 'completed'",
                [$txHash]
            );
            if ($alreadyUsed) {
                $this->log("TX {$txHash} déjà utilisée par l'achat #{$alreadyUsed['id']} — ignorée");
                continue;
            }

            $totalAtomic += $amount;

            // Confirmations de la TX qui fait franchir le seuil
            if ($totalAtomic >= $minAcceptable && $bestTxHash === '') {
                $confirmations = $candidate['confirmations'];
                if ($confirmations === null) {
                    $currentHeight = $this->getCurrentHeight() ?? 0;
                    $confirmations = max(0, $currentHeight - $candidate['block_height'] + 1);
                }
                $bestTxHash = $txHash;
                $bestConfirmations = (int) $confirmations;
            }
        }

        if ($totalAtomic <= 0) {
            return ['found' => false];
        }

        if ($totalAtomic > $expectedAtomic) {
            $this->log("Paiement excédentaire : {$totalAtomic} atomic units reçus pour {$expectedAtomic} attendus — commande validée pour le montant reçu");
        }

        if ($totalAtomic < $minAcceptable) {
            return [
                'found' => true,
                'partial' => true,
                'tx_id' => $bestTxHash,
                'amount_atomic' => (string) $totalAtomic,
                'expected_atomic' => (string) $expectedAtomic,
                'confirmations' => 0,
            ];
        }

        return [
            'found' => true,
            'partial' => false,
            'tx_id' => $bestTxHash,
            'amount_atomic' => (string) $totalAtomic,
            'expected_atomic' => (string) $expectedAtomic,
            'confirmations' => $bestConfirmations,
        ];
    }

    /**
     * Appelle l'API monero-wallet-rpc (JSON-RPC 2.0)
     */
    private function walletRpcCall(string $method, array $params, string $url, string $user, string $pass): ?array
    {
        if (!function_exists('curl_init')) return null;

        $payload = [
            'jsonrpc' => '2.0',
            'id' => '0',
            'method' => $method,
        ];
        if (!empty($params)) {
            $payload['params'] = $params;
        }

        $ch = curl_init($url);
        if ($ch === false) return null;

        $headers = ['Content-Type: application/json'];
        if (!empty($user)) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::API_CONNECT_TIMEOUT,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            // RPC local : SSL désactivé ; RPC distant : exiger HTTPS
            CURLOPT_SSL_VERIFYPEER => (stripos($url, 'https://') === 0),
            CURLOPT_SSL_VERIFYHOST => (stripos($url, 'https://') === 0) ? 2 : 0,
            CURLOPT_USERAGENT => 'EchangeLien-MoneroService/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            $this->log("Wallet RPC {$method} → HTTP {$httpCode}");
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON invalide depuis wallet RPC {$method}");
            return null;
        }

        if (isset($decoded['error'])) {
            $this->log("Wallet RPC {$method} error: " . ($decoded['error']['message'] ?? 'unknown'));
            return null;
        }

        return $decoded['result'] ?? null;
    }

    // ========================================
    // VÉRIFICATION AUTOMATIQUE (CRON)
    // ========================================

    /**
     * Vérifie les paiements en attente
     * - Via wallet RPC si configuré (auto)
     * - Via explorateur pour les TX soumises (confirme existence)
     */
    public function checkAllPendingPayments(): array
    {
        $pending = $this->db->query(
            "SELECT * FROM purchases
             WHERE payment_method = 'monero'
             AND status = 'pending'"
        );

        $results = ['checked' => 0, 'confirmed' => 0, 'expired' => 0];
        $walletRpcUrl = $this->getSetting('monero_wallet_rpc_url', '');

        foreach ($pending as $purchase) {
            if (strtotime($purchase['expires_at']) < time()) {
                $this->db->update('purchases', ['status' => 'cancelled'], "id = ? AND status = 'pending'", [(int) $purchase['id']]);
                $results['expired']++;
                continue;
            }

            $results['checked']++;

            // Mode wallet RPC : vérification automatique complète
            if (!empty($walletRpcUrl)) {
                $paymentId = $this->extractPaymentId($purchase);
                $rpcCheck = $this->checkPaymentViaWalletRpc($paymentId, (float) $purchase['amount_xelis']);

                if (!empty($rpcCheck['partial'])) {
                    $this->log("Achat #{$purchase['id']} : paiement partiel détecté — commande non validée");
                    continue;
                }

                if ($rpcCheck['found']) {
                    $minConfirmations = max(1, (int) $this->getSetting('monero_min_confirmations', '10'));
                    $txId = (string) ($rpcCheck['tx_id'] ?? '');
                    $txConfirmations = (int) ($rpcCheck['confirmations'] ?? 0);
                    if ($txId !== '' && $txConfirmations >= $minConfirmations) {
                        $this->confirmPurchase(
                            (int) $purchase['id'],
                            $purchase,
                            $txId
                        );
                        $results['confirmed']++;
                    }
                }
                continue;
            }

            // Mode manuel : vérifier les TX soumises via explorateur
            if (!empty($purchase['transaction_id'])) {
                $txDetails = $this->getTransactionFromExplorer($purchase['transaction_id']);
                if ($txDetails) {
                    $minConfirmations = (int) $this->getSetting('monero_min_confirmations', '10');
                    $txConfirmations = (int) ($txDetails['confirmations'] ?? 0);
                    // Note : en mode manuel, on ne confirme PAS automatiquement
                    // car on ne peut pas vérifier le montant/destinataire
                    // L'admin doit confirmer manuellement
                    if ($txConfirmations >= $minConfirmations) {
                        $this->log("TX {$purchase['transaction_id']} pour achat #{$purchase['id']} : {$txConfirmations} confirmations - en attente confirmation admin");
                    }
                }
            }
        }

        $this->log("Vérification cron Monero: {$results['checked']} vérifiés, {$results['confirmed']} confirmés, {$results['expired']} expirés");
        return $results;
    }

    // ========================================
    // UTILITAIRES
    // ========================================

    /**
     * Génère un payment_id unique (16 caractères hex)
     */
    private function generatePaymentId(int $userId, int $packId): string
    {
        // Combinaison unique : timestamp + user + pack + random
        $base = dechex(time()) . dechex($userId) . dechex($packId);
        $random = bin2hex(random_bytes(4));
        $paymentId = substr($base . $random, 0, self::PAYMENT_ID_LENGTH);
        return str_pad($paymentId, self::PAYMENT_ID_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Extrait le payment_id depuis un achat (stocké dans admin_notes)
     */
    private function extractPaymentId(array $purchase): string
    {
        $notes = $purchase['admin_notes'] ?? '';
        if (preg_match('/payment_id:([a-f0-9]+)/', $notes, $m)) {
            return $m[1];
        }
        // Fallback : générer un hash déterministe mais non trivial
        $base = md5('monero_pid_' . ($purchase['id'] ?? 0) . '_' . ($purchase['user_id'] ?? 0) . '_' . ($purchase['created_at'] ?? ''));
        return substr($base, 0, self::PAYMENT_ID_LENGTH);
    }

    /**
     * Version publique de extractPaymentId pour usage dans le contrôleur
     */
    public function getPaymentIdFromPurchase(array $purchase): ?string
    {
        return $this->extractPaymentId($purchase);
    }

    /**
     * Construit un URI Monero pour QR code
     * Format: monero:address?tx_amount=X&tx_description=Y
     * Note: 8 décimales max car DECIMAL(20,8) en base
     */
    public function buildMoneroUri(string $address, float $amount, string $paymentId = ''): string
    {
        $uri = "monero:{$address}?tx_amount=" . number_format($amount, 8, '.', '');
        if (!empty($paymentId)) {
            $uri .= "&tx_payment_id={$paymentId}";
        }
        return $uri;
    }

    /**
     * Valide une adresse Monero
     * Mainnet : commence par '4' (standard, 95 chars) ou '8' (subaddress, 97 chars)
     * Integrated address : 106 chars
     */
    public function validateAddress(string $address): bool
    {
        $len = strlen($address);
        // Adresse standard (95) ou subaddress (97) ou integrated (106)
        if ($len !== 95 && $len !== 97 && $len !== 106) return false;
        // Caractères alphanumériques uniquement
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]+$/', $address)) return false;
        // Doit commencer par 4 (standard) ou 8 (subaddress)
        return ($address[0] === '4' || $address[0] === '8');
    }

    public function getExplorerTxUrl(string $txId): string
    {
        return $this->explorerUrl . '/tx/' . urlencode($txId);
    }

    public function getExplorerAddressUrl(string $address): string
    {
        return $this->explorerUrl . '/search?value=' . urlencode($address);
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
                usleep(300000 * ($i + 1));
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

        $url = $this->explorerUrl . $path;
        $ch = curl_init($url);
        if ($ch === false) return null;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::API_CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'EchangeLien-MoneroService/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            if ($httpCode === 429) {
                $this->log("Rate limit API Monero (429) sur GET {$path}");
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
        $price = coingecko_fetch_eur('monero');

        if ($price > 0) {
            $this->setCache('monero_price_eur_stale', (string) $price, 86400);
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
                'message' => '[MoneroService] ' . $message,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {}
    }
}
