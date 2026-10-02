<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Models\SalonSetting;
use App\Models\Service;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** How many promos and how many services the landing page shows. */
    private const PROMO_LIMIT = 2;

    private const SERVICE_LIMIT = 6;

    /**
     * Customer Flow 14 — Landing / Home page.
     *
     * The hero, then two sections that were absent: the promos currently running
     * and the services people book most. Both are read here rather than in the
     * view so the page stays a single render with no client-side fetch.
     *
     * `PromoBanner::all()` is deliberately not reused — it returns every active
     * promo, and a landing page with nine promos on it is a wall. The cap is the
     * only difference; the "active and inside its window" rule is `scopeActive()`
     * in both, so the page cannot show an expired offer.
     */
    public function __invoke(): View
    {
        return view('home', [
            'settings' => SalonSetting::current(),
            'promos' => Promo::query()
                ->active()
                ->orderByDesc('starts_at')
                ->limit(self::PROMO_LIMIT)
                ->get(),
            'mostBooked' => Service::mostBookedOrFeatured(self::SERVICE_LIMIT),
        ]);
    }

    /**
     * Static about page (linked from the customer nav).
     */
    public function about(): View
    {
        return view('about', [
            'settings' => SalonSetting::current(),
            'categories' => Service::categories(),
        ]);
    }
}
