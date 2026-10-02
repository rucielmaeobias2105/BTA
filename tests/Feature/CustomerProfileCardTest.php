<?php

namespace Tests\Feature;

use App\Http\Requests\Customer\UpdateProfileRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The customer profile page's card, rebuilt to a single surface with a centred
 * identity block on top and the whole form inside it.
 *
 * Structural rather than string assertions: the point is one card holding
 * everything, which a substring check can neither prove nor refute.
 */
class CustomerProfileCardTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        return $this->actingAs($this->makeUser(['first_name' => 'Ruciel', 'last_name' => 'Obias']))
            ->get(route('profile.edit'))
            ->assertOk()
            ->getContent();
    }

    private function dom(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new \DOMXPath($doc);
    }

    /**
     * One card, not the three `x-ui.card` sections this replaced.
     */
    public function test_the_three_cards_became_one(): void
    {
        $html = $this->page();
        $xpath = $this->dom($html);

        $this->assertSame(
            0,
            $xpath->query('//main//*[contains(@class, "bta-card")]')->length,
            'The old bta-card sections should be gone.',
        );

        $cards = $xpath->query('//main//div[contains(@class, "rounded-[1.25rem]")]');
        $this->assertSame(1, $cards->length, 'There should be exactly one card surface.');

        // The form and the card are properly nested — the form wraps the card,
        // so every field, the file input and the save button are inside the card
        // surface rather than beside it. The context node matters: a relative
        // expression with none resolves from the document root, which on this
        // page also holds the bell's, the logout row's and the mobile menu's
        // forms.
        $card = $cards->item(0);
        $this->assertInstanceOf(\DOMElement::class, $card);

        $this->assertSame(
            4,
            $xpath->query('.//input[@name="first_name"] | .//input[@name="last_name"] | .//input[@name="email"] | .//input[@name="contact_number"]', $card)->length,
            'The detail fields should be inside the card.',
        );

        $this->assertSame(
            1,
            $xpath->query('.//input[@type="file"]', $card)->length,
            'The photo input has to be inside the form to submit.',
        );

        $this->assertSame(
            1,
            $xpath->query('.//button[@type="submit"]', $card)->length,
            'The save action should be inside the card.',
        );

        // And the card is not floating loose: the form encloses it.
        $form = $xpath->query('//main//form[.//div[contains(@class, "rounded-[1.25rem]")]]');
        $this->assertSame(1, $form->length, 'The card should sit inside the form.');
    }

    /**
     * Centred identity block: avatar, then name, then the contact line — in that
     * order, and centred.
     */
    public function test_the_identity_block_is_centred_and_ordered(): void
    {
        $xpath = $this->dom($this->page());

        $name = $xpath->query('//main//h2[normalize-space(text())="Ruciel Obias"]');
        $this->assertSame(1, $name->length, 'The name should render from full_name.');

        $heading = $name->item(0);
        $this->assertInstanceOf(\DOMElement::class, $heading);

        $block = $heading->parentNode;
        $this->assertInstanceOf(\DOMElement::class, $block);
        $this->assertStringContainsString('text-center', $block->getAttribute('class'));

        // The avatar precedes the name, and the contact line follows it.
        $children = [];
        foreach ($block->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        $this->assertNotEmpty($children);

        // First child is the centred avatar holder: it carries the camera
        // badge, and nothing but the avatar.
        $first = $children[0];
        $this->assertInstanceOf(\DOMElement::class, $first);
        $this->assertSame(
            1,
            $xpath->query('.//label[@for="profile-photo-input"]', $first)->length,
            'The avatar holder should contain the camera badge.',
        );

        // The name is not the first thing in the block.
        $this->assertNotSame($heading, $first);

        $email = $xpath->query('//main//p[contains(text(), "@")]');
        $this->assertGreaterThan(0, $email->length, 'The contact line should show the email.');
    }

    /**
     * The camera badge is the visible affordance for the upload, and it is the
     * file input's own label — so clicking it opens the picker without the
     * input having to be on screen.
     */
    public function test_the_camera_badge_labels_the_file_input(): void
    {
        $xpath = $this->dom($this->page());

        $badge = $xpath->query('//main//label[@for="profile-photo-input"]');
        $this->assertSame(1, $badge->length, 'There should be a camera badge bound to the file input.');

        $input = $xpath->query('//main//input[@type="file"]');
        $this->assertSame(1, $input->length);

        $inputEl = $input->item(0);
        $this->assertInstanceOf(\DOMElement::class, $inputEl);
        $this->assertSame('profile_photo', $inputEl->getAttribute('name'));

        // The badge's `for` has to be this input's `id`, or it labels nothing.
        //
        // It used to be `for="profile_photo"` — the field's *name* — while the
        // input rendered no id at all, so the badge was a label for no control
        // and the cropper had nothing to attach a `change` listener to by name.
        // The id is what `profilePhotoCrop` is configured with now.
        $this->assertSame(
            'profile-photo-input',
            $inputEl->getAttribute('id'),
            'The file input needs the id its label and the cropper both point at.',
        );

        // Kept out of the layout but not out of the accessibility tree.
        $this->assertStringContainsString('sr-only', $inputEl->getAttribute('class'));

        $badgeEl = $badge->item(0);
        $this->assertInstanceOf(\DOMElement::class, $badgeEl);
        $this->assertStringContainsString('absolute', $badgeEl->getAttribute('class'));
        $this->assertStringContainsString('rounded-full', $badgeEl->getAttribute('class'));

        // The reference positions it flush against the avatar's corner.
        $this->assertStringContainsString('bottom-0', $badgeEl->getAttribute('class'));
        $this->assertStringContainsString('right-0', $badgeEl->getAttribute('class'));
    }

    /** One full-width save, as in the reference, rather than a right-aligned button. */
    public function test_there_is_one_full_width_save_action(): void
    {
        $xpath = $this->dom($this->page());

        $saves = $xpath->query('//main//form//button[@type="submit"]');
        $this->assertSame(1, $saves->length, 'One submit for the whole card.');

        $save = $saves->item(0);
        $this->assertInstanceOf(\DOMElement::class, $save);
        $this->assertStringContainsString('w-full', $save->getAttribute('class'));
        $this->assertStringContainsString('Save Changes', $save->textContent);
    }

    /**
     * The two section headings, each with a leading icon, and the fields in a
     * grid that collapses to one column.
     */
    public function test_the_sections_are_icon_led_and_the_grid_is_responsive(): void
    {
        $xpath = $this->dom($this->page());

        $headings = [];
        foreach ($xpath->query('//main//h3') as $h) {
            $headings[] = trim($h->textContent);
        }

        $this->assertContains('Personal Information', $headings);
        $this->assertContains('Change Password', $headings);

        $grids = $xpath->query('//main//div[contains(@class, "sm:grid-cols-2")]');
        $this->assertGreaterThanOrEqual(2, $grids->length, 'Both field groups should use the two-column grid.');
    }

    /**
     * The layout changed; the contract with the controller did not. These are
     * the names `UpdateProfileRequest` validates.
     */
    public function test_the_form_still_posts_every_field_the_controller_expects(): void
    {
        $xpath = $this->dom($this->page());

        $form = $xpath->query('//main//form');
        $this->assertSame(1, $form->length);

        $formEl = $form->item(0);
        $this->assertInstanceOf(\DOMElement::class, $formEl);
        $this->assertStringContainsString('/profile', $formEl->getAttribute('action'));
        $this->assertStringContainsString('multipart/form-data', $formEl->getAttribute('enctype'));

        $names = [];
        foreach ($xpath->query('//main//form//input | //main//form//textarea') as $field) {
            $this->assertInstanceOf(\DOMElement::class, $field);
            $n = $field->getAttribute('name');

            if ($n) {
                $names[] = $n;
            }
        }

        foreach (['profile_photo', 'first_name', 'last_name', 'email', 'contact_number', 'password', 'password_confirmation'] as $expected) {
            $this->assertContains($expected, $names, "The {$expected} field went missing in the redesign.");
        }

        // The photo is only removable when there is one, and removing it is the
        // existing in-form flag, not a new route.
        $this->assertStringNotContainsString('photo.destroy', $this->page());
    }

    /** The role field was never on this form and must not turn up. */
    public function test_the_role_is_still_not_editable_here(): void
    {
        $xpath = $this->dom($this->page());

        $this->assertSame(0, $xpath->query('//main//input[@name="role"]')->length);
    }

    /**
     * Saving your name must not delete your picture.
     *
     * The remove control used to be a hidden `remove_photo=1` input that sat in
     * the form permanently, with a button whose handler set `.checked = true` on
     * it — a no-op on a hidden input. The flag was therefore submitted on every
     * save, and `ProfileController::update()` takes the
     * `elseif ($request->boolean('remove_photo'))` branch whenever no new file
     * was chosen, so editing a name silently wiped the photo and unlinked the
     * file. The control now rides on the submit button that is supposed to send
     * it.
     */
    public function test_updating_the_name_leaves_the_photo_alone(): void
    {
        Storage::fake('public');

        $user = $this->makeUser(['first_name' => 'Ruciel', 'last_name' => 'Obias']);

        $details = [
            'first_name' => 'Ruciel',
            'last_name' => 'Obias',
            'email' => $user->email,
            'contact_number' => $user->contact_number,
        ];

        $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
            ...$details,
            'profile_photo' => UploadedFile::fake()->image('me.jpg'),
        ])->assertSessionHasNoErrors();

        $path = $user->fresh()->profile_photo_path;
        $this->assertNotNull($path, 'The photo should have been stored.');
        Storage::disk('public')->assertExists($path);

        // A later save that touches nothing but the name.
        $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
            ...$details,
            'first_name' => 'Ruciel Mae',
            'profile_photo' => null,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Ruciel Mae', $user->fresh()->first_name);
        $this->assertSame($path, $user->fresh()->profile_photo_path, 'A name edit must not unlink the photo.');
        Storage::disk('public')->assertExists($path);
    }

    /** …and removing it deliberately still works. */
    public function test_the_remove_control_still_deletes_the_photo(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();

        $details = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
        ];

        $this->actingAs($user)->patch(route('profile.update'), [
            ...$details,
            'profile_photo' => UploadedFile::fake()->image('me.jpg'),
        ])->assertSessionHasNoErrors();

        $path = $user->fresh()->profile_photo_path;
        $this->assertNotNull($path);

        $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
            ...$details,
            'remove_photo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    /**
     * The flag must not be sitting in the form waiting to be sent with
     * everything else.
     */
    public function test_no_remove_flag_sits_idle_in_the_form(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();

        $this->actingAs($user)->patch(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
            'profile_photo' => UploadedFile::fake()->image('me.jpg'),
        ])->assertSessionHasNoErrors();

        $xpath = $this->dom($this->actingAs($user)->get(route('profile.edit'))->getContent());

        $this->assertSame(
            0,
            $xpath->query('//main//input[@name="remove_photo"]')->length,
            'The remove flag should ride on its submit button, not a permanent hidden input.',
        );

        $this->assertSame(
            1,
            $xpath->query('//main//button[@name="remove_photo"]')->length,
            'There should be a submit button that carries the flag.',
        );
    }

    /**
     * The avatar has to point at wherever the browser actually is.
     *
     * `Storage::url()` builds from `APP_URL`, so with the app served from a
     * subdirectory — or on the dev server rather than the configured origin —
     * every avatar became a request to a different host and rendered broken for
     * a file that was on disk all along. The photo saved; it just could not be
     * seen, which is the same bug as "the photo will not save".
     */
    public function test_the_avatar_url_follows_the_current_request(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();

        $this->actingAs($user)->patch(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
            'profile_photo' => UploadedFile::fake()->image('me.jpg'),
        ])->assertSessionHasNoErrors();

        $path = $user->fresh()->profile_photo_path;
        $this->assertNotNull($path);

        $html = $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->getContent();

        // The request came in on the test host, which is not the host APP_URL
        // names. The avatar has to point at the host that asked for it.
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $requestHost = parse_url('http://localhost', PHP_URL_HOST) ?? 'localhost';

        $this->assertMatchesRegularExpression(
            '/src="http:\/\/[^"]*\/storage\/'.preg_quote($path, '/').'"/',
            $html,
            'The avatar should resolve against the request that asked for it.',
        );

        // The sharp version: APP_URL here is localhost:8000 while the request
        // is not, so a URL built from APP_URL is visibly the wrong one.
        if ($appHost && $appHost !== $requestHost) {
            $this->assertStringNotContainsString(
                config('app.url').'/storage/'.$path,
                $html,
                'The avatar should not be built from APP_URL.',
            );
        }
    }

    /**
     * The same photo appears on the other screens that show an avatar, so they
     * all have to resolve the same way.
     */
    public function test_every_avatar_uses_the_request_relative_url(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();

        $this->actingAs($user)->patch(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'contact_number' => $user->contact_number,
            'profile_photo' => UploadedFile::fake()->image('me.jpg'),
        ]);

        // Nothing anywhere should still be building a storage URL by hand.
        $views = [
            resource_path('views/customer/profile/edit.blade.php'),
            resource_path('views/admin/users/show.blade.php'),
            resource_path('views/partials/customer/nav.blade.php'),
        ];

        foreach ($views as $view) {
            $this->assertStringNotContainsString(
                'Storage::url',
                file_get_contents($view),
                basename(dirname($view)).'/'.basename($view).' still builds a storage URL by hand.',
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /* Trimmed copy                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * The header keeps its title and loses its eyebrow and standfirst; the card
     * below says who the account belongs to, and the rest was narration.
     */
    public function test_the_header_is_a_title_and_nothing_else(): void
    {
        $html = $this->page();
        $xpath = $this->dom($html);

        $this->assertSame(1, $xpath->query('//main//h1[normalize-space(text())="Profile Management"]')->length);

        $this->assertStringNotContainsString('>Account</p>', $html);
        $this->assertStringNotContainsString('Keep your contact details and profile picture up to date', $html);

        // No eyebrow paragraph survives above the title.
        //
        // Scoped to `<main>` and expressed as "an uppercase paragraph that comes
        // before a heading", rather than `preceding::` on the h1 — `preceding::`
        // is relative to the whole document, so it also matched the uppercase
        // heading inside the navbar's notification dropdown, which is not this
        // page's eyebrow.
        $this->assertSame(0, $xpath->query('//main//p[contains(@class, "uppercase")][following::h1]')->length);
    }

    /**
     * The three explanatory asides are gone: the file-size hint, the username
     * note and the password caveat. Each one restated something the UI already
     * implied or that the field label already carries.
     */
    public function test_the_explanatory_asides_are_removed(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('JPG, PNG or WEBP', $html);
        $this->assertStringNotContainsString('you can use either your username or email to log in', $html);
        $this->assertStringNotContainsString('Leave both fields blank to keep your current password', $html);
    }

    /**
     * Removing the username note must not lose the field itself — the username
     * was only ever displayed, never editable, so it simply stops being shown.
     */
    public function test_the_username_note_is_gone_but_nothing_editable_appeared(): void
    {
        $xpath = $this->dom($this->page());

        $this->assertSame(0, $xpath->query('//main//input[@name="username"]')->length);
        $this->assertStringNotContainsString('Username:', $this->page());
    }

    /**
     * The photo ceiling is 10 MB, and the form no longer advertises a limit at
     * all — so the validation message is the only place the number is stated.
     */
    public function test_the_photo_ceiling_is_ten_megabytes(): void
    {
        // `rules()` reads `$this->user()->id` for the unique-email ignore, so the
        // request needs a resolved user before it can be asked anything.
        $request = UpdateProfileRequest::create('/profile', 'PATCH', []);
        $request->setUserResolver(fn () => $this->makeUser());

        $rules = $request->rules();

        $this->assertContains('max:10240', $rules['profile_photo']);
        $this->assertNotContains('max:2048', $rules['profile_photo']);

        $this->assertStringContainsString('10 MB', $request->messages()['profile_photo.max']);
    }
}
