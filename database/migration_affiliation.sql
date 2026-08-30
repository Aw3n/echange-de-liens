-- ============================================================
-- Migration : adresses de paiement crypto des utilisateurs
--             + module d'affiliation (commissions & payouts)
-- À exécuter sur une base déjà installée (le schema.sql complet
-- contient déjà ces définitions pour les nouvelles installations).
-- Toutes les requêtes sont idempotentes (les échecs de type
-- « colonne déjà présente » sont tolérés par l'exécuteur).
-- ============================================================

-- 1) Portefeuilles crypto des utilisateurs
-- (paypal_email et xelis_address existent déjà dans users)
ALTER TABLE `users` ADD COLUMN `kaspa_address` VARCHAR(255) NULL AFTER `xelis_address`;
ALTER TABLE `users` ADD COLUMN `firo_address` VARCHAR(255) NULL AFTER `kaspa_address`;
ALTER TABLE `users` ADD COLUMN `verge_address` VARCHAR(255) NULL AFTER `firo_address`;
ALTER TABLE `users` ADD COLUMN `pepecoin_address` VARCHAR(255) NULL AFTER `verge_address`;
ALTER TABLE `users` ADD COLUMN `vertcoin_address` VARCHAR(255) NULL AFTER `pepecoin_address`;
ALTER TABLE `users` ADD COLUMN `dragonx_address` VARCHAR(255) NULL AFTER `vertcoin_address`;
ALTER TABLE `users` ADD COLUMN `monero_address` VARCHAR(255) NULL AFTER `dragonx_address`;

-- 2) Commissions d'affiliation : une ligne par commande validée d'un
-- filleul, montant figé à la création (barème au moment du gain).
-- La contrainte UNIQUE sur purchase_id garantit l'idempotence du sync.
CREATE TABLE IF NOT EXISTS `affiliate_commissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referrer_id` INT UNSIGNED NOT NULL,
    `referred_id` INT UNSIGNED NOT NULL,
    `purchase_id` INT UNSIGNED NOT NULL,
    `amount_eur` DECIMAL(10,2) NOT NULL COMMENT 'Montant EUR de la commande validée',
    `pct` DECIMAL(5,2) NOT NULL COMMENT 'Pourcentage du barème appliqué',
    `commission_eur` DECIMAL(10,2) NOT NULL COMMENT 'Commission gagnée en EUR',
    `status` ENUM('earned','paid') NOT NULL DEFAULT 'earned',
    `payout_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `uk_purchase` (`purchase_id`),
    INDEX `idx_referrer` (`referrer_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_ac_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ac_referred` FOREIGN KEY (`referred_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Paiements d'affiliation effectués par l'admin aux parrains
CREATE TABLE IF NOT EXISTS `affiliate_payouts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `amount_eur` DECIMAL(10,2) NOT NULL,
    `method` VARCHAR(20) NOT NULL COMMENT 'paypal|xelis|kaspa|firo|verge|pepecoin|vertcoin|dragonx|monero',
    `address` VARCHAR(255) NOT NULL COMMENT 'Adresse/email renseigné par l''utilisateur au moment du paiement',
    `status` ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'paid',
    `admin_note` VARCHAR(255) NULL,
    `paid_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    CONSTRAINT `fk_ap_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Paramètres du module d'affiliation (ignorés s'ils existent déjà)
-- Barème par défaut conforme aux exemples : 5€→1% (0,05€),
-- 20€→5% (1€), 50€→6% (3€). Les pourcentages acceptent les décimales.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `type`, `description`) VALUES
('affiliate_enabled', '0', 'bool', 'Module d''affiliation : actif ou inactif (à la première activation, les commandes déjà validées de filleuls génèrent aussi leurs commissions)'),
('affiliate_tier1_min', '0', 'string', 'Barème palier 1 : montant minimum de commande (EUR)'),
('affiliate_tier1_pct', '1', 'string', 'Barème palier 1 : pourcentage de commission (ex : 1 pour 1%)'),
('affiliate_tier2_min', '20', 'string', 'Barème palier 2 : montant minimum de commande (EUR)'),
('affiliate_tier2_pct', '5', 'string', 'Barème palier 2 : pourcentage de commission (ex : 5 pour 5%)'),
('affiliate_tier3_min', '50', 'string', 'Barème palier 3 : montant minimum de commande (EUR)'),
('affiliate_tier3_pct', '6', 'string', 'Barème palier 3 : pourcentage de commission (ex : 6 pour 6%)'),
('affiliate_min_payout', '10', 'int', 'Solde minimum (EUR) avant qu''un paiement d''affiliation puisse être effectué');
