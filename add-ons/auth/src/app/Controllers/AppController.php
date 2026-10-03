<?php

declare(strict_types=1);

namespace app\Controllers;

/**
 * Inserted into the main AppController bootstrap by the add-on install script.
 */
class AppController
{
    public function __construct()
    {
        // @addon-insert:after('new SessionController();')
        new AuthController();
        // @addon-end
    }
}
