<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\GuestWalletService;

/**
 * Contrôleur d'authentification
 */
class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Page de connexion
     */
    public function loginForm(Request $request, Response $response): never
    {
        if (Session::isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $this->view('auth.login', [
            'title' => 'Connexion',
            'page' => 'login',
        ]);
    }

    /**
     * Traitement de la connexion
     */
    public function login(Request $request, Response $response): never
    {
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/login', 'Jeton CSRF invalide.');
        }

        $email = trim($request->post('email', ''));
        $password = $request->post('password', '');

        if (empty($email) || empty($password)) {
            $this->redirectWithError('/login', 'Veuillez remplir tous les champs.');
        }

        $user = $this->authService->login($email, $password, $request->getIp(), $request->getUserAgent());

        if (!$user) {
            $this->redirectWithError('/login', 'Email ou mot de passe incorrect.');
        }

        $this->redirectWithSuccess('/dashboard', 'Connexion réussie ! Bienvenue ' . $user['username']);
    }

    /**
     * Page d'inscription
     */
    public function registerForm(Request $request, Response $response): never
    {
        if (Session::isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $ref = (string) $request->get('ref', '');

        // Lien de parrainage : résolution côté serveur (ID ou code),
        // +1 point par IP unique/24h + mémorisation session/cookie
        if ($ref !== '') {
            $this->authService->handleReferral($ref, $request->getIp());
        }

        $this->generateCaptcha();

        $this->view('auth.register', [
            'title' => 'Inscription',
            'page' => 'register',
            'ref' => Session::get('referral_ref'),
            'captcha_question' => Session::get('captcha_question'),
            // Cagnotte visiteur en attente : affichée pour motiver
            // l'inscription, transférée automatiquement au compte créé
            'guest_points' => (new GuestWalletService())->balanceFromCookie(),
        ]);
    }

    /**
     * Traitement de l'inscription
     */
    public function register(Request $request, Response $response): never
    {
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/register', 'Jeton CSRF invalide.');
        }

        // Anti-bot : captcha en premier (usage unique), pour empêcher
        // toute sonde automatisée des champs suivants
        if (!$this->verifyCaptcha($request)) {
            $this->generateCaptcha();
            $this->redirectWithError('/register', 'Réponse incorrecte au captcha.');
        }

        $username = trim($request->post('username', ''));
        $email = trim($request->post('email', ''));
        $password = $request->post('password', '');
        $passwordConfirm = $request->post('password_confirm', '');

        // Parrain résolu côté serveur (jamais fait confiance au champ POST brut) :
        // champ caché → session → cookie 30 jours
        $referrerId = $this->authService->resolveReferrer((string) $request->post('referrer_id', ''));
        if ($referrerId === null) {
            $sessionRef = (int) Session::get('referral_ref', 0);
            $referrerId = $sessionRef > 0 ? $sessionRef : null;
        }
        if ($referrerId === null) {
            $referrerId = $this->authService->referrerFromCookie();
        }

        // Validation
        $errors = [];

        if (empty($username) || strlen($username) < 3) {
            $errors[] = "Le nom d'utilisateur doit contenir au moins 3 caractères.";
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
            $errors[] = "Le nom d'utilisateur ne peut contenir que des lettres, chiffres, tirets et points (3 à 30 caractères).";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Adresse email invalide.";
        }

        if (strlen($password) < 8) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
        }

        if ($password !== $passwordConfirm) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }

        if ($this->authService->emailExists($email)) {
            $errors[] = "Cet email est déjà utilisé.";
        }

        if ($this->authService->usernameExists($username)) {
            $errors[] = "Ce nom d'utilisateur est déjà pris.";
        }

        if (!empty($errors)) {
            Session::setFlash('error', implode("\n", $errors));
            $this->redirect('/register');
        }

        // Anti-fraude : limiter le nombre d'inscriptions par IP sur 24h
        $userRepo = new UserRepository();
        $maxRegistrations = (int) Config::get('app.security.max_registrations_per_ip', 3);
        if ($userRepo->countRegistrationsByIp($request->getIp()) >= $maxRegistrations) {
            $this->redirectWithError('/register', 'Trop d\'inscriptions depuis votre adresse IP. Veuillez réessayer plus tard.');
        }

        // Création du compte
        $userId = $this->authService->register([
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ], $referrerId, $request->getIp());

        // Parrainage consommé : nettoyer session et cookie d'affiliation
        Session::remove('referral_ref');
        setcookie('ref_code', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']);

        // Cagnotte visiteur : transfert des points accumulés en tant que
        // visiteur non connecté vers le nouveau compte (cookie consommé)
        $guestPoints = 0;
        try {
            $guestPoints = (new GuestWalletService())->claimForUser($userId, $request->getIp());
        } catch (\Throwable $e) {
            // Cagnotte indisponible : l'inscription aboutit quand même
        }

        // Connexion automatique
        $this->authService->login($email, $password, $request->getIp(), $request->getUserAgent());

        $message = "Inscription réussie ! Un email de vérification vous a été envoyé.";
        if ($guestPoints > 0) {
            $message .= " Vos {$guestPoints} point(s) accumulés en tant que visiteur ont été crédités !";
        }
        $this->redirectWithSuccess('/dashboard', $message);
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request, Response $response): never
    {
        $this->authService->logout();
        $this->redirectWithSuccess('/', 'Déconnexion réussie.');
    }

    /**
     * Formulaire « mot de passe oublié »
     */
    public function forgotForm(Request $request, Response $response): never
    {
        if (Session::isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $this->generateCaptcha();

        $this->view('auth.forgot_password', [
            'title' => 'Mot de passe oublié',
            'page' => 'forgot-password',
            'captcha_question' => Session::get('captcha_question'),
        ]);
    }

    /**
     * Traitement « mot de passe oublié »
     * Anti-énumération : message identique que l'email existe ou non
     */
    public function forgot(Request $request, Response $response): never
    {
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/forgot-password', 'Jeton CSRF invalide.');
        }

        if (!$this->verifyCaptcha($request)) {
            $this->generateCaptcha();
            $this->redirectWithError('/forgot-password', 'Réponse incorrecte au captcha.');
        }

        $email = trim($request->post('email', ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->authService->requestPasswordReset($email, $request->getIp());
        }

        $this->redirectWithSuccess(
            '/login',
            "Si un compte existe avec cet email, un lien de réinitialisation vient d'être envoyé."
        );
    }

    /**
     * Formulaire de définition du nouveau mot de passe
     */
    public function resetForm(Request $request, Response $response): never
    {
        $token = (string) $request->get('token', '');

        if (!$this->authService->findValidPasswordReset($token)) {
            $this->redirectWithError('/forgot-password', 'Lien de réinitialisation invalide ou expiré.');
        }

        $this->view('auth.reset_password', [
            'title' => 'Nouveau mot de passe',
            'page' => 'reset-password',
            'token' => $token,
        ]);
    }

    /**
     * Traitement de la réinitialisation du mot de passe
     */
    public function reset(Request $request, Response $response): never
    {
        if (!$this->verifyCsrf($request)) {
            $this->redirectWithError('/forgot-password', 'Jeton CSRF invalide.');
        }

        $token = (string) $request->post('token', '');
        $password = $request->post('password', '');
        $passwordConfirm = $request->post('password_confirm', '');

        if (strlen($password) < 8) {
            $this->redirectWithError('/reset-password?token=' . urlencode($token), 'Le mot de passe doit contenir au moins 8 caractères.');
        }

        if ($password !== $passwordConfirm) {
            $this->redirectWithError('/reset-password?token=' . urlencode($token), 'Les mots de passe ne correspondent pas.');
        }

        if ($this->authService->resetPassword($token, $password)) {
            $this->redirectWithSuccess('/login', 'Mot de passe modifié ! Vous pouvez maintenant vous connecter.');
        }

        $this->redirectWithError('/forgot-password', 'Lien de réinitialisation invalide ou expiré.');
    }

    /**
     * Vérification de l'adresse email (lien reçu par email)
     */
    public function verifyEmail(Request $request, Response $response): never
    {
        $token = (string) $request->get('token', '');

        if ($this->authService->verifyEmail($token)) {
            $this->redirectWithSuccess('/login', 'Adresse email vérifiée avec succès !');
        }

        $this->redirectWithError('/login', 'Lien de vérification invalide ou déjà utilisé.');
    }

    /**
     * Génère un captcha mathématique simple (stocké en session)
     */
    private function generateCaptcha(): void
    {
        if (!Config::get('app.security.captcha_enabled', false)) {
            return;
        }

        $a = random_int(1, 9);
        $b = random_int(1, 9);
        Session::set('captcha_answer', (string) ($a + $b));
        Session::set('captcha_question', "Combien font {$a} + {$b} ?");
    }

    /**
     * Vérifie la réponse au captcha (usage unique)
     */
    private function verifyCaptcha(Request $request): bool
    {
        if (!Config::get('app.security.captcha_enabled', false)) {
            return true;
        }

        $answer = trim($request->post('captcha', ''));
        $expected = (string) Session::get('captcha_answer', '');

        // Usage unique : le captcha est consommé même en cas d'échec
        Session::remove('captcha_answer');
        Session::remove('captcha_question');

        return $expected !== '' && hash_equals($expected, $answer);
    }
}
