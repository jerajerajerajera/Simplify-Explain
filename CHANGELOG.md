# Changelog

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
