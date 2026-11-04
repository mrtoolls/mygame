<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\Fantasy\FantasyScoring;

class FantasyRulesController extends Controller
{
    public function index(FantasyScoring $scoring)
    {
        $pageTitle = 'Rules & Fair Play';
        $rules = $scoring->rules();

        return view('Template::user.fantasy.rules', compact('pageTitle', 'rules'));
    }
}
