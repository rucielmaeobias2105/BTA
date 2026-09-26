<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the admin role matrix.
 *
 * Three admin roles share one panel: Super Admin holds every ability, Manager
 * runs the salon but cannot edit legal text or destroy customer records, and
 * Staff get the appointment queue plus read-only reference screens. The same
 * AdminRole::abilities() table drives the `admin.role:*` middleware on the
 * routes and the `@can` directives in the views, so these tests assert the
 * server-side half and the page half together.
 */
class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeSalonSettings();
    }

    private function admin(AdminRole $role): Admin
    {
        return Admin::create([
            'first_name' => 'Test',
            'last_name' => $role->label(),
            'username' => $role->value,
            'email' => $role->value.'@example.test',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_the_ability_table_covers_every_role_without_gaps(): void
    {
        foreach (AdminRole::abilities() as $ability) {
            // Super Admin implicitly holds everything.
            $this->assertTrue(
                AdminRole::SuperAdmin->can($ability),
                "Super Admin should be able to {$ability}.",
            );
        }

        $this->assertNotEmpty(AdminRole::Manager->abilitiesFor(AdminRole::Manager));
        $this->assertNotEmpty(AdminRole::Staff->abilitiesFor(AdminRole::Staff));

        // A narrower role must never hold an ability the wider one lacks.
        $this->assertTrue(
            count(AdminRole::abilitiesFor(AdminRole::Manager)) < count(AdminRole::abilities()),
        );
        $this->assertTrue(
            count(AdminRole::abilitiesFor(AdminRole::Staff)) < count(AdminRole::abilitiesFor(AdminRole::Manager)),
        );
    }

    public function test_a_super_admin_reaches_every_admin_screen(): void
    {
        $admin = $this->admin(AdminRole::SuperAdmin);

        foreach (['/', 'appointments', 'catalog', 'services', 'inventory', 'tags', 'calendar', 'users', 'terms', 'reviews', 'reports', 'promos', 'messages'] as $path) {
            $this->actingAs($admin, 'admin')
                ->get('/admin/'.$path)
                ->assertOk();
        }
    }

    public function test_a_manager_runs_the_salon_but_cannot_edit_legal_text(): void
    {
        $manager = $this->admin(AdminRole::Manager);

        // Manager runs the day to day.
        foreach (['/', 'appointments', 'catalog', 'services', 'inventory', 'tags', 'calendar', 'reports', 'promos', 'messages'] as $path) {
            $this->actingAs($manager, 'admin')
                ->get('/admin/'.$path)
                ->assertOk();
        }

        // ...but T&C editing stays with the Super Admin.
        $this->actingAs($manager, 'admin')->get('/admin/terms')->assertOk();
        $this->actingAs($manager, 'admin')->get('/admin/terms/create')->assertForbidden();
    }

    public function test_a_manager_cannot_destroy_a_customer_record(): void
    {
        $manager = $this->admin(AdminRole::Manager);
        $user = $this->makeUser();

        $this->actingAs($manager, 'admin')
            ->delete(route('admin.users.destroy', $user))
            ->assertForbidden();

        $this->assertNotSoftDeleted($user);
    }

    public function test_a_super_admin_can_destroy_a_customer_record(): void
    {
        $admin = $this->admin(AdminRole::SuperAdmin);

        // A second row so the target's id cannot collide with the admin's own
        // id and trip the "cannot delete yourself" guard.
        $this->makeUser();
        $user = $this->makeUser();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted($user);
    }

    public function test_staff_get_the_appointment_queue_and_read_only_reference(): void
    {
        $staff = $this->admin(AdminRole::Staff);

        foreach (['/', 'appointments', 'catalog', 'services', 'inventory', 'tags', 'calendar', 'users', 'reviews'] as $path) {
            $this->actingAs($staff, 'admin')
                ->get('/admin/'.$path)
                ->assertOk();
        }
    }

    public function test_staff_are_denied_the_management_screens(): void
    {
        $staff = $this->admin(AdminRole::Staff);

        foreach (['terms', 'reports', 'promos', 'messages'] as $path) {
            $this->actingAs($staff, 'admin')
                ->get('/admin/'.$path)
                ->assertForbidden();
        }
    }

    public function test_staff_cannot_reach_any_write_route(): void
    {
        $staff = $this->admin(AdminRole::Staff);
        $service = $this->makeService();
        $user = $this->makeUser();

        $this->actingAs($staff, 'admin')->get('/admin/services/create')->assertForbidden();
        $this->actingAs($staff, 'admin')->get('/admin/inventory/create')->assertForbidden();
        $this->actingAs($staff, 'admin')->get('/admin/terms/create')->assertForbidden();

        $this->actingAs($staff, 'admin')
            ->delete(route('admin.services.destroy', $service))
            ->assertForbidden();

        $this->actingAs($staff, 'admin')
            ->delete(route('admin.users.destroy', $user))
            ->assertForbidden();

        $this->assertNotSoftDeleted($service);
        $this->assertNotSoftDeleted($user);
    }

    public function test_a_staff_member_can_still_work_the_appointment_queue(): void
    {
        $staff = $this->admin(AdminRole::Staff);

        $this->actingAs($staff, 'admin')
            ->get('/admin/appointments')
            ->assertOk();
    }

    public function test_the_sidebar_hides_screens_the_role_cannot_open(): void
    {
        $staff = $this->admin(AdminRole::Staff);

        $html = $this->actingAs($staff, 'admin')->get('/admin')->assertOk()->getContent();

        // Narrow to the nav itself: the dashboard body links to some of these
        // URLs too, so asserting on the whole page would pass even if the
        // sidebar rendered nothing at all.
        preg_match('/<nav[^>]*aria-label="Admin">(.*?)<\/nav>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'The admin sidebar nav should be present.');

        $nav = $matches[1];

        // Staff keep the queue and the read-only screens...
        $this->assertStringContainsString('Appointments', $nav);
        $this->assertStringContainsString('Services', $nav);
        $this->assertStringContainsString('Calendar', $nav);
        $this->assertStringContainsString('Registered Users', $nav);

        // ...but are never offered a link that would 403.
        $this->assertStringNotContainsString('Terms', $nav);
        $this->assertStringNotContainsString('Reports', $nav);
        $this->assertStringNotContainsString('Promo', $nav);
    }

    public function test_the_sidebar_shows_every_screen_to_a_super_admin(): void
    {
        $admin = $this->admin(AdminRole::SuperAdmin);

        $html = $this->actingAs($admin, 'admin')->get('/admin')->assertOk()->getContent();

        preg_match('/<nav[^>]*aria-label="Admin">(.*?)<\/nav>/s', $html, $matches);
        $this->assertNotEmpty($matches);
        $nav = $matches[1];

        foreach (['Dashboard', 'Appointments', 'Calendar', 'Services &amp; Items', 'Services', 'Promo', 'Inventory', 'Low-Stock Tags', 'Registered Users', 'Reviews', 'Terms &amp; Conditions', 'Reports'] as $label) {
            $this->assertStringContainsString($label, $nav);
        }
    }

    public function test_write_buttons_are_hidden_from_a_read_only_role(): void
    {
        $staff = $this->admin(AdminRole::Staff);
        $service = $this->makeService();
        $user = $this->makeUser();

        $services = $this->actingAs($staff, 'admin')->get('/admin/services')->assertOk()->getContent();
        $this->assertStringNotContainsString(route('admin.services.create'), $services);
        $this->assertStringNotContainsString(route('admin.services.edit', $service), $services);
        $this->assertStringNotContainsString(route('admin.services.destroy', $service), $services);

        $inventory = $this->actingAs($staff, 'admin')->get('/admin/inventory')->assertOk()->getContent();
        $this->assertStringNotContainsString(route('admin.inventory.create'), $inventory);

        $users = $this->actingAs($staff, 'admin')->get('/admin/users')->assertOk()->getContent();

        // The deactivate form posts to a distinct /status URL, so it can be
        // matched by URL. The delete form shares its URL with the "View"
        // link, so it is matched on its confirm handler instead.
        $this->assertStringNotContainsString(route('admin.users.status', $user), $users);
        $this->assertStringNotContainsString("return confirm('Delete", $users);
        $this->assertStringContainsString(route('admin.users.show', $user), $users);
    }

    public function test_a_customer_session_never_reaches_the_admin_panel(): void
    {
        $customer = $this->makeUser();

        $this->actingAs($customer)
            ->get('/admin')
            ->assertRedirect(route('admin.login'));
    }
}
