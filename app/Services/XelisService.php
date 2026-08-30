<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Service XELIS - Intégration complète avec la blockchain XELIS
 *
 * Fonctionnalités :
 * - Prix temps réel EUR/XEL via CoinGecko
 * - Adresses intégrées pour identifier les paiements
 * - Vérification des transactions via wallet RPC, Index API ou daemon RPC
 * - Gestion de l'expiration des paiements
 *
 * Configuration admin requise (settings) :
 * - xelis_daemon_url : URL du daemon RPC JSON-RPC 2.0 (ex: http://127.0.0.1:8080)
 * - xelis_index_url : URL de l'API Index REST (ex: https://index.xelis.io)
 * - xelis_wallet_url : URL du wallet RPC (ex: http://127.0.0.1:8081)
 * - xelis_wallet_user : Utilisateur RPC wallet
 * - xelis_wallet_password : Mot de passe RPC wallet
 * - xelis_address : Adresse XELIS de réception des paiements
 * - xelis_confirmations : Nombre de confirmations requises (défaut: 5)
 * - xelis_rate_eur : Taux EUR/XEL manuel (0 = auto CoinGecko)
 * - xelis_payment_timeout : Délai expiration paiement en minutes (défaut: 30)
 */
class XelisService
{
    private const XELIS_ASSET = '0000000000000000000000000000000000000000000000000000000000000000';
    private const ATOMIC_UNITS = 100000000; // 1 XEL = 100 000 000 atomic units
    private const COINGECKO_API = 'https://api.coingecko.com/api/v3/simple/price';
    private const CACHE_TTL = 300; // 5 minutes cache prix

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ========================================
    // PRIX ET CONVERSION
    // ========================================

    /**
     * Récupère le prix XEL/EUR via CoinGecko avec cache
     */
    public function getXelPriceEur(): float
    {
        // Vérifier le taux manuel admin
        $manualRate = $this->getSetting('xelis_rate_eur', '0');
        if ((float) $manualRate > 0) {
            return (float) $manualRate;
        }

        // Cache
        $cached = $this->getCache('xelis_price_eur');
        if ($cached !== null) {
            return (float) $cached;
        }

        // API CoinGecko
        $price = $this->fetchCoinGeckoPrice();
        if ($price > 0) {
            $this->setCache('xelis_price_eur', (string) $price, self::CACHE_TTL);
        } else {
            // Repli sur le dernier prix connu (24 h) si l'API est indisponible
            $stalePrice = $this->getCache('xelis_price_eur_stale');
            if ($stalePrice !== null) {
                return (float) $stalePrice;
            }
        }

        return $price;
    }

    /**
     * Convertit un montant EUR en XEL
     */
    public function eurToXel(float $eur): float
    {
        $price = $this->getXelPriceEur();
        if ($price <= 0) {
            return 0.0;
        }
        return round($eur / $price, 8);
    }

    /**
     * Convertit un montant XEL en EUR
     */
    public function xelToEur(float $xel): float
    {
        $price = $this->getXelPriceEur();
        return round($xel * $price, 2);
    }

    /**
     * Convertit XEL en atomic units (pour la blockchain)
     */
    public function toAtomicUnits(float $xel): int
    {
        return (int) round($xel * self::ATOMIC_UNITS);
    }

    /**
     * Convertit atomic units en XEL
     */
    public function fromAtomicUnits(int $atomic): float
    {
        return $atomic / self::ATOMIC_UNITS;
    }

    // ========================================
    // PAIEMENTS
    // ========================================

    /**
     * Crée un paiement XELIS pour un achat VIP
     *
     * @param int $userId ID utilisateur
     * @param int $packId ID du pack VIP
     * @return array|null Données du paiement ou null si erreur
     */
    public function createPayment(int $userId, int $packId): ?array
    {
        // Récupérer les infos du pack
        $points = (int) ($this->getSetting("vip_points_{$packId}", '0'));
        $priceEur = (float) $this->getSetting("vip_price_{$packId}", '0');

        if ($points <= 0 || $priceEur <= 0) {
            return null;
        }

        // Conversion EUR → XEL
        $xelAmount = $this->eurToXel($priceEur);
        if ($xelAmount <= 0) {
            return null;
        }

        // Adresse de réception
        $baseAddress = $this->getSetting('xelis_address', '');
        if (empty($baseAddress)) {
            return null;
        }

        // Timeout paiement
        $timeoutMinutes = (int) $this->getSetting('xelis_payment_timeout', '30');
        $expiresAt = date('Y-m-d H:i:s', time() + ($timeoutMinutes * 60));

        // Générer une adresse intégrée avec l'ID utilisateur pour identification
        $integratedAddress = $this->generateIntegratedAddress($baseAddress, [
            'user_id' => $userId,
            'pack_id' => $packId,
            'ts' => time(),
        ]);

        // Créer l'achat en base
        $purchaseId = $this->db->insert('purchases', [
            'user_id' => $userId,
            'amount_eur' => $priceEur,
            'amount_xelis' => $xelAmount,
            'payment_method' => 'xelis',
            'points_purchased' => $points,
            'status' => 'pending',
            'payment_address' => $integratedAddress,
            'user_xelis_address' => '',
            'expires_at' => $expiresAt,
        ]);

        return [
            'purchase_id' => $purchaseId,
            'payment_address' => $integratedAddress,
            'base_address' => $baseAddress,
            'xel_amount' => $xelAmount,
            'xel_atomic' => $this->toAtomicUnits($xelAmount),
            'eur_amount' => $priceEur,
            'points' => $points,
            'expires_at' => $expiresAt,
            'timeout_minutes' => $timeoutMinutes,
        ];
    }

    /**
     * Vérifie le statut d'un paiement
     *
     * @param int $purchaseId ID de l'achat
     * @return array Statut du paiement
     */
    public function getPaymentStatus(int $purchaseId): array
    {
        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'xelis'",
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

        // Vérifier la TX si hash fourni
        if (!empty($purchase['transaction_id'])) {
            $txStatus = $this->verifyTransaction($purchase['transaction_id']);
            if ($txStatus['confirmed']) {
                $this->confirmPurchase($purchaseId, $purchase);
                return [
                    'status' => 'completed',
                    'message' => 'Paiement confirmé sur la blockchain ! Points crédités.',
                    'transaction_hash' => $purchase['transaction_id'],
                    'confirmations' => $txStatus['confirmations'],
                ];
            }
            return [
                'status' => 'pending_confirmation',
                'message' => 'Transaction détectée, en attente de confirmations...',
                'transaction_hash' => $purchase['transaction_id'],
                'confirmations' => $txStatus['confirmations'] ?? 0,
                'required_confirmations' => $this->getRequiredConfirmations(),
            ];
        }

        // En attente de paiement
        $remainingSeconds = max(0, strtotime($purchase['expires_at']) - time());
        return [
            'status' => 'waiting',
            'message' => 'En attente du paiement XELIS...',
            'remaining_seconds' => $remainingSeconds,
            'xel_amount' => (float) $purchase['amount_xelis'],
            'payment_address' => $purchase['payment_address'],
        ];
    }

    /**
     * Soumet un hash de transaction pour vérification
     */
    public function submitTransactionHash(int $purchaseId, string $txHash): array
    {
        // Valider le format du hash (64 caractères hex)
        if (!preg_match('/^[a-f0-9]{64}$/i', $txHash)) {
            return ['success' => false, 'message' => 'Format de hash invalide.'];
        }

        $purchase = $this->db->queryOne(
            "SELECT * FROM purchases WHERE id = ? AND payment_method = 'xelis' AND status = 'pending'",
            [$purchaseId]
        );

        if (!$purchase) {
            return ['success' => false, 'message' => 'Achat non trouvé ou déjà traité.'];
        }

        // Vérifier que la TX n'est pas déjà utilisée
        $existing = $this->db->queryOne(
            "SELECT id FROM purchases WHERE transaction_id = ? AND id != ?",
            [$txHash, $purchaseId]
        );
        if ($existing) {
            return ['success' => false, 'message' => 'Cette transaction a déjà été soumise.'];
        }

        // Sauvegarder le hash
        $this->db->update('purchases', [
            'transaction_id' => strtolower($txHash),
        ], 'id = ?', [$purchaseId]);

        // Tenter une vérification automatique
        $txStatus = $this->verifyTransaction($txHash);

        if ($txStatus['confirmed']) {
            $this->confirmPurchase($purchaseId, $purchase);
            return [
                'success' => true,
                'message' => 'Transaction confirmée ! Points crédités.',
                'auto_confirmed' => true,
            ];
        }

        return [
            'success' => true,
            'message' => 'Transaction enregistrée. En attente de confirmation sur la blockchain.',
            'auto_confirmed' => false,
            'confirmations' => $txStatus['confirmations'] ?? 0,
        ];
    }

    /**
     * Confirme un achat et crédite les points
     */
    public function confirmPurchase(int $purchaseId, array $purchase): bool
    {
        // Garde atomique : seule une commande encore « pending » peut être
        // confirmée. Une commande annulée ou expirée n'est jamais créditée
        // (pas de remboursement : rien n'a été débité à la création).
        $updated = $this->db->execute(
            "UPDATE purchases SET status = 'completed', confirmed_at = ?
             WHERE id = ? AND status = 'pending'",
            [date('Y-m-d H:i:s'), $purchaseId]
        );
        if ($updated === 0) {
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
            'description' => "Achat VIP XELIS confirmé - {$purchase['points_purchased']} points",
            'reference_id' => $purchaseId,
            'reference_type' => 'purchase',
        ]);

        return true;
    }

    // ========================================
    // VÉRIFICATION BLOCKCHAIN
    // ========================================

    /**
     * Vérifie une transaction sur la blockchain
     *
     * @param string $txHash Hash de la transaction
     * @return array ['confirmed' => bool, 'confirmations' => int, 'details' => array]
     */
    public function verifyTransaction(string $txHash): array
    {
        // 1) Wallet RPC : le plus fiable (peut déchiffrer les transferts)
        if (!empty($this->getSetting('xelis_wallet_url', ''))) {
            return $this->verifyViaWalletRpc($txHash);
        }

        // 2) Index API : vue indexée REST, idéale pour la recherche
        if (!empty($this->getSetting('xelis_index_url', 'https://index.xelis.io'))) {
            return $this->verifyViaIndexApi($txHash);
        }

        // 3) Daemon RPC : JSON-RPC 2.0 sur {url}/json_rpc
        if (!empty($this->getSetting('xelis_daemon_url', ''))) {
            return $this->verifyViaDaemonRpc($txHash);
        }

        // Pas de RPC configuré : vérification manuelle par l'admin
        return [
            'confirmed' => false,
            'confirmations' => 0,
            'needs_manual' => true,
            'message' => 'Configuration RPC requise pour la vérification automatique.',
        ];
    }

    /**
     * Vérifie via le wallet RPC (peut décrypter les transactions)
     */
    private function verifyViaWalletRpc(string $txHash): array
    {
        $walletUrl = rtrim($this->getSetting('xelis_wallet_url', ''), '/');
        $user = $this->getSetting('xelis_wallet_user', '');
        $password = $this->getSetting('xelis_wallet_password', '');

        $result = $this->rpcCall($walletUrl . '/json_rpc', [
            'jsonrpc' => '2.0',
            'method' => 'get_transaction',
            'id' => 1,
            'params' => ['hash' => $txHash],
        ], $user, $password);

        if (!$result || isset($result['error'])) {
            return ['confirmed' => false, 'confirmations' => 0];
        }

        $tx = $result['result'] ?? [];
        $topoheight = $tx['topoheight'] ?? 0;

        // Récupérer la hauteur actuelle
        $heightResult = $this->rpcCall($walletUrl . '/json_rpc', [
            'jsonrpc' => '2.0',
            'method' => 'get_topoheight',
            'id' => 1,
        ], $user, $password);

        $currentHeight = $this->extractHeight($heightResult['result'] ?? null);
        $confirmations = $currentHeight > 0 ? max(0, $currentHeight - $topoheight) : 0;
        $required = $this->getRequiredConfirmations();

        return [
            'confirmed' => $confirmations >= $required,
            'confirmations' => $confirmations,
            'required_confirmations' => $required,
            'topoheight' => $topoheight,
            'details' => $tx,
        ];
    }

    /**
     * Vérifie via le daemon RPC (transactions publiques uniquement)
     */
    private function verifyViaDaemonRpc(string $txHash): array
    {
        $daemonUrl = rtrim($this->getSetting('xelis_daemon_url', ''), '/');

        $result = $this->rpcCall($daemonUrl . '/json_rpc', [
            'jsonrpc' => '2.0',
            'method' => 'get_transaction',
            'id' => 1,
            'params' => ['hash' => $txHash],
        ]);

        if (!$result || isset($result['error'])) {
            return ['confirmed' => false, 'confirmations' => 0];
        }

        $tx = $result['result'] ?? [];
        $inMempool = $tx['in_mempool'] ?? true;
        $executedBlock = $tx['executed_in_block'] ?? null;

        if ($inMempool || !$executedBlock) {
            return [
                'confirmed' => false,
                'confirmations' => 0,
                'in_mempool' => true,
                'message' => 'Transaction dans le mempool, en attente d\'exécution.',
            ];
        }

        // Topoheight de la TX (fournie par le daemon lorsqu'elle est exécutée)
        $txTopo = (int) ($tx['topoheight'] ?? $tx['topo_height'] ?? 0);
        if ($txTopo <= 0) {
            // Exécutée mais hauteur inconnue : vérification manuelle recommandée
            return [
                'confirmed' => false,
                'confirmations' => 0,
                'needs_manual' => true,
                'executed_in_block' => $executedBlock,
                'details' => $tx,
            ];
        }

        // Hauteur actuelle du réseau via get_info
        $infoResult = $this->rpcCall($daemonUrl . '/json_rpc', [
            'jsonrpc' => '2.0',
            'method' => 'get_info',
            'id' => 1,
        ]);

        $currentTopo = $this->extractHeight($infoResult['result'] ?? null);
        $confirmations = $currentTopo > 0 ? max(0, $currentTopo - $txTopo) : 0;
        $required = $this->getRequiredConfirmations();

        return [
            'confirmed' => $confirmations >= $required,
            'confirmations' => $confirmations,
            'required_confirmations' => $required,
            'executed_in_block' => $executedBlock,
            'details' => $tx,
        ];
    }

    /**
     * Vérifie via l'API Index (REST, vue indexée de la blockchain)
     * Ex : {index}/views/transactions?where=hash::eq::{hash}
     */
    private function verifyViaIndexApi(string $txHash): array
    {
        $indexUrl = rtrim($this->getSetting('xelis_index_url', 'https://index.xelis.io'), '/');

        $rows = $this->httpGetJson(
            $indexUrl . '/views/transactions?where=' . urlencode('hash::eq::' . $txHash) . '&limit=1'
        );
        $tx = $this->firstRow($rows);
        if ($tx === null) {
            return ['confirmed' => false, 'confirmations' => 0];
        }

        $txTopo = (int) ($tx['topoheight'] ?? $tx['topo_height'] ?? $tx['height'] ?? 0);
        if ($txTopo <= 0) {
            return [
                'confirmed' => false,
                'confirmations' => 0,
                'in_mempool' => true,
                'message' => 'Transaction détectée, en attente d\'exécution.',
                'details' => $tx,
            ];
        }

        $currentTopo = $this->indexTopoheight($indexUrl);
        $confirmations = $currentTopo > 0 ? max(0, $currentTopo - $txTopo) : 0;
        $required = $this->getRequiredConfirmations();

        return [
            'confirmed' => $confirmations >= $required,
            'confirmations' => $confirmations,
            'required_confirmations' => $required,
            'topoheight' => $txTopo,
            'details' => $tx,
        ];
    }

    /**
     * Hauteur (topoheight) actuelle du réseau via l'Index API
     */
    private function indexTopoheight(string $indexUrl): int
    {
        return $this->extractHeight($this->httpGetJson($indexUrl . '/views/get_blocks_topo()'));
    }

    /**
     * Extrait une hauteur d'une réponse aux formats possibles :
     * entier, {topoheight}, {height}, [n], [{topoheight|height}]
     */
    private function extractHeight($data): int
    {
        if (is_numeric($data)) {
            return (int) $data;
        }
        if (is_array($data)) {
            if (isset($data['topoheight'])) {
                return (int) $data['topoheight'];
            }
            if (isset($data['height'])) {
                return (int) $data['height'];
            }
            $first = $data[0] ?? null;
            if (is_numeric($first)) {
                return (int) $first;
            }
            if (is_array($first)) {
                return (int) ($first['topoheight'] ?? $first['height'] ?? 0);
            }
        }
        return 0;
    }

    /**
     * Première ligne d'une réponse de vue Index (array, {data:[...]}, objet seul)
     */
    private function firstRow($data): ?array
    {
        if (!is_array($data)) {
            return null;
        }
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        if (isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }
        if (isset($data['hash'])) {
            return $data;
        }
        return null;
    }

    /**
     * Requête HTTP GET JSON (Index API)
     */
    private function httpGetJson(string $url)
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'EchangeLien-XelisService/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        return json_decode((string) $response, true);
    }

    // ========================================
    // ADRESSES INTÉGRÉES
    // ========================================

    /**
     * Génère une adresse intégrée XELIS via le wallet RPC
     * Si le wallet n'est pas disponible, retourne l'adresse de base
     */
    public function generateIntegratedAddress(string $baseAddress, array $data): string
    {
        $walletUrl = $this->getSetting('xelis_wallet_url', '');
        $user = $this->getSetting('xelis_wallet_user', '');
        $password = $this->getSetting('xelis_wallet_password', '');

        if (!empty($walletUrl)) {
            $result = $this->rpcCall($walletUrl . '/json_rpc', [
                'jsonrpc' => '2.0',
                'method' => 'get_address',
                'id' => 1,
                'params' => ['integrated_data' => $data],
            ], $user, $password);

            if ($result && isset($result['result'])) {
                return $result['result'];
            }
        }

        // Fallback : adresse de base + référence en commentaire
        return $baseAddress;
    }

    // ========================================
    // VÉRIFICATION AUTOMATIQUE (CRON)
    // ========================================

    /**
     * Vérifie tous les paiements en attente
     * À appeler via cron ou manuellement
     *
     * @return array Résumé des vérifications
     */
    public function checkAllPendingPayments(): array
    {
        $pending = $this->db->query(
            "SELECT * FROM purchases
             WHERE payment_method = 'xelis'
             AND status = 'pending'
             AND transaction_id IS NOT NULL
             AND transaction_id != ''"
        );

        $results = ['checked' => 0, 'confirmed' => 0, 'expired' => 0, 'errors' => 0];

        foreach ($pending as $purchase) {
            // Vérifier expiration
            if (strtotime($purchase['expires_at']) < time()) {
                $this->db->update('purchases', ['status' => 'cancelled'], "id = ? AND status = 'pending'", [(int) $purchase['id']]);
                $results['expired']++;
                continue;
            }

            // Vérifier la transaction
            $txStatus = $this->verifyTransaction($purchase['transaction_id']);
            $results['checked']++;

            if ($txStatus['confirmed']) {
                $this->confirmPurchase((int) $purchase['id'], $purchase);
                $results['confirmed']++;
            }
        }

        // Annuler les paiements expirés sans TX
        $this->db->execute(
            "UPDATE purchases SET status = 'cancelled'
             WHERE payment_method = 'xelis' AND status = 'pending'
             AND expires_at < NOW()"
        );

        return $results;
    }

    // ========================================
    // UTILITAIRES
    // ========================================

    /**
     * Appel RPC JSON
     */
    private function rpcCall(string $url, array $payload, string $user = '', string $password = ''): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $headers = ['Content-Type: application/json'];
        if (!empty($user)) {
            $headers[] = 'Authorization: Basic ' . base64_encode($user . ':' . $password);
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Récupère le prix XEL/EUR depuis CoinGecko
     */
    private function fetchCoinGeckoPrice(): float
    {
        // Appel groupé partagé entre tous les services (limite de débit CoinGecko)
        $price = coingecko_fetch_eur('xelis');

        if ($price > 0) {
            $this->setCache('xelis_price_eur_stale', (string) $price, 86400);
        }

        return $price;
    }

    /**
     * Nombre de confirmations requises
     */
    private function getRequiredConfirmations(): int
    {
        return max(1, (int) $this->getSetting('xelis_confirmations', '5'));
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
            // Silencieux si la table n'existe pas
        }
    }
}
