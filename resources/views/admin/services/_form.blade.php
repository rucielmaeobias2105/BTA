{{--
    Shared service create/edit form: name, category, price, an optional
    description and an Available switch. Nothing else — no slug, no duration, no
    variants, no photo and no separate visibility block. The slug is derived from
    the name server-side, so there is no field for it here.

    The card carries its own small title row with the way back, which is the
    reference's form header. The `x-ui.page-header` is gone: the top bar already
    says which page this is, and a second heading above the card is what made
    these pages taller than the reference for no extra information.
--}}
@extends('layouts.admin')

@section('title', $service->exists ? 'Edit Service' : 'Add Service')
@section('heading', $service->exists ? 'Edit Service' : 'Add Service')

@section('content')

    @php $action = $service->exists ? route('admin.services.update', $service) : route('admin.services.store'); @endphp

    <form method="POST" action="{{ $action }}" novalidate>
        @csrf
        @if ($service->exists) @method('PUT') @endif

        <div class="bta-card p-6 sm:p-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-lg font-semibold text-primary">
                    {{ $service->exists ? 'Edit Service' : 'Add Service' }}
                </h2>

                <a href="{{ route('admin.services.index') }}" class="btn-secondary btn-sm">
                    <x-heroicon-o-arrow-left class="h-4 w-4" />
                    Back
                </a>
            </div>

            <div class="max-w-2xl space-y-6">
                <x-ui.form.input
                    name="name"
                    label="Service Name"
                    required
                    :value="old('name', $service->name)"
                    placeholder="e.g. Glow Manicure"
                />

                <x-ui.form.select
                    name="category"
                    label="Category"
                    required
                    :value="old('category', $service->category)"
                    :options="$categories->pluck('name', 'name')->all()"
                    :includeBlank="true"
                    blankLabel="Select a category"
                />

                {{-- One text field, so a price that is not a single number
                     survives: 100+ for "starting at", 249/499 for two options,
                     1,200 with a thousands separator. Booking totals are worked
                     out from the first figure, which for "100+" is 100 — the
                     amount the customer is actually told the service starts at.

                     The hint that used to sit here spelled that out. It was
                     removed on request: the field takes the formats as written,
                     and a sentence under it on every page of the form was noise
                     for an admin who types prices all day. --}}
                <x-ui.form.input
                    name="price"
                    type="text"
                    inputmode="text"
                    label="Price (₱)"
                    required
                    prefix="₱"
                    :value="old('price', $service->price)"
                />

                <x-ui.form.switch
                    name="is_active"
                    label="Available"
                    :checked="$service->exists ? $service->is_active : true"
                />
            </div>

            <div class="mt-7 border-t border-primary/10 pt-6">
                <button type="submit" class="btn-primary">
                    <x-heroicon-o-check class="h-4 w-4" />
                    {{ $service->exists ? 'Update Service' : 'Add Service' }}
                </button>
            </div>
        </div>
    </form>
@endsection
