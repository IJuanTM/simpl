<?php

namespace app\Controllers;

use app\Utils\Log;

/**
 * Handles email creation and delivery throughout the application.
 */
class MailController
{
    /**
     * Processes an HTML email template by replacing placeholder variables.
     *
     * @param string $name Template filename without extension
     * @param array $vars Variables to replace in format {{ key }}
     *
     * @return string|false Processed HTML email content or false if template not found
     */
    public static function template(string $name, array $vars): string|false
    {
        $templatePath = BASEDIR . "/app/Mails/$name.phtml";

        // Check if template exists
        if (!file_exists($templatePath)) {
            Log::warning("Email template not found: \"$name.phtml\"");
            return false;
        }

        // Extract variables for use in the template
        extract($vars, EXTR_SKIP);

        // Include the template file
        include $templatePath;

        // Get the buffered content and clean the buffer
        return ob_get_clean();
    }

    /**
     * Sends an email with automatic async handling when possible.
     *
     * @param string $senderName Sender's display name
     * @param string $to Recipient's email address
     * @param string $senderEmail Sender's email address
     * @param string $subject Email subject line
     * @param string $message HTML content of the email
     *
     * @return bool Success status (sent or queued)
     */
    public static function send(string $senderName, string $to, string $senderEmail, string $subject, string $message): bool
    {
        // Validate recipient email
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::warning("Invalid recipient email: \"$to\"");
            return false;
        }

        // Use async sending if possible
        if (function_exists('fastcgi_finish_request')) {
            $_SESSION['pending_email'] = compact('senderName', 'to', 'senderEmail', 'subject', 'message');
            register_shutdown_function([self::class, 'sendEmailAsync']);
            return true;
        }

        // Fall back to synchronous sending
        return self::sendEmail($senderName, $to, $senderEmail, $subject, $message);
    }

    /**
     * Performs the actual email sending using PHP's mail() function.
     *
     * @param string $senderName Sender's display name
     * @param string $to Recipient's email address
     * @param string $senderEmail Sender's email address
     * @param string $subject Email subject line
     * @param string $message HTML content of the email
     *
     * @return bool Success status
     */
    private static function sendEmail(string $senderName, string $to, string $senderEmail, string $subject, string $message): bool
    {
        // Set the email headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $senderName . ' <' . $senderEmail . '>',
            'Reply-To: ' . $senderEmail,
            'X-Mailer: PHP/' . PHP_VERSION
        ];

        // Send the email
        $result = mail($to, $subject, $message, implode("\r\n", $headers));

        // Log any errors
        if (!$result) Log::error("Failed to send email from \"$senderEmail\" to \"$to\": $subject");

        return $result;
    }

    /**
     * Processes queued emails after the HTTP response has been sent.
     */
    public static function sendEmailAsync(): void
    {
        if (isset($_SESSION['pending_email'])) {
            // Get the email data from the session
            $email = $_SESSION['pending_email'];

            // Send the email
            self::sendEmail(
                $email['senderName'],
                $email['to'],
                $email['senderEmail'],
                $email['subject'],
                $email['message']
            );

            // Remove the email data from the session
            unset($_SESSION['pending_email']);
        }
    }
}
