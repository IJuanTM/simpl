<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Enums\AlertType;

/**
 * Utilities for validating form input and queuing alert markup for display.
 *
 * Reads raw POST values via RequestController::rawPost() for validation, but
 * writes directly to $_POST to clear invalid fields so views do not repopulate them.
 */
class FormController
{
    public static array $alerts = [];

    /**
     * Validate a single form field in $_POST against a set of rules.
     * The method mutates $_POST[$field] to an empty string on validation
     * failures to avoid re-using invalid values later in request handling.
     *
     * Positional rules: 'required'
     * Keyed rules: 'minLength' => int, 'maxLength' => int, 'minValue' => mixed, 'maxValue' => mixed, 'type' => 'number'|'email'
     *
     * @param string                  $field Field name expected in $_POST
     * @param array<int|string,mixed> $rules Validation rules and parameters
     *
     * @return bool True when validation passes, false on first failure
     */
    public static function validate(string $field, array $rules): bool
    {
        $fieldName = str_replace('-', ' ', $field);

        // Reject a field submitted as an array (e.g. name="x[]") - an optional field has
        // no rule below that would otherwise catch it before a raw $_POST read elsewhere.
        if (isset($_POST[$field]) && !is_string($_POST[$field])) {
            $_POST[$field] = '';
            static::addAlert("The input in the $fieldName field is invalid!", AlertType::WARNING);
            return false;
        }

        $value = RequestController::rawPost($field);

        if (($value === null || $value === '') && in_array('required', $rules, true)) {
            static::addAlert("Please enter an input in the $fieldName field!", AlertType::WARNING);
            return false;
        }

        if ($value === null || $value === '') return true;

        if ((isset($rules['minValue']) || isset($rules['maxValue'])) && !is_numeric($value)) {
            $_POST[$field] = '';
            static::addAlert("The input in the $fieldName field is not a number!", AlertType::WARNING);
            return false;
        }

        $checks = [
            ['minLength', static fn($v, $r) => mb_strlen($v) < $r, "The input of the $fieldName field is too short!"],
            ['maxLength', static fn($v, $r) => mb_strlen($v) > $r, "The input of the $fieldName field is too long!"],
            ['minValue', static fn($v, $r) => $v < $r, "The input in the $fieldName field is too low!"],
            ['maxValue', static fn($v, $r) => $v > $r, "The input in the $fieldName field is too high!"],
        ];

        foreach ($checks as [$rule, $fails, $message]) {
            if (isset($rules[$rule]) && $fails($value, $rules[$rule])) {
                $_POST[$field] = '';
                static::addAlert($message, AlertType::WARNING);
                return false;
            }
        }

        $typeFails = match ($rules['type'] ?? null) {
            'number' => !is_numeric($value),
            'email' => !filter_var($value, FILTER_VALIDATE_EMAIL),
            default => false,
        };

        if ($typeFails) {
            $_POST[$field] = '';
            static::addAlert("The input in the $fieldName field is not " . ($rules['type'] === 'number' ? 'a number' : 'a valid email address') . '!', AlertType::WARNING);
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
     * @param string    $message The message text to show
     * @param AlertType $type Visual type/style for the alert
     * @param int|null  $timeout Optional auto-dismiss timeout in milliseconds
     *
     * @return void
     */
    public static function addAlert(string $message, AlertType $type, ?int $timeout = null): void
    {
        static::$alerts[] = "<div class='alert $type->value' role='alert'" . ($timeout !== null ? " data-timeout='$timeout'" : "") . ">" . AppController::sanitize($message) . "</div>";
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

        $html = '<div class="form-alerts f-col g-row-1">' . implode('', self::$alerts) . '</div>';
        self::$alerts = [];

        return $html;
    }

    // @addon-placeholder
}
