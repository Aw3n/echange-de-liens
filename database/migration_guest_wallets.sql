-- Migration : cagnotte visiteur (guest_wallets)
-- Les visiteurs non connectés accumulent leurs points de visites
-- dans un portefeuille (jeton en cookie, hash en base), transféré
-- automatiquement vers leur compte lors de l'inscription.
-- Idempotente : applicable sans risque sur un site déjà installé.

-- -----------------------------------------------------------
-- Table: guest_wallets
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `guest_wallets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token_hash` VARCHAR(64) NOT NULL UNIQUE,
    `points` INT NOT NULL DEFAULT 0,
    `last_ip` VARCHAR(45) NULL,
    `claimed_user_id` INT UNSIGNED NULL,
    `claimed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_guest_wallets_ip` (`last_ip`),
    INDEX `idx_guest_wallets_claimed` (`claimed_user_id`),
    CONSTRAINT `fk_guest_wallets_user` FOREIGN KEY (`claimed_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- points_history : ajout du type 'guest_wallet'
-- (MODIFY COLUMN avec la liste complète de l'ENUM = idempotent)
-- -----------------------------------------------------------
ALTER TABLE `points_history` MODIFY COLUMN `type`
    ENUM('earn_visit', 'spend_visit', 'bonus', 'purchase', 'referral', 'referral_visit', 'admin_adjust', 'banner_click', 'refund', 'guest_wallet') NOT NULL;
