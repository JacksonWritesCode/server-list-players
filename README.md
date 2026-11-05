# Player Counter - Server List Extension

Adds a live player-count badge (e.g. `3 / 10`) to every server card on the Pelican server list.
Counts come from Boy132's **Player Counter** plugin, so any game it can query works here too.

## AI Disclosure
This plugin was entirely created with the use of LLMs. I am not a software developer, I am just sharing a plugin that I found to improve my own use case. It would not surprise me to see something similar added to the official plugin.
It is not likely that I will be maintaining this. There has been no security review. Use at your own risk.


## Requirements
- Pelican panel with plugin support - Tested with panel 1.0.0-rc.2.
- Boy123's `player-counter` plugin installed and enabled - Tested with plugin version 1.1.0.
- A **Game Query** linked to each egg you want counted (Admin → Game Queries). Servers whose egg has no Game Query will not receive a player count on the server badge.


## Install
1. Copy this folder to `<panel>/plugins/server-list-players` (folder name must match the plugin id).

Alternatively...

1. Use the Import button on the Plugins page with the zip.

## Badge states
| Badge | Meaning |
|---|---|
| green `3 / 10` | running, players online |
| grey `0 / 10` | running, nobody online |
| faded `—` | running, but the query got no answer (hover for text) |
| none | offline, or no Game Query linked to the egg |

## Settings (`.env`)
| Variable | Default | |
|---|---|---|
| `SERVER_LIST_PLAYERS_CACHE` | `20` | Seconds a result is shared between all users |
| `SERVER_LIST_PLAYERS_POLL` | `30` | Browser refresh interval (min 10) |
| `SERVER_LIST_PLAYERS_SHOW_OFFLINE` | `false` | Show a muted `0` on offline servers |

## How it works / limitations
Pelican has no hook for adding content to a server card, so this plugin mounts one invisible Livewire
component on the server-list page (render hook, `app` panel only). A small script finds each card (Pelican's
cards are `<div>`s whose click handler navigates to `/server/<id>/console`; plain `<a href>` links also work),
asks the component for the counts, and places a badge in the card's title row.
It re-applies the badge whenever Livewire re-renders a card.

- The component only answers for servers the logged-in user can access.
- Results are cached per server, so many viewers do not multiply queries against your game servers.
- Because it's DOM-based, a major redesign of the server cards in a future panel release could require a tweak
  to the selectors in `resources/views/counts.blade.php` (`SERVER_HREF`, `findTitle`, `place`).
- If you see no badges, check in this order:
  1. The egg has a Game Query linked (Admin → Game Queries). Servers without one get no badge.
  2. The server is running. Offline servers are hidden unless `SERVER_LIST_PLAYERS_SHOW_OFFLINE=true`.
  3. Browser console (with the Debug/Verbose level enabled): the script logs a line when it finds no server cards.
