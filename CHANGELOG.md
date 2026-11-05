# Changelog

## Unreleased

## 2.0.0-beta.6

- With Simplify, root admins find Explain's AI service under **Connections** in the account menu: the service or Ollama server, its key or address, Test and Remove key, saved with the same Save as everywhere. Who may use Explain stays in the settings.

## 2.0.0-beta.5

- Explain's window and its emergency page use Simplify's header model: what is explained as the title, where the lines come from as the subtitle, and Check again on the right.
- The question button after an error takes its colours from Simplify's theme, or from Pelican's without Simplify, instead of fixed ones.
- Explain on Pelican's own errors, also without Simplify: every red message gets an Explain link, Pelican's Health page an Explain button while a check fails, and its 500 page a link to the emergency page for root admins.
- Uninstalling keeps the settings and saved keys, so installing again brings everything back (see "Updates" in the README).
- Checks: CI updates from the Hub-stable 1.1.0 with every setting filled in, puts the previous release back over this version, and uninstalls and installs again, checking that the settings stay each time. PHPStan runs without a baseline, `tests/run.sh` checks for secrets and internal addresses, and Dependabot keeps the GitHub Actions current.
- With a Simplify that has add-on settings (add-on API 8), Explain's settings sit in Simplify settings › Add-ons and its own page leaves the menu. The page still opens by its address and uses the same form. Test is now a button in the form.
- Failure messages in Simplify's bell can carry the button too: the whole id can be the template `__ID__` (see the README).
- An error shown as a Filament notification can carry an Explain button too: `Problems::notificationAction($id)` (see the README).
- A question button right after an error explains it: Simplify, its add-ons and any other plugin can mark an error with the render hook `simplify::problem.actions` and give the log that belongs to it (see "Explain your plugin's errors" in the README).
- With Simplify, every health check that failed or warns gets that button on the Health page, for root admins. It explains what is wrong and how to fix it, from the check's result and the panel log.
- Clean-up: the PHPStan findings from the start are fixed.
- Checks: PHPStan (level 5) runs in CI, and the panel smoke also runs with the real Simplify core (main) instead of a stub.

## 2.0.0-beta.4

- Settings: "Remove the saved key" is a button that asks first, instead of a switch. It removes the key at once and leaves other unsaved changes alone.

## 2.0.0-beta.1

- First version on the Hub, as a beta. It holds everything of 1.1.1.

## 1.1.1

- A download of the repository (the Pelican Hub's zip) holds only the plugin, without docs, tests, scripts and workflows.
- Settings: with Simplify, a change shows "Changes made" with Save, and leaving the page first asks Save or Discard.
- The plugin description mentions Ollama.
- Checks: a change to docs or Markdown only skips the panel smoke; tests and pint still run.

## 1.1.0

- Ollama as a fourth choice: Explain can use a model on your own Ollama server, with its address and model name
  instead of an API key. The logs stay on your own network.
- "Open a ticket" under a problem Explain found only shows when Simplify Hosting is installed and a support link is
  set in Simplify's settings.

## 1.0.5

- The settings page no longer scrolls as a whole page with Simplify 1.5: the form scrolls under the page title.

## 1.0.4

- Under a problem Explain found, customers get an "Open a ticket" button to the panel's support link from Simplify's
  settings, as in PelicanSimplify 0.7.0. Admins do not see it.

## 1.0.3

- Settings: Test sits in the head of the page next to Save, instead of a button inside the form. In Simplify's host mode
  both are in the title bar.

## 1.0.2

- Simplify's setup (from Simplify 1.3) now asks for the AI service, its API key and who may use Explain, in its last
  step "Set up add-ons", so a new panel is set up in one go. Older Simplify versions and panels without Simplify are
  not affected.

## 1.0.1

- Explain now sits in the Simplify group of Pelican's admin menu, together with the other Simplify add-ons,
  in the same order everywhere. This works with or without the Simplify core.

## 1.0.0

First version as a separate plugin. Explain used to be part of PelicanSimplify.

- **Explain** button on a server's Console page: checks the last 100 console lines (or the install log) and says
  whether something is wrong. When it is, you get the likely cause and steps to fix it, in your panel language.
- **Explain panel log** on the Logs page and **Explain Wings log** on a node's page, for root admins.
- An emergency page at `/simplify-explain/emergency` and the command `php artisan simplify-explain:check` for when
  the panel itself shows an error. `--url` gives a temporary address that works for 60 minutes without signing in.
- Choose Claude, GPT or Gemini with your own API key, stored encrypted. Choose who may use it: everyone who can
  see a console, or administrators only.
- Addresses, e-mail addresses, passwords and secrets are removed before anything is sent.
- Limits: 6 questions per person per 10 minutes, 200 per panel per day; the same lines get the saved answer for
  10 minutes.
- Settings and keys from PelicanSimplify's Explain are taken over.
- Shows up in Simplify's add-on list when Simplify is installed.
- 32 languages.
