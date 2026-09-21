# Release checklist

This checklist describes manual actions for the release owner. Preparing files
or running validation does not authorize commit, push, merge, release publication,
SVN writes, or deployment. Publishing a qualifying GitHub Release triggers the
WordPress.org deployment workflow.

## Review and merge

1. In GitHub Desktop, review all changed and untracked files, especially request
   handling, saved-setting compatibility, metadata, workflow triggers, and
   `.distignore`. Confirm target version **1.1.0**, WordPress minimum **6.8**, PHP
   minimum **7.4**, and `Tested up to: 7.1`.
2. Confirm the five approved `.wordpress-org` PNGs are included in the reviewed
   changes and will be tracked. Preserve their bytes, including the current
   1.1.0 `.wordpress-org/screenshot-1.png`. Review the obsolete root
   `screenshot-1.png` deletion alongside the canonical artwork additions.
   Artwork must be excluded from the runtime ZIP.
3. Run available local validation, read `docs/audit-1.1.0.md`, and record pending
   runtime checks. Ensure no ZIPs, dependencies, credentials, cache/log files, or
   local WordPress/database files are included in the commit.
4. Use GitHub Desktop to commit the reviewed working tree on the appropriate
   review branch, then push it. Create the PR through Desktop/GitHub. Have it
   reviewed, resolve findings, and require **Validate plugin** and **Plugin
   Check** to pass. Merge through the normal review process. No release tag is
   needed for these checks.

## Build and test the release commit

5. Confirm the merged release commit on `main` has passing validation and Plugin
   Check. Inspect their logs and WordPress/PHP versions, rather than relying only
   on an old green badge. Do not ignore genuine Plugin Check findings.
6. Open **GitHub > Actions > Build release ZIP > Run workflow** and select the
   reviewed release ref. Wait for success and download the
   **staging-bot-block-1.1.0.zip** verified installable artifact. The workflow
   stages a `.distignore`-filtered slug directory before the 10up action and
   verifies that action's actual output. Do not use GitHub's automatically
   generated source-code archive or a locally modified checkout.
7. Inspect the archive and install it on a disposable **WordPress 7.1.1** site.
   Complete [manual QA](manual-qa.md) using MySQL/MariaDB, including upgrade, external redirects,
   administrator recovery, ACME, proxy/cache behavior, uninstall, and the
   WordPress/PHP support floor. Record unavailable checks as pending. Release
   approval requires the target runtime checks, not merely static checks.
8. Verify the artwork bytes and filenames on the release commit: both banners,
   both icons, and `screenshot-1.png`. The screenshot depicts the current 1.1.0
   settings interface and matches screenshot 1's public readme caption. The deploy
   configuration must use `ASSETS_DIR: .wordpress-org` and runtime packaging must
   exclude it.

## Publish deliberately

9. In repository **Settings > Secrets and variables > Actions**, confirm
   `SVN_USERNAME` and `SVN_PASSWORD` exist. Configure them only if missing or
   deliberately rotating them. Use the appropriate WordPress.org SVN credentials;
   never paste their values into code, logs, issues, or release notes. Build and
   validation workflows must continue to work without these secrets. Configure
   required reviewers on the `wordpress-org` GitHub environment if available;
   its YAML declaration alone is not an approval gate.
10. After approval, use GitHub Desktop to tag the exact tested commit **1.1.0**
    and push the tag. Never move/reuse an already published tag. Tag creation
    alone is not the deploy trigger in this repository.
11. In GitHub Releases, select **1.1.0**, title it **1.1.0**, write user-facing
    release notes, and attach the tested installable ZIP if a GitHub asset is
    desired. Verify the target commit and avoid marking the release as a
    prerelease. Publishing this release deliberately starts **Deploy
    WordPress.org**; a draft does not.
12. Follow **Actions > Deploy WordPress.org**. Its validation/Plugin Check gates
    must pass before the 10up action runs. The release tag must exactly equal
    plugin header Version, `STAGING_BOT_BLOCK_VERSION`, and readme Stable tag,
    all **1.1.0**. Missing/duplicate metadata or `v1.1.0` fails without prefix
    normalization. The validated version is passed explicitly to 10up. It
    publishes runtime files to SVN trunk and `tags/1.1.0`, and publishes the
    canonical artwork to top-level `/assets`. If it fails, inspect the actual
    SVN state before retrying. Do not move the tag, overwrite a published
    version, or manually force a deployment to conceal the failure.

## Verify WordPress.org

13. Inspect [SVN trunk](https://plugins.svn.wordpress.org/staging-bot-block/trunk/)
    and [SVN tags/1.1.0](https://plugins.svn.wordpress.org/staging-bot-block/tags/1.1.0/).
    Confirm both contain the intended runtime files and version **1.1.0**, no
    development files or directory artwork, and readme `Stable tag: 1.1.0`.
14. Inspect [SVN assets](https://plugins.svn.wordpress.org/staging-bot-block/assets/).
    Confirm the five correctly named PNGs and their dimensions/bytes. They must
    be siblings of trunk/tags at SVN top level, not nested in either runtime
    tree.
15. After directory processing, verify the [public plugin
    page](https://wordpress.org/plugins/staging-bot-block/): version **1.1.0**,
    `Tested up to: 7.1`, minimums, newest changelog first, and correct banners,
    icons, and screenshot. Directory processing/caching can delay visibility;
    investigate rather than publishing a second tag reflexively.
16. Download the ZIP from the WordPress.org plugin page itself. Inspect its root,
    runtime contents, version, and artwork exclusions. Install this exact public
    ZIP on a second clean disposable site and repeat activation, default-off,
    external redirect, administrator/login access, and uninstall checks. Record
    this result as the final publication validation.
