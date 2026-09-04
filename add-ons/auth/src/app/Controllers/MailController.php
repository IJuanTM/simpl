<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Utils\Log;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

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
    private static array $pendingEmails = [];

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
     * @param string      $senderName  Display name of the sender
     * @param string      $to          Recipient email address
     * @param string      $senderEmail Sender/envelope-from email address
     * @param string      $subject     Email subject
     * @param string      $message     HTML message body
     * @param string|null $replyTo     Reply-To address, defaults to $senderEmail when null
     *
     * @return bool True when the message was sent or queued successfully
     */
    public static function send(string $senderName, string $to, string $senderEmail, string $subject, string $message, ?string $replyTo = null): bool
    {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Invalid recipient email: "{to}"', ['to' => $to]);
            return false;
        }

        $replyTo ??= $senderEmail;

        if (function_exists('fastcgi_finish_request')) {
            self::$pendingEmails[] = compact('senderName', 'to', 'senderEmail', 'subject', 'message', 'replyTo');

            static $registered = false;
            if (!$registered) {
                register_shutdown_function([self::class, 'sendEmailAsync']);
                $registered = true;
            }

            return true;
        }

        return self::sendEmail($senderName, $to, $senderEmail, $subject, $message, $replyTo);
    }

    /**
     * Perform the actual email delivery over SMTP via PHPMailer, using SMTP_CONFIG's
     * development or production settings depending on the DEV flag.
     *
     * @param string $senderName  Display name of the sender
     * @param string $to          Recipient email address
     * @param string $senderEmail Sender/envelope-from email address
     * @param string $subject     Email subject
     * @param string $message     HTML message body
     * @param string $replyTo     Reply-To address
     *
     * @return bool True on success, false on failure
     */
    private static function sendEmail(string $senderName, string $to, string $senderEmail, string $subject, string $message, string $replyTo): bool
    {
        $config = SMTP_CONFIG[DEV ? 'development' : 'production'];

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->Port = $config['port'];
            $mail->SMTPAuth = $config['smtp_auth'];
            $mail->SMTPSecure = $config['encryption'] ?? '';

            if ($config['smtp_auth']) {
                $mail->Username = $config['username'];
                $mail->Password = $config['password'];
            }

            $mail->setFrom($senderEmail, $senderName);
            $mail->addReplyTo($replyTo);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->send();
            return true;
        } catch (PHPMailerException) {
            Log::error("Failed to send email from \"$senderEmail\" to \"$to\": {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Send emails queued during request handling. Registered via register_shutdown_function.
     *
     * @return void
     */
    public static function sendEmailAsync(): void
    {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

        foreach (self::$pendingEmails as $email) {
            self::sendEmail($email['senderName'], $email['to'], $email['senderEmail'], $email['subject'], $email['message'], $email['replyTo']);
        }

        self::$pendingEmails = [];
    }
}
