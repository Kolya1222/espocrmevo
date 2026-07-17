<?php

namespace roilafx\Espocrmevo\Controllers;

class CrmController
{
    public function index()
    {
        $espocrmUrl = '/manager/media/espoCRM';
        return view('espocrmevo::iframe', compact('espocrmUrl'));
    }
}