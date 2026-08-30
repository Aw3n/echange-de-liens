<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Gestionnaire de réponses HTTP
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';

    /**
     * Définit le code de statut HTTP
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Ajoute un en-tête HTTP
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Envoie une réponse JSON
     */
    public function json(mixed $data, int $status = 200): never
    {
        $this->statusCode = $status;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->send_headers();
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit($status);
    }

    /**
     * Redirige vers une URL
     */
    public function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header("Location: {$url}");
        exit($status);
    }

    /**
     * Envoie une réponse HTML
     */
    public function html(string $content, int $status = 200): never
    {
        $this->statusCode = $status;
        $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        $this->send_headers();
        echo $content;
        exit($status);
    }

    /**
     * Envoie les en-têtes HTTP
     */
    private function send_headers(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    /**
     * Définit les en-têtes de sécurité
     */
    public function setSecurityHeaders(): self
    {
        $this->setHeader('X-Content-Type-Options', 'nosniff');
        // Pas de X-Frame-Options global : le site doit rester intégrable dans
        // les visionneuses d'échange de liens. La politique d'iframe est gérée
        // par App::applyFramePolicy() (restrictive uniquement pages sensibles).
        $this->setHeader('X-XSS-Protection', '1; mode=block');
        $this->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        return $this;
    }
}
