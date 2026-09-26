{{-- Shared promo create/edit form. --}}
@extends('layouts.admin')

@section('title', $promo->exists ? 'Edit Promo' : 'New Promo')
@section('heading', $promo->exists ? 'Edit Promo' : 'New Promo')

@section('content')
    <a href="{{ route('admin.promos.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        Back to promos
    </a>

    <x-ui.page-header
        eyebrow="Marketing"
        :title="$promo->exists ? 'Edit Promo' : 'New Promo'"
        description="Active promos surface as a site-wide banner. Use the promo list to push them to customers as notifications."
    />

    <x-ui.alert type="error" class="mb-6" :dismissible="false">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </x-ui.alert>

    @php $action = $promo->exists ? route('admin.promos.update', $promo) : route('admin.promos.store'); @endphp

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" novalidate>
        @csrf
        @if ($promo->exists) @method('PUT') @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-ui.card title="Promo Details">
                    <x-ui.form.input name="title" label="Promo Title" required :value="old('title', $promo->title)" placeholder="e.g. Glow Package Discount" />

                    <div class="mt-5">
                        <x-ui.form.textarea
                            name="description"
                            label="Description"
                            required
                            rows="5"
                            :value="old('description', $promo->description)"
                            placeholder="What is the offer, and what does the customer get?"
                        />
                    </div>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <x-ui.form.input name="starts_at" type="date" label="Valid From" required :value="old('starts_at', $promo->starts_at?->toDateString())" />
                        <x-ui.form.input name="ends_at" type="date" label="Valid Until" required :value="old('ends_at', $promo->ends_at?->toDateString())" />
                    </div>
                </x-ui.card>
            </div>

            <aside class="space-y-6">
                <x-ui.card title="Promo Image">
                    @if ($promo->image_path)
                        <img src="{{ Storage::url($promo->image_path) }}" alt="" class="mb-4 aspect-[16/9] w-full rounded-xl object-cover">
                    @endif

                    <x-ui.form.input name="image" type="file" label="Upload Image" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG or WEBP. Max 3 MB." />
                </x-ui.card>

                <x-ui.card title="Visibility">
                    <x-ui.form.checkbox name="is_active" value="1" :checked="old('is_active', $promo->exists ? $promo->is_active : true)" label="Active" hint="Must also fall within the validity period to be shown." />
                </x-ui.card>

                <div class="flex flex-col gap-3">
                    <button type="submit" class="btn-primary w-full">{{ $promo->exists ? 'Save Changes' : 'Create Promo' }}</button>
                    <a href="{{ route('admin.promos.index') }}" class="btn-ghost w-full">Cancel</a>
                </div>
            </aside>
        </div>
    </form>
@endsection
