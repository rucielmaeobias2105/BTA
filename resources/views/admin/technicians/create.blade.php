@extends('layouts.admin')

@section('title', 'Add Technician')
@section('heading', 'Add Technician')

@section('content')
    <div class="bta-card p-6 sm:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-lg font-semibold text-primary">Add Technician</h2>

            <a href="{{ route('admin.technicians.index') }}" class="btn-secondary btn-sm">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                Back
            </a>
        </div>

        @include('admin.technicians._form', [
            'technician' => $technician,
            'action' => route('admin.technicians.store'),
            'method' => 'POST',
            'submitLabel' => 'Add Technician',
        ])
    </div>
@endsection
