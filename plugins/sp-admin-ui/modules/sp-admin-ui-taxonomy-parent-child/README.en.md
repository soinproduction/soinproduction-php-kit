# SP Admin UI Taxonomy Parent Child

Opt-in taxonomy metabox for one root term (radio buttons) and multiple descendants (checkboxes). Enable `sp-admin-ui-taxonomy-parent-child` in the `sp-admin-ui` submodule list alongside the other modules your theme needs; it is disabled by default.

Set a hierarchical taxonomy's `meta_box_cb` to `sp_taxonomy_parent_radio_child_checkboxes`, or pass that callback to `add_meta_box()` with `['taxonomy' => 'your_taxonomy']` as callback arguments. Taxonomy names and post types are supplied by the consuming application.

The existing callback and `sp_taxonomy_parent_radio_child_checkboxes_save($post_id)` API are preserved. Input names remain `sp_parent_child_tax[taxonomy][parent]` and `sp_parent_child_tax[taxonomy][children][]`. The save handler runs at `save_post` priority 10, checks the post context and edit/term-assignment permissions, and only accepts hierarchical taxonomies attached to the post type. `0` clears selection; a root term retains only selected descendants from its own branch. Autosaves, revisions and requests without the payload leave assignments unchanged.

The UI keeps children of other roots disabled and unchecked. Descendants at arbitrary depth are supported. Rendering uses existing assignments; no terms or relationships are migrated. The standard WordPress post editor validates its save request before calling this hook; custom callers must validate their own request too.
