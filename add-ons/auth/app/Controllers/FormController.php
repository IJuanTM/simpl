<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Enums\AlertType;

/**
 * FormController (auth add-on)
 *
 * Utilities for validating form input and queuing alert markup for display.
 * Clears invalid $_POST fields on validation failure to prevent reuse.
 */
class FormController
{
    public static array $alerts = [];

    /**
     * Validate a single form field in $_POST against a set of rules.
     * The method mutates $_POST[$field] to an empty string on validation
     * failures to avoid re-using invalid values later in request handling.
     *
     * Supported rules (examples): 'required', 'minLength', 'maxLength', 'minValue', 'maxValue', 'type' (number|email).
     *
     * @param string $field Field name expected in $_POST
     * @param array<string,mixed> $rules Validation rules and parameters
     *
     * @return bool True when validation passes, false on first failure
     */
    public static function validate(string $field, array $rules): bool
    {
        // Convert field identifier to a user-friendly label for messages.
        $fieldName = str_replace('-', ' ', $field);

        // Required check: field must be set and not empty.
        if (empty($_POST[$field]) && in_array('required', $rules, true)) {
            static::addAlert("Please enter an input in the $fieldName field!", AlertType::WARNING);
            return false;
        }

        // Minimum length for strings.
        if (in_array('minLength', $rules, true) && strlen($_POST[$field]) < $rules['minLength']) {
            // Clear invalid value to avoid reuse.
            $_POST[$field] = '';
            static::addAlert("The input of the $fieldName field is too short!", AlertType::WARNING);
            return false;
        }

        // Maximum length for strings.
        if (in_array('maxLength', $rules, true) && strlen($_POST[$field]) > $rules['maxLength']) {
            $_POST[$field] = '';
            static::addAlert("The input of the $fieldName field is too long!", AlertType::WARNING);
            return false;
        }

        // Minimum numeric value.
        if (in_array('minValue', $rules, true) && $_POST[$field] < $rules['minValue']) {
            $_POST[$field] = '';
            static::addAlert("The input in the $fieldName field is too low!", AlertType::WARNING);
            return false;
        }

        // Maximum numeric value.
        if (in_array('maxValue', $rules, true) && $_POST[$field] > $rules['maxValue']) {
            $_POST[$field] = '';
            static::addAlert("The input in the $fieldName field is too high!", AlertType::WARNING);
            return false;
        }

        // Numeric type enforcement.
        if (in_array('type', $rules, true) && $rules['type'] === 'number' && !is_numeric($_POST[$field])) {
            $_POST[$field] = '';
            static::addAlert("The input in the $fieldName field is not a number!", AlertType::WARNING);
            return false;
        }

        // Email format validation.
        if (in_array('type', $rules, true) && $rules['type'] === 'email' && !filter_var($_POST[$field], FILTER_VALIDATE_EMAIL)) {
            $_POST[$field] = '';
            static::addAlert("The input in the $fieldName field is not a valid email address!", AlertType::WARNING);
            return false;
        }

        return true;
    }

    /**
     * Queue an alert message to be displayed to the user.
     * The alert HTML is stored in an internal array and can be rendered
     * by calling formAlerts(). The method accepts an optional timeout
     * attribute that front-end code may use to auto-dismiss the alert.
     *
     * @param string $message The message text to show
     * @param AlertType $type Visual type/style for the alert
     * @param int|null $timeout Optional auto-dismiss timeout in milliseconds
     *
     * @return void
     */
    public static function addAlert(string $message, AlertType $type, ?int $timeout = null): void
    {
        static::$alerts[] = "<div class='alert $type->value' role='alert'" . ($timeout !== null ? " data-timeout='$timeout'" : "") . ">$message</div>";
    }

    /**
     * Render all queued alerts as a single HTML string and clear the queue.
     * Returns null when there are no alerts queued.
     *
     * @return string|null HTML block with alerts or null if none
     */
    public static function formAlerts(): ?string
    {
        if (!self::$alerts) return null;

        // Wrap the individual alert HTML strings in a container.
        $html = '<div class="form-alerts f-col g-row-1">' . implode('', self::$alerts) . '</div>';

        // Clear queue to avoid duplicate output on subsequent calls.
        self::$alerts = [];

        return $html;
    }

    /**
     * Validate password fields: check policy on the first field and match with the second.
     * Clears invalid fields and adds alerts on failure.
     *
     * @param string $passwordField
     * @param string $confirmField
     *
     * @return bool True if valid, false otherwise
     */
    public static function validatePasswords(string $passwordField, string $confirmField): bool
    {
        // Validate password against policy (this adds alert if invalid)
        if (!AuthController::validatePassword($_POST[$passwordField])) {
            $_POST[$passwordField] = '';
            $_POST[$confirmField] = '';
            return false;
        }

        // Check if passwords match
        if ($_POST[$passwordField] !== $_POST[$confirmField]) {
            $_POST[$passwordField] = '';
            $_POST[$confirmField] = '';
            static::addAlert('The entered passwords do not match!', AlertType::WARNING);
            return false;
        }

        return true;
    }
}
