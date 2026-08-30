-- ============================================================
-- Migration : module de paiement DragonX (DRGX)
-- À exécuter sur une base déjà installée (le schema.sql complet
-- contient déjà ces définitions pour les nouvelles installations)
-- ============================================================

-- 1) Ajoute 'dragonx' aux méthodes de paiement
ALTER TABLE `purchases`
  MODIFY COLUMN `payment_method` ENUM('paypal', 'xelis', 'kaspa', 'firo', 'verge', 'monero', 'pepecoin', 'vertcoin', 'dragonx') NOT NULL;

-- 2) Paramètres DragonX (ignorés s'ils existent déjà)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `type`, `description`) VALUES
('dragonx_address', '', 'string', 'Adresse DragonX transparente de réception des paiements'),
('dragonx_api_url', 'https://explorer.dragonx.is/api', 'string', 'URL de l''API de l''explorateur DragonX'),
('dragonx_explorer_url', 'https://explorer.dragonx.is', 'string', 'URL de l''explorateur de blocs DragonX'),
('dragonx_rate_eur', '0', 'string', 'Taux EUR/DRGX manuel (0 = auto CoinGecko)'),
('dragonx_payment_timeout', '30', 'int', 'Délai expiration paiement DragonX (minutes)'),
('dragonx_min_confirmations', '10', 'int', 'Confirmations minimales pour DragonX (~6 min)');
