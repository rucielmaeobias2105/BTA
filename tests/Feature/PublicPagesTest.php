<?php

namespace Tests\Feature;

use App\Notifications\PasswordResetCodeNotification;
use App\Models\PasswordResetCode;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Guards the public, unauthenticated surface: the landing page, both service
 * browsing variants, contact, the T&C pages and the admin login screen.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_renders(): void
    {
        $this->makeSalonSettings();

        $this->get('/')
            ->assertOk()
            ->assertSee('Balai ti Arjud')
            ->assertSee('Glow &amp; Co. Beauty Lounge', false)
            ->assertSee('Book an Appointment')
            ->assertSee('images/hero2.jpg', false);
    }

    public function test_browsing_services_requires_no_authentication(): void
    {
        $this->makeSalonSettings();
        $this->makeService(['name' => 'Gelish Manicure', 'category' => 'Nail Care']);

        $this->get('/services')
            ->assertOk()
            ->assertSee('Browse Services')
            ->assertSee('Gelish Manicure');
    }

    public function test_services_can_be_filtered_by_category_and_price(): void
    {
        $this->makeSalonSettings();
        $this->makeService(['name' => 'Cheap Service', 'category' => 'Nail Care', 'price' => 300]);
        $this->makeService(['name' => 'Fancy Service', 'category' => 'Makeup', 'price' => 5000]);

        $this->get('/services?category=Nail Care')
            ->assertOk()
            ->assertSee('Cheap Service')
            ->assertDontSee('Fancy Service');

        $this->get('/services?min_price=1000')
            ->assertOk()
            ->assertSee('Fancy Service')
            ->assertDontSee('Cheap Service');
    }

    public function test_the_refined_grid_view_renders(): void
    {
        $this->makeSalonSettings();
        $this->makeService(['name' => 'Spa Pedicure', 'category' => 'Nail Care']);

        $this->get('/services-grid')
            ->assertOk()
            ->assertSee('Browse by Category')
            ->assertSee('Spa Pedicure');

        $this->get('/services-grid?category=Nail Care')
            ->assertOk()
            ->assertSee('Spa Pedicure');
    }

    public function test_a_service_detail_page_renders_with_variants(): void
    {
        $this->makeSalonSettings();

        $service = $this->makeService(['name' => 'Signature Blowout', 'slug' => 'signature-blowout']);
        $service->variants()->create(['name' => 'Long Hair', 'price' => 550]);

        $this->get('/services/signature-blowout')
            ->assertOk()
            ->assertSee('Signature Blowout')
            ->assertSee('Long Hair')
            ->assertSee('Book Now');
    }

    public function test_inactive_services_are_not_publicly_reachable(): void
    {
        $this->makeSalonSettings();

        $service = $this->makeService([
            'name' => 'Hidden Service',
            'slug' => 'hidden-service',
            'is_active' => false,
        ]);

        $this->get('/services/'.$service->slug)->assertNotFound();
    }

    public function test_the_contact_page_renders(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('Contact Us')
            ->assertSee('Inquiry Topic');
    }

    public function test_a_visitor_can_send_a_contact_message(): void
    {
        $this->post('/contact', [
            'name' => 'Liza Mercado',
            'email' => 'liza@example.test',
            'topic' => 'pricing_question',
            'message' => 'Do you have a package for hair colour plus a blowout?',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'liza@example.test',
            'topic' => 'pricing_question',
        ]);
    }

    public function test_a_contact_message_validates_its_fields(): void
    {
        $this->post('/contact', [
            'name' => '',
            'email' => 'nope',
            'topic' => 'not_a_topic',
            'message' => 'short',
        ])->assertSessionHasErrors(['name', 'email', 'topic', 'message']);
    }

    public function test_the_about_page_renders(): void
    {
        $this->get('/about')->assertOk()->assertSee('About Us');
    }

    public function test_the_admin_login_page_is_public(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Admin Login')
            ->assertSee('Staff Portal');
    }

    public function test_the_password_reset_flow_starts_at_the_email_step(): void
    {
        // The new-password step is not reachable before a code is verified.
        $this->get('/password/reset')->assertForbidden();

        $this->get('/password')
            ->assertOk()
            ->assertSee('Forgot Password');
    }

    public function test_a_password_reset_code_is_issued_and_the_code_step_unlocks(): void
    {
        Notification::fake();

        $user = $this->makeUser(['email' => 'juan@example.test']);

        $this->post('/password', ['email' => 'juan@example.test'])
            ->assertRedirect(route('password.code'));

        $this->get('/password/code')
            ->assertOk()
            ->assertSee('Verify Email')
            ->assertSee('juan@example.test');

        // Only the hash is persisted — never the plaintext code.
        $record = PasswordResetCode::where('email', 'juan@example.test')->first();

        $this->assertNotNull($record);
        $this->assertNotSame('000000', $record->code_hash);
        $this->assertTrue($record->expires_at->isFuture());

        Notification::assertSentTo($user, PasswordResetCodeNotification::class);
    }

    public function test_the_password_flow_never_reveals_whether_an_email_is_registered(): void
    {
        Notification::fake();

        $this->makeUser(['email' => 'juan@example.test']);

        $this->post('/password', ['email' => 'nobody@example.test'])
            ->assertRedirect(route('password.code'))
            ->assertSessionHas('status');
    }

    public function test_a_wrong_reset_code_is_rejected(): void
    {
        Notification::fake();

        $this->makeUser(['email' => 'juan@example.test']);

        $this->post('/password', ['email' => 'juan@example.test']);
        $this->post('/password/code', ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_valid_reset_code_allows_a_new_password(): void
    {
        Notification::fake();

        $user = $this->makeUser(['email' => 'juan@example.test']);

        $this->post('/password', ['email' => 'juan@example.test']);

        // Read the plaintext straight out of the faked notification.
        $code = null;
        Notification::assertSentTo(
            $user,
            PasswordResetCodeNotification::class,
            function ($notification) use (&$code) {
                $code = $notification->code;

                return true;
            },
        );

        $this->post('/password/code', ['code' => $code])
            ->assertRedirect(route('password.reset'));

        $this->get('/password/reset')->assertOk()->assertSee('New Password');

        $this->post('/password/reset', [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(password_verify('newpassword123', $user->fresh()->password));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_reset_code_cannot_be_reused(): void
    {
        Notification::fake();

        $user = $this->makeUser(['email' => 'juan@example.test']);

        $this->post('/password', ['email' => 'juan@example.test']);

        $code = null;
        Notification::assertSentTo(
            $user,
            PasswordResetCodeNotification::class,
            function ($notification) use (&$code) {
                $code = $notification->code;

                return true;
            },
        );

        $this->post('/password/code', ['code' => $code]);
        $this->post('/password/reset', [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $this->assertNotNull(
            PasswordResetCode::where('email', 'juan@example.test')->first()->used_at,
        );
    }

    public function test_the_new_password_step_is_unreachable_without_a_verified_code(): void
    {
        $this->get('/password/reset')->assertForbidden();
        $this->post('/password/reset', ['password' => 'password123'])->assertForbidden();
    }
}
