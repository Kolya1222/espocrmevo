<?php

namespace roilafx\Espocrmevo\Controllers;

use EvolutionCMS\Facades\ManagerTheme;

class CrmController
{
    public function index()
    {
        if (!evo()->getLoginUserID('mgr')) {
            return evo()->redirect('/manager/?return=' . urlencode($_SERVER['REQUEST_URI']));
        }

        if (!evo()->hasPermission('settings')) {
            return ManagerTheme::render('403');
        }

        $espocrmUrl = '/manager/media/espoCRM';

        return view('espocrmevo::iframe', compact('espocrmUrl'));
    }
}