<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\MessageBag;

/**
 * Turns a bag of validation errors into one line of toast copy.
 *
 * Every form used to open with a red `x-ui.errors` block pinned in the page
 * flow: a bold title, then one bullet per failed field. That is a good pattern
 * for a page whose main content is a report, and a poor one for a form — it
 * pushes the fields the person needs to fix down the screen, and it stays there
 * competing with them long after it has been read.
 *
 * The app had already settled the other half of this question: a toast is the
 * only *success* style anywhere in it. Success feedback floats, auto-dismisses
 * and never shifts the content it is confirming. Failure feedback now matches,
 * so a form is told the same way whether the save worked or not, and the form
 * itself is the first thing on the page either way.
 *
 * `x-ui.errors` is no longer rendered by any view. This class exists so the
 * wording is decided in one place rather than in each form's redirect — 24 of
 * them flashed their own titles, and none of those titles survive the move,
 * because the handler cannot know which form it is answering.
 */
final class ValidationToast
{
    /**
     * The toast copy for a failed submission.
     *
     * Messages are joined into a single line rather than bulleted: a toast is
     * one small panel, and a `<ul>` inside it wraps unpredictably at the narrow
     * widths it is most likely to be seen at. The messages are already written
     * as full sentences — "The expiry date cannot be before the date it came in"
     * — so joining them reads as one sentence per problem.
     *
     * Duplicates are dropped: "The name field is required." arrives once per
     * field that was left blank, and a toast repeating the same sentence four
     * times is worse than one that lists the fields that failed.
     *
     * Both shapes are accepted because `ValidationException::errors()` does not
     * agree with itself across the framework's own signatures — it is annotated
     * as a `MessageBag` and returns `field => [messages]` at runtime. So the
     * list is flattened before it is joined, which is right for either, and
     * non-strings are dropped rather than rendered as "Array".
     *
     * @param  MessageBag|array  $errors  What Laravel is about to redirect with.
     * @return string  Empty when there is nothing to say, so the caller can skip the flash.
     */
    public static function messageFor(MessageBag|array $errors): string
    {
        $messages = $errors instanceof MessageBag ? $errors->all() : $errors;

        $messages = array_filter(Arr::flatten($messages), 'is_string');

        if ($messages === []) {
            return '';
        }

        return implode(' ', array_unique($messages));
    }
}
