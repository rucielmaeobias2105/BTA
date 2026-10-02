<?php

namespace App\Http\Requests\Admin;

use App\Models\Service;
use App\Support\PriceFormatter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validation for the service form.
 *
 * The form is name, category, the advertised price and an Available switch — so
 * that is exactly what is validated here. The slug is not a field: it is derived
 * from the name in `prepareForValidation()` and stays unique, so nothing has to
 * be typed twice or clash on a rename.
 *
 * `description` used to be here. The column is still on `services` and still
 * read by the customer-facing service card and the single service page, but it
 * is no longer collected: dropping the rule means `$request->safe()->all()` no
 * longer hands the controller a key for it, so neither `store()` nor `update()`
 * writes one. Existing rows keep whatever copy they already have, which is the
 * reason the column was left in place rather than dropped — that would be a
 * schema migration and a decision about copy the salon has already written.
 */
class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:180', 'alpha_dash',
                // A soft-deleted service keeps its slug, so an existing name
                // cannot be reused until that row is gone for good.
                Rule::unique('services', 'slug')->ignore($this->route('service')?->id),
            ],
            // The dropdown offers the active admin-created categories, so a
            // category an admin has switched off cannot be chosen here. The
            // `category` string is still what the customer pages group by, so
            // the name is what travels.
            'category' => ['required', 'string', 'max:80'],
            // `price` is the advertised figure, so it is a short string and not
            // a number: "100+", "249/499" and "1,200" are all legitimate. The
            // number the booking totals need is derived from it on save, by
            // `Service::booted()`, so there is no second field to forget.
            'price' => PriceFormatter::rules(),
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => 'Pick a category for this service.',
            'category.max' => 'A category may not be longer than 80 characters.',
            'price.regex' => 'Enter a price like 799, 100+, 249/499 or 1,200 — numbers, +, / and the peso sign only.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->uniqueSlug(Str::slug((string) $this->input('name'))),
            // A browser posts "550" as a string, so the strict `string` rule
            // would only ever fail for a hand-built request. Casting here also
            // drops the spaces a pasted price tends to bring with it.
            'price' => $this->has('price') ? trim((string) $this->input('price')) : $this->input('price'),
        ]);
    }

    /**
     * A slug for the name that no service — including a soft-deleted one — is
     * already holding.
     *
     * The slug is what the customer URLs and the booking form address a service
     * by, and `services.slug` carries a unique index, so two services called
     * "Gelish Manicure" cannot both be `gelish-manicure`. Since nobody types a
     * slug any more, a clash is resolved here with a numeric suffix instead of
     * surfacing as a validation error about a field that is not on the form.
     */
    protected function uniqueSlug(string $base): string
    {
        $base = $base !== '' ? $base : 'service';
        $slug = $base;
        $suffix = 1;

        $service = $this->route('service');

        while (Service::withTrashed()
            ->where('slug', $slug)
            ->when($service, fn ($query) => $query->whereKeyNot($service->getKey()))
            ->exists()
        ) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
