<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the bulk "Delete selected" action on the appointments list.
 *
 * Shaped like `DeleteNotificationsRequest` and for the same reasons: the ids
 * arrive from a ticked set that the select-all handler rewrites out of the
 * rendered checkboxes, so `distinct` stops a duplicate inflating the count in
 * the confirmation prompt and in the flash.
 *
 * Again deliberately *not* scoped with an `exists` rule. Every id is resolved
 * through `$request->user()->appointments()` in the controller, so somebody
 * else's id matches no row and deletes nothing — the ownership check is the
 * query, and an `exists` rule would only duplicate it (and leak, through its
 * error message, that the id exists at all).
 */
class DeleteAppointmentsRequest extends FormRequest
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
            'ids.*' => ['required', 'integer', 'distinct'],
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
            'ids.required' => 'Select at least one appointment to delete.',
            'ids.min' => 'Select at least one appointment to delete.',
        ];
    }
}
