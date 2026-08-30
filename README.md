# 🔗 Echange de Liens — CMS open-source de netlinking & SEO

**CMS open-source développé par [Awen Crypto](https://x.com/Awen__crypto)** — plateforme bilingue (FR/EN) d'échange de liens et de visites conçue pour **renforcer votre SEO et votre netlinking** : les membres visitent les sites des autres et gagnent des points, puis utilisent ces points pour propulser leurs propres liens dans le classement et obtenir des visites réelles.

Paiements acceptés : **PayPal** + **9 cryptomonnaies** (XELIS, Kaspa, Firo, Verge, Monero, Pepecoin, Vertcoin, DragonX).

---

## 🇫🇷 Version française

### ✨ Fonctionnalités principales

**SEO & netlinking**
- Échange de visites avec classement en temps réel : vos liens gagnent en visibilité à chaque visite validée.
- Liens de parrainage partageables (accueil, inscription, FAQ) pour diffuser vos URLs.
- SEO intégré : sitemap dynamique, OpenGraph, canonical, vérifications Google/Bing, meta par page, pages bilingues.

**Membres & visiteurs**
- Inscription gratuite : captcha, vérification d'email, mot de passe **Argon2id**, anti-fraude par IP.
- Visionneuse sécurisée (iframe sandbox, durée configurable, compteur en direct, bouton « Ouvrir le site »).
- Cagnotte visiteur : les non-connectés accumulent des points, récupérés à l'inscription.
- Roue de la fortune (1 tour gratuit / 3 h) et Balloon Pop-Up (ballons à éclater après 15 visites).
- Parrainage : 1 000 points par filleul inscrit + points par visite IP unique.

**Monétisation**
- Packs VIP payables par **PayPal** ou **9 cryptos** (vérification blockchain automatique, taux EUR figé à la commande).
- Module d'affiliation : commissions sur 3 paliers, paiements aux parrains.
- Bannières publicitaires (image/HTML) sur 7 positions, dont la visionneuse.

**Administration**
- Panneau complet : utilisateurs, liens, bannières, commandes VIP, affiliation, statistiques, pages CMS.
- Tous les réglages pilotables depuis l'admin (points, durées, crypto, VIP, roue, ballons, SEO…).
- Thèmes CSS auto-détectés : `default`, `aurora`, `neon`, `sunset`.
- API REST publique et authentifiée.

### 🧰 Prérequis

| Composant | Version minimale |
|---|---|
| PHP | **8.2** (`pdo_mysql`, `mbstring`, `json`, `openssl`) |
| MySQL / MariaDB | **8+** (InnoDB, `utf8mb4`) |
| Hébergement | Mutualisé classique (Apache + mod_rewrite), **sans Composer ni SSH** |

### 🚀 Installation de zéro

1. **Cloner ou télécharger** ce dépôt, puis l'**uploader par FTP** sur votre hébergement, **sans** `config/database.php` (généré par l'installateur).
2. Ouvrir `https://votre-domaine/install.php` et suivre les **4 étapes** :
   1. *Prérequis* — diagnostic PHP, extensions, permissions (`install.php?debug` pour détails) ;
   2. *Base de données* — créée automatiquement (ou pré-créée chez l'hébergeur) ;
   3. *Site & admin* — nom du site, URL auto-détectée, compte administrateur ;
   4. *Installation* — import du schéma complet, vérifications, écriture de la config.
3. Se connecter avec l'email admin, puis configurer SEO, adresses crypto de réception et PayPal dans **Admin → Paramètres**.
4. Supprimer `public/install.php` après installation (recommandé).

> `database/schema.sql` contient toutes les tables et données initiales : une installation neuve est immédiatement complète.
> Mises à jour d'un site existant : uploader les fichiers modifiés puis ouvrir `install.php?update` (migrations idempotentes).

---

## 🇬🇧 English version

### ✨ Key features

**SEO & netlinking**
- Link-exchange platform with real-time ranking: your links gain visibility with every validated visit.
- Shareable referral links (home, registration, FAQ) to spread your URLs.
- Built-in SEO: dynamic sitemap, OpenGraph, canonical, Google/Bing verification tags, per-page meta, bilingual pages.

**Members & visitors**
- Free registration: captcha, email verification, **Argon2id** password hashing, IP-based anti-fraud.
- Secure viewer (sandboxed iframe, configurable duration, live counter, “Open the site” button).
- Guest wallet: non-logged visitors accumulate points, recovered upon registration.
- Wheel of Fortune (1 free spin / 3 h) and Balloon Pop-Up (golden balloons after 15 visits).
- Referral program: 1,000 points per referred signup + points per unique IP visit.

**Monetization**
- VIP packs payable via **PayPal** or **9 cryptocurrencies** (automatic on-chain verification, EUR rate locked at order).
- Affiliate module: 3-tier commissions, payouts to referrers.
- Ad banners (image/HTML) on 7 positions, including the viewer.

**Administration**
- Full admin panel: users, links, banners, VIP orders, affiliates, stats, CMS pages.
- Every setting manageable from the admin area (points, durations, crypto, VIP, wheel, balloons, SEO…).
- Auto-detected CSS themes: `default`, `aurora`, `neon`, `sunset`.
- Public and authenticated REST API.

### 🧰 Requirements

| Component | Minimum version |
|---|---|
| PHP | **8.2** (`pdo_mysql`, `mbstring`, `json`, `openssl`) |
| MySQL / MariaDB | **8+** (InnoDB, `utf8mb4`) |
| Hosting | Standard shared hosting (Apache + mod_rewrite), **no Composer or SSH needed** |

### 🚀 Install from scratch

1. **Clone or download** this repository, then **upload it via FTP** to your hosting, **excluding** `config/database.php` (generated by the installer).
2. Open `https://your-domain/install.php` and follow the **4 steps**:
   1. *Requirements* — PHP, extensions, permissions check (`install.php?debug` for details);
   2. *Database* — created automatically (or pre-created at your host);
   3. *Site & admin* — site name, auto-detected URL, admin account;
   4. *Installation* — full schema import, checks, config write.
3. Log in with the admin email, then configure SEO, crypto receiving addresses and PayPal in **Admin → Settings**.
4. Delete `public/install.php` after installation (recommended).

> `database/schema.sql` contains every table and initial dataset: a fresh install is immediately complete.
> Updating an existing site: upload the changed files, then open `install.php?update` (idempotent migrations).

---

## 🔐 Sécurité / Security

- Mots de passe **Argon2id**, jetons hashés **SHA-256**, **CSRF** sur tous les formulaires, prepared statements réels, limitations par IP, iframe sandbox, vérification on-chain des paiements crypto (aucun double crédit).
- **Argon2id** passwords, **SHA-256**-hashed tokens, **CSRF** on every form, real prepared statements, IP rate limiting, sandboxed iframe, on-chain crypto payment verification (no double credit).

## 📄 Licence / License

CMS **open-source** publié tel quel pour lancer votre propre plateforme d'échange de trafic — licence MIT (voir `LICENSE`).
Open-source CMS provided as-is to launch your own traffic-exchange platform — MIT license (see `LICENSE`).

---

Développé avec ❤️ par **[Awen Crypto](https://x.com/Awen__crypto)**
Developed with ❤️ by **[Awen Crypto](https://x.com/Awen__crypto)**
