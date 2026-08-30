<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Service d'envoi d'emails
 * Utilise la fonction mail() native de PHP (compatible hébergement mutualisé,
 * aucune dépendance externe). Emails en texte brut uniquement.
 */
class MailService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Envoie un email en texte brut
     * @return bool true si mail() a accepté le message pour livraison
     */
    public function send(string $to, string $subject, string $body): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $fromEmail = $this->getSetting('admin_email', 'noreply@localhost');
        $siteName = $this->getSetting('site_name', 'Echange de Liens');

        // Encodage MIME du sujet si caractères non-ASCII
        $encodedSubject = $this->encodeHeader($subject);

        $headers = implode("\r\n", [
            'From: ' . $this->encodeHeader($siteName) . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: EchangeLiens/1.0',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ]);

        // Supprimer tout retour à la ligne des champs contrôlés (header injection)
        $to = str_replace(["\r", "\n"], '', $to);

        $sent = @mail($to, $encodedSubject, $body, $headers, '-f' . $fromEmail);

        if (!$sent) {
            error_log("[MailService] Échec envoi email à {$to} (sujet: {$subject})");
        }

        return $sent;
    }

    /**
     * Envoie l'email de réinitialisation de mot de passe
     */
    public function sendPasswordReset(string $to, string $resetUrl, string $siteName): bool
    {
        $subject = "Réinitialisation de votre mot de passe - {$siteName}";
        $body = "Bonjour,\r\n\r\n"
            . "Une demande de réinitialisation de mot de passe a été effectuée pour votre compte.\r\n\r\n"
            . "Cliquez sur le lien ci-dessous pour définir un nouveau mot de passe (valable 1 heure) :\r\n"
            . "{$resetUrl}\r\n\r\n"
            . "Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.\r\n\r\n"
            . "-- \r\n{$siteName}";

        return $this->send($to, $subject, $body);
    }

    /**
     * Envoie l'email de vérification d'adresse email
     */
    public function sendEmailVerification(string $to, string $verifyUrl, string $siteName): bool
    {
        $subject = "Vérifiez votre adresse email - {$siteName}";
        $body = "Bonjour,\r\n\r\n"
            . "Merci pour votre inscription sur {$siteName} !\r\n\r\n"
            . "Veuillez confirmer votre adresse email en cliquant sur le lien ci-dessous :\r\n"
            . "{$verifyUrl}\r\n\r\n"
            . "Si vous n'avez pas créé de compte, ignorez cet email.\r\n\r\n"
            . "-- \r\n{$siteName}";

        return $this->send($to, $subject, $body);
    }

    /**
     * Encode un en-tête en MIME si nécessaire (RFC 2047)
     */
    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function getSetting(string $key, string $default = ''): string
    {
        try {
            $result = $this->db->queryOne(
                "SELECT setting_value FROM settings WHERE setting_key = ?",
                [$key]
            );
            return $result['setting_value'] ?? $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
