@extends('layouts.customer')

@section('title', 'Notifications')

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        {{-- Title only. The eyebrow and the subtitle used to sit here, and both
             were describing the list rather than titling it: this page is the
             appointment status history, and the rows say so themselves. --}}
        <x-ui.page-header title="Notifications" />

        @include('customer.notifications._list')
    </div>
@endsection
