@extends('layouts.admin')

@section('title', 'Edit Item')
@section('heading', 'Edit Item')

@section('content')
    <div class="bta-card p-6 sm:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-lg font-semibold text-primary">Edit Item</h2>

            <a href="{{ route('admin.inventory.index') }}" class="btn-secondary btn-sm">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                Back
            </a>
        </div>

        @include('admin.inventory._form', [
            'item' => $item,
            'action' => route('admin.inventory.update', $item),
            'method' => 'PUT',
            'submitLabel' => 'Update Item',
        ])
    </div>
@endsection
