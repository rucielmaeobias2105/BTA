<?php

namespace Tests\Feature;

use App\Models\ServiceCategory;
use App\Services\BookingAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Calendar & Blocked Dates feature is gone, and it is gone completely.
 *
 * A removal like this fails in one of two ways. Either something still points at
 * the feature — a nav item, a route, a leftover method — and a user finds a dead
 * screen or a 500. Or the removal went further than intended and took something
 * the booking flow still depends on with it.
 *
 * Both are asserted here. The first group is "nothing is left"; the second is
 * "booking still works, and every rule that is left is still enforced".
 */
class CalendarRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeSalonSettings();
    }

    /* ------------------------------------------------------------------ */
    /* 1. The screen, the route and the table are gone                     */
    /* ------------------------------------------------------------------ */

    public function test_the_calendar_page_no_longer_exists(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/calendar')
            ->assertNotFound();
    }

    /**
     * A 404 rather than a 500.
     *
     * The distinction matters: a removed route that still resolved to a controller
     * that queried a dropped table would throw, which in development is an error
     * page and in production is a stack trace. 404 is the honest answer.
     *
     * @return array<string, array{0: string}>
     */
    public static function removedPaths(): array
    {
        return [
            'calendar index' => ['/admin/calendar'],
        ];
    }

    /** @dataProvider removedPaths */
    public function test_the_removed_path_is_a_clean_404(string $path): void
    {
        $this->actingAs($this->makeAdmin(), 'admin')
            ->get($path)
            ->assertNotFound();
    }

    public function test_the_blocked_dates_table_is_gone(): void
    {
        $this->assertFalse(
            Schema::hasTable('blocked_dates'),
            'The table went with the feature — see the drop migration.',
        );
    }

    /** The model went too, so nothing can query a table that is not there. */
    public function test_the_blocked_date_model_is_gone(): void
    {
        $this->assertFalse(
            class_exists(\App\Models\BlockedDate::class),
            'The model should have been deleted rather than left pointing at a dropped table.',
        );
    }

    public function test_the_calendar_controller_is_gone(): void
    {
        $this->assertFalse(class_exists(\App\Http\Controllers\Admin\CalendarController::class));
    }

    /* ------------------------------------------------------------------ */
    /* 2. Nothing points at the feature any more                           */
    /* ------------------------------------------------------------------ */

    /** No nav item: a sidebar link to a 404 is the most visible way to fail this. */
    public function test_the_sidebar_has_no_calendar_link(): void
    {
        $html = $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin')
            ->assertOk()
            ->getContent();

        preg_match('/<nav[^>]*aria-label="Admin">(.*?)<\/nav>/s', $html, $matches);

        $this->assertNotEmpty($matches);
        $this->assertStringNotContainsString('Calendar', $matches[1]);
        $this->assertStringNotContainsString('/admin/calendar', $matches[1]);
    }

    public function test_the_nav_map_has_no_calendar_key(): void
    {
        // `Nav::ADMIN` is a private const, so this goes through the only thing
        // that reads it: a route name that no longer exists resolves to no nav
        // key, and there is nothing stale to match.
        $this->assertNull(\App\Support\Nav::adminCurrent());
    }

/**
 * No *code* mentions the feature.
 *
 * Comments are stripped first, deliberately. A removed feature leaves explanatory
 * comments behind on purpose — "this went with the calendar" is the most useful
 * thing a future reader can find — and a grep that flagged those would push
 * towards deleting the explanations rather than the code.
 *
 * What has to be gone is the executable half: a model reference, a route name, a
 * method call, a query. Those are what turn a removed feature into a 500.
 */
public function test_no_code_still_references_the_feature(): void
{
    $patterns = [
        'BlockedDate',
        'blocked_dates',
        'admin.calendar',
        'blockedRanges',
        'blockedDatesByDate',
        'BLOCKED_MESSAGE',
        'isBlocked',
        'calendarMap',
    ];

    $offenders = [];

    foreach (['app', 'resources/views', 'routes', 'database/seeders'] as $root) {
        foreach ($this->phpFiles(base_path($root)) as $file) {
            $code = $this->stripComments((string) file_get_contents($file));

            foreach ($patterns as $pattern) {
                if (str_contains($code, $pattern)) {
                    $offenders[] = str_replace(base_path().'\\', '', $file).' → '.$pattern;
                }
            }
        }
    }

    $this->assertSame([], $offenders, "Still referencing the removed feature:\n".implode("\n", $offenders));
}

/**
 * Remove `//`, `/* *\/` and Blade `{{-- --}}` comments.
 *
 * Not a real parser: the goal is only to stop an explanatory comment from
 * failing an assertion about code, and an over-eager regex that ate a string
 * literal would be a false negative rather than a false positive — which is the
 * safer direction for this check.
 */
private function stripComments(string $code): string
{
    $code = preg_replace('/\{\{--.*?--\}\}/s', '', $code);
    $code = preg_replace('#/\*.*?\*/#s', '', $code ?? '');
    $code = preg_replace('#(^|\s)//[^\n]*#m', '$1', $code ?? '');

    return $code ?? '';
}

    /**
     * @return array<int, string>
     */
    private function phpFiles(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php'], true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /* ------------------------------------------------------------------ */
    /* 3. Booking still works, and still enforces what is left             */
    /* ------------------------------------------------------------------ */

    /**
     * The flow end to end, with no blocked-date check anywhere in it.
     *
     * This is the test that would fail if the removal had taken something the
     * booking form depends on — the most likely casualty being operating hours,
     * which are the only closure mechanism left.
     */
    public function test_booking_still_works_without_any_blocked_date_check(): void
    {
        $user = $this->makeUser();
        $service = $this->makeService();
        $date = Carbon::parse(today()->addDays(3));

        $this->actingAs($user)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), [
                'customer_name' => 'Ana Reyes',
                'customer_phone' => '09171234567',
                'preferred_date' => $date->toDateString(),
                'preferred_time' => '10:00',
                'special_request' => '',
                'services' => [['service_id' => $service->id, 'quantity' => 1]],
                'last_services_availed_note' => 'Glow Manicure',
                'agree_terms' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_the_slot_lookup_still_answers(): void
    {
        $date = Carbon::parse(today()->addDays(3));

        $payload = $this->getJson('/book/slots?date='.$date->toDateString())
            ->assertOk()
            ->json();

        $this->assertTrue($payload['open']);
        $this->assertContains('10:00', $payload['slots']);

        // The key is still present and permanently empty — see the note in
        // AppointmentController::slots().
        $this->assertArrayHasKey('blockedDates', $payload);
        $this->assertSame([], (array) $payload['blockedDates']);
    }

    /** Operating hours are now the only closure mechanism, so they still bite. */
    public function test_a_closed_weekday_is_still_unbookable(): void
    {
        $this->makeSalonSettings()->update(['operating_hours' => ['monday' => ['09:00', '17:00']]]);

        $sunday = Carbon::parse(today()->next(Carbon::SUNDAY));

        $this->assertSame(
            ['We are closed on Sundays.'],
            BookingAvailability::make()->dateProblems($sunday->toDateString()),
        );

        $this->actingAs($this->makeUser())
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), [
                'customer_name' => 'Ana Reyes',
                'customer_phone' => '09171234567',
                'preferred_date' => $sunday->toDateString(),
                'preferred_time' => '10:00',
                'special_request' => '',
                'services' => [['service_id' => $this->makeService()->id, 'quantity' => 1]],
                'last_services_availed_note' => 'Glow Manicure',
                'agree_terms' => '1',
            ])
            ->assertSessionHasErrors('preferred_date');

        $this->assertDatabaseCount('appointments', 0);
    }

    /** Rescheduling is unaffected too — it went through the same availability class. */
    public function test_rescheduling_still_works(): void
    {
        $user = $this->makeUser();

        $appointment = $this->makeAppointment($user, $this->makeService(), [
            'preferred_date' => today()->addDays(3),
            'preferred_time' => '10:00',
            'status' => 'confirmed',
        ]);

        $newDate = Carbon::parse(today()->addDays(5));

        $this->actingAs($user)
            ->from(route('appointments.reschedule', $appointment))
            ->patch(route('appointments.reschedule.update', $appointment), [
                'preferred_date' => $newDate->toDateString(),
                'preferred_time' => '11:00',
                'reason' => 'Work ran late.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($newDate->toDateString(), $appointment->fresh()->preferred_date->toDateString());
    }

    /* ------------------------------------------------------------------ */
    /* 4. What replaced it                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Operating hours are still a first-class thing: the model reads them, the
     * booking rules use them, and the customer-facing pages print them.
     *
     * Worth stating because "remove the calendar" could reasonably have been read
     * as "remove closing days too", and that would have quietly made every date
     * bookable.
     */
    public function test_operating_hours_survived_the_removal(): void
    {
        $settings = \App\Models\SalonSetting::current();

        $this->assertNotEmpty($settings->operating_hours, 'The salon still has hours.');
        $this->assertTrue($settings->isOpenOn(today()));
        $this->assertNotEmpty($settings->slotsFor(today()->addDay()));

        // And they are still published on the contact page.
        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertStringContainsString('Operating Hours', $html);
    }

    /**
     * Categories keep their colours.
     *
     * The grid that painted them is gone, so the stored colour is now read by the
     * model rather than by a grid cell — but it is still assigned, still editable
     * and still what `categoryColor()` resolves to.
     */
    public function test_service_categories_keep_their_colour_and_palette(): void
    {
        $this->makeService(['category' => 'Manicure & Pedicure']);

        $category = ServiceCategory::where('name', 'Manicure & Pedicure')->sole();

        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $category->color);
        $this->assertMatchesRegularExpression('/^rgba\(\d+, \d+, \d+, 0\.16\)$/', $category->tint);

        // And the palette it draws from is untouched by the removal.
        $this->assertSame('#E11D48', $category->color, 'The first palette slot.');
        $this->assertNotSame('#7A241B', $category->color, 'Not the house fallback.');
    }
}