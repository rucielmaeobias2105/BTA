<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the inventory item form.
 *
 * The form is four fields — name, date in, expiry date and quantity — so that is
 * exactly what is validated here.
 *
 * Three columns are written without being fields. The SKU is derived from the
 * name by the model on create. The unit is `pcs`, because the salon counts stock
 * in pieces rather than choosing per item. The category is
 * `InventoryItem::DEFAULT_CATEGORY`, because it used to ask the admin to file
 * stock under a second, parallel set of category names — one for the catalogue,
 * one for the shelf — that nothing kept in step with each other.
 *
 * Reorder threshold, supplier, notes, the status tag, the Active switch and the
 * linked-service picker are gone from the form too, so nothing typed for them is
 * honoured either.
 */
class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /**
     * The columns the form writes. Anything else on the request is ignored by
     * `safe()->only(...)` in the controller, so a crafted post cannot set a
     * column the admin can no longer see — which is what `unit` and `category`
     * have become.
     *
     * @return array<int, string>
     */
    public static function fields(): array
    {
        return ['name', 'date_in', 'expiry_date', 'quantity'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'date_in' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:date_in'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expiry_date.after_or_equal' => 'The expiry date cannot be before the date it came in.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
