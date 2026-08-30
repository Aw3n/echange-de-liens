-- ============================================================
-- Migration : module « Roue de la fortune » (wheel of fortune)
-- Installé ET activé par défaut (wheel_enabled = 1).
-- Un tour gratuit toutes les wheel_interval_hours (défaut 3 h),
-- gain de points selon 9 segments pondérés configurables.
-- Toutes les requêtes sont idempotentes.
-- ============================================================

-- 1) Historique des tours (une ligne par spin, sert au cooldown)
CREATE TABLE IF NOT EXISTS `wheel_spins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `points` INT NOT NULL COMMENT 'Points gagnés sur ce tour',
    `segment` TINYINT UNSIGNED NOT NULL COMMENT 'Index du segment sorti (0-8)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ws_user` (`user_id`),
    CONSTRAINT `fk_ws_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Le type « wheel » dans l'historique des points
ALTER TABLE `points_history` MODIFY COLUMN `type` ENUM('earn_visit', 'spend_visit', 'bonus', 'purchase', 'referral', 'referral_visit', 'admin_adjust', 'banner_click', 'refund', 'guest_wallet', 'wheel') NOT NULL;

-- 3) Paramètres du module (ignorés s'ils existent déjà)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `type`, `description`) VALUES
('wheel_enabled', '1', 'bool', 'Roue de la fortune : module activé ou désactivé'),
('wheel_interval_hours', '3', 'int', 'Roue de la fortune : délai minimum (en heures) entre deux tours par utilisateur'),
('wheel_url', '', 'string', 'Roue de la fortune : lien ouvert dans un nouvel onglet quand un utilisateur lance la roue (vide = aucun lien)'),
('wheel_seg1', '10', 'int', 'Roue de la fortune : points du segment 1'),
('wheel_seg2', '20', 'int', 'Roue de la fortune : points du segment 2'),
('wheel_seg3', '44', 'int', 'Roue de la fortune : points du segment 3'),
('wheel_seg4', '88', 'int', 'Roue de la fortune : points du segment 4'),
('wheel_seg5', '100', 'int', 'Roue de la fortune : points du segment 5'),
('wheel_seg6', '250', 'int', 'Roue de la fortune : points du segment 6'),
('wheel_seg7', '300', 'int', 'Roue de la fortune : points du segment 7'),
('wheel_seg8', '1000', 'int', 'Roue de la fortune : points du segment 8'),
('wheel_seg9', '2000', 'int', 'Roue de la fortune : points du segment 9'),
('wheel_weight1', '30', 'int', 'Roue de la fortune : poids (probabilité) du segment 1'),
('wheel_weight2', '24', 'int', 'Roue de la fortune : poids (probabilité) du segment 2'),
('wheel_weight3', '18', 'int', 'Roue de la fortune : poids (probabilité) du segment 3'),
('wheel_weight4', '14', 'int', 'Roue de la fortune : poids (probabilité) du segment 4'),
('wheel_weight5', '10', 'int', 'Roue de la fortune : poids (probabilité) du segment 5'),
('wheel_weight6', '7', 'int', 'Roue de la fortune : poids (probabilité) du segment 6'),
('wheel_weight7', '5', 'int', 'Roue de la fortune : poids (probabilité) du segment 7'),
('wheel_weight8', '2', 'int', 'Roue de la fortune : poids (probabilité) du segment 8'),
('wheel_weight9', '1', 'int', 'Roue de la fortune : poids (probabilité) du segment 9');
