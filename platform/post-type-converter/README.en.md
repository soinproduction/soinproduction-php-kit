# Post Type Converter

Adds single-item and bulk administration actions for moving content between constructor-enabled post types. Enable it with `post-type-converter`.

Targets are restricted to post types with a compatible ACF `builder` flexible-content field and can be changed with `sp_post_type_converter_targets`. Requests verify nonce and edit capabilities before changing `post_type`.
