<img src="docs/logo.png" alt="" width="96">

# Simplify Explain

A plugin for the [Pelican panel](https://pelican.dev) that explains logs in plain words. Press **Explain** on a
server's console and it first checks whether something is wrong. When it is, you get the likely cause and steps to
fix it. Root admins can also have the panel log and the Wings log of a node explained.

It works on its own. With [Simplify](https://github.com/jerajerajerajera/Simplify) installed, it also shows up in
Simplify's add-on list.

## Setup

1. Install the plugin from the Pelican Hub.
2. Open **Admin → Advanced → Explain**.
3. Choose Claude, GPT or Gemini, paste your own API key and press **Test**.
4. Choose who can use it: everyone who can see a console, or administrators only.

## Outside services

Explain only contacts an outside service after a root admin saved an API key, and only when someone presses
Explain or Test. It then sends the last log lines (at most 100) to the chosen service, with that key:

| Service | Address |
| --- | --- |
| Claude by Anthropic | `api.anthropic.com` |
| GPT by OpenAI | `api.openai.com` (with `store: false`) |
| Gemini by Google | `generativelanguage.googleapis.com` |

Before sending, IP addresses, e-mail addresses, passwords, tokens and long secrets are replaced with `[hidden]`.
The API key is stored encrypted with the panel's `APP_KEY` and never shown again. There is no telemetry.

## Updates

Updates come from the Pelican Hub only.

## License

See [LICENSE.md](LICENSE.md).
