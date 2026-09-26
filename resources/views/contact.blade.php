@extends('layouts.customer')

@section('title', 'Contact Us')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow="Get in Touch"
            title="Contact Us"
            description="Have a question about our services, pricing or bookings? Send us a message and we'll reply as soon as we can."
        />

        <x-ui.alert type="error" class="mb-6" :dismissible="false">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>

        <div class="grid gap-8 lg:grid-cols-3">
            {{-- Form --}}
            <div class="lg:col-span-2">
                <form method="POST" action="{{ route('contact.store') }}" class="bta-card p-6 sm:p-8" novalidate>
                    @csrf

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.form.input
                            name="name"
                            label="Name"
                            required
                            autocomplete="name"
                            :value="auth()->user()?->full_name"
                        />
                        <x-ui.form.input
                            name="email"
                            type="email"
                            label="Email"
                            required
                            autocomplete="email"
                            :value="auth()->user()?->email"
                        />
                    </div>

                    <div class="mt-5">
                        <x-ui.form.select
                            name="topic"
                            label="Inquiry Topic"
                            required
                            :includeBlank="true"
                            blankLabel="Select a topic"
                            :options="$topics"
                        />
                    </div>

                    <div class="mt-5">
                        <x-ui.form.textarea
                            name="message"
                            label="Message"
                            required
                            rows="6"
                            placeholder="How can we help?"
                            hint="Please include as much detail as you can so we can assist you properly."
                        />
                    </div>

                    <button type="submit" class="btn-primary mt-6">Send Message</button>
                </form>
            </div>

            {{-- Contact details --}}
            <aside class="space-y-5">
                <x-ui.card title="Visit Us">
                    <ul class="space-y-4 text-sm">
                        <li class="flex gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                                <svg class="h-4.5 w-4.5" style="width:1.1rem;height:1.1rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                            </span>
                            <span>
                                <span class="block font-medium text-primary">Balai ti Arjud</span>
                                <span class="text-ink-muted">Glow &amp; Co. Beauty Lounge<br>Purok 5, Abra, Philippines</span>
                            </span>
                        </li>

                        <li class="flex gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                                <svg class="h-4.5 w-4.5" style="width:1.1rem;height:1.1rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                            </span>
                            <span>
                                <span class="block font-medium text-primary">Phone</span>
                                <a href="tel:+639000000000" class="text-ink-muted transition hover:text-primary">+63 900 000 0000</a>
                            </span>
                        </li>

                        <li class="flex gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold-light/50 text-gold-dark">
                                <svg class="h-4.5 w-4.5" style="width:1.1rem;height:1.1rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                            </span>
                            <span>
                                <span class="block font-medium text-primary">Email</span>
                                <a href="mailto:hello@balaitiarjud.test" class="text-ink-muted transition hover:text-primary">hello@balaitiarjud.test</a>
                            </span>
                        </li>
                    </ul>
                </x-ui.card>

                <x-ui.card title="Opening Hours">
                    <dl class="space-y-2.5 text-sm">
                        @foreach (\App\Models\SalonSetting::dayNames() as $key => $label)
                            @php $hours = $settings->operating_hours[$key] ?? null; @endphp
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-ink-muted">{{ $label }}</dt>
                                <dd class="font-medium text-primary">
                                    {{ $hours ? substr($hours[0], 0, 5).' – '.substr($hours[1], 0, 5) : 'Closed' }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </x-ui.card>

                <div class="rounded-card bg-primary p-6 text-center">
                    <p class="font-display text-lg font-semibold text-cream">Ready to book?</p>
                    <p class="mt-1.5 text-sm text-cream/75">Skip the wait — reserve your slot online in under a minute.</p>
                    <a href="{{ route('appointments.create') }}" class="mt-4 inline-flex rounded-pill bg-cream px-5 py-2.5 text-sm font-medium text-primary transition hover:bg-gold-light">
                        Book an Appointment
                    </a>
                </div>
            </aside>
        </div>
    </div>
@endsection
