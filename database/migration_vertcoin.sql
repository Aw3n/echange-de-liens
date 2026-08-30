-- ============================================================
-- Migration : module de paiement Vertcoin (VTC)
-- À exécuter sur une base déjà installée (le schema.sql complet
-- contient déjà ces définitions pour les nouvelles installations)
-- ============================================================

-- 1) Ajoute 'vertcoin' aux méthodes de paiement
ALTER TABLE `purchases`
  MODIFY COLUMN `payment_method` ENUM('paypal', 'xelis', 'kaspa', 'firo', 'verge', 'monero', 'pepecoin', 'vertcoin') NOT NULL;

-- 2) Paramètres Vertcoin (ignorés s'ils existent déjà)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `type`, `description`) VALUES
('vertcoin_address', '', 'string', 'Adresse Vertcoin de réception des paiements'),
('vertcoin_api_url', 'https://blockbook.vertcoin.io/api/v2', 'string', 'URL de l''API Blockbook Vertcoin'),
('vertcoin_explorer_url', 'https://blockbook.vertcoin.io', 'string', 'URL de l''explorateur de blocs Vertcoin'),
('vertcoin_rate_eur', '0', 'string', 'Taux EUR/VTC manuel (0 = auto CoinGecko)'),
('vertcoin_payment_timeout', '45', 'int', 'Délai expiration paiement Vertcoin (minutes)'),
('vertcoin_min_confirmations', '6', 'int', 'Confirmations minimales pour Vertcoin (~15 min)');
