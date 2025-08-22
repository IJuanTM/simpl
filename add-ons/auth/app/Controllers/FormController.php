<?php

namespace app\Controllers;

use app\Enums\AlertType;

/**
 * Handles form validation, sanitization, and feedback messages.
 */
class FormController
{
    public static array $alerts = [];

    /**
     * Validates form fields against specified rules.
     *
     * @param string $field Field name to validate
     * @param array $rules Validation rules to apply
     *
     * @return bool Whether validation passed
     */
    public static function validate(string $field, array $rules): bool
    {
        // Replace dashes with spaces in the field name
        $fieldName = str_replace('-', ' ', $field);

        // Check if the field is required and if it is empty
        if (empty($_POST[$field]) && in_array('required', $rules, true)) {
            static::addAlert("Please enter an input in the $fieldName field!", AlertType::WARNING);
            return false;
        }

        // Check if the field is too short
        if (in_array('minLength', $rules, true) && strlen($_POST[$field]) < $rules['minLength']) {
            $_POST[$field] = '';
            static::addAlert("The input of the $fieldName field is too short!", AlertType::WARNING);
            return false;
        }

        // Check if the field is too long
        if (in_array('maxLength', $rules, true) && strlen($_POST[$field]) > $rules['maxLength']) {
            $_POST[$field] = '';
            static::addAlert("The input of the $fieldName field is too long!", AlertType::WARNING);
            return false;
        }

        // Check if the field's value is too low
        if (in_array('minValue', $rules, true) && $_POST[$field] < $rules['minValue']) {
            $_POST[$field] = '';
            static::addAlert("The input in the $fieldName field is too low!", AlertType::WARNING);
            return false;
        }

        // Check if the field's value is too high
        if (in_array('maxValue', $rules, true) && $_POST[$field] > $rules['maxValue']) {
            $_POST[$field] = '';
            static::addAlert("The input in the $fieldName field is too high!", AlertType::WARNING);
            return false;
        }

        // Check if the field is a number
        if (in_array('type', $rules, true) && $rules['type'] === 'number' && !is_numeric($_POST[$field])) {
            $_POST[$field] = '';
            static::addAlert("The input in the $fieldName field is not a number!", AlertType::WARNING);
            return false;
        }

        // Check if the field is an email
        if (in_array('type', $rules, true) && $rules['type'] === 'email' && !filter_var($_POST[$field], FILTER_VALIDATE_EMAIL)) {
            $_POST[$field] = '';
            static::addAlert("The input in the $fieldName field is not a valid email address!", AlertType::WARNING);
            return false;
        }

        // If all checks passed, return true
        return true;
    }

    /**
     * Adds an alert message to be displayed to the user.
     *
     * @param string $message Alert message text
     * @param AlertType $type Alert type (success, warning, error, etc.)
     */
    public static function addAlert(string $message, AlertType $type): void
    {
        static::$alerts[] = "<div class='alert $type->value' role='alert'>$message</div>";
    }

    /**
     * Sanitizes input data to prevent XSS attacks.
     *
     * @param string $data Raw input data
     *
     * @return string Sanitized data
     */
    public static function sanitize(string $data): string
    {
        return htmlspecialchars(trim($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Returns HTML for all queued alert messages.
     *
     * @return string|null HTML for alerts or null if none exist
     */
    public static function formAlerts(): string|null
    {
        // Check if there are any alerts to show
        if (!self::$alerts) return null;

        $html = '<div class="form-alerts f-col g-row-1">' . implode('', self::$alerts) . '</div>';

        // Clear the alerts after showing them
        self::$alerts = [];

        return $html;
    }
}
