<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the bulk "Delete selected" action on the notifications list.
 *
 * `distinct` matters more than it looks: the tick handler rewrites `selected`
 * from the checkbox values on every select-all toggle, so a duplicate would
 * otherwise inflate the count in the confirmation prompt and in the flash.
 *
 * Deliberately *not* scoped with an `exists` rule. The ids are resolved through
 * `$request->user()->notifications()` in the controller, so a value belonging
 * to somebody else matches no row and deletes nothing — the ownership check is
 * the query itself, and an `exists` rule would only duplicate it (and leak, by
 * its error message, that the id exists at all).
 */
class DeleteNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'uuid', 'distinct'],
        ];
    }

    /**
     * `ids[]` reads as a list, so a hand-edited payload arrives as an
     * associative array. Re-index it so `whereKey()` gets a plain list of ids
     * and the `distinct` rule has nothing to trip over.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('ids'))) {
            $this->merge(['ids' => array_values($this->input('ids'))]);
        }
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one notification to delete.',
            'ids.min' => 'Select at least one notification to delete.',
        ];
    }
}
