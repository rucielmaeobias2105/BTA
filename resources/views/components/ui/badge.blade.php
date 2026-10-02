@props(['status' => 'pending', 'label' => null, 'dot' => true])

@php
    use App\Support\BadgeTone;

    /*
     * The tone → class and tone → label tables live in
     * `App\Support\BadgeTone` rather than here. This component renders a badge
     * server-side, but the Appointment Details dialog binds the same tones
     * client-side from its payload, and a map written into this markup would
     * be a second copy that nothing keeps in step.
     */
    $class = BadgeTone::classFor($status);

    $label ??= BadgeTone::labelFor($status);
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$class]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
    @endif
    {{ $label }}
</span>
