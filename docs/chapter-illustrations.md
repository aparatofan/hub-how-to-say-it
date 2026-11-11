# Chapter illustrations — spec

Status: approved prototype (11 Nov 2026), ready to build.
Prototype: the "HUB Chapter Illustrations" artifact (Volume 1, Chapter 1, three pictures).

## What it is

Each branch of a mind map has one watercolour illustration. On the chapter page they appear as a strip of pictures between the blue header and the first toggle ("Access the mind map"). The reader swipes to see the next one. The name of the branch sits small and pale at the bottom of each picture.

The strip is decoration and orientation. It does not link anywhere and has no buttons.

## Where the pictures come from

Mariusz uploads the pictures to the WordPress Media Library. The plugin finds them by file name. There is nothing to paste into the HUB admin.

**File name rule:** `v{volume}ch{chapter}b{branch}` + `.jpeg`, `.jpg`, `.png` or `.webp`.

| File | Goes to |
| --- | --- |
| `v1ch1b1.jpeg` | Volume 1, Chapter 1, Branch 1 |
| `v1ch1b5.jpeg` | Volume 1, Chapter 1, Branch 5 |
| `v2ch10b6.webp` | Volume 2, Chapter 10, Branch 6 |

- The branch number is the number printed on the mind map (the map is the record). It is the same order as the branch rows on the Chapters admin page.
- Matching is on the end of the attachment's file path (`_wp_attached_file`), case-insensitive, with this pattern:
  `(^|/)v(\d+)ch(\d+)b(\d+)(-\d+)?(-scaled)?\.(jpe?g|png|webp)$`
  The optional `-2`, `-scaled` endings cover what WordPress adds when a file with the same name is uploaded again or is very large.
- If two files match the same branch (for example a re-upload of a corrected picture), the newest upload wins. To go back, delete the newer one from the Media Library.
- A branch with no picture is skipped. The strip shows only what exists, so it fills in as pictures are uploaded.

## Caption

- Text: the branch title from the Chapters admin page (`branches[n-1].title`). No number, no chapter name.
- If that branch has no title, the picture shows without a caption.
- Look: Roboto Slab (the HUB's `--slab`), weight 500, 13px, letter-spacing 0.02em, centred, white at 72% opacity, on a soft gradient from navy 30% at the bottom edge to transparent about 40px up. Faint text shadow so it reads on light skies.

## Alt text

The attachment's own Alt Text field from the Media Library. If that is empty: `Illustration: {branch title}`.

## Layout and behaviour

- Full width of the HUB column, no side margin (same edge as the blue header). On a wide screen it stays inside the same max width as the rest of the HUB.
- Every picture is shown at 16:9 with `object-fit: cover`. Pictures with a slightly different shape (for example `v1ch1b5` at 1920×1120) are trimmed a few pixels top and bottom rather than letterboxed.
- While a picture loads, the frame shows a warm paper colour (`#EDE6DA`), and the frame has its final height from the start, so nothing jumps.
- One picture per screen. Swiping moves exactly one picture (scroll snap, `scroll-snap-stop: always`). Vertical scrolling of the page is never blocked.
- No looping: the last picture stays the last. No autoplay.
- Dots in the top right corner, one per picture: white at 45%, the current one white at 95%. Hidden when there is only one picture.
- Mouse: drag to move to the next or previous picture (desktop testing, teachers on laptops). Keyboard: the strip can take focus; left and right arrows move one picture. Visible focus ring in TBT blue.
- `prefers-reduced-motion`: jump instead of smooth scroll; no dot animation.
- Zero pictures for a chapter: no strip at all, the page looks exactly as it does today.

### Keeping the position

The HUB redraws the whole screen when a toggle opens or closes. Without care, the strip would jump back to picture 1 every time.

- Keep the current picture per chapter in the app state, next to `S.open`: `S.slide["v-c"] = index`.
- After each redraw, set the strip's `scrollLeft` to that index straight away (no animation).
- Reset when the reader signs out, the same way `S.open` is cleared.
- Coming back to a chapter later in the same visit: start where the reader left it. A fresh page load starts at picture 1.

## Data

`GET htsi/v1/chapter` gets one more field, `pictures`, in branch order:

```json
"pictures": [
  {
    "n": 1,
    "src": "https://thebluetree.pl/wp-content/uploads/2026/11/v1ch1b1-1024x572.jpeg",
    "srcset": "…-768x429.jpeg 768w, …-1024x572.jpeg 1024w, …v1ch1b1.jpeg 1920w",
    "width": 1024,
    "height": 572,
    "alt": "Kat and Tom greet each other in a café",
    "caption": "Greeting people you know"
  }
]
```

- `src` is the `large` size; `srcset` comes from `wp_get_attachment_image_srcset()`. The front end uses `sizes="(min-width: 720px) 720px, 100vw"`, so a phone downloads about 768–1024px, not the 1920px original.
- The first picture loads normally (`fetchpriority="high"`). The rest use `loading="lazy"` and `decoding="async"`.
- The picture files are ordinary public Media Library files. Only the list of pictures sits behind sign-in, with the rest of the chapter.

### Finding the files quickly

- One query per chapter request: attachments whose `_wp_attached_file` contains `v{V}ch{C}b` (`LIKE`), then the exact pattern check in PHP.
- Cache the result for each chapter in a transient (`htsi_pics_{V}_{C}`, 12 hours).
- Clear all `htsi_pics_*` transients when an attachment is added, edited or deleted (`add_attachment`, `edit_attachment`, `delete_attachment`), so a new upload shows on the next page load.

## Admin (Chapters page)

- The "Branches and Fill games" table gets a third column, **Picture**: a small thumbnail (about 96px wide) when the plugin has found a file for that row, otherwise a grey "No picture".
- One line of help above the table: *Pictures come from the Media Library. Name each file v1ch1b1, v1ch1b2 … (volume, chapter, branch), in map order.*
- Nothing to type and nothing new to save. The column is only a check.

## Code changes

| File | Change |
| --- | --- |
| `includes/class-htsi-content.php` | New `HTSI_Content::pictures( $v, $c )`: find, match, cache, build the list above. New `flush_pictures()` hooked to the three attachment actions. |
| `includes/class-htsi-api.php` | Add `pictures` to the `/chapter` response. |
| `includes/class-htsi-admin.php` | Picture column and help line on the Chapters page. |
| `assets/hub.js` | `gallery(k, ch.pictures)` rendered between `hero()` and `.htsi-main`; `S.slide` state; restore position after `render()`; drag, keys and dots. |
| `assets/hub.css` | `.htsi-gallery`, `.htsi-gallery-track`, `.htsi-slide`, `.htsi-slide figcaption`, `.htsi-dots` — using the existing tokens (`--slab`, `--ink`, `--blue`). |
| `hub-how-to-say-it.php` | Version 0.5.0 (so phones fetch the new CSS and JS). |
| `README.md` | One row in "How it works" pointing to this file. |

No new tables, no new options, no change to the seed.

## Out of scope for now

- Pictures on the welcome page or next to the rows in "Play Fill".
- Tapping a picture to open it full screen.
- Choosing a picture by hand in the admin instead of by file name.

## Check before merging

- [ ] V1 Ch1 with `b1`, `b3`, `b5` uploaded: three pictures in order 1, 3, 5 with the right branch names.
- [ ] Upload one more (`v1ch1b2`): it appears in second place after a reload, without touching the HUB admin.
- [ ] A chapter with no pictures looks exactly as before.
- [ ] A chapter with one picture: no dots, no swiping.
- [ ] Swipe to picture 3, open and close "Play Fill": still on picture 3.
- [ ] Android Chrome and iPhone Safari: swiping sideways does not stop the page scrolling up and down.
- [ ] Laptop: mouse drag and arrow keys both work.
- [ ] Chapters admin page shows the thumbnails against the right rows.
- [ ] The phone downloads a resized picture (check the image size in the browser's network panel), not the 1920px original.

## Delivery

Built on the branch `chapter-illustrations`, then a pull request to `main`. Merging the pull request deploys to the live site.
