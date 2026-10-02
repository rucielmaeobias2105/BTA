{{--
    Book Appointment (Customer Flow 5), rebuilt as one wizard card.

    THE FORM IS ONE CARD

    The seven steps used to be seven separate cards stacked down a scrolling page,
    each with its own border, background and header rule. It read as seven
    unrelated forms that happened to be on one screen: the eye had to re-enter a
    new box at every step, and nothing said "these are all parts of one thing you
    are filling in". Everything now lives inside a single `bta-card` — one shell,
    one header carrying the step counter, one body, one footer with the
    navigation. The steps are still seven and still in the same order; they are
    now sections of one object rather than boxes on a page.

    A stepper rather than a long scroll, because a scroll made the cost of the
    seventh step invisible until you had already scrolled past six.

    NOT NOTHING IS GATED

    The wizard used to gate navigation: Continue would refuse to advance and name
    the step you had not finished. It does not any more, and this is the reason.

    Gating made reading the form into a negotiation. A customer who wanted to read
    the terms on step 7 had to satisfy steps 1 to 6 first, and a customer who had
    made a mistake on step 2 could not go and look at step 5 without fixing it
    first. The steps are a way of reading a form, not a maze.

    So Continue always advances, and `StoreBookingRequest` is the only gate. It
    refuses an incomplete booking on submit and returns every missing field at
    once, which is shown at the top of the page like any other validation error.
    One round trip with a complete list of problems is better than seven, one at a
    time — and the rules it enforces are unchanged: a service, a name, a phone
    number, a date and a time, the agreement, and the last-services answer for a
    first-time customer.

    WHAT IS LEFT OF THE HEADER

    A "Step 3 of 7" counter and nothing else. The row of clickable step pills is
    gone: it restated the counter, took a second band of vertical space above the
    first question, and wrapped into two ragged rows on a phone, where it read as a
    menu rather than as progress. With nothing gated, jumping was never needed —
    Back and Continue get everywhere.

    The selected-service basket is held in Alpine state and mirrored into hidden
    inputs on submit, so the summary total stays live without a round-trip.
--}}
@extends('layouts.customer')

@section('title', 'Book Appointment')

@section('content')
@php
    use App\Support\PriceFormatter;
@endphp
    @php
        /**
         * Catalogue shape Alpine works with.
         *
         * `price` is the advertised string, for display only. `basePrice` is the
         * amount to charge and is what the running total multiplies. They are
         * separate because a service can be listed as "249/499" or "100+" and a
         * range has no single total — so a service with no base price is shown
         * as "Price on request" and cannot be ticked, rather than being booked
         * at a figure the customer never agreed to.
         */
        $catalogue = $groupedServices
            ->flatten()
            ->mapWithKeys(fn ($service) => [
                $service->id => [
                    'name' => $service->name,
                    'category' => $service->category,
                    'price' => PriceFormatter::display($service->price, 'Price on request'),
                    'basePrice' => $service->base_price === null ? null : (float) $service->base_price,
                    'bookable' => $service->base_price !== null
                        || $service->variants->contains(fn ($v) => $v->base_price !== null),
                    'baseDuration' => (int) $service->duration_minutes,
                    'variants' => $service->variants
                        // A variant with no amount is left out rather than
                        // offered and then totalled at zero.
                        ->filter(fn ($v) => $v->base_price !== null)
                        ->map(fn ($v) => [
                            'id' => $v->id,
                            'name' => $v->name,
                            'price' => PriceFormatter::display($v->price, 'Price on request'),
                            'basePrice' => (float) $v->base_price,
                            'duration' => (int) $v->effectiveDuration(),
                        ])->values(),
                ],
            ]);

        $initialSelection = collect($selected)->map(fn ($row) => [
            'service_id' => (int) $row['service_id'],
            'service_variant_id' => $row['service_variant_id'] ? (int) $row['service_variant_id'] : null,
            'quantity' => (int) $row['quantity'],
        ])->values();

        $minDate = $availability->firstBookableDate()->toDateString();
        $maxDate = $availability->lastBookableDate()->toDateString();

        /*
         * The steps, in order.
         *
         * This used to drive the clickable step pills as well, which made it the
         * single source of the pills, the panel headings and Alpine's step count.
         * With the pills gone the array is here for one thing: how many steps
         * there are, used by the "Step X of N" counter, by Continue's visibility
         * and by the last step's submit button. Counting an array beats writing
         * "7" in five places and finding four of them disagreeing when a step is
         * added.
         *
         * The headings below are written out in the panels themselves, which is
         * where they are read.
         */
        $steps = [
            1 => 'Selected Service',
            2 => 'Your Details',
            3 => 'Preferred Date & Time',
            4 => 'Preferences & Health Notes',
            5 => 'Last Service(s) Availed',
            6 => 'Choose Technician & Special Request',
            7 => 'Terms & Conditions',
        ];

        /*
         * `$terms` used to be read here to build the summary block at the top of
         * step 7 — its heading, last-updated date and opening sentence.
         * That block is gone; the checkbox's label links to each policy and the
         * dialog renders the published text, so nothing needs the row any more.
         * The controller still passes it and this step still depends on the admin
         * having published the terms, but the view no longer has to read the row
         * to render them.
         */

        $recentServicesText = implode(', ', array_column($recentServices, 'name'));
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        {{-- Title and nothing else. The eyebrow and the sentence under it were
             describing the form rather than titling it. --}}
        <x-ui.page-header title="Book Appointment" />


        <form
            method="POST"
            action="{{ route('appointments.store') }}"
            enctype="multipart/form-data"
            x-data="bookingForm"
            novalidate
        >
            @csrf

            <div class="grid gap-8 lg:grid-cols-3">
                {{-- ================= THE ONE CARD: all seven steps ================= --}}
                <div class="lg:col-span-2">
                    <section class="bta-card overflow-hidden">
                        {{-- The step counter.

                             This used to carry a row of seven clickable step pills as
                             well, so a customer could jump straight to any step. They
                             are gone: they duplicated what the "Step 3 of 7" line
                             already said, took a whole extra band of vertical space
                             above the first question, and on a phone they wrapped
                             into two ragged rows that read as a menu rather than as
                             progress.

                             What is left says where you are and how much is left,
                             which is what a counter is for. The steps are walked with
                             Back and Continue at the foot of the card, and — since
                             nothing is gated any more — a customer who wants to go
                             backwards simply does.

                             `aria-live="polite"` so the step number is announced when
                             it changes; without it the counter is silent to a screen
                             reader and moving a step is an unexplained jump. --}}
                        <header class="border-b border-primary/10 bg-linen/40 px-4 py-3.5 sm:px-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted" aria-live="polite">
                                Step <span x-text="step">1</span> of {{ count($steps) }}
                            </p>
                        </header>

                        {{-- ================= THE STEPS ================= --}}
                        <div class="px-4 py-5 sm:px-6 sm:py-6">

                            {{-- ---------------------------------------------- 1 --}}
                            <section x-show="step === 1" x-cloak>
                                <h3 class="font-display text-lg font-semibold text-primary">1. Selected Service</h3>

                                @if ($groupedServices->isEmpty())
                                    <x-ui.empty title="No services available" description="Please check back soon." />
                                @else
                                    {{--
                                        Grouped by category and capped at four, exactly as the
                                        Browse Services page's cards do — so a customer who
                                        has already read the price list on that page meets
                                        the same four-then-reveal shape here, rather than a
                                        flat grid of thirty checkboxes.

                                        WHY THE EXPANDED STATE IS NOT LOCAL TO THE CATEGORY

                                        Each disclosure used to be its own `x-data` scope
                                        holding a bare `expanded` boolean. That is the right
                                        shape for the price list, where a category card is
                                        the whole of what the page is showing. It is the
                                        wrong shape here, because this step is only one
                                        panel of a form that re-renders around it: tick a
                                        service, the basket in the summary re-renders; press
                                        Continue or Back, the panel visibility flips; either
                                        one re-initialises scopes under it, and the boolean
                                        goes back to false. The customer opens a category,
                                        goes to look at the date, comes back — and the
                                        category has collapsed and forgotten they were
                                        looking at it.

                                        So the state lives on the form's own scope instead,
                                        keyed by category, in `bookingForm.expandedCategories`.
                                        One object survives every re-render of this list, and
                                        it is per category, so opening Manicure leaves
                                        Threading as it was.

                                        The `serviceCategory()` component is still what the
                                        Browse Services page uses. It is not shared with
                                        this form, because this form needs the opposite
                                        lifetime.
                                    --}}
                                    <div class="mt-4 space-y-5">
                                        @foreach ($groupedServices as $category => $services)
                                            @php
                                                $preview = $services->take(4);
                                                $hidden = $services->count() - $preview->count();
                                                // The category name as a JS object key. Slugged so
                                                // it is stable in source and safe as a property
                                                // name; a name is what the form already groups
                                                // by, so nothing new has to be kept in step.
                                                $categoryKey = \Illuminate\Support\Str::slug($category ?: 'uncategorised');
                                            @endphp

                                            <div data-booking-category="{{ $categoryKey }}">
                                                <div class="mb-2.5 flex items-baseline justify-between gap-3">
                                                    <p class="text-xs font-semibold uppercase tracking-wider text-gold-dark">{{ $category }}</p>
                                                    <p class="text-xs text-ink-muted">
                                                        {{ $services->count() }} {{ Str::plural('service', $services->count()) }}
                                                    </p>
                                                </div>

                                                <div class="grid gap-2.5 sm:grid-cols-2">
                                                    @foreach ($preview as $service)
                                                        <x-salon.service-choice :service="$service" />
                                                    @endforeach

                                                    {{-- The rest of the category. The same component as
                                                         the visible rows, carrying the key that hides
                                                         them — which is the reason the row is a
                                                         component, and why the key is passed as
                                                         data rather than written here as an
                                                         attribute: an attribute on a component tag
                                                         is a literal string, never compiled as
                                                         Blade, so an `x-show` written here reached
                                                         the browser as literal text and hid nothing.
                                                         The component emits it instead.

                                                         Read the parent's key rather than a local
                                                         boolean, so ticking a service, changing a
                                                         quantity, or leaving and returning to this
                                                         step cannot collapse what was opened. --}}
                                                    @foreach ($services->slice(4) as $service)
                                                        <x-salon.service-choice
                                                            :service="$service"
                                                            :expanded-key="$categoryKey"
                                                        />
                                                    @endforeach
                                                </div>

                                                @if ($hidden > 0)
                                                    <div class="mt-2.5 flex justify-end">
                                                        {{-- Plain markup, not a component tag, so
                                                             `@js()` compiles here — that is the
                                                             difference between this button working
                                                             and the rows above it silently not
                                                             hiding, when both used the same
                                                             expression.

                                                             `type="button"` so it can never submit
                                                             the form, and the whole thing is Alpine
                                                             state on the parent scope, so toggling
                                                             changes no step and no scroll. --}}
                                                        <button
                                                            type="button"
                                                            class="btn-ghost btn-sm"
                                                            x-on:click="toggleCategory(@js($categoryKey))"
                                                            :aria-expanded="isCategoryExpanded(@js($categoryKey))"
                                                        >
                                                            <span x-show="! isCategoryExpanded(@js($categoryKey))" x-cloak>See More ({{ $hidden }} more)</span>
                                                            <span x-show="isCategoryExpanded(@js($categoryKey))" x-cloak>See Less</span>
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @error('services')
                                    <p class="input-error-text mt-3">{{ $message }}</p>
                                @enderror
                                @error('services.*')
                                    <p class="input-error-text mt-3">{{ $message }}</p>
                                @enderror
                            </section>

                            {{-- ---------------------------------------------- 2 --}}
                            <section x-show="step === 2" x-cloak>
                                <h3 class="font-display text-lg font-semibold text-primary">2. Your Details</h3>

                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <x-ui.form.input
                                        name="customer_name"
                                        label="Full Name"
                                        required
                                        autocomplete="name"
                                        :value="auth()->user()?->full_name"
                                    />
                                    <x-ui.form.input
                                        name="customer_phone"
                                        label="Phone Number"
                                        required
                                        autocomplete="tel"
                                        placeholder="09XX XXX XXXX"
                                        :value="auth()->user()?->contact_number"
                                    />
                                </div>

                                @guest
                                    <p class="mt-4 rounded-xl bg-linen/70 px-4 py-3 text-xs text-ink-muted">
                                        You are booking as a guest. <a href="{{ route('login') }}" class="font-medium text-primary underline underline-offset-2">Log in</a>
                                        to track, reschedule or cancel this appointment later.
                                    </p>
                                @endguest
                            </section>

                            {{-- ---------------------------------------------- 3 --}}
                            <section x-show="step === 3" x-cloak>
                                <h3 class="font-display text-lg font-semibold text-primary">3. Preferred Date &amp; Time</h3>

                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    {{--
                                        `min` is tomorrow, so today and the past cannot be
                                        picked in the first place. The server enforces the
                                        same rule in StoreBookingRequest — this is the hint,
                                        not the guarantee.
                                    --}}
                                    <x-ui.form.input
                                        name="preferred_date"
                                        type="date"
                                        label="Preferred Date"
                                        required
                                        :value="old('preferred_date', $prefilledDate)"
                                        :min="$minDate"
                                        :max="$maxDate"
                                        x-model="date"
                                        x-bind:min="minDate"
                                    />

                                    {{-- Why the server would refuse this date, in its own words:
                                         minimum notice or a closed weekday, quoted from the
                                         slots endpoint's `problems` so it cannot disagree with
                                         what a submit would do. --}}
                                    {{-- The server's own reason, or nothing.

                                         It was `x-show="! dateUnblocked && date"`, and
                                         `dateUnblocked` does not exist on the form's
                                         scope — so this threw "dateUnblocked is not
                                         defined" on every load of the booking page. A
                                         hint does not need a flag saying whether the
                                         date is blocked; the presence of a reason *is*
                                         that flag, and `dateBlockedReason` is set only
                                         when the server refuses the date. --}}
                                    <p class="input-hint" x-show="date && dateBlockedReason" x-cloak x-text="dateBlockedReason"></p>

                                    <div>
                                        <label for="preferred_time" class="label">
                                            Preferred Time <span class="text-status-cancelled">*</span>
                                        </label>

                                        <select id="preferred_time" name="preferred_time" class="input" required x-model="time">
                                            <option value="">Select a time</option>
                                            <template x-for="slot in slots" :key="slot">
                                                <option :value="slot" x-text="formatTime(slot)"></option>
                                            </template>
                                        </select>

                                        <p class="input-hint" x-show="loadingSlots">Checking availability&hellip;</p>
                                        <p class="input-hint" x-show="! loadingSlots && slots.length === 0" x-cloak>
                                            No open slots on this date. Please choose another day.
                                        </p>

                                        @error('preferred_time')
                                            <p class="input-error-text">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </section>

                            {{-- ---------------------------------------------- 4 --}}
                            {{--
                                Was a grid of eight allergy checkboxes beside a free-text box.

                                A checkbox grid is a poor fit for this question. It is long
                                enough that customers skim it, it can only ever answer
                                "yes" to a list somebody thought of in advance, and the
                                free-text box sitting next to it — the only field that could
                                actually answer the question — looked like an afterthought.

                                So the primary input is one dropdown: a single decision
                                instead of eight, ordered from "nothing to declare" through
                                the allergies to the handling notes, and a closed set the
                                server can validate. The free-text box stays, but only for
                                the detail that will not fit in a label.

                                The column is still `allergies` and still a comma-joined
                                list of words — `StoreBookingRequest::healthNote()` folds the
                                two together, storing the choice as its sentence rather
                                than its backing value, because this column is read by
                                people.
                            --}}
                            <section x-show="step === 4" x-cloak>
                                <h3 class="font-display text-lg font-semibold text-primary">4. Preferences &amp; Health Notes</h3>

                                <div class="mt-4 space-y-4">
                                    <x-ui.form.select
                                        name="allergy_preference"
                                        label="Do you have any allergies or sensitivities we should know about?"
                                        :options="\App\Enums\BookingPreference::options()"
                                        :value="old('allergy_preference', '')"
                                    />

                                    <x-ui.form.textarea
                                        name="allergies_other"
                                        label="Anything else we should know?"
                                        rows="3"
                                        placeholder="Products to avoid, a reaction you have had before, how firmly you like to be worked on…"
                                    />
                                </div>
                            </section>

                            {{-- ---------------------------------------------- 5 --}}
                            {{--
                                Two different questions, so two different fields.

                                A first-timer has no record here for the salon to read the
                                answer out of, so they are asked — and required to answer,
                                because "we have no idea what you have had before" is not a
                                record any stylist can work from.

                                A returning customer does have a record, so they are not
                                asked. Asking someone to retype what the system already
                                holds is asking them to do the salon's bookkeeping: they
                                either remember and are guessing at the truth, or they do
                                not and the answer is worse than the truth. Their history
                                is shown read-only, with the visit each service came from,
                                and submitted for them by `BookingHistory` on the server.

                                Both cases come from the same `BookingHistory` helper the
                                validation rule asks, so the field shown and the field
                                required cannot disagree.
                            --}}
                            <section x-show="step === 5" x-cloak>
                                <h3 class="font-display text-lg font-semibold text-primary">5. Last Service(s) Availed</h3>

                                <div class="mt-4">
                                    @if ($isRepeatCustomer)
                                        {{-- The greeting that stood here — "Welcome back.
                                             Here is what you last had with us…" — is gone. It
                                             restated the heading two lines above and told a
                                             returning customer they had returned, which the
                                             list under it already shows by existing. --}}
                                        <ul class="mt-3 divide-y divide-primary/8 overflow-hidden rounded-xl border border-primary/15 bg-linen/40">
                                            @foreach ($recentServices as $row)
                                                <li class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                                                    <span class="text-sm font-medium text-primary">{{ $row['name'] }}</span>
                                                    @if ($row['date'])
                                                        <span class="shrink-0 text-xs text-ink-muted">{{ $row['date'] }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>

                                        {{-- Carries the same text the list shows, so the booking is
                                             documented whether or not anyone retypes it. Read by
                                             `BookingHistory` server-side, which is what fills the
                                             column when nothing is posted. --}}
                                        <input
                                            type="hidden"
                                            name="last_services_availed"
                                            value="{{ old('last_services_availed', $recentServicesText) }}"
                                        >
                                    @else
                                        <p class="text-sm text-ink">
                                            This is your first visit with us, so we have no record of what you have had done before.
                                            Telling us helps your therapist prepare.
                                        </p>

                                        {{-- No `x-model`: with the submit guard gone there is
                                             nothing on the client that needs to know what is
                                             in this box. It is a plain posted field, like
                                             every other input on this form, and a failed
                                             submit comes back with it filled in through
                                             `old()`. --}}
                                        <x-ui.form.textarea
                                            name="last_services_availed_note"
                                            label="What services have you had elsewhere?"
                                            rows="3"
                                            required
                                            placeholder="e.g. Colour &amp; Highlights last March, Glow Manicure last month"
                                        />

                                        <input type="hidden" name="last_services_availed" value="{{ old('last_services_availed', '') }}">
                                    @endif
                                </div>

                                @error('last_services_availed')
                                    <p class="input-error-text mt-3">{{ $message }}</p>
                                @enderror
                            </section>

                            {{-- ---------------------------------------------- 6 --}}
                            {{--
                                Was a grid of radio cards, each with the technician's face
                                beside their name. It is now a dropdown.

                                A `<select>` cannot show a photograph, so this is a real loss
                                on that step and the face has been moved rather than
                                dropped: the chosen technician's photo and initials are
                                shown beside the dropdown as soon as one is picked. What
                                survives is the thing the dropdown is better at — a list
                                that stays a fixed height no matter how many technicians
                                there are, that is keyboard- and screen-reader-native, and
                                that reads the same on a phone as on a desktop.

                                "No preference" stays a first-class option and submits an
                                empty string, which the `integer` rule turns into null and
                                the server reads as "assign one for me".
                            --}}
                            <section x-show="step === 6" x-cloak>
                                <h3 class="font-display text-lg font-semibold text-primary">6. Choose Technician &amp; Special Request</h3>

                                <div class="mt-4">
                                    @if ($technicians->isEmpty())
                                        <p class="text-sm text-ink-muted">
                                            No technicians have been set up yet, so we will assign one for you.
                                        </p>
                                        <input type="hidden" name="technician_id" value="">
                                    @else
                                        <x-ui.form.select
                                            name="technician_id"
                                            label="Who would you like to see?"
                                            :includeBlank="true"
                                            blankLabel="No preference — assign one for me"
                                            :options="$technicians->mapWithKeys(fn ($t) => [(string) $t->id => $t->name])->all()"
                                            :value="old('technician_id', '')"
                                            x-model="technicianId"
                                        />

                                        {{-- The face the dropdown cannot show. `x-show` on the
                                             wrapper rather than a `<template x-if>` per branch,
                                             so switching technicians reuses one element instead
                                             of tearing down and rebuilding the image. --}}
                                        <div
                                            class="mt-3 flex items-center gap-3 rounded-xl border border-primary/15 bg-linen/40 px-4 py-3"
                                            x-show="technicianId !== ''"
                                            x-cloak
                                        >
                                            <template x-if="technicianPreview()?.photo">
                                                <img :src="technicianPreview().photo" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover">
                                            </template>

                                            <template x-if="! technicianPreview()?.photo">
                                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-cream" x-text="technicianPreview()?.initials"></span>
                                            </template>

                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Your technician</p>
                                                <p class="truncate text-sm font-medium text-primary" x-text="technicianPreview()?.name || '—'"></p>
                                            </div>
                                        </div>

                                        @error('technician_id')
                                            <p class="input-error-text mt-3">{{ $message }}</p>
                                        @enderror
                                    @endif

                                    <div class="mt-4">
                                        <x-ui.form.textarea
                                            name="special_request"
                                            label="Special Request"
                                            rows="3"
                                            placeholder="Quiet corner, extra time for consultation, specific products to avoid&hellip;"
                                        />
                                    </div>
                                </div>
                            </section>

                            {{-- ---------------------------------------------- 7 --}}
                            <section x-show="step === 7" x-cloak>
                                <h3 class="font-display text-lg font-semibold text-primary">7. Terms &amp; Conditions</h3>

                                {{--
                                    The gate, and only the gate.

                                    The block that stood here — the published terms'
                                    own heading, "Last updated …", a
                                    "Read the full General Terms & Conditions" button,
                                    and a list of the Cancellation and Rescheduling
                                    policies — is gone. It repeated the checkbox's
                                    label, which already names all three documents
                                    and links to each one, and it made step 7 a
                                    changelog entry rather than a question. The links
                                    in the label below still open the full published
                                    text in the dialog, so nothing became unreadable.

                                    `required` is kept on the control, and `accepted`
                                    in `StoreBookingRequest` is what actually refuses a
                                    booking. The form is `novalidate`, so the browser
                                    will not enforce the attribute — but the server
                                    does not need to be told twice to fail a booking
                                    with no agreement, and errors are shown on submit
                                    like everything else on this form.
                                --}}
                                <div class="mt-4 rounded-xl border border-primary/15 bg-white/60 p-4">
                                    <x-ui.form.checkbox name="agree_terms" value="1" required>
                                        I agree to the
                                        <x-terms.link :category="'booking'">Booking Terms &amp; Conditions</x-terms.link>
                                        , the
                                        <x-terms.link :category="'cancellation'">Cancellation Policy</x-terms.link>
                                        and the
                                        <x-terms.link :category="'rescheduling'">Rescheduling Policy</x-terms.link>.
                                    </x-ui.form.checkbox>
                                </div>
                            </section>
                        </div>

                        {{-- ================= THE NAVIGATION =================

                             Continue is unconditional. It used to be gated on the step
                             being complete — "you have not chosen a service yet" — which
                             meant walking the form was a negotiation: every Continue
                             click either advanced or scolded, and a customer who wanted
                             to see step 7 to read the terms had to satisfy steps 1 to 6
                             first.

                             Nothing is gated now. The steps are a way of reading the
                             form, not a maze, and the server is the only gate: it
                             refuses an incomplete booking on submit and says which
                             fields were missing, all at once, in the usual place at
                             the top of the page. One refusal with a full list of
                             problems beats seven of them one at a time.
                        --}}
                        <footer class="flex items-center justify-between gap-3 border-t border-primary/10 bg-linen/40 px-4 py-4 sm:px-5">
                            {{-- `invisible` rather than `x-show` on the empty first step, so
                                 "Back" does not make the row jump when the first step
                                 arrives. It is still not focusable either way. --}}
                            <button
                                type="button"
                                class="btn-ghost"
                                x-bind:class="step === 1 && 'invisible pointer-events-none'"
                                x-on:click="back()"
                            >
                                <span aria-hidden="true">&larr;</span> Back
                            </button>

                            <button
                                type="button"
                                class="btn-primary"
                                x-show="step < {{ count($steps) }}"
                                x-on:click="next()"
                            >
                                Continue
                                <span aria-hidden="true">&rarr;</span>
                            </button>

                            {{-- On the last step the footer's job is to submit. The
                                 sidebar's Confirm Booking does the same thing, and both
                                 post the same form to the same place. --}}
                            <button
                                type="submit"
                                class="btn-primary"
                                x-show="step === {{ count($steps) }}"
                                x-cloak
                            >
                                Confirm Booking
                            </button>
                        </footer>
                    </section>
                </div>

                {{-- ================= RIGHT: booking summary ================= --}}
                {{--
                    Outside the wizard card on purpose.

                    This is not a step — nothing here is filled in, and it has to stay
                    visible while the steps change, which is what makes it usable: you
                    can see what you have picked and what it costs from every step,
                    rather than only from the step where you picked it.

                    It was already the one panel on this page that was not a numbered
                    card, and keeping it that way is what lets the seven steps merge
                    into a single card without the page losing its anchor.
                --}}
                <div class="lg:col-span-1">
                    <div class="bta-card overflow-hidden lg:sticky lg:top-28">
                        <div class="border-b border-primary/10 bg-primary px-5 py-4">
                            <h2 class="font-display text-lg font-semibold text-cream">Booking Summary</h2>
                            <p class="text-xs text-cream/70">Read-only summary</p>
                        </div>

                        <div class="space-y-4 p-5 text-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Customer</p>
                                <p class="mt-1 font-medium text-primary" x-text="$el.closest('form').querySelector('[name=customer_name]').value || '—'"></p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Service(s)</p>

                                <div x-show="selectedIds.length === 0" class="mt-1 text-ink-muted">No service selected yet.</div>

                                <ul class="mt-1.5 space-y-2">
                                    <template x-for="row in lines" :key="row.key">
                                        <li class="rounded-lg bg-linen/60 px-3 py-2">
                                            {{-- The basket itself is Alpine state, so the ids and
                                                 quantities have to ride along as real inputs for
                                                 StoreBookingRequest to see them. --}}
                                            <input type="hidden" :name="`services[${row.index}][service_id]`" :value="row.serviceId">
                                            <input type="hidden" :name="`services[${row.index}][service_variant_id]`" :value="row.variantId ?? ''">

                                            <div class="flex items-start justify-between gap-2">
                                                <span class="min-w-0 flex-1 text-ink" x-text="row.name"></span>
                                                <span class="shrink-0 font-medium text-primary" x-text="money(row.price * row.quantity)"></span>
                                            </div>
                                            {{-- A range is not a fixed amount, so it is
                                                 shown rather than totalled silently at
                                                 the starting figure.

                                                 The "Advertised as … final price
                                                 confirmed at the salon" line that
                                                 used to sit here is gone on
                                                 request. It repeated the service
                                                 name and its own price back to the
                                                 customer immediately below the row
                                                 that already carried both, which
                                                 read as a second, smaller price
                                                 rather than a reassurance.

                                                 `row.advertised` is still sent by
                                                 the controller and is still what
                                                 the line item renders, so nothing
                                                 downstream depends on this. --}}
                                            <div class="mt-1.5 flex items-center gap-2">
                                                <select
                                                    class="rounded-lg border border-primary/15 bg-white px-2 py-1 text-[11px] text-ink"
                                                    x-show="row.hasVariants"
                                                    x-model="row.variantId"
                                                    x-on:change="onVariantChange(row)"
                                                >
                                                    <template x-for="v in row.variants" :key="v.id">
                                                        <option :value="v.id" x-text="v.name"></option>
                                                    </template>
                                                </select>

                                                <input type="number" min="1" max="10"
                                                       class="w-16 rounded-lg border border-primary/15 bg-white px-2 py-1 text-[11px] text-ink"
                                                       :name="`services[${row.index}][quantity]`"
                                                       x-model.number="row.quantity">
                                            </div>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Date &amp; Time</p>
                                <p class="mt-1 font-medium text-primary" x-text="dateTimeLabel"></p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Technician</p>
                                <p class="mt-1 font-medium text-primary" x-text="technicianName"></p>
                            </div>

                            <div class="bta-divider"></div>

                            {{--
                                Total, and only the total.

                                The Reference Number line used to sit here too, saying
                                "Generated automatically on submit". It was a field that
                                could not be filled in, could not be changed, and could
                                not be acted on before the booking existed — a row of
                                text promising a number to arrive. The reference is still
                                generated, still unique and still on the booking; it is
                                just not advertised on the form, and the customer is
                                shown it afterwards, where it is actually a fact.
                            --}}
                            <div class="flex items-baseline justify-between">
                                <span class="text-sm font-semibold text-ink">Total Amount</span>
                                <span class="font-display text-2xl font-bold text-primary" x-text="money(total)"></span>
                            </div>
                        </div>

                        <div class="border-t border-primary/10 bg-linen/50 p-5">
                            {{--
                                Nothing is disabled and nothing is nagged about here.

                                The button used to be inert on an empty basket, with a
                                line above it naming whichever step was missing. Both
                                are gone: a control that refuses to be pressed, and
                                which does not explain itself while doing it, is worse
                                than one that submits and returns the server's list of
                                problems — and the server already refuses a booking with
                                no service in it, so the case that was being prevented
                                here could not have saved anything.
                            --}}
                            <button
                                type="submit"
                                class="btn-primary w-full btn-lg"
                            >
                                Confirm Booking
                            </button>

                            <p class="mt-2.5 text-center text-[11px] text-ink-muted">
                                By confirming you agree to our booking terms.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    @php
        // Built in PHP so Blade's @json() parser never has to walk closures.
        $bookingConfig = [
            'catalogue' => $catalogue,
            'initial' => $initialSelection,
            'slots' => $slots,
            'slotsUrl' => route('appointments.slots'),
            'date' => old('preferred_date', $prefilledDate),
            'time' => old('preferred_time', ''),
            // The picker's state, so the read-only summary can name the
            // technician without reading the DOM, and so the photo preview
            // beside the dropdown has something to show. Seeded from `old()`
            // so a failed submit comes back with the choice still made.
            //
            // One map rather than the two this used to need: the name is inside
            // the technician's entry, so the summary and the preview cannot
            // read from different sources and print different people.
            'technicianId' => (string) old('technician_id', ''),
            'technicians' => $technicians->mapWithKeys(fn ($t) => [
                (string) $t->id => [
                    'name' => $t->name,
                    // `null` rather than a broken `src` when no photo was
                    // uploaded — the preview falls back to initials.
                    'photo' => $t->photo_url,
                    'initials' => $t->initials,
                ],
            ])->all(),
            // The date rules, so the form can grey out what the server will
            // refuse without waiting for a failed submit. `minDate` is tomorrow.
            'minDate' => $minDate,
            'maxDate' => $maxDate,
            // Was the blocked-dates map, scoped to the first selected service's
            // category. Empty now that the Calendar & Blocked Dates feature is
            // gone: no rule left depends on which services are in the basket, and
            // the key stays so the client's shape does not change.
            'blockedDates' => (object) [],
            'stepCount' => count($steps),
        ];
    @endphp

    {{-- Server-rendered config, read once by the Alpine component. --}}
    <script type="application/json" id="bta-booking-config">{!! json_encode($bookingConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

    <script>
        /**
         * Live booking basket, and the wizard that steps through it.
         *
         * Owns the selected services, their variants and quantities, mirrors them
         * into the named inputs that StoreBookingRequest validates, keeps the
         * read-only Booking Summary in sync, and moves the seven steps one at a
         * time.
         *
         * It gates nothing. Not the steps and not the submit: there is no
         * `guardSubmit`, no `canSubmit`, and the Confirm button is never disabled.
         * Everything this file would have checked is checked by
         * `StoreBookingRequest`, which is the real gate and has always been — so
         * what is left here is convenience, and a convenience that refused to let
         * somebody read the next step was a cost rather than a saving.
         *
         * Configuration arrives as JSON from the server.
         */
        document.addEventListener('alpine:init', () => {
            Alpine.data('bookingForm', () => ({
                config: {},
                catalogue: {},
                selectedIds: [],
                rows: [],
                date: '',
                time: '',
                slots: [],
                loadingSlots: false,
                technicianId: '',
                technicians: {},
                slotsUrl: '',
                // Date rules. `minDate` is what the date input's `min` binds
                // to, so the browser greys today and the past out; the server
                // re-checks both regardless. Seeded from the config node
                // because this object is defined inside a JS string.
                minDate: '',
                maxDate: '',
blockedDates: {},
                dateBlockedReason: '',

                /*
                 * The wizard.
                 *
                 * `step` is the only state, and every panel's visibility is derived
                 * from it. Nothing reads it to decide whether a step may be left —
                 * Continue is unconditional and the server is the only gate.
                 */
                step: 1,
                stepCount: 1,

                /*
                 * Which service categories the customer has expanded.
                 *
                 * Keyed by category rather than held in a per-category Alpine scope,
                 * because this list re-renders around it: ticking a service redraws
                 * the basket in the summary, and every step change flips panel
                 * visibility. Either one re-initialises nested scopes underneath
                 * this, so a local `expanded` boolean goes back to false and a
                 * category the customer had opened silently collapses when they come
                 * back to it.
                 *
                 * Living here, on the one scope that owns the form, it survives all
                 * of that — and it is still per category, so opening Manicure &
                 * Pedicure leaves Threading exactly as it was.
                 */
                expandedCategories: {},

                /**
                 * The first selected service.
                 *
                 * Still sent on every slot lookup because the endpoint accepts it —
                 * but nothing reads it there any more, and nothing here depends on
                 * it since the blocked-date feature went. Kept so the request
                 * shape does not change in one file without the other.
                 *
                 * `blockedDates` and `dateBlockedReason` are likewise kept and now
                 * always empty: the server's `problems` list is what the hint under
                 * the date quotes instead.
                 */
                get firstServiceId() {
                    const first = this.rows[0];
                    return first ? first.serviceId : null;
                },

                init() {
                    const node = document.getElementById('bta-booking-config');

                    this.config = node ? JSON.parse(node.textContent) : {};

                    this.catalogue = this.config.catalogue || {};
                    this.slots = this.config.slots || [];
                    this.slotsUrl = this.config.slotsUrl || '';
                    this.date = this.config.date || '';
                    this.time = this.config.time || '';
                    this.technicianId = this.config.technicianId || '';
                    this.technicians = this.config.technicians || {};
                    this.stepCount = Number(this.config.stepCount) || 7;

                    // Just the date. It used to also watch the basket, because a
                    // category-scoped block only applied once a service from that
                    // category was selected — the watcher is gone with the feature,
                    // since nothing left depends on the basket.
                    this.$watch('date', () => this.loadSlots());

                    (this.config.initial || []).forEach((row) => {
                        this.selectedIds.push(Number(row.service_id));
                        this.buildRow(Number(row.service_id), row.service_variant_id, row.quantity);
                    });

                },

                /* ---------------------------------------------------------------- */
                /* The wizard                                                        */
                /* ---------------------------------------------------------------- */

                /**
                 * Move to a step.
                 *
                 * Clamped rather than refused, so `next()` off the last step and
                 * `back()` off the first are no-ops instead of errors — there is
                 * nowhere else to go in either direction, and the buttons that call
                 * these are hidden at those points anyway.
                 */
                goTo(number) {
                    const target = Number(number);

                    if (!Number.isFinite(target)) return;

                    this.step = Math.min(Math.max(Math.trunc(target), 1), this.stepCount);
                },

                next() {
                    this.goTo(this.step + 1);
                },

                back() {
                    this.goTo(this.step - 1);
                },

                /* ---------------------------------------------------------------- */
                /* Step 1 — which service categories are expanded                   */
                /* ---------------------------------------------------------------- */

                /**
                 * Has this category been opened past its first four services?
                 *
                 * A read, not a write, so the button label, its `aria-expanded` and
                 * every hidden row all ask the same question of the same place. If
                 * they each kept their own copy of the flag they would disagree the
                 * first time one of them re-rendered.
                 */
                isCategoryExpanded(key) {
                    return Boolean(this.expandedCategories[key]);
                },

                /**
                 * Open or close one category, leaving the others as they are.
                 *
                 * Reassigned rather than mutated: Alpine's reactivity is proxy-based,
                 * so `expandedCategories[key] = true` would change the object
                 * without telling the `x-show` bindings watching it. The whole map
                 * being replaced is what makes every open row appear.
                 */
                toggleCategory(key) {
                    this.expandedCategories = {
                        ...this.expandedCategories,
                        [key]: ! this.expandedCategories[key],
                    };
                },

                /* ---------------------------------------------------------------- */
                /* Basket                                                            */
                /* ---------------------------------------------------------------- */

                isSelected(id) {
                    return this.selectedIds.includes(Number(id));
                },

                toggle(id, checked) {
                    const meta = this.catalogue[Number(id)];

                    // Nothing to charge means nothing to total, so a service whose
                    // admin has not set an amount is not bookable. The server
                    // refuses it too; this is only here so the tick does not lie.
                    if (checked && meta && meta.bookable === false) return;

                    if (checked) {
                        this.buildRow(Number(id), null, 1);
                    } else {
                        this.rows = this.rows.filter((row) => row.serviceId !== Number(id));
                    }

                    this.reindex();
                },

                buildRow(serviceId, variantId, quantity) {
                    const meta = this.catalogue[serviceId];

                    if (!meta) return;

                    const variants = meta.variants || [];
                    const chosen = variants.find((v) => v.id === Number(variantId)) || variants[0] || null;

                    this.rows.push({
                        key: serviceId + '-' + (chosen ? chosen.id : 'base'),
                        index: this.rows.length,
                        serviceId: serviceId,
                        name: chosen ? meta.name + ' (' + chosen.name + ')' : meta.name,
                        variants: variants,
                        variantId: chosen ? chosen.id : null,
                        hasVariants: variants.length > 1,
                        // The amount to charge, not the advertised string: this is
                        // what the running total multiplies.
                        price: Number(chosen ? chosen.basePrice : meta.basePrice) || 0,
                        // What the service is advertised at, so a range like
                        // "249/499" is visible next to the amount being totalled.
                        advertised: chosen ? chosen.price : meta.price,
                        quantity: Number(quantity) || 1,
                    });
                },

                onVariantChange(row) {
                    const meta = this.catalogue[row.serviceId];
                    const chosen = row.variants.find((v) => v.id === Number(row.variantId));

                    row.name = chosen ? meta.name + ' (' + chosen.name + ')' : meta.name;
                    row.price = Number(chosen ? chosen.basePrice : meta.basePrice) || 0;
                    row.advertised = chosen ? chosen.price : meta.price;
                    row.key = row.serviceId + '-' + (chosen ? chosen.id : 'base');
                },

                reindex() {
                    this.rows.forEach((row, index) => { row.index = index; });
                },

                get lines() {
                    return this.rows;
                },

                get total() {
                    return this.rows.reduce((sum, row) => sum + row.price * row.quantity, 0);
                },

                /* ---------------------------------------------------------------- */
                /* Technician                                                        */
                /* ---------------------------------------------------------------- */

                /**
                 * The chosen technician, or null for "No preference".
                 *
                 * Null rather than a placeholder row, because the preview beside
                 * the dropdown is hidden entirely in that case — showing an empty
                 * avatar would imply a technician had been picked.
                 */
                technicianPreview() {
                    return this.technicianId ? (this.technicians[this.technicianId] || null) : null;
                },

                get technicianName() {
                    const preview = this.technicianPreview();

                    return preview ? preview.name : 'No preference';
                },

                /* ---------------------------------------------------------------- */
                /* Date & time                                                       */
                /* ---------------------------------------------------------------- */

                get dateTimeLabel() {
                    if (!this.date) return '—';

                    const pretty = new Date(this.date + 'T00:00:00')
                        .toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });

                    return this.time ? pretty + ' at ' + this.formatTime(this.time) : pretty;
                },

                async loadSlots() {
                    if (!this.slotsUrl) return;

                    // The first selected service decides which category-scoped
                    // blocks apply, exactly as StoreBookingRequest treats
                    // `services.0.service_id`.
                    const serviceId = this.firstServiceId || '';
                    const query = new URLSearchParams();

                    if (this.date) query.set('date', this.date);

                    // With no date picked there is nothing to look up. The
                    // earliest bookable date used to stand in so the blocked-date
                    // map would arrive anyway; now the endpoint needs a real
                    // date, so it is sent and the answer discarded until one is
                    // chosen.
                    if (query.has('date') === false) {
                        query.set('date', this.minDate);
                    }

                    if (serviceId) query.set('service_id', serviceId);

                    const previous = this.time;
                    this.loadingSlots = true;

                    try {
                        const response = await fetch(this.slotsUrl + '?' + query.toString(), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) throw new Error('slot lookup failed');

                        const data = await response.json();

                        // Only adopt the server's slots once a real date is
                        // chosen; the stand-in lookup above exists only to keep
                        // min/max current, and must not pre-fill a time.
                        this.slots = (this.date && data.date === this.date) ? (data.slots || []) : [];

                        if (data.minDate) this.minDate = data.minDate;
                        if (data.maxDate) this.maxDate = data.maxDate;

                        // The server's own reason, quoted verbatim rather than
                        // rebuilt from a client-side map. Empty for a date it
                        // would accept, which is what the hint keys off.
                        this.dateBlockedReason = this.date && data.date === this.date
                            ? (data.problems || [])[0] || ''
                            : '';

                        if (previous && !this.slots.includes(previous)) {
                            this.time = '';
                        }
                    } catch (error) {
                        this.slots = [];
                    } finally {
                        this.loadingSlots = false;
                    }
                },

                formatTime(value) {
                    const parts = String(value).split(':');
                    const hour = parseInt(parts[0], 10);
                    const minute = parts[1] || '00';
                    const suffix = hour >= 12 ? 'PM' : 'AM';
                    const display = hour % 12 === 0 ? 12 : hour % 12;

                    return display + ':' + minute + ' ' + suffix;
                },

                money(value) {
                    return '₱' + Number(value || 0).toLocaleString('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                },
            }));
        });
    </script>
@endpush
