<?php

namespace app\Controllers;

class AppController
{
    public function __construct()
    {
        // Log in the user if the remember cookie is set
        if (!isset($_SESSION['user']) && isset($_COOKIE['remember'])) UserController::rememberLogin($_COOKIE['remember']);
    }
}
