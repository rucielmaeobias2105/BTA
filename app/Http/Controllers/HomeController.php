<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Review;
use App\Models\Service;
use App\Models\SalonSetting;
use App\Support\PromoBanner;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Customer Flow 14 — Landing / Home page.
     */
    public function __invoke(): View
    {
        $settings = SalonSetting::current();

        return view('home', [
            'settings' => $settings,
            'featured' => Service::query()
                ->active()
                ->featured()
                ->with('variants')
                ->orderBy('category')
                ->take(6)
                ->get(),
            'categories' => Service::categories(),
            'promo' => PromoBanner::current(),
            'reviewCount' => Review::query()->count(),
            'averageRating' => round((float) Review::query()->avg('rating'), 1),
            'upcomingCount' => Appointment::query()->upcoming()->count(),
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
