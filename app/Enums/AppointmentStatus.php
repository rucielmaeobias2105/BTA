<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * The admin list's status menu, keyed by the status the booking is in now.
     *
     * This one table is both the menu and the rule. The dropdown renders from
     * it and `AppointmentController::updateStatus()` re-checks it, so the menu is
     * a convenience rather than the authority — posting a transition it does
     * not list is refused.
     *
     * Every menu is a subset of the four statuses the salon actually works
     * with: Confirmed, In Progress, Completed and Declined. There is no separate
     * "Cancelled" action — declining is how the salon stops a booking, and one
     * word for it keeps the history readable.
     *
     * Three properties matter as much as the individual entries:
     *
     *   - Every list is a subset of `pending`'s keys, and an *empty* list means
     *     "finished" — see `completed` below and `isLocked()`.
     *   - No entry resolves to the status it is keyed by, so a list that is
     *     non-empty always has something to pick, and re-posting the current
     *     status stays a no-op.
     *   - `declined` is the only key that reaches the stored `cancelled` state.
     *     The appointments table has no declined column and adding one would
     *     mean a migration on two engines, so resolveAction() maps it, and the
     *     state still reads "Cancelled" wherever it is *described* — a booking
     *     the customer called off is a cancellation, whatever the admin calls
     *     the button.
     *
     * @var array<string, array<string, string>>
     */
    private const MENUS = [
        // Waiting to be answered: everything is on the table.
        'pending' => [
            'confirmed' => 'Confirmed',
            'in_progress' => 'Start / In Progress',
            'declined' => 'Declined',
            'completed' => 'Completed',
        ],
        // Accepted: no longer a request, but not started either.
        'confirmed' => [
            'in_progress' => 'Start / In Progress',
            'declined' => 'Declined',
            'completed' => 'Completed',
        ],
        // On the chair: only finishing or stopping make sense.
        'in_progress' => [
            'completed' => 'Completed',
            'declined' => 'Declined',
        ],
        // Finished. Terminal: a completed service is a record of work that
        // happened, and re-opening it would be rewriting history rather than
        // correcting a mistake.
        //
        // This is deliberately the one empty menu. An earlier version offered
        // 'Reopen / In Progress' from here, on the grounds that a mis-filed
        // booking needs an escape hatch — but a disabled dropdown was then
        // reintroduced on purpose, so the escape hatch is gone with it. The
        // recovery path for a wrongly-completed booking is now a database
        // correction, not a UI affordance; that trade is the point of the
        // terminal state.
        //
        // Declining a finished visit would be a lie about what happened, and
        // re-confirming it is meaningless, so an empty list is all that is left.
        'completed' => [],
        // Declined or called off. Re-accepting it puts the booking — and the
        // stock it released — back.
        'cancelled' => [
            'confirmed' => 'Restore / Confirmed',
            'in_progress' => 'Restore / In Progress',
            'completed' => 'Restore / Completed',
        ],
    ];

    /**
     * The actions offered from this status, as dropdown value => label.
     *
     * @return array<string, string>
     */
    public function menu(): array
    {
        return self::MENUS[$this->value];
    }

    /**
     * Whether this status is finished — nothing further can be done to it.
     *
     * Derived from an empty menu rather than being a hand-maintained flag, so
     * it cannot disagree with the transitions: a status with no way out of it
     * is exactly a status whose control should be disabled. That is what lets
     * the appointments table decide to grey out a dropdown by asking this
     * instead of naming `Completed`, which would need changing again the next
     * time a status became terminal.
     */
    public function isLocked(): bool
    {
        return $this->menu() === [];
    }

    /** @return array<int, string> */
    public static function actionKeys(): array
    {
        return array_keys(self::MENUS['pending']);
    }

    /** The state a dropdown key puts the appointment into. */
    public static function resolveAction(string $action): self
    {
        return match ($action) {
            'declined' => self::Cancelled,
            default => self::from($action),
        };
    }

    public function canTransitionTo(self $target): bool
    {
        // Staying put is always allowed: the dropdown renders the current
        // status as its selected option, and re-posting it must be a no-op
        // rather than an error.
        if ($target === $this) {
            return true;
        }

        return array_key_exists($action = $this->actionKeyFor($target), $this->menu());
    }

    /**
     * The dropdown key that reaches `$target` from here, or null when this
     * status has no key for it.
     *
     * Needed because two keys can resolve to one state — a booking that has
     * moved on is offered 'cancelled' rather than the redundant 'declined' that
     * means the same thing.
     */
    public function actionKeyFor(self $target): ?string
    {
        foreach ($this->menu() as $key => $label) {
            if (self::resolveAction($key) === $target) {
                return $key;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Tailwind badge modifier used by <x-ui.badge>. */
    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Confirmed => 'confirmed',
            self::InProgress => 'in_progress',
            self::Completed => 'completed',
            self::Cancelled => 'cancelled',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Customer-selectable status filter options (no input fields, action buttons only).
     *
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        return [
            'all' => 'All',
            self::Pending->value => 'Pending',
            self::Confirmed->value => 'Confirmed',
            self::Completed->value => 'Completed',
            self::Cancelled->value => 'Cancelled',
        ];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Statuses that a customer may still act on: cancel, reschedule.
     *
     * Deletion is deliberately not here — it runs the other way round, on the
     * *settled* statuses. See `Appointment::canBeDeletedByCustomer()`.
     */
    public function isCustomerActionable(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    /**
     * Whether this status means the booking is finished with.
     *
     * The single rule behind every removal in the admin panel: only a Completed or
     * Cancelled appointment can be archived, and only one of those can be deleted
     * outright. Stated on the enum rather than in a controller so the delete
     * route, the archive route and the nightly auto-archive cannot drift apart.
     *
     * The statuses deliberately excluded are the live ones — Pending, Confirmed
     * and above all In Progress. A booking on the chair right now is the one row
     * the salon must never be able to lose, and a booking nobody has answered yet
     * is not history to be tidied away.
     *
     * "Rescheduled", from the original wording of this rule, is not a status in
     * this application and does not need to become one: rescheduling moves a
     * booking's date and leaves its status untouched, so a rescheduled booking is
     * still Pending or Confirmed and is covered by exactly this check. What makes
     * a booking safe to remove is that it has finished or been called off — never
     * that it was moved.
     */
    public function isSettled(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * The stored values of the settled statuses, for `whereIn()`.
     *
     * @return array<int, string>
     */
    public static function settledValues(): array
    {
        return [
            self::Completed->value,
            self::Cancelled->value,
        ];
    }

    /**
     * The stored values of the live statuses — everything still being worked.
     *
     * Used by the dashboard and the bell, which both ask "what needs the salon to
     * do something", and by the auto-archive, which must only touch the settled
     * rows and so needs to know the opposite set.
     *
     * @return array<int, string>
     */
    public static function liveValues(): array
    {
        return [
            self::Pending->value,
            self::Confirmed->value,
            self::InProgress->value,
        ];
    }
}
