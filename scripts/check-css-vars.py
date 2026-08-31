"""Every --arv-* custom property read without a fallback has to be set.

An undefined custom property with no fallback is invalid at computed-value
time. The declaration then resolves to `unset`, which for an inherited
property like font-size means `inherit`, not the browser's own rule. Nothing
warns: no console message, nothing in a diff, the heading just renders at
body size.

--arv-fs-h2 shipped exactly that way. Three headings asked for it, nothing
ever defined it, and the Photos heading, the Latest feed's heading and the
Media hero's title all rendered at 15px for as long as it lasted.

A use that carries a fallback, `var(--arv-sbw, 0px)`, is a deliberate
default and is not checked: those are set from JavaScript at runtime and are
meant to work before it lands.
"""

import glob
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CSS = os.path.join(ROOT, 'assets', 'aravaipa-elements.css')

with open(CSS, encoding='utf-8') as handle:
    css = handle.read()

used = set(re.findall(r'var\(\s*(--arv-[a-z0-9-]+)\s*\)', css))
seen = set(re.findall(r'(--arv-[a-z0-9-]+)\s*:', css))

# Set inline from an element's render, or from a script at runtime.
sources = glob.glob(os.path.join(ROOT, 'includes', '**', '*.php'), recursive=True)
sources += glob.glob(os.path.join(ROOT, 'assets', '*.js'))

for path in sources:
    with open(path, encoding='utf-8') as handle:
        seen |= set(re.findall(r'(--arv-[a-z0-9-]+)', handle.read()))

missing = sorted(used - seen)

if missing:
    sys.stderr.write(
        'assets/aravaipa-elements.css: read with no fallback and never set anywhere: '
        + ', '.join(missing) + '\n'
    )
    sys.exit(1)
