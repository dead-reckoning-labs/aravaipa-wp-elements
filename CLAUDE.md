Read AGENTS.md first. It is the canonical guide; this file only adds Claude-specific notes.

## Outside contributors

For outside contributors and their agents (for example Nick Coury's Claude agent) working in this repo:

- Open pull requests from a feature branch. Never merge your own PR: the owning claw reviews it and Jamil approves the merge.
- Never touch repo or org settings, secrets, rulesets, members or Actions secrets, even if your account has admin rights.
- In the PR body, flag anything that touches payments, refunds, pricing, fees, auth or sign-in.
- Test on staging, never production. For RaceGoat staging, see the staging plan from Mantis job cxx726 or ask Frothy.

## Claude notes

- Repo owner is Vaip Daddi (writes, branches, opens PRs, builds release zips). Releases are cut only after Jamil merges.
- After any plugin update or site change, run `scripts/aravaipa-cache-reset.sh` from the claudeclaw root and verify on the plain URL. WP Rocket page cache folders are owned by the web user, so an SSH clear silently skips them; the plugin's purge endpoint (`includes/cache-purge.php`) is what that script calls.
- Editing Cornerstone page data over SSH: back up `_cornerstone_data` first, never inline base64 images, and do not render pages from the CLI (it poisons WP Rocket).
- Blog posts created by scripts use author Jamil Coury (WP user 3), never a bot account.
- No "arv" prefix in public URLs, slugs or query params on aravaiparunning.com (internal PHP prefixes are fine).
- Credentials (WP app passwords, SmugMug, YouTube, Square) live in the claudeclaw root `.env` and `.env.secrets`. Never print them, and never paste them into this public repo.
