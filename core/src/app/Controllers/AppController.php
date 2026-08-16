<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Utils\Log;

/**
 * The AppController class serves as the main application controller.
 * It initializes essential controllers needed for core functionality
 * and provides utility methods for application-specific operations.
 */
class AppController
{
    public function __construct()
    {
        // Auto-injects a CSRF hidden field into every POST form at flush time.
        ob_start(self::injectCsrf(...));

        new SessionController();
        new AlertController();
        new AliasController();
        new PageController();
    }

    /**
     * Validate the CSRF token submitted with a POST request.
     * Accepts the token from the csrf_token POST field or the X-CSRF-Token header
     * (the latter lets fetch-based API calls authenticate without a form body).
     *
     * @return bool
     */
    public static function validateCsrf(): bool
    {
        $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return is_string($submitted) && hash_equals(self::csrfToken(), $submitted);
    }

    /**
     * Retrieve the CSRF token from the session, generating and storing one if it does not exist.
     *
     * @return string The CSRF token.
     */
    private static function csrfToken(): string
    {
        if (!SessionController::has('csrf_token')) SessionController::set('csrf_token', bin2hex(random_bytes(32)));
        return SessionController::get('csrf_token');
    }

    /**
     * Build a <meta> tag carrying the CSRF token so client-side scripts can send it
     * with fetch requests via the X-CSRF-Token header.
     *
     * @return string
     */
    public static function csrfMeta(): string
    {
        return '<meta name="csrf-token" content="' . self::sanitize(self::csrfToken()) . '">';
    }

    /**
     * HTML-escapes and trims a string for safe output.
     *
     * @param string $data
     *
     * @return string
     */
    public static function sanitize(string $data): string
    {
        return htmlspecialchars(trim($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Shared flags for security-sensitive cookies: site-wide path, Strict same-site, httponly,
     * and secure conditional on HTTPS. Callers merge in their own 'expires' or 'lifetime' key.
     *
     * @return array{path: string, secure: bool, httponly: bool, samesite: string}
     */
    public static function secureCookieFlags(): array
    {
        return [
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }

    /**
     * Retrieves the contents of an SVG file by its name.
     *
     * @param string $name
     *
     * @return string The SVG file contents as a string, or a boolean false if the file does not exist.
     */
    public static function svg(string $name): bool|string
    {
        $file = BASEDIR . "/public/img/svg/$name.svg";

        if (!file_exists($file)) {
            $message = "SVG \"$name\" not found";
            Log::warning($message);
            return DEV ? $message : "<!-- $message -->";
        }

        return file_get_contents($file);
    }

    /**
     * Output buffer callback that injects a CSRF hidden input field into every POST form.
     *
     * @param string $output The raw HTML output buffer.
     *
     * @return string The HTML with CSRF hidden fields injected after each POST form's opening tag.
     */
    private static function injectCsrf(string $output): string
    {
        return preg_replace_callback(
            '/<form\b[^>]*\bmethod\s*=\s*["\']post["\'][^>]*>/i',
            static fn(array $m): string => $m[0] . '<input type="hidden" name="csrf_token" value="' . self::sanitize(self::csrfToken()) . '">',
            $output
        );
    }
}
