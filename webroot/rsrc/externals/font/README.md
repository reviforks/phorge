# Bundled UI fonts

The default UI uses IBM Plex Sans, with system UI, emoji and symbol fonts
before IBM Plex Sans KR. Monospaced text uses IBM Plex Mono followed by
Nanum Gothic Coding and system monospace fallbacks. The Latin families come
first for their smaller files and real italic cuts. Both Korean families
deliberately reuse their upright files for italic faces, preventing
synthesized oblique Hangul. Their `unicode-range` declarations restrict them
to Hangul, CJK punctuation and fullwidth characters.

These files come from the pinned jsDelivr npm packages used by Hong Yongmin's
Apache-2.0 phorge-fonts extension in `revi/sandbox`, at commit
`9ae44140e0ec7a3a1ea4e4cfb1d4b6f6bb1e8dd4` under `PhabExt/phorge-fonts/`.
The font binaries are unchanged; only Nanum's filenames are shortened.

| Directory | Package | Version | License |
| --- | --- | --- | --- |
| `plex-sans/` | `@ibm/plex-sans` | 1.1.0 | SIL OFL 1.1 |
| `plex-sans-kr/` | `@ibm/plex-sans-kr` | 1.1.0 | SIL OFL 1.1 |
| `plex-mono/` | `@ibm/plex-mono` | 2.5.0 | SIL OFL 1.1 |
| `nanum-gothic-coding/` | `@fontsource/nanum-gothic-coding` | 5.3.0 | SIL OFL 1.1 |

Each directory includes the package's complete license as `LICENSE.txt`,
including its copyright notice. The font licenses permit bundling with
software while requiring the fonts to remain under OFL. They are separate
from Phorge's Apache-2.0 license, as described in the root `NOTICE`.

To refresh these committed assets, use `https://cdn.jsdelivr.net/npm/` plus
the package name, `@` and pinned version above, then these package paths:

- Sans: `fonts/complete/woff2/IBMPlexSans-{style}.woff2`, with `Regular`,
  `Italic`, `Bold` and `BoldItalic` styles; license: `LICENSE.txt`.
- Sans KR: `fonts/complete/woff2/hinted/IBMPlexSansKR-{style}.woff2`, with
  `Regular` and `Bold` styles; license: `LICENSE.txt`.
- Mono: `fonts/complete/woff2/IBMPlexMono-{style}.woff2`, with `Regular`,
  `Italic`, `Bold` and `BoldItalic` styles; license: `LICENSE.txt`.
- Nanum: `files/nanum-gothic-coding-korean-{weight}-normal.woff2`, with
  weights `400` and `700`; save as `korean-{weight}-normal.woff2` in
  `nanum-gothic-coding/`. Only the Korean subset is used. License: `LICENSE`.

Verify licenses when updating packages. Keep the binaries and licenses
together here, update `webroot/rsrc/css/font/font-plex.css` if necessary, and
run `bin/celerity map`. Celerity serves the fonts locally; stylesheet URLs
must not point at the download CDN.

Both IBM licenses declare "Plex" as a Reserved Font Name. Later subsetting or
re-encoding of the Plex files requires renaming the family under the OFL.
Nanum has no reserved name, so the Fontsource Korean subset is fine as-is.

Font stacks are defined in `CelerityDefaultPostprocessor` and inherited by
Dark Mode, High Contrast and Red/Green modes. Standard pages load `font-plex`
even without chrome, and preload Plex Sans Regular through its Celerity URI.
The existing Monospaced Font preference overrides code through the page's
inline style, while embedded comments and controls keep the UI font.
Remarkup quotations and reply headers are upright; emphasis within them
remains italic. FontAwesome is unchanged.
