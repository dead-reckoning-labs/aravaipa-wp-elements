# Watch and app marks (Featured Race card)

The logo row on the Featured Race side card ("Syncs with") uses the same
official marks Obsession shows on obsession.run, copied byte for byte from the
Obsession repo's `public/integrations/` (a rename is the only change). Their
provenance and license terms are recorded in Obsession's
`docs/INTEGRATION-LOGOS.md`; this file mirrors the parts that matter here.

Rules, the same as Obsession's:

- Never redraw, recolor, crop, recompress, mask or add effects. The card sizes
  a mark with CSS only (`object-fit: contain`, height-led).
- Every mark sits on a plain white tile with a neutral 1px hairline. No
  colored edges.
- If a brand has no web-licensed file, the tile sets its name in our own type.
  Never a third-party logo site, never a drawn mark, never a generic watch.

| File | Obsession file | Source and license | sha256 |
|---|---|---|---|
| `garmin-connect-tile.png` | `Garmin_Connect_app_1024x1024-02.png` | Garmin Connect app tile from the Garmin Connect Developer Program branding kit (Garmin Developer API Brand Guidelines, "Authenticating applications": full app name and tile, name never abbreviated). The card shows the name "Garmin Connect" beside it. | `05b3d66f7f3bb7f4320c5365774abb6c97ec9caf9bfa6c47d1697bc7f0dd3113` |
| `polar-logo.svg` | `polar-logo.svg` | Polar's own site logo from polar.com, interoperability use under the Polar API License Agreement section 7.3. | `eefcee1957b6d3736f17c2a099c0c0aa18a25a451ea22902b9d3baf7dc76d179` |
| `wahoo-logo.png` | `WahooOnly_BlackOnWhite_TransBkgd_Logo.png` | Official Wahoo Media Kit logo, attribution clause of the Wahoo API Agreement; used in full, never cropped or altered. | `68dc1cf70d8ab37f5027615f1c63181b73f188b4265b29a62a2d163b24f3236d` |

Not files here:

- **COROS** and **Strava** keep their inline SVG marks in
  `includes/elements/featured-race.php` (`arv_featured_race_sync_mark`). The
  COROS path is the same coros.com file Obsession ships as `COROS.svg`
  (sha256 `0a15519317dd30a22442b7b3202ae726ed839bd3566de7d12e00012a85b38299`).
- **Apple Health**: Apple's HealthKit artwork is licensed for iOS app UI only,
  so on the web its name is set in our type, as Obsession does.
- Suunto and Health Connect are switched off in Obsession (2026-11-09), so
  their marks are not bundled.

The Garmin tile is Garmin's 4267 px master (about 260 KB). It is loaded lazily
and only where the card lists Garmin Connect; it is not resized here because
the files stay byte for byte as supplied.
