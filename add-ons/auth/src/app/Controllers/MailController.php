<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Utils\Log;

/**
 * Provides email templating and delivery helpers.
 *
 * When FastCGI is available, outgoing mail is queued and sent after the
 * response via register_shutdown_function. Otherwise delivery is synchronous.
 *
 * Note: template() extracts variables directly into the include scope,
 * so watch for variable name collisions in templates.
 */
class MailController
{
    /**
     * Render an email template file and return the resulting HTML.
     * Variables in $vars are extracted into the template scope and may be
     * referenced using the template's variable names.
     *
     * @param string              $name Template filename without extension (e.g. 'verification')
     * @param array<string,mixed> $vars Associative array of variables to expose to the template
     *
     * @return string|false Rendered HTML content or false when template not found
     */
    public static function template(string $name, array $vars): string|false
    {
        $templatePath = BASEDIR . "/app/Mails/$name.phtml";

        if (!file_exists($templatePath)) {
            Log::warning("Email template not found: \"$name.phtml\"");
            return false;
        }

        // Capture only this template's output in its own buffer, leaving the outer request buffer intact.
        ob_start();
        extract($vars, EXTR_SKIP);
        include $templatePath;
        return ob_get_clean();
    }

    /**
     * Send an email. When running under FastCGI the payload is queued and
     * delivery is deferred until after the response has been sent to the
     * client (via register_shutdown_function). Otherwise sending is
     * performed synchronously.
     *
     * @param string $senderName  Display name of the sender
     * @param string $to          Recipient email address
     * @param string $senderEmail Sender email address
     * @param string $subject     Email subject
     * @param string $message     HTML message body
     *
     * @return bool True when the message was sent or queued successfully
     */
    public static function send(string $senderName, string $to, string $senderEmail, string $subject, string $message): bool
    {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::warning("Invalid recipient email: \"$to\"");
            return false;
        }

        if (function_exists('fastcgi_finish_request')) {
            $pending = SessionController::get('pending_emails') ?? [];
            $pending[] = compact('senderName', 'to', 'senderEmail', 'subject', 'message');
            SessionController::set('pending_emails', $pending);

            static $registered = false;
            if (!$registered) {
                register_shutdown_function([self::class, 'sendEmailAsync']);
                $registered = true;
            }

            return true;
        }

        return self::sendEmail($senderName, $to, $senderEmail, $subject, $message);
    }

    /**
     * Perform the actual email delivery using PHP's mail() function.
     * Constructs typical HTML headers and logs any failure.
     *
     * @param string $senderName  Display name of the sender
     * @param string $to          Recipient email address
     * @param string $senderEmail Sender email address
     * @param string $subject     Email subject
     * @param string $message     HTML message body
     *
     * @return bool True on success, false on failure
     */
    private static function sendEmail(string $senderName, string $to, string $senderEmail, string $subject, string $message): bool
    {
        // Strip CR/LF from untrusted header values to prevent header injection (e.g. via the contact form name field)
        $senderName = str_replace(["\r", "\n"], '', $senderName);
        $senderEmail = str_replace(["\r", "\n"], '', $senderEmail);
        $subject = str_replace(["\r", "\n"], '', $subject);

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $senderName . ' <' . $senderEmail . '>',
            'Reply-To: ' . $senderEmail,
            'X-Mailer: PHP/' . PHP_VERSION
        ];

        $result = mail($to, $subject, $message, implode("\r\n", $headers));

        if (!$result) Log::error("Failed to send email from \"$senderEmail\" to \"$to\": $subject");

        return $result;
    }

    /**
     * Send emails queued during request handling. Registered via register_shutdown_function.
     *
     * @return void
     */
    public static function sendEmailAsync(): void
    {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

        $pending = SessionController::get('pending_emails');
        if (!empty($pending)) {
            SessionController::remove('pending_emails');
            foreach ($pending as $email) {
                self::sendEmail($email['senderName'], $email['to'], $email['senderEmail'], $email['subject'], $email['message']);
            }
        }
    }
}
