<?php

namespace App\Http\Controllers\Customer;

use App\Enums\TermsCategory;
use App\Http\Controllers\Controller;
use App\Models\TermsAndCondition;
use Illuminate\View\View;

/**
 * Renders the admin-authored T&C content that the booking, cancellation and
 * reschedule checkboxes link to.
 */
class TermsController extends Controller
{
    public function __invoke(string $category): View
    {
        $enum = TermsCategory::tryFrom($category);

        abort_if($enum === null, 404);

        $terms = TermsAndCondition::publishedFor($enum);

        return view('customer.terms.show', [
            'category' => $enum,
            'terms' => $terms,
        ]);
    }
}
