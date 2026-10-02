@extends('layouts.admin')

@section('title', 'Add Category')
@section('heading', 'Add Category')

@section('content')
    <div class="bta-card p-6 sm:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-lg font-semibold text-primary">Add Category</h2>

            <a href="{{ route('admin.categories.index') }}" class="btn-secondary btn-sm">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                Back
            </a>
        </div>

        @include('admin.categories._form', [
            'category' => $category,
            'action' => route('admin.categories.store'),
            'method' => 'POST',
            'submitLabel' => 'Add Category',
        ])
    </div>
@endsection
