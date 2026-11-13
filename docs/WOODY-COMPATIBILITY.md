# Woody compatibility

Inspected plugin: Insert PHP / Woody Code Snippets 2.7.12 in `wp-content/plugins/insert-php`.

## Records

| Item | Woody behavior | Snippet Studio |
|---|---|---|
| Post type | `wbcr-snippets` | Read and updated in place. IDs do not change. |
| Code | `post_content` | Written with `wp_update_post( wp_slash( ... ) )` so backslashes survive. Content filters are removed for code types so KSES, emoji conversion, and link rel rewriting cannot alter the source. Text and advert snippets still pass through normal content filters. |
| Shortcode | `[wbcr_{type}_snippet id="{id}"]`, or `[wbcr_snippet id="{id}"]` for universal | Computed the same way, including the `wbcr/inp/viewtable/where_use` filter. The ID is never rewritten. |
| Execution | `WINP_Execute_Snippet` | Not called. Studio does not eval snippet code and does not register a second runner. |
| Activation | meta `wbcr_inp_snippet_activate` equals `1` | Written only from the status switch or a properties save that changes it. |

## Save hooks that must not run

Woody's editor hooks `save_post`:

- `WINP_Snippet_MetaBox::after_saving_snippet` turns activation off, then may turn it back on from the "activate by default" option, and it evals PHP when the posted scope is `evrywhere` or `auto`.
- `WINP_Common_Snippet::save_post` writes the JS/CSS file only when the Woody editor nonce is present, so a REST save would otherwise leave the public file stale.

During a Studio write those Woody callbacks are removed and restored immediately afterwards. Studio then writes the JS/CSS file itself with the same header, inline wrapper, slug, and `wbcr_inp_css_js_version` rules. Page cache flushing calls `WINP_Helper::flush_page_cache()` when Woody provides it.

WordPress post revisions are not added for Studio saves, because this WordPress version records them from `post_updated` / `wp_after_insert_post`. Existing WordPress revisions are still readable. Studio's own history is the `{prefix}snippet_studio_revisions` table.

## Metadata that is never rewritten on a code save

Filters, WPML language, custom names, API ids, and every other `wbcr_inp_*` key stay untouched unless the field is one of the writable properties and its value actually changed. Duplicating a snippet copies Woody meta, forces the copy inactive, and drops `css_js_slug` / `css_js_exist_slug` / `css_js_version` so the copy does not share the original's asset file.

## Scopes and locations

Confirmed stored scopes: `evrywhere`, `shortcode`, `auto`.

The prototype's separate "administration" and "front-end" choices are not stored by this Woody version. Automatic insertion uses `auto` plus `wbcr_inp_snippet_location`. Locations are read at runtime from `WINP_Insertion_Locations`, including locations added through `wbcr/woody/add_custom_location`.

## Syntax

`WINP_Code_Validator::check_syntax()` tokenizes PHP and universal snippets. It does not `eval`. Studio refuses to save active PHP that fails that check. Woody's own fatal-error recovery is unchanged because Studio never executes the snippet in the admin request.

## Removing Studio

Deactivation does not delete snippets, meta, or asset files. Uninstall drops only `{$wpdb->prefix}snippet_studio_revisions` and the Studio user-preference rows. Woody keeps running the same posts.
