-- ============================================================
-- Migration : paramètres crypto ajoutés après les premières
-- installations (mode pro Kaspa + clé API Verge)
-- À exécuter sur une base déjà installée (le schema.sql complet
-- contient déjà ces définitions pour les nouvelles installations).
-- Toutes les instructions sont idempotentes (INSERT IGNORE).
-- ============================================================

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `type`, `description`) VALUES
('kaspa_rpc_url', '', 'string', 'Optionnel (mode pro) : URL d''un nœud/indexer Kaspa privé testé en secours de l''API REST (ex : http://127.0.0.1:16110)'),
('verge_api_key', '', 'string', 'Clé API NOWNodes optionnelle, côté PHP uniquement (jamais exposée au navigateur)');
