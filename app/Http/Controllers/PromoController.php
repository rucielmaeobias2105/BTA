<?php

namespace App\Http\Controllers;

use App\Support\PromoBanner;
use Illuminate\View\View;

/**
 * Customer Flow 18 — Promo / Special Offers.
 *
 * Promos used to render as a section on the landing page. They now live on
 * their own page so the home page stays a landing page, with the customer nav
 * linking straight here.
 */
class PromoController extends Controller
{
    public function index(): View
    {
        return view('promos.index', [
            'promos' => PromoBanner::all(),
        ]);
    }
}
