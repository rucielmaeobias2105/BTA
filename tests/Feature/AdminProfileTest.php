<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin profile screen.
 *
 * Two things are pinned, one of them a bug this file exists for:
 *
 *   - the descriptive copy around the fields is gone; and
 *   - saving the profile works. `Admin\ProfileController::update()` used to call
 *     `$request->safe()` on a plain `Illuminate\Http\Request`, and that method
 *     only exists on a Form Request, via `ValidatesWhenResolvedTrait`. Every
 *     save died with `Method Illuminate\Http\Request::safe does not exist` before
 *     anything was written, so the page looked fine and did nothing.
 */
class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return $this->makeAdmin();
    }

    /* ------------------------------------------------------------------ */
    /* 1. The copy that came out                                           */
    /* ------------------------------------------------------------------ */

    public function test_the_profile_page_carries_no_descriptive_copy_around_its_fields(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Account', $html);
        $this->assertStringNotContainsString('Update the credentials', $html);
        $this->assertStringNotContainsString('Leave both fields blank', $html);
        $this->assertStringNotContainsString('Minimum of 8 characters', $html);
    }

    /**
     * Only the copy went. The password fields stay, because an admin still has
     * to be able to change their password, and a bare field with no label at all
     * is worse than one with a hint.
     */
    public function test_the_fields_are_all_still_there(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->getContent();

        foreach ([
            'first_name',
            'last_name',
            'email',
            'username',
            'password',
            'password_confirmation',
        ] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "{$field} should still be on the form.");
        }

        $this->assertStringContainsString('Change Password', $html);
        $this->assertStringContainsString('Personal Details', $html);
        $this->assertStringContainsString(route('admin.profile.update'), $html);
    }

    /* ------------------------------------------------------------------ */
    /* 2. Saving — the `safe()` bug                                        */
    /* ------------------------------------------------------------------ */

    /**
     * The regression. A plain `Request` has no `safe()`, so this used to throw
     * before the update ran.
     */
    public function test_saving_the_profile_updates_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.profile.edit'))
            ->patch(route('admin.profile.update'), [
                'first_name' => 'Ana',
                'last_name' => 'Reyes',
                'email' => 'ana@example.test',
                'username' => 'ana_reyes',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHas('status');

        $admin->refresh();

        $this->assertSame('Ana', $admin->first_name);
        $this->assertSame('Reyes', $admin->last_name);
        $this->assertSame('ana@example.test', $admin->email);
        $this->assertSame('ana_reyes', $admin->username);
    }

    /**
     * Blank password fields mean "keep the current one", which is what the
     * removed subtitle used to say. Asserted so the behaviour survives the copy.
     */
    public function test_a_blank_password_leaves_the_existing_one_alone(): void
    {
        $admin = $this->admin();
        $before = $admin->password;

        $this->actingAs($admin, 'admin')->patch(route('admin.profile.update'), [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.test',
            'username' => 'ana_reyes',
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame($before, $admin->fresh()->password);
    }

    /**
     * And a real one is applied — hashed, never stored as typed.
     *
     * The fix reads the validated payload instead of `safe()`, which returns the
     * plain confirmed password; writing that straight through would have stored
     * the admin's password in the clear. So this checks the hash, not just that
     * the field changed.
     */
    public function test_a_new_password_is_hashed_and_applied(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->patch(route('admin.profile.update'), [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.test',
            'username' => 'ana_reyes',
            'password' => 'a-longer-secret',
            'password_confirmation' => 'a-longer-secret',
        ])->assertSessionHasNoErrors();

        $admin->refresh();

        $this->assertNotSame('a-longer-secret', $admin->password);
        $this->assertTrue(Hash::check('a-longer-secret', $admin->password));
    }

    /** The minimum length still holds — the hint went, the rule did not. */
    public function test_a_short_password_is_still_refused(): void
    {
        $admin = $this->admin();
        $before = $admin->password;

        $this->actingAs($admin, 'admin')->patch(route('admin.profile.update'), [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.test',
            'username' => 'ana_reyes',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        // Refused, so nothing was written — including the password.
        $this->assertSame($before, $admin->fresh()->password);
    }
}