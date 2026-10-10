<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function perPage(int $default = 15): int
    {
        $requested = request()->query('per_page');

        if ($requested === 'all') {
            return 1_000_000;
        }

        $requested = filter_var($requested, FILTER_VALIDATE_INT);

        return in_array($requested, [10, 15, 20, 25, 50], true) ? $requested : $default;
    }
}
