-- ============================================================
-- Migration : module de paiement Pepecoin (PEPE)
-- À exécuter sur une base déjà installée (le schema.sql complet
-- contient déjà ces définitions pour les nouvelles installations)
-- ============================================================

-- 1) Ajoute 'pepecoin' aux méthodes de paiement
ALTER TABLE `purchases`
  MODIFY COLUMN `payment_method` ENUM('paypal', 'xelis', 'kaspa', 'firo', 'verge', 'monero', 'pepecoin') NOT NULL;

-- 2) Paramètres Pepecoin (ignorés s'ils existent déjà)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `type`, `description`) VALUES
('pepecoin_address', '', 'string', 'Adresse Pepecoin de réception des paiements'),
('pepecoin_api_url', 'https://www.pepeblocks.com/api/v2', 'string', 'URL de l''API Blockbook Pepecoin'),
('pepecoin_explorer_url', 'https://www.pepeblocks.com', 'string', 'URL de l''explorateur de blocs Pepecoin'),
('pepecoin_rate_eur', '0', 'string', 'Taux EUR/PEPE manuel (0 = auto CoinGecko)'),
('pepecoin_payment_timeout', '30', 'int', 'Délai expiration paiement Pepecoin (minutes)'),
('pepecoin_min_confirmations', '6', 'int', 'Confirmations minimales pour Pepecoin (~6 min)');
