<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('fr_nombre')) {
    /**
     * Nombre au format français : 1 234,5. Null => « — » (donnée absente).
     */
    function fr_nombre(float|int|string|null $valeur, int $decimales = 1): string
    {
        if ($valeur === null || $valeur === '') {
            return '—';
        }

        $texte = number_format((float) $valeur, $decimales, ',', "\u{202F}");

        // « 3,0 » s'affiche « 3 » ; « -0 » s'affiche « 0 ».
        if ($decimales > 0) {
            $texte = rtrim(rtrim($texte, '0'), ',');
        }

        return $texte === '-0' ? '0' : $texte;
    }
}
