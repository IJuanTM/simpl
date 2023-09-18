<?php

namespace app\Controllers;

/**
 * The MailController class is used for sending emails. It contains methods for sending emails to the site owner and to the user.
 */
class MailController
{
    /**
     * This method is called when the user submits the contact form on the contact page.
     *
     * @param string $from
     * @param string $sender
     * @param string $subject
     * @param string $message
     *
     * @return void
     */
    public static function contact(string $from, string $sender, string $subject, string $message): void
    {
        // Get the template from the views/parts/mails folder
        $contents = self::template('contact', [
            'date' => date('Y-m-d'),
            'time' => date('H:i'),
            'contents' => nl2br($message),
            'from' => $from
        ]);

        // Send the message
        if (self::send($from, SITE_MAIL, $sender, $subject, $contents)) {
            // Redirect the user to the redirect page
            PageController::redirect(REDIRECT);

            // Set the success message
            AppController::alert('Your message has been sent!', ['success', 'global'], 4);
        } else FormController::alert('An error occurred while sending your message!', 'danger', 'contact', 2);
    }

    /**
     * This method is for creating an email based on a template from the views/parts/mails folder.
     * It replaces the variables in the template with the values from the array.
     *
     * @param string $name
     * @param array $vars
     *
     * @return string
     */
    private static function template(string $name, array $vars): string
    {
        // Get the template from the views/parts/mails folder
        $template = file_get_contents(BASEDIR . "/views/parts/mails/$name.phtml");

        // Replace the variables in the template with the values from the array
        foreach ($vars as $key => $value) $template = str_replace("{{ $$key }}", $value, $template);

        // Return the template with the replaced variables
        return $template;
    }

    /**
     * This method is used for sending emails.
     *
     * @param string $from
     * @param string $to
     * @param string $sender
     * @param string $subject
     * @param string $message
     *
     * @return bool
     */
    public static function send(string $from, string $to, string $sender, string $subject, string $message): bool
    {
        // Set the headers
        $headers = [
            'From' => "$from <$sender>",
            'X-Mailer' => 'PHP/' . phpversion(),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8'
        ];

        // Send the email
        if (!@mail($to, $subject, $message, $headers)) {
            // Log the error
            LogController::log("An error occurred while sending an email from \"$sender\" to \"$to\" with subject \"$subject\"", 'error');

            // Return false
            return false;
        }
        return true;
    }

    /**
     * This method is used for sending a verification mail to the user after registration.
     *
     * @param int $id
     * @param string $to
     * @param string $code
     *
     * @return void
     */
    public static function verification(int $id, string $to, string $code): void
    {
        // Get the template from the views/parts/mails folder
        $contents = self::template('verification', [
            'code' => $code,
            'link' => PageController::url("verify-account/$id/$code")
        ]);

        // Send the message
        self::send(APP_NAME, $to, NO_REPLY_MAIL, 'Verify account', $contents);
    }

    /**
     * This method is used for sending a password reset mail to the user.
     *
     * @param int $id
     * @param string $to
     * @param string $token
     *
     * @return void
     */
    public static function reset(int $id, string $to, string $token): void
    {
        // Get the template from the views/parts/mails folder
        $contents = self::template('reset', [
            'link' => PageController::url("reset-password/$id/$token")
        ]);

        // Send the message
        self::send(APP_NAME, $to, NO_REPLY_MAIL, 'Reset password', $contents);
    }
}
