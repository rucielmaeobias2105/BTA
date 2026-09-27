# Agent Instructions

## Prompt logging (do not break this)

Every user prompt is captured **automatically** by the opencode plugin at
`.opencode/plugins/prompt-logger.js`. It fires on `chat.message` — the instant a
prompt is submitted, before the model runs — so prompts are on disk even if the
session is closed, interrupted, or navigated away from.

The plugin appends to both `prompt.log` and `PROMPT_LOG.md` and handles
de-duplication itself.

**Self-heal check (do this first, every turn).** The plugin is loaded once at
opencode startup, so it captures nothing if opencode has not been restarted since
the plugin was added. Before doing any work, check that the user's current prompt
is actually on disk:

```bash
grep -c "the first few words of the current prompt" prompt.log
```

If that returns `0`, the plugin is not running — append the prompt yourself with
the plugin's exact format, then tell the user a restart is still required:

```bash
php artisan prompts:log "<the prompt text, verbatim>"
```

**Your job is otherwise only to log outcomes.** Never hand-write a `### Task:` /
`* Prompt used:` block for a prompt the plugin already logged — grep first, and a
manual copy creates a duplicate. Append a result entry instead:

```bash
php artisan prompts:log --module="Customer Flows 5-9" "What you actually built or changed"
```

Use `--context=` for a short note (e.g. a test command you ran). This only ever
appends; it never overwrites.

## Conventions

- Laravel LTS + Blade/Tailwind. Reuse `resources/views/components/` and the
  shared layouts rather than re-implementing a partial in each page.
- Design tokens live in `tailwind.config.js` / `resources/css/app.css`. Use the
  semantic classes (`btn-primary`, `card`, `badge`, `customer-nav-link`, …)
  instead of raw Tailwind utilities for anything that already has a component.
- Validate server-side with Form Request classes in addition to client hints.
- After any change, run `php artisan test` and `npm run build` before reporting
  done.
- Never commit `.env`, and never introduce secrets into the repo.
