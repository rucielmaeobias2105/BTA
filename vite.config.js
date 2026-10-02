import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    // Relative asset URLs.
    //
    // The default base of `/` makes Vite write root-absolute `url(/build/…)`
    // references into the compiled CSS. That only works when the app is served
    // from the domain root, and this one is not — it is served from
    // `htdocs/BTA/public`, so every webfont resolved to a 404 and the icons
    // rendered as empty boxes. A relative base makes each `url()` resolve
    // against the CSS file's own location instead, which is correct wherever
    // the document happens to live.
    base: './',

    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
