-- =============================================================
-- Migration : taux de change figé par commande (crypto_rate_eur)
-- =============================================================
-- But : chaque commande crypto conserve le taux EUR utilisé au
-- moment de sa création, pour que la facture reste cohérente
-- même si le cours varie ensuite.
--
-- À exécuter UNE SEULE FOIS dans phpMyAdmin (onglet SQL).
-- (ALTER TABLE ... ADD COLUMN n'est pas idempotent : si la colonne
-- existe déjà, l'erreur 1060 « Duplicate column name » est normale
-- et peut être ignorée.)
-- =============================================================

ALTER TABLE `purchases`
    ADD COLUMN `crypto_rate_eur` DECIMAL(18,8) NULL AFTER `amount_xelis`;

-- =============================================================
-- Bonus : remplacer l'ancien défaut de verge_explorer_url
-- (l'hôte NOWNodes est une API machine, pas un explorateur public)
-- Ne modifie que la valeur par défaut ; une valeur personnalisée
-- saisie par l'admin est préservée. Cette instruction est idempotente.
-- =============================================================

UPDATE `settings`
SET `setting_value` = 'https://verge-blockchain.info'
WHERE `setting_key` = 'verge_explorer_url'
  AND `setting_value` = 'https://xvg-blockbook.nownodes.io';
