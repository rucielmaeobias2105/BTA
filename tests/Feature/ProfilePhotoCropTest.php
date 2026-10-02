<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cropping a profile picture before it is uploaded.
 *
 * Ported from the pattern in MCACAFE_v1: Cropper.js opens over the chosen file,
 * the user adjusts a square crop box, and the cropped canvas is written back into
 * the file input so the ordinary Save posts the cropped image.
 *
 * What can be asserted here is the wiring, not the crop: there is no browser and
 * no canvas in PHPUnit. So these tests pin the contract the component depends on
 * — the library is actually there and is the version the port expects, the dialog
 * is inside the scope that drives it, the input and its label agree, and the
 * server still accepts what the cropper produces.
 */
class ProfilePhotoCropTest extends TestCase
{
    use RefreshDatabase;

    private function unescaped(string $html): string
    {
        return str_replace('\\', '', str_replace('\u0022', '"', $html));
    }

    private function renderPage(): string
    {
        $user = $this->makeUser();

        return $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();
    }

    /* ------------------------------------------------------------------ */
    /* 1. The library is actually there                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Cropper.js is vendored, and it is the version the port was written against.
     *
     * The version matters more than it looks: 1.x defaults `getCroppedCanvas` to
     * the image's natural size and `toBlob` takes a quality argument that PNG
     * ignores, so a major bump would change the stored dimensions without
     * anything in this project saying so.
     */
    public function test_cropper_is_vendored_at_the_version_the_port_targets(): void
    {
        $js = public_path('vendor/cropper/cropper.min.js');
        $css = public_path('vendor/cropper/cropper.min.css');

        $this->assertFileExists($js, 'Cropper.js should be vendored under public/vendor/cropper.');
        $this->assertFileExists($css, 'Its stylesheet is needed too — the cropper is unstyled without it.');

        $this->assertStringContainsString('Cropper.js v1.6.2', (string) file_get_contents($js));
    }

    /** Both files exist and are the real library, not a truncated copy. */
    public function test_both_cropper_assets_exist_and_are_not_empty(): void
    {
        foreach (['cropper.min.js', 'cropper.min.css'] as $file) {
            $path = public_path('vendor/cropper/'.$file);

            $this->assertFileExists($path);
            $this->assertGreaterThan(
                1000,
                filesize($path),
                "{$file} is too small to be the real library — a failed copy would leave a stub.",
            );
        }
    }

    /**
     * The page loads them, and only this page does.
     *
     * Asserted on the layout and on the customer home page as well: 37 KB on
     * every page to serve one dialog is not worth it, and the way that regresses
     * is by moving the `<script>` into the layout.
     */
    public function test_the_profile_page_loads_cropper_and_nothing_else_does(): void
    {
        $profile = $this->renderPage();

        $this->assertStringContainsString('vendor/cropper/cropper.min.css', $profile);
        $this->assertStringContainsString('vendor/cropper/cropper.min.js', $profile);

        $home = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('vendor/cropper', $home);
    }

    /* ------------------------------------------------------------------ */
    /* 2. The wiring                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * The scope, the dialog and the input are all present and agree on ids.
     *
     * The dialog reads `open`, `close()` and `busy` from the scope that encloses
     * it, so "inside the scope" is load-bearing rather than tidy: outside it, the
     * dialog has nothing to bind to and never opens.
     */
    public function test_the_dialog_and_its_scope_are_wired_together(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('profilePhotoCrop(', $this->unescaped($html));

        // The scope is told about each element by selector, and each of those
        // selectors has to match an id that actually exists — a typo here shows
        // up as a dialog that never opens, with nothing in the console.
        foreach (['input', 'preview', 'crop'] as $element) {
            $selector = '#profile-photo-'.$element;

            $this->assertStringContainsString(
                $selector,
                $this->unescaped($html),
                "The scope should be configured with {$selector}.",
            );

            $this->assertStringContainsString(
                'id="profile-photo-'.$element.'"',
                $html,
                "Nothing carries id=\"profile-photo-{$element}\" for that selector to find.",
            );
        }
    }

    /**
     * The label is really associated with the input.
     *
     * It was not before this change: the label pointed at `for="profile_photo"`
     * while the component rendered `id=""`, so the camera badge was a label for
     * nothing and clicking it did not open the picker for anyone using a
     * keyboard or a screen reader.
     */
    public function test_the_camera_badge_is_a_real_label_for_the_input(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('for="profile-photo-input"', $html);
        $this->assertStringContainsString('id="profile-photo-input"', $html);
        $this->assertStringNotContainsString('for="profile_photo"', $html);
    }

    /** Both ways out are offered, and the apply button is not a form submit. */
    public function test_the_dialog_offers_cancel_and_apply_and_submits_nothing(): void
    {
        $html = $this->renderPage();

        $this->assertStringContainsString('Apply Crop', $html);
        $this->assertStringContainsString('Cancel', $html);

        // A <button> inside a form defaults to type="submit". The dialog sits
        // outside the <form>, but "outside" is a placement that a later edit can
        // undo silently, so the explicit type is asserted rather than assumed.
        $this->assertMatchesRegularExpression(
            '/<button\s+type="button"[^>]*x-on:click="apply\(\)"/s',
            $html,
            'Apply must not submit the profile form.',
        );
        $this->assertMatchesRegularExpression(
            '/<button\s+type="button"[^>]*x-on:click="close\(\)"/s',
            $html,
            'Cancel must not submit the profile form.',
        );
    }

    /**
     * The crop is square, because every surface this picture appears on is a circle.
     */
    public function test_the_crop_is_square_and_output_at_500(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString('aspectRatio: 1', $source);
        $this->assertStringContainsString('width: this.width', $source);
        $this->assertStringContainsString('height: this.height', $source);
        $this->assertStringContainsString('width: 500', $source);
        $this->assertStringContainsString('height: 500', $source);
    }

    /**
     * The cropper is built after the dialog is visible.
     *
     * Cropper measures the element it attaches to, and the dialog is `x-show`
     * driven — built while it is closed, the canvas comes up zero-sized and the
     * user gets a crop box with nothing in it.
     */
    public function test_the_cropper_is_built_only_once_the_dialog_is_open(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString('$nextTick(', $source);
        $this->assertStringContainsString('requestAnimationFrame', $source);
        $this->assertStringContainsString('new window.Cropper(image', $source);
    }

    /**
     * The cropped blob goes back into the input rather than being uploaded.
     *
     * This is what keeps Cancel meaningful: nothing is sent until the user
     * presses Save, so backing out of the dialog genuinely leaves the profile
     * untouched.
     */
    public function test_the_crop_is_written_back_to_the_input_rather_than_uploaded(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString('new DataTransfer()', $source);
        $this->assertStringContainsString('input.files = transfer.files', $source);
        $this->assertStringNotContainsString("'/profile'", $source, 'Nothing here should upload on its own.');
    }

    /* ------------------------------------------------------------------ */
    /* 3. What the server receives                                         */
    /* ------------------------------------------------------------------ */

    /**
     * A 500×500 PNG is what the cropper produces, and it has to be accepted.
     *
     * The cropper always writes `image/png` whatever the source file was, so
     * `mimes:` on the request has to include png or every cropped upload fails
     * with "must be a file of type: jpeg, webp" — a message that would make no
     * sense to a customer who picked a JPEG.
     */
    public function test_a_cropped_png_upload_is_accepted(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
                'profile_photo' => UploadedFile::fake()->image('profile-photo.png', 500, 500),
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->profile_photo_path);
    }

    /** A non-image is still refused — the cropper is a convenience, not a gate. */
    public function test_a_non_image_is_still_refused(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
                'profile_photo' => UploadedFile::fake()->create('notes.pdf', 32, 'application/pdf'),
            ])
            ->assertSessionHasErrors('profile_photo');

        $this->assertNull($user->fresh()->profile_photo_path);
    }
}
