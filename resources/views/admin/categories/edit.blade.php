@extends('layouts.admin')

@section('title', 'Edit Category')
@section('heading', 'Edit Category')

@section('content')
    <div class="bta-card p-6 sm:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-lg font-semibold text-primary">Edit Category</h2>

            <a href="{{ route('admin.categories.index') }}" class="btn-secondary btn-sm">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                Back
            </a>
        </div>

        @include('admin.categories._form', [
            'category' => $category,
            'action' => route('admin.categories.update', $category),
            'method' => 'PUT',
            'submitLabel' => 'Update Category',
        ])
    </div>
@endsection
