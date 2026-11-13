# Testing

Verified on the WordPress install in this workspace (`http://localhost/wordpress`, WordPress 7.1.4, PHP 8.2.18, Woody Code Snippets 2.7.12). The editor was not clicked through in a logged-in browser, and it was not deployed to `https://staging.driveredsafety.com` from this session.

## Automated roundtrip

A temporary PHP snippet and a temporary JavaScript snippet were created through `Repository`, then deleted, including their revision rows and generated files. No existing snippet titles matching the probe remained.

| Check | Result |
|---|---|
| Create an inactive PHP snippet | Passed |
| Code roundtrip, including backslashes, quotes, and newlines | Passed |
| Shortcode contains the new snippet ID | Passed |
| Saving code leaves `wbcr_inp_snippet_filters` and the description unchanged | Passed |
| Saving code does not activate an inactive snippet | Passed |
| Stale `base_modified` is rejected and does not write | Passed |
| Active PHP with a syntax error is rejected | Passed |
| A Studio revision is stored | Passed |
| A JS snippet writes `uploads/winp-css-js/{id}.js` with Woody's file header and the snippet code | Passed |
| Probe posts and the generated JS file are removed afterwards | Passed |
| `php -l` on every plugin PHP file | Passed |
| `tsc --noEmit` | Passed |
| `npm run build` | Passed. React, ReactDOM, and `wp-api-fetch` stay external. |

## Not verified in a browser

- Tab switching, unsaved-change prompts, and CodeMirror cursor/scroll retention
- Quick Open, code search, and keyboard shortcuts
- The revision diff and restore confirmation
- Panel resizing
- The original Woody editor screen after a Studio save
- A logged-in pass on staging

## Known limitations

- PHP snippets are stored the way Woody stores them: without a leading `<?php` tag. The syntax check wraps the code itself. A snippet that already starts with `<?php` fails that check, which is the same tokenizer Woody uses.
- Snippet type is chosen at creation and is not changed later, so existing shortcode names stay stable.
- The prototype's separate admin-only and front-end-only scopes are not values this Woody version stores. Supported scopes are `evrywhere`, `auto`, and `shortcode`. Other stored values are shown and left unchanged until you pick a supported one.
- JavaScript and CSS are not syntax-checked on the server. PHP and universal snippets are tokenized and never executed.
- `DISALLOW_FILE_EDIT` or `DISALLOW_FILE_MODS` makes Studio read-only. Accounts without `unfiltered_html` cannot save.
- Duplicates are inactive so the copy cannot register the same functions while the original is still running. File slugs are not copied.
- Inline JavaScript that already contains a `<script>` tag has block comments removed in the generated file only, matching Woody's own save.
- Editor font size, indent, and wrap are stored in the browser.
- Studio does not add a WordPress core revision on its own saves. Older WordPress revisions that already exist are still listed.
