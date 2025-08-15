<?php

namespace app\Enums;

/**
 * The AlertType enum is used for defining the types of alerts that can be shown.
 */
enum AlertType: string
{
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
    case INFO = 'info';
}
