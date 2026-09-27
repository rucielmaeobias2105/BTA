<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Promo;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the browser tab title format for every public and admin section:
 * "SECTION | Balai ti Arjud" (admin pages keep the trailing "Admin").
 */
class TabTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_public_section_uses_the_tab_title_format(): void
    {
        $this->makeSalonSettings();
        $this->makeService(['name' => 'Gelish Manicure', 'category' => 'Nail Care']);

        $sections = [
            '/' => 'HOME | Balai ti Arjud',
            '/about' => 'ABOUT US | Balai ti Arjud',
            '/contact' => 'CONTACT US | Balai ti Arjud',
            '/services' => 'SERVICES | Balai ti Arjud',
            '/services-grid' => 'REFINED GRID VIEW | Balai ti Arjud',
            '/login' => 'LOG IN | Balai ti Arjud',
            '/register' => 'REGISTER | Balai ti Arjud',
            '/password' => 'FORGOT PASSWORD | Balai ti Arjud',
            '/admin/login' => 'ADMIN SIGN IN | Balai ti Arjud',
        ];

        foreach ($sections as $uri => $expected) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('<title>'.$expected.'</title>', false);
        }
    }

    public function test_a_service_detail_page_titles_from_the_service_name(): void
    {
        $this->makeSalonSettings();
        $service = $this->makeService(['name' => 'Gelish Manicure', 'category' => 'Nail Care']);

        $this->get("/services/{$service->slug}")
            ->assertOk()
            ->assertSee('<title>GELISH MANICURE | Balai ti Arjud</title>', false);
    }

    public function test_the_promo_link_and_its_section_both_declare_the_promo_tab_title(): void
    {
        $this->makeSalonSettings();
        $this->makeService(['name' => 'Gelish Manicure', 'category' => 'Nail Care']);

        // The offers section only renders when a promo is active.
        Promo::create([
            'title' => 'Glow Package',
            'description' => 'Manicure and facial for one price.',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            // The jump link and its target must agree, otherwise clicking the
            // link sets one title and the hash change immediately resets it.
            ->assertSee('id="offers" class="home-section" data-tab-title="PROMO | Balai ti Arjud"', false)
            ->assertSee('href="'.route('home').'#offers" class="customer-nav-link" data-tab-title="PROMO | Balai ti Arjud"', false);
    }

    public function test_admin_pages_keep_the_admin_suffix(): void
    {
        $admin = Admin::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'username' => 'tabtitle',
            'email' => 'tabtitle@example.test',
            'password' => 'password',
            'role' => AdminRole::SuperAdmin,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('<title>DASHBOARD | Balai ti Arjud Admin</title>', false);
    }
}
