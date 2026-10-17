<?php

namespace App\Http\Controllers;


abstract class Controller
{
    /**
     * Keep compatibility with application controllers that call the parent constructor.
     */
    public function __construct()
    {
    }

    public static function middleware()
    {
        return [];
    }

}
