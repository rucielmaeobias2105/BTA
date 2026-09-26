@php
    /** Shared 4-step indicator for the password reset wizard. */
    $steps = [
        1 => 'Email',
        2 => 'Verify Code',
        3 => 'New Password',
        4 => 'Confirm Password',
    ];
    $current = $currentStep ?? 1;
@endphp

<div class="mb-8">
    <ol class="flex items-center gap-2" aria-label="Password reset progress">
        @foreach ($steps as $number => $label)
            @php $isDone = $number < $current; $isCurrent = $number === $current; @endphp
            <li class="flex flex-1 flex-col items-center gap-1.5">
                <span @class([
                    'flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-semibold transition',
                    'bg-primary text-cream' => $isDone || $isCurrent,
                    'bg-linen text-ink-muted ring-1 ring-primary/15' => ! $isDone && ! $isCurrent,
                ])>
                    @if ($isDone)
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span @class([
                    'text-center text-[10px] font-medium leading-tight',
                    'text-primary' => $isCurrent,
                    'text-ink-muted' => ! $isCurrent,
                ])>{{ $label }}</span>
            </li>

            @if (! $loop->last)
                <span aria-hidden="true" class="-mt-5 h-px flex-1 {{ $number < $current ? 'bg-primary' : 'bg-primary/15' }}"></span>
            @endif
        @endforeach
    </ol>
</div>
