<?php

namespace app\Controllers;

class AppController
{
    public function __construct()
    {
        // @addon-insert:after('new AlertController();')
        new AuthController();
        // @addon-end
    }
}
