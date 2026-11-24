<?php

namespace App\Controllers;

/** Pages d'information statiques. */
class Pages extends BaseController
{
    public function aPropos(): string
    {
        return view('pages/a-propos');
    }

    /** Mention d'information RGPD obligatoire (cf. Conception RGPD de l'équipe). */
    public function donneesPersonnelles(): string
    {
        return view('pages/donnees-personnelles');
    }
}
