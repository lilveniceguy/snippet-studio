# Snippet Studio

Snippet Studio is a companion editor for [Woody Code Snippets](https://wordpress.org/plugins/insert-php/) (the Insert PHP plugin). It does not replace Woody, does not change Woody's files, and does not run snippets itself. Woody remains the only execution engine. Deactivating or deleting Snippet Studio leaves every snippet where it is.

## Requirements

- WordPress 6.5 or newer
- PHP 8.0 or newer
- Woody Code Snippets active
- An administrator who can edit Woody snippets and who has the `unfiltered_html` capability

## Install

1. Copy `wp-content/plugins/snippet-studio` into the site.
2. In WordPress admin, activate **Snippet Studio**.
3. Open **Snippet Studio** in the admin menu, or the Snippet Studio link under Woody snippets.

The `build/` directory already contains the production JavaScript and CSS. You do not need Node to install the plugin.

To rebuild the editor after changing `src/`:

```bash
cd wp-content/plugins/snippet-studio
npm install
npm run build
```

## What it edits

Snippet Studio reads and writes the Woody post type `wbcr-snippets`. Code stays in `post_content`. These meta keys are the only Woody fields the editor writes, and only when you change them:

- `wbcr_inp_snippet_type` (set when a snippet is created; not changed later)
- `wbcr_inp_snippet_activate`
- `wbcr_inp_snippet_scope`
- `wbcr_inp_snippet_priority`
- `wbcr_inp_snippet_location`
- `wbcr_inp_snippet_p_number`
- `wbcr_inp_snippet_linking`
- `wbcr_inp_snippet_description`
- `wbcr_inp_snippet_tags`

For JavaScript and CSS snippets, saving code or linking rebuilds the file in `uploads/winp-css-js` using Woody's external/inline wrapping rules, then asks Woody to flush page caches when that helper is available.

Scope values are stored exactly as Woody uses them, including the historical spelling `evrywhere`. Other legacy values are shown and kept until you choose a supported scope.

## Revisions

Revisions live in `{prefix}snippet_studio_revisions`. The first successful edit of an existing snippet stores the original code before the new version. Restoring a revision stores the current version first. WordPress revisions that already exist for a snippet are listed when they are real; Snippet Studio does not invent history. Uninstalling the plugin drops only this table.

## Safety

- REST requests use the WordPress cookie nonce.
- Writes require the Woody edit capability and `unfiltered_html`.
- `DISALLOW_FILE_EDIT` or `DISALLOW_FILE_MODS` makes the screen read-only.
- PHP is checked with Woody's tokenizer (`WINP_Code_Validator::check_syntax`) and is not executed.
- Active PHP with a syntax error is rejected. Inactive PHP can be saved after confirmation.
- A save is rejected when the snippet changed after it was opened, unless you explicitly overwrite it.

See `docs/WOODY-COMPATIBILITY.md` and `docs/TESTING.md`.
