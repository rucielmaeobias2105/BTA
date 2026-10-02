@extends('layouts.admin')

@section('title', 'Services')
@section('heading', 'Service Management')

@section('content')
    @php
        // The Actions column is capability-gated, so the empty rows have to
        // span whatever the admin who is looking actually gets.
        $columns = auth('admin')->user()?->can('admin.catalog.manage') ? 5 : 4;
    @endphp

    <x-ui.admin-table
        add-label="Add Service"
        :add-href="route('admin.services.create')"
        :search="$search"
    >
        <table class="bta-table">
            <thead>
                <tr>
                    <x-ui.sortable-th column="name" label="Name" :sort="$sort" :direction="$direction" :action="route('admin.services.index')" :params="['search' => $search]" />
                    <x-ui.sortable-th column="category" label="Category" :sort="$sort" :direction="$direction" :action="route('admin.services.index')" :params="['search' => $search]" />
                    <x-ui.sortable-th column="base_price" label="Price" :sort="$sort" :direction="$direction" :action="route('admin.services.index')" :params="['search' => $search]" align="right" />
                    <x-ui.sortable-th column="is_active" label="Available" :sort="$sort" :direction="$direction" :action="route('admin.services.index')" :params="['search' => $search]" align="center" />
                    @can('admin.catalog.manage')
                        <th class="text-right">Actions</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($services as $service)
                    <tr data-row x-show="isShown({{ $loop->index }})">
                        <td class="font-medium text-primary">{{ $service->name }}</td>
                        <td>
                            <span class="badge badge-gold">{{ $service->category }}</span>
                        </td>
                        <td class="whitespace-nowrap text-right font-medium text-primary">
                            {{ \App\Support\PriceFormatter::display($service->price) }}
                        </td>
                        <td class="text-center">
                            @can('admin.catalog.manage')
                                <x-ui.table-toggle
                                    :action="route('admin.services.toggle', $service)"
                                    :checked="$service->is_active"
                                    on-label="Available"
                                    off-label="Hidden"
                                    :label="$service->is_active ? 'Mark “'.$service->name.'” as hidden' : 'Mark “'.$service->name.'” as available'"
                                />
                            @else
                                <x-ui.badge :status="$service->is_active ? 'confirmed' : 'cancelled'" :label="$service->is_active ? 'Available' : 'Hidden'" />
                            @endcan
                        </td>
                        @can('admin.catalog.manage')
                            <td>
                                <div class="flex items-center justify-end gap-1.5">
                                    <x-ui.icon-action
                                        label="Edit {{ $service->name }}"
                                        icon="heroicon-o-pencil-square"
                                        tone="primary"
                                        :href="route('admin.services.edit', $service)"
                                    />
                                    <button
                                        type="button"
                                        class="icon-action icon-action-danger"
                                        title="Delete {{ $service->name }}"
                                        aria-label="Delete {{ $service->name }}"
                                        x-on:click.prevent="$dispatch('confirm-delete-service', { id: {{ $service->id }}, name: @js($service->name) })"
                                    >
                                        <x-heroicon-o-trash class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columns }}" class="py-12 text-center text-sm text-ink-muted">
                            No services yet. Use “Add Service” to create the first one.
                        </td>
                    </tr>
                @endforelse

                {{-- Shown when the search box has filtered every row away. --}}
                @if ($services->isNotEmpty())
                    <tr x-show="total === 0" x-cloak>
                        <td colspan="{{ $columns }}" class="py-12 text-center text-sm text-ink-muted">No data available</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </x-ui.admin-table>
@endsection

@push('modals')
    {{-- The shared `x-ui.confirm-dialog`, replacing the native `confirm()` the
         trash action used to raise.

         One dialog for the whole table rather than a form per row: the trash
         button dispatches the row's id and `confirmDialog.ask()` resolves `{id}`
         in the action template when the event fires. That keeps this to a single
         CSRF token and a single `_method` spoof instead of one pair per service.

         The `{id}` is appended *after* `route()` returns, which is what makes it
         safe: passed inside `route()` the URL generator would scan the finished
         path for `{parameter}` tokens and throw on a placeholder meant for
         JavaScript. --}}
    <x-ui.confirm-dialog
        title="Delete service"
        event="confirm-delete-service"
        :action="route('admin.services.index').'/{id}'"
        subject="service"
        warning="This removes the service from the catalogue. Bookings that already used it keep their saved details."
        title-id="confirm-delete-service-title"
        confirm-label="Yes"
        cancel-label="No"
    />
@endpush
