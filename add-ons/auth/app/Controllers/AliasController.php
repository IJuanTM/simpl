<?php

declare(strict_types=1);

namespace app\Controllers;

/**
 * Add-on AliasController (auth)
 *
 * Inserted into the main AliasController bootstrap by the add-on install script.
 */
class AliasController
{
    public function __construct()
    {
        // @addon-insert:after("self::register('welcome', new Alias('home'));")

        // Register profile alias
        self::register('profile', new Alias('user', [SessionController::get('user')['id'] ?? null]));
        // @addon-end
    }
}
