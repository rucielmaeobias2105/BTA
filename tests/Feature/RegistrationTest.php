<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.test',
            'contact_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ], $overrides);
    }

    public function test_registration_screen_renders(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Register')
            ->assertSee('First Name')
            ->assertSee('Confirm Password');
    }

    /**
     * The navbar no longer offers Register, so the login screen is the only
     * way in — which is why it has to keep linking to registration itself.
     *
     * Without this, dropping the navbar button would leave a visitor with no
     * path to an account at all.
     */
    public function test_registration_is_reachable_from_the_login_screen(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString(
            'href="'.route('register').'"',
            $html,
            'The login screen should link to registration.',
        );

        // Guest navbar: a Log In link, and no Register link.
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('login').'"', false)
            ->assertDontSee('href="'.route('register').'"', false);
    }

    public function test_a_visitor_can_register_and_is_signed_in(): void
    {
        $response = $this->post('/register', $this->payload());

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $user = User::firstWhere('email', 'juan@example.test');

        $this->assertNotNull($user);
        $this->assertSame('Juan', $user->first_name);
        $this->assertSame('Dela Cruz', $user->last_name);
        $this->assertSame('09171234567', $user->contact_number);
        $this->assertTrue($user->is_active);
    }

    public function test_password_is_hashed_and_never_stored_in_plain_text(): void
    {
        $this->post('/register', $this->payload());

        $user = User::firstWhere('email', 'juan@example.test');

        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(password_verify('password123', $user->password));
    }

    public function test_a_username_is_derived_for_username_or_email_login(): void
    {
        $this->post('/register', $this->payload());

        $this->assertSame('juan', User::firstWhere('email', 'juan@example.test')->username);
    }

    public function test_email_must_be_unique(): void
    {
        $this->makeUser(['email' => 'taken@example.test']);

        $this->post('/register', $this->payload(['email' => 'taken@example.test']))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_email_is_validated(): void
    {
        $this->post('/register', $this->payload(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_password_must_be_at_least_eight_characters(): void
    {
        $this->post('/register', $this->payload([
            'password' => 'short12',
            'password_confirmation' => 'short12',
        ]))->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->post('/register', $this->payload([
            'password_confirmation' => 'different123',
        ]))->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_terms_must_be_accepted(): void
    {
        $this->post('/register', $this->payload(['terms' => null]))
            ->assertSessionHasErrors('terms');

        $this->assertGuest();
    }

    public function test_first_and_last_name_are_required(): void
    {
        $this->post('/register', $this->payload([
            'first_name' => '',
            'last_name' => '',
        ]))->assertSessionHasErrors(['first_name', 'last_name']);

        $this->assertGuest();
    }

    public function test_a_registered_customer_receives_a_welcome_notification(): void
    {
        $this->post('/register', $this->payload());

        $user = User::firstWhere('email', 'juan@example.test');

        $this->assertCount(1, $user->notifications);
        $this->assertSame(
            'Welcome to '.config('app.name'),
            $user->notifications()->first()->data['title'],
        );
    }

    public function test_email_is_normalised_to_lowercase(): void
    {
        $this->post('/register', $this->payload(['email' => '  Juan@Example.TEST ']));

        $this->assertDatabaseHas('users', ['email' => 'juan@example.test']);
    }
}
