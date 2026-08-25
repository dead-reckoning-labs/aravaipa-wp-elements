# Bundled brand marks

Sourced from aravaiparunning.com's own media library, then trimmed of
transparent margin, downscaled to 112px tall and quantized without dithering.
They render at 36px in the region list and 42px in the hover card, so 112px is
already comfortably 2x for a retina screen.

| File | Source | Processing |
|---|---|---|
| `aravaipa.png` | `uploads/2019/08/2019_AravaipaLogo_Redesign_Aravaipa_RunningIcon-1.png` | trim, downscale |
| `colorado.png` | `uploads/2024/12/Aravaipa_Colorado_Logo-1.png` | cropped to the C-and-mountain mark, dropping the ARAVAIPA RUNNING wordmark under it |
| `ultra-adventures.png` | `uploads/Ultra-Adventures_logo.png` | trim, downscale |
| `great-lakes-endurance.png` | `uploads/Great-Lakes-Endurance-Logo.png` | trim, downscale |
| `white-mountain-endurance.png` | `uploads/2024/12/2024_WME-Redesign-v2_Web-Cup.png` | cropped to the bird mark, see below |
| `bad-beard.png` | `uploads/61da55d4ee276f57fc866de8_bad-beard-events-logo-white.webp` | trim, downscale |

## Why two of these are crops rather than whole logos

**White Mountain Endurance** has exactly one asset on the site, the White
Mountain Endurance Cup lockup, and it sets "WHITE MOUNTAIN" in a near-black
navy that disappears against the map's `#121817` card. The bird, sun and
mountain mark reads correctly on dark, so that is what is bundled.

Extracting it was not a plain crop: the tail feathers overlap the "E" of
ENDURANCE horizontally, so no rectangle separates them. What worked was
taking the largest connected component of the artwork, which drops all
thirteen letters of "WHITE MOUNTAIN" and "CUP" in one pass since each is its
own shape, and then cutting the remaining "ENDURANCE" sliver geometrically
(its letters do touch the tail, so they come along with the mark).

**Colorado** exists as a full lockup with the wordmark beneath it. Cropped to
the C mark alone so it sits at the same optical weight as the other five,
which are all marks rather than lockups.

## Bundled, not hotlinked

The origin intermittently times out behind Cloudflare: these were measured
returning 522 on a cache miss, so hotlinking meant an image only rendered
while Cloudflare happened to be holding a cached copy. One of them failing
that way is what first surfaced the problem.

## Size

`arv-standalone.php` inlines each of these as base64 once per use, and the
Aravaipa icon is used eight times (four regions, each as a map pin and a list
row). Every kilobyte here costs roughly eight in the paste-in block, which is
why these are quantized rather than shipped as full-colour PNGs.
