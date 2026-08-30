-- ============================================================
-- Migration : module « Balloon Pop-Up »
-- Module optionnel, configurable uniquement via le panneau admin
-- (n'apparaît pas dans le menu utilisateur).
-- Toutes les balloon_every_visits visites validées, 1 à 3 ballons
-- flottent à l'écran ; chaque ballon éclaté rapporte un nombre
-- aléatoire de points (balloon_points_min..balloon_points_max).
-- Toutes les requêtes sont idempotentes.
-- ============================================================

-- 1) Le type « balloon » dans l'historique des points
ALTER TABLE `points_history` MODIFY COLUMN `type` ENUM('earn_visit', 'spend_visit', 'bonus', 'purchase', 'referral', 'referral_visit', 'admin_adjust', 'banner_click', 'refund', 'guest_wallet', 'wheel', 'balloon') NOT NULL;

-- 2) Paramètres du module (ignorés s'ils existent déjà)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `type`, `description`) VALUES
('balloon_enabled', '1', 'bool', 'Balloon Pop-Up : module activé ou désactivé'),
('balloon_every_visits', '15', 'int', 'Balloon Pop-Up : nombre de visites validées déclenchant l''apparition des ballons'),
('balloon_points_min', '1', 'int', 'Balloon Pop-Up : points minimum gagnés par ballon éclaté'),
('balloon_points_max', '15', 'int', 'Balloon Pop-Up : points maximum gagnés par ballon éclaté');
