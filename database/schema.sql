-- ============================================================
-- Echange de Liens - Schema SQL complet
-- MySQL 8+ / InnoDB / UTF8MB4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- Table: roles
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`name`, `slug`, `description`) VALUES
('Administrateur', 'admin', 'Accès complet au système'),
('Modérateur', 'moderator', 'Gestion des liens et utilisateurs'),
('Membre', 'member', 'Utilisateur inscrit'),
('Visiteur', 'visitor', 'Utilisateur non inscrit');

-- -----------------------------------------------------------
-- Table: permissions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` INT UNSIGNED NOT NULL,
    `permission` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_role_id` (`role_id`),
    CONSTRAINT `fk_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: users
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` INT UNSIGNED NOT NULL DEFAULT 3,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `points` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `email_verification_token` VARCHAR(64) NULL,
    `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `two_factor_secret` VARCHAR(255) NULL,
    `api_token` VARCHAR(128) NULL UNIQUE,
    `referrer_id` INT UNSIGNED NULL,
    `referral_code` VARCHAR(16) NULL UNIQUE,
    `paypal_email` VARCHAR(255) NULL,
    `xelis_address` VARCHAR(255) NULL,
    `kaspa_address` VARCHAR(255) NULL,
    `firo_address` VARCHAR(255) NULL,
    `verge_address` VARCHAR(255) NULL,
    `pepecoin_address` VARCHAR(255) NULL,
    `vertcoin_address` VARCHAR(255) NULL,
    `dragonx_address` VARCHAR(255) NULL,
    `monero_address` VARCHAR(255) NULL,
    `last_login_at` DATETIME NULL,
    `last_login_ip` VARCHAR(45) NULL,
    `created_ip` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_role_id` (`role_id`),
    INDEX `idx_referrer_id` (`referrer_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_api_token` (`api_token`),
    INDEX `idx_created_ip` (`created_ip`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET DEFAULT,
    CONSTRAINT `fk_users_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: languages
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `languages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(5) NOT NULL UNIQUE,
    `name` VARCHAR(50) NOT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `languages` (`code`, `name`, `is_default`) VALUES
('fr', 'Français', 1),
('en', 'English', 0);

-- -----------------------------------------------------------
-- Table: links
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `links` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `url` VARCHAR(2048) NOT NULL,
    `title` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `points` INT NOT NULL DEFAULT 0,
    `total_visits` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_clicks` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_blacklisted` TINYINT(1) NOT NULL DEFAULT 0,
    `http_status` SMALLINT UNSIGNED NULL,
    `last_checked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_points` (`points` DESC),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_links_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: link_visits
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `link_visits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `link_id` INT UNSIGNED NOT NULL,
    `visitor_id` INT UNSIGNED NULL,
    `visitor_ip` VARCHAR(45) NOT NULL,
    `visitor_user_agent` TEXT NULL,
    `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_validated` TINYINT(1) NOT NULL DEFAULT 0,
    `points_earned` INT NOT NULL DEFAULT 0,
    `points_spent` INT NOT NULL DEFAULT 0,
    `session_token` VARCHAR(64) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_link_id` (`link_id`),
    INDEX `idx_visitor_id` (`visitor_id`),
    INDEX `idx_visitor_ip` (`visitor_ip`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_visits_link` FOREIGN KEY (`link_id`) REFERENCES `links`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_visits_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: points_history
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `points_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `amount` INT NOT NULL,
    `type` ENUM('earn_visit', 'spend_visit', 'bonus', 'purchase', 'referral', 'referral_visit', 'admin_adjust', 'banner_click', 'refund', 'guest_wallet', 'wheel', 'balloon') NOT NULL,
    `description` VARCHAR(255) NULL,
    `reference_id` INT UNSIGNED NULL,
    `reference_type` VARCHAR(50) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_points_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: guest_wallets
-- Cagnotte des visiteurs non connectés (jeton en cookie,
-- hash en base), transférée au compte lors de l'inscription
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
-- Table: sessions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(128) NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload` TEXT NOT NULL,
    `last_activity` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_last_activity` (`last_activity`),
    CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: failed_logins
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `failed_logins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_ip_address` (`ip_address`),
    INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: password_resets
-- Jetons de réinitialisation de mot de passe (hashés, expiration 1h)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_token_hash` (`token_hash`),
    INDEX `idx_email` (`email`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: api_tokens
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(128) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `last_used_at` DATETIME NULL,
    `expires_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_token` (`token`),
    INDEX `idx_user_id` (`user_id`),
    CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: notifications
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `data` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_read` (`is_read`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: reports
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reports` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `link_id` INT UNSIGNED NOT NULL,
    `reporter_id` INT UNSIGNED NULL,
    `reason` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('pending', 'reviewed', 'resolved', 'dismissed') NOT NULL DEFAULT 'pending',
    `admin_notes` TEXT NULL,
    `resolved_by` INT UNSIGNED NULL,
    `resolved_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_link_id` (`link_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_reports_link` FOREIGN KEY (`link_id`) REFERENCES `links`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: ads
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL,
    `title` VARCHAR(255) NOT NULL,
    `type` ENUM('banner', 'html') NOT NULL DEFAULT 'banner',
    `image_url` VARCHAR(2048) NULL,
    `target_url` VARCHAR(2048) NULL,
    `html_code` TEXT NULL,
    `position` ENUM('top', 'bottom', 'left', 'right', 'viewer_bottom', 'home_top', 'home_bottom') NOT NULL DEFAULT 'bottom',
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
    `points_assigned` INT NOT NULL DEFAULT 0,
    `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
    `width` INT UNSIGNED NOT NULL DEFAULT 468,
    `height` INT UNSIGNED NOT NULL DEFAULT 60,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_position` (`position`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_user_id` (`user_id`),
    CONSTRAINT `fk_ads_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: banner_clicks (anti-spam des clics bannières bonus)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `banner_clicks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ad_id` INT UNSIGNED NOT NULL,
    `visitor_id` INT UNSIGNED NULL,
    `visitor_ip` VARCHAR(45) NOT NULL,
    `points_earned` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ad_ip` (`ad_id`, `visitor_ip`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_banner_clicks_ad` FOREIGN KEY (`ad_id`) REFERENCES `ads`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_banner_clicks_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: pages (CMS)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(100) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `meta_title` VARCHAR(255) NULL,
    `meta_description` TEXT NULL,
    `language_code` VARCHAR(5) NOT NULL DEFAULT 'fr',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_slug_lang` (`slug`, `language_code`),
    INDEX `idx_slug` (`slug`),
    INDEX `idx_language` (`language_code`),
    CONSTRAINT `fk_pages_language` FOREIGN KEY (`language_code`) REFERENCES `languages`(`code`) ON DELETE SET DEFAULT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pages` (`slug`, `title`, `content`, `meta_title`, `meta_description`, `language_code`) VALUES
('faq', 'FAQ — Comment ça marche ?', '<h2>Tout savoir pour bien démarrer et gagner du trafic</h2><p>Vous vous demandez comment fonctionne notre plateforme d''échange de liens ? Cette FAQ vous explique tout, pas à pas, pour transformer vos visites en points et vos points en trafic réel vers votre site.</p><h3>1. Qu''est-ce que l''échange de liens ?</h3><p>C''est simple : vous visitez les sites des autres membres, et en échange votre propre site reçoit des visites. Chaque visite validée vous rapporte des points, et ce sont ces points qui propulsent votre lien en tête du classement.</p><h3>2. Comment gagner des points ?</h3><ul><li><strong>Visitez des liens</strong> : chaque visite validée (quelques secondes sur le site) crédite des points sur votre compte.</li><li><strong>Page Bonus</strong> : des bannières rémunérées vous offrent 5 points supplémentaires par visite de 15 secondes.</li><li><strong>Parrainage</strong> : partagez votre lien de parrainage et gagnez 1 point par visite unique (1 par IP toutes les 24 h), plus un bonus de 1000 points à l''inscription de chaque filleul.</li></ul><h3>3. Comment propulser mon lien dans le classement ?</h3><p>Ajoutez votre lien depuis votre tableau de bord, puis attribuez-lui une partie de vos points. Plus un lien accumule de points, plus il monte dans le classement et plus il est vu et visité par la communauté.</p><h3>4. Comment acheter des points VIP ?</h3><p>Depuis la section VIP de votre tableau de bord, via PayPal ou en cryptomonnaies. Les points sont crédités après confirmation du paiement par l''administrateur.</p><h3>5. Est-ce vraiment gratuit ?</h3><p>Oui, à 100 %. L''inscription et toutes les fonctionnalités de base sont gratuites. Les packs VIP, facultatifs, permettent simplement d''acheter des points supplémentaires et de soutenir le site.</p><h3>6. Est-ce sécurisé ?</h3><p>Oui. Les visites passent par une visionneuse intégrée, l''auto-visite est bloquée par notre système anti-fraude, les liens abusifs sont signalables puis blacklistés, et vos données sont protégées (mot de passe chiffré, connexion sécurisée).</p><h3>7. Comment commencer ?</h3><ol><li>Créez votre compte gratuit en 30 secondes.</li><li>Ajoutez votre premier lien.</li><li>Visitez quelques sites pour gagner vos premiers points.</li><li>Attribuez vos points à votre lien et regardez-le grimper !</li></ol><p><a href="register"><strong>Créer mon compte gratuitement et gagner mes premiers points →</strong></a></p>', 'FAQ — Comment fonctionne l''échange de liens ?', 'Comment fonctionne l''échange de liens ? Gagnez des points, boostez votre classement et parrainez vos amis : toutes les réponses pour bien démarrer.', 'fr'),
('faq', 'FAQ — How does it work?', '<h2>Everything you need to start earning traffic</h2><p>Wondering how our link exchange platform works? This FAQ walks you through everything, step by step, so you can turn your visits into points and your points into real traffic for your website.</p><h3>1. What is link exchange?</h3><p>It''s simple: you visit other members'' websites, and in return your own website receives visits. Every validated visit earns you points, and those points push your link to the top of the ranking.</p><h3>2. How do I earn points?</h3><ul><li><strong>Visit links</strong>: every validated visit (a few seconds on the site) credits points to your account.</li><li><strong>Bonus page</strong>: rewarded banners give you 5 extra points per 15-second visit.</li><li><strong>Referrals</strong>: share your referral link and earn 1 point per unique visit (1 per IP every 24 h), plus a 1000-point bonus when each referred user signs up.</li></ul><h3>3. How do I boost my link in the ranking?</h3><p>Add your link from your dashboard, then assign part of your points to it. The more points a link collects, the higher it climbs and the more it gets seen and visited by the community.</p><h3>4. How do I buy VIP points?</h3><p>From the VIP section of your dashboard, via PayPal or cryptocurrency. Points are credited after the payment is confirmed by the administrator.</p><h3>5. Is it really free?</h3><p>Yes, 100% free. Registration and all core features cost nothing. Optional VIP packs simply let you buy extra points and support the site.</p><h3>6. Is it safe?</h3><p>Yes. Visits run through an integrated viewer, self-visiting is blocked by our anti-fraud system, abusive links can be reported and are blacklisted, and your data is protected (hashed passwords, secure connection).</p><h3>7. How do I get started?</h3><ol><li>Create your free account in 30 seconds.</li><li>Add your first link.</li><li>Visit a few sites to earn your first points.</li><li>Assign points to your link and watch it climb!</li></ol><p><a href="register"><strong>Create my free account and earn my first points →</strong></a></p>', 'FAQ — How does link exchange work?', 'How does link exchange work? Earn points, climb the ranking and refer friends: every answer you need to get started.', 'en'),
('terms', 'Conditions d’utilisation', '<h2>Conditions d’utilisation</h2><p><em>Dernière mise à jour : 26 août 2026</em></p><p>Les présentes conditions d’utilisation (ci-après « les Conditions ») régissent l’accès et l’utilisation du site Echange de Liens (ci-après « le Service »), une plateforme communautaire d’échange de visites et de visibilité entre sites web. En créant un compte ou en utilisant le Service, vous acceptez sans réserve les présentes Conditions. Si vous n’êtes pas d’accord, nous vous invitons à ne pas utiliser le Service.</p><h3>1. Définitions</h3><ul><li><strong>Membre</strong> : toute personne inscrite disposant d’un compte.</li><li><strong>Lien</strong> : une URL soumise par un membre pour être présentée dans le classement.</li><li><strong>Point</strong> : unité virtuelle interne utilisée pour le classement et les fonctionnalités du Service.</li><li><strong>Bannière Bonus</strong> : emplacement publicitaire financé en points, affiché sur la page Bonus.</li></ul><h3>2. Description du Service</h3><p>Le Service permet à chaque membre de : visiter les sites des autres membres via une visionneuse intégrée et gagner des points ; attribuer ses points à ses liens pour améliorer leur position dans le classement ; soumettre des bannières rémunérées sur la page Bonus ; parrainer d’autres utilisateurs ; acheter des packs de points VIP via PayPal ou en cryptomonnaies.</p><h3>3. Compte et obligations</h3><ul><li>L’inscription est gratuite et ouverte à toute personne de 16 ans et plus.</li><li>Un seul compte par personne est autorisé ; les multi-comptes destinés à fausser l’économie ou le classement entraînent la suspension de l’ensemble des comptes concernés.</li><li>Vous êtes responsable de la confidentialité de vos identifiants et de toute activité réalisée depuis votre compte.</li><li>Les informations fournies (pseudo, e-mail, URL) doivent être exactes et licites.</li></ul><h3>4. Points : une monnaie virtuelle sans valeur monétaire</h3><ul><li>Les points sont une unité de mesure interne : ils ne constituent ni une monnaie électronique ni un droit à paiement, et ne sont ni remboursables ni convertibles en argent.</li><li>Les points obtenus par fraude (auto-visite, robots, VPN destinés à contourner les limites, clics artificiels) sont annulés et peuvent entraîner des sanctions.</li><li>En cas de refus ou de suppression d’une bannière par un administrateur, les points non consommés de la bannière sont automatiquement recrédités sur le compte du membre.</li><li>Le solde affiché est synchronisé avec le serveur à chaque page ; seul le solde enregistré en base de données fait foi.</li></ul><h3>5. Liens soumis : responsabilité et modération</h3><ul><li>Vous garantissez être autorisé à promouvoir les URL soumises et disposer de tous les droits nécessaires.</li><li>Sont interdits : contenus illégaux, haineux ou diffamatoires ; pornographie ; incitation à la violence ; logiciels malveillants, hameçonnage ou arnaques ; contenus portant atteinte aux droits de tiers ; pages vides ou en construction ; raccourcisseurs d’URL ou systèmes de redirection trompeurs.</li><li>Chaque lien est soumis à modération. L’administration peut refuser, dépublier ou blacklister un lien à tout moment et sans préavis. En cas de blacklistage, les points attribués au lien sont perdus, sauf décision contraire de l’administration.</li><li>Les visites s’effectuant via une visionneuse, vous acceptez que votre site soit affiché dans un cadre (iframe).</li></ul><h3>6. Achats VIP et cryptomonnaies</h3><ul><li>Les packs VIP sont facultatifs et sont crédités après confirmation du paiement par l’administrateur (PayPal) ou après vérification de la transaction sur la blockchain (cryptomonnaies).</li><li>Une commande abandonnée, non payée ou annulée avant confirmation est simplement clôturée : aucun point n’étant débité à la création de la commande, aucun remboursement n’est dû.</li><li>Une fois les points crédités, les achats ne sont pas remboursables, sauf obligation légale ou geste commercial de l’administration.</li><li>Les contreparties en cryptomonnaies sont calculées au taux affiché au moment de la commande ; aucune indexation ultérieure n’est appliquée.</li></ul><h3>7. Comportements interdits et anti-fraude</h3><p>Le Service met en œuvre un dispositif anti-fraude (limitation par adresse IP, durée minimale de visite, détection d’automates). Sont notamment interdits : l’auto-visite et la visite de ses propres bannières ; l’usage de robots, scripts, fermes de clics ou d’IA générative destinés à produire des visites artificielles ; toute tentative d’injection, d’exploration abusive ou de perturbation du Service.</p><h3>8. Sanctions</h3><p>En cas de manquement, l’administration peut, selon la gravité : avertir ; retirer ou blacklister des liens ; annuler des points ; suspendre ou supprimer le compte. La suppression pour fraude ne donne droit à aucun remboursement des packs VIP.</p><h3>9. Propriété intellectuelle</h3><p>Le Service (marque, design, code, bases de données) est protégé par le droit de la propriété intellectuelle. Vous conservez les droits sur vos contenus, mais concédez une licence d’affichage limitée à la promotion de vos liens dans le cadre du Service.</p><h3>10. Responsabilité</h3><ul><li>Le Service est fourni « en l’état », sans garantie de résultat ni de trafic minimal.</li><li>Nous ne sommes pas responsables des contenus, produits ou pratiques des sites membres visités : chaque membre reste seul responsable de son site.</li><li>Nous ne saurions être tenus responsables d’une indisponibilité temporaire, d’une perte de points liée à une fraude ou d’un fait de force majeure.</li></ul><h3>11. Données personnelles</h3><p>Le traitement de vos données est décrit dans notre <a href="page/privacy">Politique de confidentialité</a>, qui fait partie intégrante des présentes Conditions.</p><h3>12. Évolution des Conditions</h3><p>Les Conditions peuvent évoluer ; la date de mise à jour fait foi. Le maintien de l’utilisation du Service après publication vaut acceptation des nouvelles Conditions.</p><h3>13. Droit applicable et contact</h3><p>Les présentes Conditions sont régies par le droit français. Tout litige n’ayant pu être résolu à l’amiable sera porté devant les tribunaux compétents. Contact : via la page de contact ou l’adresse e-mail du site.</p>', 'Conditions d’utilisation de la plateforme d’échange de liens', 'Conditions d’utilisation du Service : compte, points, modération des liens, achats VIP, anti-fraude, responsabilité et données personnelles.', 'fr'),
('terms', 'Terms of Use', '<h2>Terms of Use</h2><p><em>Last updated: 26 August 2026</em></p><p>These terms of use (the “Terms”) govern access to and use of the Echange de Liens website (the “Service”), a community platform for exchanging visits and visibility between websites. By creating an account or using the Service, you accept these Terms without reservation. If you do not agree, please do not use the Service.</p><h3>1. Definitions</h3><ul><li><strong>Member</strong>: any registered person holding an account.</li><li><strong>Link</strong>: a URL submitted by a member to be listed in the ranking.</li><li><strong>Point</strong>: an internal virtual unit used for the ranking and the Service’s features.</li><li><strong>Bonus banner</strong>: a points-funded advertising slot displayed on the Bonus page.</li></ul><h3>2. Description of the Service</h3><p>The Service allows each member to: visit other members’ websites through an integrated viewer and earn points; assign points to their links to improve their ranking position; submit rewarded banners on the Bonus page; refer other users; purchase VIP point packs via PayPal or cryptocurrencies.</p><h3>3. Account and obligations</h3><ul><li>Registration is free and open to anyone aged 16 or over.</li><li>One account per person is allowed; multiple accounts intended to distort the economy or the ranking lead to the suspension of all related accounts.</li><li>You are responsible for the confidentiality of your credentials and for all activity originating from your account.</li><li>The information provided (username, e-mail, URL) must be accurate and lawful.</li></ul><h3>4. Points: a virtual currency with no monetary value</h3><ul><li>Points are an internal unit of measurement: they constitute neither e-money nor a right to payment, and are neither refundable nor convertible into money.</li><li>Points obtained by fraud (self-visits, bots, VPNs used to bypass limits, artificial clicks) are cancelled and may lead to sanctions.</li><li>If a banner is rejected or removed by an administrator, the banner’s unconsumed points are automatically credited back to the member’s account.</li><li>The displayed balance is synchronised with the server on every page; only the balance stored in the database is authoritative.</li></ul><h3>5. Submitted links: responsibility and moderation</h3><ul><li>You warrant that you are authorised to promote the submitted URLs and hold all necessary rights.</li><li>The following are prohibited: illegal, hateful or defamatory content; pornography; incitement to violence; malware, phishing or scams; content infringing third-party rights; empty or under-construction pages; URL shorteners or misleading redirect systems.</li><li>Every link is subject to moderation. The administration may reject, unpublish or blacklist a link at any time and without notice. Upon blacklisting, points assigned to the link are lost unless the administration decides otherwise.</li><li>As visits run through an integrated viewer, you accept that your site may be displayed within a frame (iframe).</li></ul><h3>6. VIP purchases and cryptocurrencies</h3><ul><li>VIP packs are optional and are credited after the payment is confirmed by the administrator (PayPal) or after the transaction is verified on the blockchain (cryptocurrencies).</li><li>An order that is abandoned, unpaid or cancelled before confirmation is simply closed: as no points are debited when the order is created, no refund is due.</li><li>Once points are credited, purchases are non-refundable, except where legally required or as a commercial gesture by the administration.</li><li>Cryptocurrency amounts are calculated at the rate displayed at the time of the order; no subsequent adjustment is applied.</li></ul><h3>7. Prohibited behaviour and anti-fraud</h3><p>The Service operates an anti-fraud system (IP-based limits, minimum visit duration, bot detection). The following are notably prohibited: self-visiting and visiting your own banners; using bots, scripts, click farms or generative AI to produce artificial visits; any attempt to inject, abusively crawl or disrupt the Service.</p><h3>8. Sanctions</h3><p>In case of breach, the administration may, depending on severity: issue a warning; remove or blacklist links; cancel points; suspend or delete the account. Deletion for fraud entitles you to no refund of VIP packs.</p><h3>9. Intellectual property</h3><p>The Service (brand, design, code, databases) is protected by intellectual property law. You retain the rights over your content but grant a limited display licence to promote your links within the Service.</p><h3>10. Liability</h3><ul><li>The Service is provided “as is”, without warranty of result or minimum traffic.</li><li>We are not responsible for the content, products or practices of member websites: each member remains solely responsible for their site.</li><li>We shall not be liable for temporary unavailability, loss of points resulting from fraud, or force majeure.</li></ul><h3>11. Personal data</h3><p>The processing of your data is described in our <a href="page/privacy">Privacy Policy</a>, which forms an integral part of these Terms.</p><h3>12. Changes to the Terms</h3><p>The Terms may evolve; the update date prevails. Continued use of the Service after publication constitutes acceptance of the new Terms.</p><h3>13. Governing law and contact</h3><p>These Terms are governed by French law. Any dispute that cannot be resolved amicably shall be brought before the competent courts. Contact: via the contact page or the site’s e-mail address.</p>', 'Terms of use of the link exchange platform', 'Terms of use of the Service: account, points, link moderation, VIP purchases, anti-fraud, liability and personal data.', 'en'),
('privacy', 'Politique de confidentialité', '<h2>Politique de confidentialité</h2><p><em>Dernière mise à jour : 26 août 2026</em></p><p>La présente politique décrit, conformément au Règlement général sur la protection des données (RGPD) et à la loi Informatique et Libertés, comment vos données personnelles sont traitées lorsque vous utilisez le Service.</p><h3>1. Responsable du traitement</h3><p>L’éditeur du site Echange de Liens, joignable via la page de contact ou l’adresse e-mail du site, est responsable du traitement de vos données personnelles.</p><h3>2. Données collectées</h3><ul><li><strong>Données de compte</strong> : pseudo, adresse e-mail, mot de passe (stocké uniquement sous forme hachée), lien de parrainage.</li><li><strong>Données de contenu</strong> : URL soumises, bannières, points attribués, historique des points et des commandes.</li><li><strong>Données techniques et anti-fraude</strong> : adresse IP, horodatage et durée des visites, navigateur, afin d’appliquer les limitations anti-fraude (visites uniques par IP et par période, détection d’automates).</li><li><strong>Données de paiement</strong> : aucune donnée bancaire n’est traitée par le Service. Les paiements PayPal sont traités par PayPal ; les paiements en cryptomonnaies sont vérifiés publiquement sur la blockchain (adresse de destination, identifiant de transaction).</li></ul><h3>3. Finalités et bases légales</h3><ul><li><strong>Exécution du contrat</strong> : gestion du compte, des points, des liens et des commandes.</li><li><strong>Intérêt légitime</strong> : sécurité du Service, prévention de la fraude et des abus, statistiques de fréquentation.</li><li><strong>Consentement</strong> : dépôt de cookies non essentiels, le cas échéant.</li><li><strong>Obligation légale</strong> : conservation de certaines données de facturation lorsque la loi l’exige.</li></ul><h3>4. Cookies et traceurs</h3><p>Le Service utilise uniquement des cookies techniques nécessaires (session de connexion, préférences de langue et de thème) et, le cas échéant, des mesures d’audience anonymisées. Aucun cookie publicitaire tiers n’est déposé par le Service. Les sites membres visités peuvent en revanche utiliser leurs propres cookies, sous leur seule responsabilité.</p><h3>5. Durées de conservation</h3><ul><li>Compte : pendant toute la durée d’utilisation, jusqu’à suppression ou demande d’effacement.</li><li>Journaux anti-fraude (IP, visites) : le temps strictement nécessaire aux limitations (de quelques minutes à 24 heures selon le dispositif), puis suppression ou agrégation.</li><li>Historique des points et commandes : durée de vie du compte.</li><li>Mots de passe : hachés, jamais conservés en clair.</li></ul><h3>6. Destinataires des données</h3><ul><li>L’hébergeur du Service, pour le stockage sécurisé.</li><li>Les prestataires de paiement (PayPal) pour les achats VIP, chacun agissant en qualité de responsable de traitement distinct.</li><li>Les réseaux blockchain pour les paiements en cryptomonnaies (données pseudonymes publiques par nature).</li><li>Aucune vente, location ou échange de vos données à des tiers à des fins publicitaires.</li></ul><h3>7. Vos droits</h3><p>Conformément aux articles 15 à 22 du RGPD, vous disposez des droits d’accès, de rectification, d’effacement, de limitation, de portabilité et d’opposition, ainsi que du droit de définir des directives relatives à vos données après votre décès. Vous pouvez les exercer via la page de contact ou l’adresse e-mail du site. Vous pouvez également introduire une réclamation auprès de la CNIL (cnil.fr) ou de l’autorité de contrôle de votre pays.</p><h3>8. Sécurité</h3><p>Mesures mises en œuvre : chiffrement HTTPS des échanges, hachage des mots de passe, protection CSRF des formulaires, limitation des tentatives et anti-fraude par adresse IP, sauvegardes régulières.</p><h3>9. Décisions automatisées et anti-fraude</h3><p>La validation des visites et des points repose sur un traitement automatisé (durée minimale de visite, unicité par adresse IP). Ces traitements n’emportent pas d’effet juridique significatif ; en cas de désaccord (points non crédités, compte suspendu), vous pouvez demander une révision humaine via la page de contact.</p><h3>10. Mineurs</h3><p>Le Service est réservé aux personnes de 16 ans et plus. Si vous avez moins de 16 ans, n’utilisez pas le Service sans l’autorisation d’un représentant légal.</p><h3>11. Transferts hors Union européenne</h3><p>Vos données sont hébergées dans l’Union européenne. En cas de recours à des prestataires situés hors UE, des garanties appropriées (clauses contractuelles types ou décisions d’adéquation) sont mises en place.</p><h3>12. Modification de la présente politique</h3><p>En cas d’évolution, la nouvelle version sera publiée sur cette page avec sa date de mise à jour ; pour les changements substantiels, un avis sera affiché sur le site.</p>', 'Politique de confidentialité — RGPD', 'Comment vos données personnelles sont collectées, utilisées et protégées sur la plateforme (RGPD) : finalités, durées, droits, cookies et sécurité.', 'fr'),
('privacy', 'Privacy Policy', '<h2>Privacy Policy</h2><p><em>Last updated: 26 August 2026</em></p><p>This policy describes, in accordance with the General Data Protection Regulation (GDPR), how your personal data is processed when you use the Service.</p><h3>1. Data controller</h3><p>The publisher of the Echange de Liens website, reachable via the contact page or the site’s e-mail address, is the data controller for your personal data.</p><h3>2. Data we collect</h3><ul><li><strong>Account data</strong>: username, e-mail address, password (stored only in hashed form), referral link.</li><li><strong>Content data</strong>: submitted URLs, banners, assigned points, points and order history.</li><li><strong>Technical and anti-fraud data</strong>: IP address, visit timestamps and duration, browser, in order to apply anti-fraud limits (unique visits per IP and per period, bot detection).</li><li><strong>Payment data</strong>: no banking data is processed by the Service. PayPal payments are processed by PayPal; cryptocurrency payments are verified publicly on the blockchain (destination address, transaction identifier).</li></ul><h3>3. Purposes and legal bases</h3><ul><li><strong>Performance of the contract</strong>: management of the account, points, links and orders.</li><li><strong>Legitimate interest</strong>: security of the Service, prevention of fraud and abuse, audience measurement.</li><li><strong>Consent</strong>: placement of non-essential cookies, where applicable.</li><li><strong>Legal obligation</strong>: retention of certain billing data where the law requires it.</li></ul><h3>4. Cookies and trackers</h3><p>The Service only uses strictly necessary technical cookies (login session, language and theme preferences) and, where applicable, anonymised audience measurement. No third-party advertising cookies are placed by the Service. Visited member websites may, however, use their own cookies, under their sole responsibility.</p><h3>5. Retention periods</h3><ul><li>Account: for the duration of use, until deletion or erasure request.</li><li>Anti-fraud logs (IP, visits): strictly as long as needed for the limits (from a few minutes to 24 hours depending on the mechanism), then deletion or aggregation.</li><li>Points and order history: lifetime of the account.</li><li>Passwords: hashed, never stored in plain text.</li></ul><h3>6. Recipients of the data</h3><ul><li>The Service’s hosting provider, for secure storage.</li><li>Payment providers (PayPal) for VIP purchases, each acting as a separate data controller.</li><li>Blockchain networks for cryptocurrency payments (pseudonymous data, public by nature).</li><li>No sale, rental or exchange of your data to third parties for advertising purposes.</li></ul><h3>7. Your rights</h3><p>In accordance with Articles 15 to 22 of the GDPR, you have the rights of access, rectification, erasure, restriction, portability and objection. You may exercise them via the contact page or the site’s e-mail address. You may also lodge a complaint with the CNIL (cnil.fr) or your local supervisory authority.</p><h3>8. Security</h3><p>Measures implemented: HTTPS encryption of traffic, hashed passwords, CSRF protection on forms, rate limiting and IP-based anti-fraud, regular backups.</p><h3>9. Automated decisions and anti-fraud</h3><p>Visit and point validation relies on automated processing (minimum visit duration, uniqueness per IP address). These processes do not produce significant legal effects; if you disagree (points not credited, suspended account), you may request human review via the contact page.</p><h3>10. Minors</h3><p>The Service is intended for persons aged 16 or over. If you are under 16, do not use the Service without the authorisation of a legal guardian.</p><h3>11. Transfers outside the European Union</h3><p>Your data is hosted within the European Union. Should providers located outside the EU be used, appropriate safeguards (standard contractual clauses or adequacy decisions) are put in place.</p><h3>12. Changes to this policy</h3><p>If this policy evolves, the new version will be published on this page with its update date; for material changes, a notice will be displayed on the site.</p>', 'Privacy Policy — GDPR', 'How your personal data is collected, used and protected on the platform (GDPR): purposes, retention, rights, cookies and security.', 'en');

-- -----------------------------------------------------------
-- Table: settings
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `type` ENUM('string', 'int', 'bool', 'json') NOT NULL DEFAULT 'string',
    `description` VARCHAR(255) NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`, `type`, `description`) VALUES
('site_name', 'Echange de Liens', 'string', 'Nom du site'),
('site_url', 'http://localhost', 'string', 'URL du site'),
('site_description', 'Système moderne d''échange de liens', 'string', 'Description du site'),
('site_theme', 'default', 'string', 'Thème visuel du site public (fichiers assets/css/theme-*.css)'),
('force_https', '0', 'bool', 'Redirige tout le trafic HTTP vers HTTPS + HSTS (exige un certificat SSL actif)'),
('partners_html', '', 'string', 'Code HTML des bannières/liens partenaires (réciprocité d''échange, ex : Echange Gagnant) affiché dans le footer public — vide = bloc masqué'),
('admin_email', 'admin@echange-lien.com', 'string', 'Email administrateur'),
('points_per_visit', '1', 'int', 'Points gagnés par visite'),
('points_per_banner_click', '5', 'int', 'Points gagnés par clic bannière bonus'),
('visit_duration_seconds', '5', 'int', 'Durée minimum d''une visite en secondes'),
('bonus_visit_duration_seconds', '15', 'int', 'Durée minimum visite bonus en secondes'),
('referral_points', '1000', 'int', 'Points bonus par filleul'),
('paypal_email', '', 'string', 'Adresse PayPal admin'),
('xelis_address', '', 'string', 'Adresse Xelis de réception des paiements'),
('xelis_daemon_url', '', 'string', 'URL du daemon RPC XELIS (ex: http://127.0.0.1:8080)'),
('xelis_index_url', 'https://index.xelis.io', 'string', 'URL de l’API Index XELIS (ex : https://index.xelis.io)'),
('xelis_wallet_url', '', 'string', 'URL du wallet RPC XELIS (ex: http://127.0.0.1:8081)'),
('xelis_wallet_user', '', 'string', 'Utilisateur RPC wallet XELIS'),
('xelis_wallet_password', '', 'string', 'Mot de passe RPC wallet XELIS'),
('xelis_confirmations', '5', 'int', 'Nombre de confirmations blockchain requises'),
('xelis_rate_eur', '0', 'string', 'Taux EUR/XEL manuel (0 = auto CoinGecko)'),
('xelis_payment_timeout', '30', 'int', 'Délai expiration paiement XELIS (minutes)'),
('kaspa_address', '', 'string', 'Adresse Kaspa de réception des paiements'),
('kaspa_api_url', 'https://api.kaspa.org', 'string', 'URL de l’API REST Kaspa (indexer)'),
('kaspa_rpc_url', '', 'string', 'Optionnel (mode pro) : URL d’un nœud/indexer Kaspa privé testé en secours de l’API REST (ex : http://127.0.0.1:16110)'),
('kaspa_explorer_url', 'https://explorer.kaspa.org', 'string', 'URL de l''explorateur de blocs Kaspa'),
('kaspa_rate_eur', '0', 'string', 'Taux EUR/KAS manuel (0 = auto CoinGecko)'),
('kaspa_payment_timeout', '30', 'int', 'Délai expiration paiement Kaspa (minutes)'),
('kaspa_min_confirmations', '1', 'int', 'Seuil de confirmation du BlockDAG : TX d’abord acceptée dans la chaîne virtuelle (POST /transactions/acceptance) puis ce seuil de score DAA (10 blocs/s, 1 = quasi-instantané)'),
('firo_address', '', 'string', 'Adresse Firo de réception des paiements'),
('firo_api_url', 'https://explorer.firo.org/insight-api-firo/api', 'string', 'URL de l’API Insight Firo AVEC le préfixe /api'),
('firo_explorer_url', 'https://explorer.firo.org', 'string', 'URL de l''explorateur de blocs Firo'),
('firo_rate_eur', '0', 'string', 'Taux EUR/FIRO manuel (0 = auto CoinGecko)'),
('firo_payment_timeout', '45', 'int', 'Délai expiration paiement Firo (minutes, stocké en base à la création de la commande)'),
('firo_min_confirmations', '2', 'int', 'Confirmations minimales pour Firo (~20 min)'),
('verge_address', '', 'string', 'Adresse Verge de réception des paiements'),
('verge_api_url', 'https://xvg-blockbook.nownodes.io/api/v2', 'string', 'URL de l’API Blockbook Verge (interface machine, PHP)'),
('verge_api_key', '', 'string', 'Clé API NOWNodes optionnelle, côté PHP uniquement (jamais exposée au navigateur)'),
('verge_explorer_url', 'https://verge-blockchain.info', 'string', 'URL de l’explorateur public Verge (interface utilisateur)'),
('verge_rate_eur', '0', 'string', 'Taux EUR/XVG manuel (0 = auto CoinGecko)'),
('verge_payment_timeout', '30', 'int', 'Délai expiration paiement Verge (minutes)'),
('verge_min_confirmations', '6', 'int', 'Confirmations minimales pour Verge (blocs ~30s, ~3 min — estimation non garantie)'),
('pepecoin_address', '', 'string', 'Adresse Pepecoin MAINNET de réception (préfixe « P ») — validée avant enregistrement'),
('pepecoin_api_url', 'https://www.pepeblocks.com/api/v2', 'string', 'URL de l''API Blockbook Pepecoin'),
('pepecoin_explorer_url', 'https://www.pepeblocks.com', 'string', 'URL de l''explorateur de blocs Pepecoin'),
('pepecoin_rate_eur', '0', 'string', 'Taux EUR/PEPE manuel (0 = auto CoinGecko)'),
('pepecoin_payment_timeout', '30', 'int', 'Délai expiration paiement Pepecoin (minutes)'),
('pepecoin_min_confirmations', '6', 'int', 'Confirmations minimales pour Pepecoin (blocs ~1 min, ~6 min — estimation non garantie). Paiement détecté ≠ confirmé.'),
('vertcoin_address', '', 'string', 'Adresse Vertcoin MAINNET de réception (préfixe « V ») — validée avant enregistrement'),
('vertcoin_api_url', 'https://blockbook.vertcoin.io/api/v2', 'string', 'URL de l''API Blockbook Vertcoin'),
('vertcoin_explorer_url', 'https://blockbook.vertcoin.io', 'string', 'URL de l''explorateur de blocs Vertcoin'),
('vertcoin_rate_eur', '0', 'string', 'Taux EUR/VTC manuel (0 = auto CoinGecko)'),
('vertcoin_payment_timeout', '45', 'int', 'Délai expiration paiement Vertcoin (minutes)'),
('vertcoin_min_confirmations', '6', 'int', 'Confirmations minimales pour Vertcoin (blocs ~2,5 min, ~15 min — estimation non garantie). Paiement détecté ≠ confirmé.'),
('dragonx_address', '', 'string', 'Adresse DragonX transparente MAINNET de réception (préfixe « R ») — validée avant enregistrement'),
('dragonx_api_url', 'https://explorer.dragonx.is/api', 'string', 'URL de l''API de l''explorateur DragonX'),
('dragonx_explorer_url', 'https://explorer.dragonx.is', 'string', 'URL de l''explorateur de blocs DragonX'),
('dragonx_rate_eur', '0', 'string', 'Taux EUR/DRGX manuel (0 = auto CoinGecko)'),
('dragonx_payment_timeout', '30', 'int', 'Délai expiration paiement DragonX (minutes)'),
('dragonx_min_confirmations', '10', 'int', 'Confirmations minimales pour DragonX (blocs ~34 s, ~6 min — estimation non garantie). Paiement détecté ≠ confirmé.'),
('monero_address', '', 'string', 'Adresse Monero de réception des paiements'),
('monero_explorer_url', 'https://xmrchain.net', 'string', 'URL de l''explorateur Monero'),
('monero_rate_eur', '0', 'string', 'Taux EUR/XMR manuel (0 = auto CoinGecko)'),
('monero_payment_timeout', '60', 'int', 'Délai expiration paiement Monero (minutes)'),
('monero_min_confirmations', '10', 'int', 'Confirmations minimales pour Monero (~20 min)'),
('monero_wallet_rpc_url', '', 'string', 'URL du wallet RPC Monero (optionnel, ex: http://127.0.0.1:28088/json_rpc)'),
('monero_wallet_rpc_user', '', 'string', 'Utilisateur RPC wallet Monero'),
('monero_wallet_rpc_password', '', 'string', 'Mot de passe RPC wallet Monero'),
('vip_points_1', '1000', 'int', 'Pack VIP 1 - Points'),
('vip_price_1', '5.00', 'string', 'Pack VIP 1 - Prix EUR'),
('vip_points_2', '5000', 'int', 'Pack VIP 2 - Points'),
('vip_price_2', '20.00', 'string', 'Pack VIP 2 - Prix EUR'),
('vip_points_3', '15000', 'int', 'Pack VIP 3 - Points'),
('vip_price_3', '50.00', 'string', 'Pack VIP 3 - Prix EUR'),
('captcha_enabled', '1', 'bool', 'CAPTCHA activé'),
('maintenance_mode', '0', 'bool', 'Mode maintenance'),
('default_language', 'fr', 'string', 'Langue par défaut'),
('max_links_per_user', '0', 'int', 'Liens max par utilisateur (0=illimité)'),
('ads_enabled', '1', 'bool', 'Publicités activées'),
('registration_enabled', '1', 'bool', 'Inscription activée'),
('referral_visit_points', '1', 'int', 'Points par visite IP unique sur lien parrainage (24h)'),
-- Module d'affiliation (barème par défaut : 5€→1%, 20€→5%, 50€→6%)
('affiliate_enabled', '0', 'bool', 'Module d''affiliation : actif ou inactif (à la première activation, les commandes déjà validées de filleuls génèrent aussi leurs commissions)'),
('affiliate_tier1_min', '0', 'string', 'Barème palier 1 : montant minimum de commande (EUR)'),
('affiliate_tier1_pct', '1', 'string', 'Barème palier 1 : pourcentage de commission (ex : 1 pour 1%)'),
('affiliate_tier2_min', '20', 'string', 'Barème palier 2 : montant minimum de commande (EUR)'),
('affiliate_tier2_pct', '5', 'string', 'Barème palier 2 : pourcentage de commission (ex : 5 pour 5%)'),
('affiliate_tier3_min', '50', 'string', 'Barème palier 3 : montant minimum de commande (EUR)'),
('affiliate_tier3_pct', '6', 'string', 'Barème palier 3 : pourcentage de commission (ex : 6 pour 6%)'),
('affiliate_min_payout', '10', 'int', 'Solde minimum (EUR) avant qu''un paiement d''affiliation puisse être effectué'),
-- Roue de la fortune (installée et activée par défaut)
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
('wheel_weight9', '1', 'int', 'Roue de la fortune : poids (probabilité) du segment 9'),
-- Balloon Pop-Up (module optionnel, config réservé au panneau admin)
('balloon_enabled', '1', 'bool', 'Balloon Pop-Up : module activé ou désactivé'),
('balloon_every_visits', '15', 'int', 'Balloon Pop-Up : visites validées nécessaires pour déclencher les ballons'),
('balloon_points_min', '1', 'int', 'Balloon Pop-Up : points minimum gagnés par ballon éclaté'),
('balloon_points_max', '15', 'int', 'Balloon Pop-Up : points maximum gagnés par ballon éclaté'),
-- SEO Settings
('seo_site_title', 'Echange de Liens - Gagnez des visiteurs pour votre site', 'string', 'SEO : Titre principal du site (balise title)'),
('seo_meta_description', 'Échange de liens gratuit pour augmenter le trafic de votre site web. Gagnez des visiteurs en partageant vos liens. Système de points simple et efficace.', 'string', 'SEO : Meta description (150-160 caractères)'),
('seo_meta_keywords', 'échange liens, trafic site web, visiteurs gratuits, référencement, backlinks, augmenter trafic', 'string', 'SEO : Meta keywords (séparés par virgule)'),
('seo_og_image', '', 'string', 'SEO : URL image OpenGraph (partage réseaux sociaux)'),
('seo_twitter_handle', '', 'string', 'SEO : Compte Twitter (@pseudo)'),
('seo_google_verification', '', 'string', 'SEO : Code vérification Google Search Console'),
('seo_bing_verification', '', 'string', 'SEO : Code vérification Bing Webmaster'),
('seo_ga_tracking_id', '', 'string', 'SEO : Google Analytics ID (ex: G-XXXXXXX)'),
('seo_canonical_domain', '', 'string', 'SEO : Domaine canonique préféré (sans http)'),
('seo_index_links', '0', 'bool', 'SEO : Indexer les liens externes (nofollow désactivé)'),
('seo_sitemap_enabled', '1', 'bool', 'SEO : Activer le sitemap.xml dynamique');

-- -----------------------------------------------------------
-- Table: logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `level` ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
    `channel` VARCHAR(50) NOT NULL DEFAULT 'app',
    `message` TEXT NOT NULL,
    `context` JSON NULL,
    `user_id` INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_level` (`level`),
    INDEX `idx_channel` (`channel`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: statistics
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `statistics` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stat_date` DATE NOT NULL,
    `total_visits` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_registrations` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_links_added` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_points_distributed` INT NOT NULL DEFAULT 0,
    `total_points_spent` INT NOT NULL DEFAULT 0,
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: purchases
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchases` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `amount_eur` DECIMAL(10,2) NOT NULL,
    `amount_xelis` DECIMAL(20,8) NULL,
    `crypto_rate_eur` DECIMAL(18,8) NULL,
    `payment_method` ENUM('paypal', 'xelis', 'kaspa', 'firo', 'verge', 'monero', 'pepecoin', 'vertcoin', 'dragonx') NOT NULL,
    `points_purchased` INT NOT NULL,
    `status` ENUM('pending', 'confirmed', 'completed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
    `transaction_id` VARCHAR(255) NULL,
    `payment_address` VARCHAR(512) NULL,
    `user_xelis_address` VARCHAR(255) NULL,
    `expires_at` DATETIME NULL,
    `admin_notes` TEXT NULL,
    `confirmed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_purchases_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: referral_visits
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `referral_visits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referrer_id` INT UNSIGNED NOT NULL,
    `visitor_ip` VARCHAR(45) NOT NULL,
    `visit_date` DATE NOT NULL,
    `visited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_referrer_ip_date` (`referrer_id`, `visitor_ip`, `visit_date`),
    INDEX `idx_referrer_id` (`referrer_id`),
    CONSTRAINT `fk_referral_visits_user` FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: affiliate_commissions (module d'affiliation)
-- Une ligne par commande validée d'un filleul ; montant et barème
-- figés à la création. UNIQUE purchase_id = sync idempotent.
-- -----------------------------------------------------------
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

-- -----------------------------------------------------------
-- Table: affiliate_payouts (paiements d'affiliation par l'admin)
-- -----------------------------------------------------------
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

-- -----------------------------------------------------------
-- Table: wheel_spins (module Roue de la fortune)
-- -----------------------------------------------------------
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

-- -----------------------------------------------------------
-- Table: blacklist
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blacklist` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `url_pattern` VARCHAR(2048) NOT NULL,
    `reason` VARCHAR(255) NULL,
    `added_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_added_by` (`added_by`),
    CONSTRAINT `fk_blacklist_admin` FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Table: cache (prix XELIS, données temporaires)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cache` (
    `cache_key` VARCHAR(100) NOT NULL,
    `cache_value` TEXT NOT NULL,
    `cache_expires` DATETIME NOT NULL,
    PRIMARY KEY (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
