@php
    use App\Support\PromoBanner;
@endphp

<footer class="mt-auto border-t border-primary/10 bg-cream">
    {{-- Active promo banner surfaces here too, not only in notifications. --}}
    @if ($promo = PromoBanner::current())
        <div class="border-b border-gold/30 bg-gold/10">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-3 px-4 py-2.5 text-center text-sm sm:px-6 lg:px-8">
                <span class="badge badge-gold">Promo</span>
                <span class="text-ink">{{ $promo->title }} — {{ \Illuminate\Support\Str::limit(strip_tags($promo->description), 90) }}</span>
            </div>
        </div>
    @endif

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-10 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-brand.logo size="md" image="images/logo.png" />
              <!--  <p class="mt-4 max-w-sm text-sm leading-relaxed text-ink-muted">
                    Where beauty meets hospitality. Book your services online and let our therapists
                    pamper you — from glow manicures and lash extensions to relaxing massage and
                    spa packages in the heart of Abra.
                </p> -->
            </div>

            <!--
            <div>
                <h4 class="mb-4 font-display text-sm font-semibold uppercase tracking-wider text-primary">Explore</h4>
                <ul class="space-y-2.5 text-sm text-ink-muted">
                    <li><a href="{{ route('services.index') }}" class="transition hover:text-primary">Browse Services</a></li>
                    <li><a href="{{ route('services.refined') }}" class="transition hover:text-primary">Refined Grid View</a></li>
                    <li><a href="{{ route('appointments.create') }}" class="transition hover:text-primary">Book Appointment</a></li>
                    <li><a href="{{ route('about') }}" class="transition hover:text-primary">About Us</a></li>
                </ul>
            </div> -->

            <div>
                <h4 class="mb-4 font-display text-sm font-semibold uppercase tracking-wider text-primary">Visit Us</h4>
                <ul class="space-y-2.5 text-sm text-ink-muted">
                    <li>Balai ti Arjud, Glow &amp; Co. Beauty Lounge</li>
                    <li>Unit 4, 2 wins Bldg. Abra Kalinga Rd. Patucannay, Tayum, Abra</li>
                    <li>
                        <a href="tel:+639000000000" class="transition hover:text-primary">+63 965 6244 405</a>
                    </li>
                    <li>
                        <a href="mailto:hello@balaitiarjud.test" class="transition hover:text-primary">hello@balaitiarjud.test</a>
                    </li>
                </ul>
            </div>
        </div>

      <!--  <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-primary/10 pt-6 sm:flex-row">
            <p class="text-xs text-ink-muted">&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</p>
            <a href="{{ route('admin.login') }}" class="text-xs text-ink-muted/70 transition hover:text-primary">Staff Portal</a>
        </div> -->
    </div>
</footer>
