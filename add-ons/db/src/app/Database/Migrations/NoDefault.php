<?php

declare(strict_types=1);

namespace app\Database\Migrations;

/**
 * Sentinel for Blueprint's column-builder methods, distinguishing "no DEFAULT clause was requested" from every real default value they can take - including null (DEFAULT NULL) and any legitimate integer.
 */
enum NoDefault
{
    case VALUE;
}
