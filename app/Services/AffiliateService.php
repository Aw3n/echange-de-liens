<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Service d'affiliation
 *
 * Module optionnel (activable/désactivable par l'admin via le réglage
 * affiliate_enabled) : lorsqu'un filleul (utilisateur inscrit via le
 * lien de parrainage d'un parrain) passe une commande VALIDÉE
 * (statut « completed », tous modules de paiement crypto + PayPal),
 * le parrain gagne un pourcentage du montant selon un barème à paliers
 * configurable par l'admin.
 *
 * Fonctionnement :
 * - Les commissions sont matérialisées dans affiliate_commissions
 *   (montant et pourcentage FIGÉS à la création ; UNIQUE purchase_id
 *   garantit qu'une commande ne génère jamais deux commissions).
 * - Le sync est paresseux et idempotent : syncCommissions() scanne les
 *   commandes completed sans commission et crée les lignes manquantes.
 *   Il est appelé à l'affichage (dashboard, page admin) donc couvre
 *   tous les chemins de confirmation (auto crypto + PayPal manuel)
 *   sans avoir à modifier chaque service de paiement.
 * - L'admin paie un parrain quand son solde disponible atteint le
 *   minimum configurable (défaut 10 €), via l'un des moyens de paiement
 *   renseignés par l'utilisateur (adresses crypto de son compte ou
 *   PayPal). Le paiement solde la totalité du disponible et marque les
 *   commissions correspondantes comme payées.
 *
 * Confidentialité : les adresses de paiement d'un utilisateur ne sont
 * visibles que par lui-même (page Mon compte) et par l'admin.
 */
class AffiliateService
{
    /** Moyens de paiement utilisateurs : méthode → colonne users + libellé */
    public const METHODS = [
        'paypal'    => ['column' => 'paypal_email',     'label' => 'PayPal',    'icon' => 'fab fa-paypal',        'color' => '#0070ba'],
        'xelis'     => ['column' => 'xelis_address',    'label' => 'Xelis',     'icon' => 'fas fa-coins',         'color' => '#f7b32b'],
        'kaspa'     => ['column' => 'kaspa_address',    'label' => 'Kaspa',     'icon' => 'fas fa-gem',           'color' => '#4fc3f7'],
        'firo'      => ['column' => 'firo_address',     'label' => 'Firo',      'icon' => 'fas fa-shield-alt',    'color' => '#e65100'],
        'verge'     => ['column' => 'verge_address',    'label' => 'Verge',     'icon' => 'fas fa-lock',          'color' => '#2b1f6b'],
        'pepecoin'  => ['column' => 'pepecoin_address', 'label' => 'Pepecoin',  'icon' => 'fas fa-frog',          'color' => '#2f9e44'],
        'vertcoin'  => ['column' => 'vertcoin_address', 'label' => 'Vertcoin',  'icon' => 'fas fa-leaf',          'color' => '#048657'],
        'dragonx'   => ['column' => 'dragonx_address',  'label' => 'DragonX',   'icon' => 'fas fa-dragon',        'color' => '#e8590c'],
        'monero'    => ['column' => 'monero_address',   'label' => 'Monero',    'icon' => 'fas fa-user-secret',   'color' => '#ff6600'],
    ];

    private Database $db;
    /** @var string[] */
    private array $errors = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    // ========================================
    // CONFIGURATION
    // ========================================

    /**
     * Le module d'affiliation est-il activé par l'admin ?
     */
    public function isEnabled(): bool
    {
        return $this->getSetting('affiliate_enabled', '0') === '1';
    }

    /**
     * Solde minimum (EUR) avant de pouvoir payer un parrain
     */
    public function getMinPayout(): float
    {
        return max(0.0, (float) $this->getSetting('affiliate_min_payout', '10'));
    }

    /**
     * Barème de commissions : liste de paliers [min, pct] triés du plus
     * exigeant au moins exigeant. Le palier applicable à une commande
     * est le premier dont le minimum est atteint.
     * @return array<int, array{min: float, pct: float}>
     */
    public function getTiers(): array
    {
        $tiers = [];
        for ($i = 1; $i <= 3; $i++) {
            $min = (float) $this->getSetting("affiliate_tier{$i}_min", '');
            $pct = (float) $this->getSetting("affiliate_tier{$i}_pct", '');
            if ($pct > 0) {
                $tiers[] = ['min' => $min, 'pct' => $pct];
            }
        }
        usort($tiers, fn($a, $b) => $b['min'] <=> $a['min']);
        return $tiers;
    }

    /**
     * Pourcentage applicable à un montant de commande donné
     */
    public function pctForAmount(float $amountEur): float
    {
        foreach ($this->getTiers() as $tier) {
            if ($amountEur >= $tier['min']) {
                return $tier['pct'];
            }
        }
        return 0.0;
    }

    /**
     * Commission due pour un montant de commande
     */
    public function commissionForAmount(float $amountEur): float
    {
        return round($amountEur * $this->pctForAmount($amountEur) / 100, 2);
    }

    // ========================================
    // SYNCHRONISATION DES COMMISSIONS
    // ========================================

    /**
     * Crée les commissions manquantes pour toutes les commandes validées
     * d'utilisateurs ayant un parrain. Idempotent (UNIQUE purchase_id +
     * INSERT IGNORE). Ne fait rien tant que le module est désactivé.
     * @return int nombre de commissions créées
     */
    public function syncCommissions(): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        try {
            $rows = $this->db->query(
                "SELECT p.id AS purchase_id, p.user_id AS referred_id, p.amount_eur, u.referrer_id
                 FROM purchases p
                 JOIN users u ON u.id = p.user_id
                 WHERE p.status = 'completed'
                   AND u.referrer_id IS NOT NULL
                   AND u.referrer_id != u.id
                   AND NOT EXISTS (
                       SELECT 1 FROM affiliate_commissions c WHERE c.purchase_id = p.id
                   )"
            );
        } catch (\Throwable $e) {
            // Tables absentes (installation non migrée) : non bloquant
            return 0;
        }

        $created = 0;
        foreach ($rows as $row) {
            $amount = (float) $row['amount_eur'];
            $pct = $this->pctForAmount($amount);
            if ($pct <= 0) {
                continue;
            }
            $commission = round($amount * $pct / 100, 2);
            if ($commission <= 0) {
                continue;
            }
            try {
                $affected = $this->db->execute(
                    "INSERT IGNORE INTO affiliate_commissions
                        (referrer_id, referred_id, purchase_id, amount_eur, pct, commission_eur)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        (int) $row['referrer_id'],
                        (int) $row['referred_id'],
                        (int) $row['purchase_id'],
                        number_format($amount, 2, '.', ''),
                        number_format($pct, 2, '.', ''),
                        number_format($commission, 2, '.', ''),
                    ]
                );
                $created += $affected;
            } catch (\Throwable $e) {
                // Doublon concurrent ou contrainte : ignoré
            }
        }

        return $created;
    }

    // ========================================
    // SOLDES & STATS
    // ========================================

    /**
     * Statistiques d'affiliation d'un parrain
     * @return array{earned: float, paid: float, available: float, referrals: int, commissions: array}
     */
    public function getUserStats(int $userId): array
    {
        $empty = ['earned' => 0.0, 'paid' => 0.0, 'available' => 0.0, 'referrals' => 0, 'commissions' => []];
        try {
            $sums = $this->db->queryOne(
                "SELECT
                    COALESCE(SUM(commission_eur), 0) AS earned,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_eur ELSE 0 END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN status = 'earned' THEN commission_eur ELSE 0 END), 0) AS available
                 FROM affiliate_commissions WHERE referrer_id = ?",
                [$userId]
            );
            $referrals = $this->db->queryOne(
                "SELECT COUNT(*) AS cnt FROM users WHERE referrer_id = ?",
                [$userId]
            );
            $commissions = $this->db->query(
                "SELECT c.*, u.username AS referred_username
                 FROM affiliate_commissions c
                 JOIN users u ON u.id = c.referred_id
                 WHERE c.referrer_id = ?
                 ORDER BY c.created_at DESC LIMIT 50",
                [$userId]
            );
        } catch (\Throwable $e) {
            return $empty;
        }

        return [
            'earned' => (float) ($sums['earned'] ?? 0),
            'paid' => (float) ($sums['paid'] ?? 0),
            'available' => (float) ($sums['available'] ?? 0),
            'referrals' => (int) ($referrals['cnt'] ?? 0),
            'commissions' => $commissions,
        ];
    }

    /**
     * Vue admin : synthèse de tous les parrains ayant au moins un
     * filleul ou une commission
     */
    public function getReferrersSummary(): array
    {
        try {
            return $this->db->query(
                "SELECT u.id, u.username, u.email,
                    (SELECT COUNT(*) FROM users f WHERE f.referrer_id = u.id) AS referrals,
                    COALESCE(s.earned, 0) AS earned,
                    COALESCE(s.paid, 0) AS paid,
                    COALESCE(s.available, 0) AS available,
                    COALESCE(s.nb_commissions, 0) AS nb_commissions
                 FROM users u
                 LEFT JOIN (
                     SELECT referrer_id,
                         SUM(commission_eur) AS earned,
                         SUM(CASE WHEN status = 'paid' THEN commission_eur ELSE 0 END) AS paid,
                         SUM(CASE WHEN status = 'earned' THEN commission_eur ELSE 0 END) AS available,
                         COUNT(*) AS nb_commissions
                     FROM affiliate_commissions
                     GROUP BY referrer_id
                 ) s ON s.referrer_id = u.id
                 WHERE (SELECT COUNT(*) FROM users f WHERE f.referrer_id = u.id) > 0
                    OR s.referrer_id IS NOT NULL
                 ORDER BY available DESC, earned DESC"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Historique des paiements d'affiliation (vue admin)
     */
    public function getPayouts(int $limit = 100): array
    {
        try {
            return $this->db->query(
                "SELECT ap.*, u.username
                 FROM affiliate_payouts ap
                 JOIN users u ON u.id = ap.user_id
                 ORDER BY ap.created_at DESC LIMIT ?",
                [$limit]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ========================================
    // MOYENS DE PAIEMENT UTILISATEUR
    // ========================================

    /**
     * Moyens de paiement renseignés par un utilisateur (non vides)
     * @return array<string, array{label: string, icon: string, color: string, address: string}>
     */
    public function getUserPayoutMethods(array $user): array
    {
        $methods = [];
        foreach (self::METHODS as $method => $meta) {
            $value = trim((string) ($user[$meta['column']] ?? ''));
            if ($value !== '') {
                $methods[$method] = [
                    'label' => $meta['label'],
                    'icon' => $meta['icon'],
                    'color' => $meta['color'],
                    'address' => $value,
                ];
            }
        }
        return $methods;
    }

    // ========================================
    // PAIEMENT D'UN PARRAIN PAR L'ADMIN
    // ========================================

    /**
     * Paie la totalité du solde disponible d'un parrain via le moyen
     * choisi (parmi ceux qu'il a renseignés sur son compte).
     * @return array{success: bool, message: string}
     */
    public function createPayout(int $userId, string $method, string $adminNote = ''): array
    {
        if (!isset(self::METHODS[$method])) {
            return ['success' => false, 'message' => 'Moyen de paiement inconnu.'];
        }

        $user = $this->db->queryOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            return ['success' => false, 'message' => 'Utilisateur introuvable.'];
        }

        $column = self::METHODS[$method]['column'];
        $address = trim((string) ($user[$column] ?? ''));
        if ($address === '') {
            return ['success' => false, 'message' => 'L\'utilisateur n\'a pas renseigné ce moyen de paiement sur son compte.'];
        }

        $minPayout = $this->getMinPayout();

        try {
            $this->db->beginTransaction();

            // Réclamation atomique du solde : le verrou FOR UPDATE sur les
            // commissions acquises empêche un double paiement concurrent
            // (double clic / requêtes simultanées) de solder deux fois le
            // même disponible.
            $locked = $this->db->queryOne(
                "SELECT COALESCE(SUM(commission_eur), 0) AS available
                 FROM affiliate_commissions
                 WHERE referrer_id = ? AND status = 'earned'
                 FOR UPDATE",
                [$userId]
            );
            $available = (float) ($locked['available'] ?? 0.0);
            if ($available < $minPayout) {
                $this->db->rollBack();
                return ['success' => false, 'message' => sprintf(
                    'Solde disponible %.2f € insuffisant (minimum %.2f €).',
                    $available, $minPayout
                )];
            }

            $payoutId = $this->db->insert('affiliate_payouts', [
                'user_id' => $userId,
                'amount_eur' => number_format($available, 2, '.', ''),
                'method' => $method,
                'address' => $address,
                'status' => 'paid',
                'admin_note' => $adminNote !== '' ? mb_substr($adminNote, 0, 255) : null,
                'paid_at' => date('Y-m-d H:i:s'),
            ]);

            // Marque comme payées TOUTES les commissions acquises (le
            // paiement solde l'intégralité du disponible)
            $this->db->execute(
                "UPDATE affiliate_commissions SET status = 'paid', payout_id = ?
                 WHERE referrer_id = ? AND status = 'earned'",
                [$payoutId, $userId]
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->getPdo()->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du paiement.'];
        }

        return ['success' => true, 'message' => sprintf(
            'Paiement de %.2f € enregistré via %s (%s).',
            $available, self::METHODS[$method]['label'], $address
        )];
    }

    // ========================================
    // VALIDATION DES ADRESSES UTILISATEUR
    // ========================================

    /**
     * Valide une adresse de paiement utilisateur selon la crypto.
     * Réutilise les validateurs stricts des services de paiement ;
     * PayPal = email valide ; Xelis = hex 90-106 caractères.
     */
    public function validateWalletAddress(string $method, string $address): bool
    {
        $address = trim($address);
        if ($address === '' || !isset(self::METHODS[$method])) {
            return false;
        }

        try {
            switch ($method) {
                case 'paypal':
                    return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
                case 'xelis':
                    // Adresse Xelis mainnet : hexadécimale (préfixe xel: toléré)
                    return (bool) preg_match('/^(xel:)?[a-fA-F0-9]{90,106}$/', $address);
                case 'kaspa':
                    return (new KaspaService())->validateAddress($address);
                case 'firo':
                    return (new FiroService())->validateAddress($address);
                case 'verge':
                    return (new VergeService())->validateAddress($address);
                case 'pepecoin':
                    return (new PepecoinService())->validateAddress($address);
                case 'vertcoin':
                    return (new VertcoinService())->validateAddress($address);
                case 'dragonx':
                    return (new DragonxService())->validateAddress($address);
                case 'monero':
                    return (new MoneroService())->validateAddress($address);
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    // ========================================
    // INTERNE
    // ========================================

    private function getSetting(string $key, string $default = ''): string
    {
        try {
            $result = $this->db->queryOne(
                "SELECT setting_value FROM settings WHERE setting_key = ?",
                [$key]
            );
            return $result['setting_value'] ?? $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
