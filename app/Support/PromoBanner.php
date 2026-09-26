<?php

namespace App\Support;

use App\Models\Promo;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the promo banner shown site-wide. Cached briefly so the footer
 * partial (rendered on every page) does not hit the database each request.
 */
class PromoBanner
{
    protected const CACHE_KEY = 'bta.promo.banner';

    protected const TTL_SECONDS = 300;

    public static function current(): ?Promo
    {
        $id = Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, function () {
            return Promo::query()
                ->active()
                ->orderByDesc('starts_at')
                ->value('id');
        });

        if ($id === null) {
            return null;
        }

        return Promo::find($id);
    }

    /** @return \Illuminate\Support\Collection<int, Promo> */
    public static function all()
    {
        return Promo::query()->active()->orderByDesc('starts_at')->get();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
