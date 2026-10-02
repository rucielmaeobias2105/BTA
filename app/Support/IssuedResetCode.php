<?php

namespace App\Support;

/**
 * The outcome of issuing a reset code.
 *
 * `code` is the plaintext six-digit code, for tests and for anything that has to
 * hand it over out of band. `delivered` records whether the notification actually
 * left the server — which is not the same question as whether a code was issued.
 *
 * The two come apart in exactly one situation, and it is the situation that
 * matters: the code is stored and hashed *before* the send is attempted, so a
 * rejected SMTP credential still leaves a perfectly usable reset sitting in the
 * database that nobody will ever receive. Callers used to have no way to tell that
 * case from a successful send and so reported success either way, which left a
 * broken mail server presenting as a person who forgot to check their inbox.
 */
final class IssuedResetCode
{
    public function __construct(
        public readonly string $code,
        public readonly bool $delivered,
    ) {}
}