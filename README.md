# HUB — How to Say It in English

A WordPress plugin for The Blue Tree. Readers scan the QR code on a printed mind map, sign in with the code from their book and their email, and get that chapter's mind map, TBT Fill games, revision exercises and key. Built for phones first.

## Install

1. Zip this folder (or copy it) into `wp-content/plugins/hub-how-to-say-it` and activate **HUB — How to Say It in English**.
2. Create a page (for example `/how-to-say-it/`) containing only the shortcode `[htsi_hub]`.
3. Go to **HTSI HUB → Settings**, choose that page, and add the "where to buy" links for each volume.
4. Exclude that page from any page cache if the cache also stores cookies. The page itself is the same for everyone; all personal data comes from the API, which is never cached.

## How it works

| Thing | Rule |
| --- | --- |
| Codes | `HTS1-XXXX-XXXX`. The digit after `HTS` is the volume. No `0`, `O`, `1` or `I` in the random part, so a handwritten code cannot be misread. |
| One reader per code | The first email used with a code owns it. Any other email is refused. Codes never expire. |
| Signing in again | Same code + same email, on any device. A cookie keeps the reader signed in for a year. |
| More volumes | A signed-in reader types the next volume's code inside the HUB. |
| Chapter pictures | One watercolour per branch, found in the Media Library by file name (`v1ch1b1.jpeg` = Volume 1, Chapter 1, Branch 1) and shown as a swipeable strip under the chapter header. See [docs/chapter-illustrations.md](docs/chapter-illustrations.md). |
| Readers | Kept in the plugin's own tables. They are not WordPress users and get no access to the rest of TBT. |
| News consent | A separate, unticked box at sign-in. The reader can switch it off on the home screen. The date of consent is stored. |
| Tracking | None. Exercise answers and Snake viewed-answer progress stay in memory and are gone when the page is closed or reloaded. |

## Admin (HTSI HUB)

- **Codes** — generate 1–500 codes for a volume, copy them, filter, export CSV. Tick "Given out" when a code goes into a book or to a person, so you can see which codes are still spare; used codes are ticked automatically. "Free code" detaches a code from a reader (for example after a typo in the email). Unused codes can be deleted.
- **Readers** — list and CSV export. "Delete" removes the reader and their sessions and frees their codes (use it for data-erasure requests).
- **Chapters** — per chapter: title, Mindomo link, one row per branch with its TBT Fill link, and the exercises. The page also shows the link to put in that map's QR code.
- **Settings** — HUB page, full-screen layout, shop links.

## Deploying

Every push to `main` uploads the plugin to the live site over FTPS (`.github/workflows/deploy.yml`, the same method as TBT Fill). It needs the repository secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` and `FTP_SERVER_DIR`. It can also be run by hand from the Actions tab, with a dry-run option.

## QR links

The Chapters admin page shows the QR code for each chapter and lets you download it as PNG or SVG.


`https://thebluetree.pl/<hub-page>/#/<volume>/<chapter>` — for example `#/1/1`. After sign-in the reader lands on that chapter. Without the `#/…` part they land on the volume list.

## Exercises

Stored as JSON per chapter; the key is generated from the answers in the same JSON. See [docs/exercise-format.md](docs/exercise-format.md). Volume 1 Chapter 1 ships in `data/v1c1-exercises.json`.

## Files

```
hub-how-to-say-it.php        bootstrap
includes/class-htsi-db.php       tables
includes/class-htsi-auth.php     codes, readers, sessions
includes/class-htsi-content.php  chapters, settings
includes/class-htsi-api.php      REST API (htsi/v1)
includes/class-htsi-frontend.php shortcode + full-screen template
includes/class-htsi-admin.php    admin screens
assets/hub.css, assets/hub.js    the HUB itself (no build step)
assets/admin-qr.js, assets/vendor  QR code on the Chapters admin page (qrcode-generator, MIT)
assets/fonts                     Roboto Slab + Roboto Flex, self-hosted
data/seed.php                    starting content for all 30 chapters
```

Fonts are served from the plugin, so no reader data goes to Google Fonts.


## THE SNAKE — revision game

Volume 1 includes a revision row between chapters 03 and 04, without renumbering the ten chapters. It opens `#/1/3/snake` and requires Volume 1 access, including when opened directly from a QR code. The `/snake?v=1&c=3` API uses the chapter reader/volume gate.

The 42 tasks use only the blue Useful upgrade target expressions from chapters 1–3 (14 per chapter), alternating Complete it, What would you say?, Say it differently and Make it personal. Content lives in `data/v1c1-3-snake.php`. The phone is a question-and-answer companion to the printed board. Answers start hidden; speaking tasks show model answers. Viewed tiles turn sage. Progress stays only in the current page session, and Reset clears it.

The game inherits the HUB’s self-hosted Roboto Slab / Roboto Flex, blue and sage tokens and native card/button styling. No separate Divi import or external service is required.


### Chapters 04–06 revision

A second THE SNAKE row appears after chapter 06 and before chapter 07. Its deep link is `#/1/6/snake`, API request is `/snake?v=1&c=6`, and its approved 42 tasks are in `data/v1c4-6-snake.php` (14 per chapter). Both games share the same HUB styling and controls. Their content caches and viewed-answer progress are separate: switching games or resetting one never changes the other. Both are cleared on sign-out or page reload.

For Complete it, learners say the whole expression; for Make it personal, they complete the requested exchange or explanation.
