<?php

namespace App\Support;

/**
 * Works out the SMTP `scheme` from the encryption setting people actually set.
 *
 * Laravel 11 dropped the `encryption` key from the SMTP mailer. `MailManager`
 * reads `scheme` and hands it straight to the Symfony transport as the scheme,
 * deriving one from the port only when it is null. So `MAIL_ENCRYPTION=tls` — the
 * setting every guide, tutorial and `.env.example` written before Laravel 11
 * recommends — is read by nothing at all: the transport quietly falls back to its
 * port default, and the setting looks like it is working while having no effect.
 *
 * That is worth handling rather than working around, because the failure is
 * invisible and the symptom is a connection problem on a port that should have
 * negotiated TLS.
 *
 * Extracted from `config/mail.php` so it can be named, documented and tested
 * rather than being an anonymous closure evaluated once at boot — a config file
 * is a bad place for a rule this worth being sure about.
 */
final class SmtpScheme
{
    /**
     * The scheme for an `MAIL_ENCRYPTION` value, or null to let Laravel decide.
     *
     *   tls / starttls -> smtp   STARTTLS, upgraded in band. Port 587.
     *   ssl / smtps    -> smtps  implicit TLS from the first byte. Port 465.
     *
     * Anything else returns `$fallback` (normally `MAIL_SCHEME`) rather than a
     * guess. A typo should surface as a transport error the operator can read,
     * not be silently downgraded to "no encryption, and it worked fine locally".
     *
     * @param  string|null  $encryption  The raw `MAIL_ENCRYPTION` value.
     * @param  string|null  $fallback  Used when `$encryption` is unset or unknown.
     */
    public static function forEncryption(?string $encryption, ?string $fallback = null): ?string
    {
        if ($encryption === null || trim($encryption) === '') {
            return $fallback;
        }

        return match (strtolower(trim($encryption))) {
            'tls', 'starttls' => 'smtp',
            'ssl', 'smtps' => 'smtps',
            default => $fallback,
        };
    }
}
