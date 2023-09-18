<?php

namespace app\Pages;

use app\Controllers\{AppController, FormController, MailController};

/**
 * The ContactPage class is the controller for the contact page.
 * It checks if all inputs are entered and sends an email to the site owner and the user.
 */
class ContactPage
{
    public function __construct()
    {
        // Check if the contact form is submitted
        if (isset($_POST['submit'])) {
            // Check if all the required fields are entered
            if (empty($_POST['name'])) {
                FormController::alert('Please enter your name!', 'warning', 'contact');
                return;
            }
            if (empty($_POST['email'])) {
                FormController::alert('Please enter your email!', 'warning', 'contact');
                return;
            }
            if (empty($_POST['subject'])) {
                FormController::alert('Please enter a subject!', 'warning', 'contact');
                return;
            }
            if (empty($_POST['message'])) {
                FormController::alert('Please enter a message!', 'warning', 'contact');
                return;
            }

            // Check if the values entered in fields are not too long
            if (strlen($_POST['name']) > 100) {
                $_POST['name'] = '';
                FormController::alert('The input of the name field is too long!', 'warning', 'contact');
                return;
            }
            if (strlen($_POST['email']) > 100) {
                $_POST['email'] = '';
                FormController::alert('The input of the email field is too long!', 'warning', 'contact');
                return;
            }
            if (strlen($_POST['subject']) > 100) {
                $_POST['subject'] = '';
                FormController::alert('The input of the subject field is too long!', 'warning', 'contact');
                return;
            }
            if (strlen($_POST['message']) > 1000) {
                $_POST['message'] = '';
                FormController::alert('The input of the message field is too long!', 'warning', 'contact');
                return;
            }

            // Check if the email is valid
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $_POST['email'] = '';
                FormController::alert('The entered email is not valid!', 'warning', 'contact');
                return;
            }

            // Send the contact email
            MailController::contact(
                AppController::sanitize($_POST['name']),
                AppController::sanitize($_POST['email']),
                AppController::sanitize($_POST['subject']),
                AppController::sanitize($_POST['message'])
            );
        }
    }
}
