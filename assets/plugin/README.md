# Plugin artwork

What WordPress shows for this plugin on the Plugins and Updates screens, and
in the "View details" modal. Without these it falls back to a generic grey
plug icon.

| File | Where it appears |
|---|---|
| `icon-128x128.png` | Plugins list, Updates screen |
| `icon-256x256.png` | the same, on retina displays |
| `banner-772x250.png` | header of the "View details" modal |
| `banner-1544x500.png` | the same, on retina displays |

Sizes are WordPress's own conventions, not arbitrary; naming them anything
else means they are ignored.

Built from Aravaipa's real marks, not redrawn:

- icons: `uploads/2019/08/2019_AravaipaLogo_Redesign_Aravaipa_RunningIcon-1.png`
  centred on `#1d2624`, the site's own dark, sampled from its CSS.
- banners: `uploads/2019_AravaipaLogo_Redesign_Aravaipa_RunningIcon_White-3.png`,
  the white variant, because the standard lockup sets "ARAVAIPA" in a dark
  navy that disappears against this background. The banner says only
  "ELEMENTS" beside it: the mark already reads "ARAVAIPA RUNNING", and an
  earlier version that spelled it out again said the word twice.

These are referenced by absolute URL from `includes/updater.php`, pointing at
the installed plugin's own directory, so they resolve for whatever version is
actually on the site.
