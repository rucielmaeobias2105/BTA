<?php

namespace App\Http\Requests\Admin;

use App\Enums\ItemTag;
use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryItemRequest extends FormRequest
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
        $itemId = $this->route('inventory_item')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'sku' => [
                'required', 'string', 'max:64',
                Rule::unique('inventory_items', 'sku')->ignore($itemId)->whereNull('deleted_at'),
            ],
            'category' => ['required', 'string', 'max:80'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'unit' => ['required', 'string', Rule::in(InventoryItem::UNITS)],
            'reorder_threshold' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'status_tag' => ['nullable', 'string', Rule::in(ItemTag::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],

            // Linked services (many-to-many) — which services consume this item.
            'services' => ['nullable', 'array'],
            'services.*' => ['integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit.in' => 'Please choose a valid unit of measure.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => strtoupper(trim((string) $this->input('sku'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
