# Bundled brand marks

Sourced from aravaiparunning.com's own media library, then trimmed of
transparent margin, downscaled to 96px and quantized to 64 colours. They
render at 36px in the region list and 42px in the hover card, so 96px is
already about 2.3x for a retina screen.

| File | Source |
|---|---|
| `aravaipa.png` | `uploads/2019/08/2019_AravaipaLogo_Redesign_Aravaipa_RunningIcon-1.png` |
| `ultra-adventures.png` | `uploads/Ultra-Adventures_logo.png` |
| `great-lakes-endurance.png` | `uploads/Great-Lakes-Endurance-Logo.png` |

Bundled rather than hotlinked from the media library because that origin
intermittently times out behind Cloudflare: both logos were measured
returning 522 on a cache miss, so hotlinking meant the image only rendered
while Cloudflare happened to be holding a cached copy.

Size matters more than it looks: `arv-standalone.php` inlines each of these
as base64 once per use, and the Aravaipa icon is used ten times (five
regions, each as a map pin and a list row). Every kilobyte here costs
roughly ten in the paste-in block.

Still needed: **White Mountain Endurance** and **Bad Beard Events**. Neither
has a brand mark anywhere on aravaiparunning.com (their pages carry sponsor
logos only), so both currently render without one.
