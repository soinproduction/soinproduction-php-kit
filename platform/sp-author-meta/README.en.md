# SP Author Meta

Adds a reusable author metabox to selected post types. Enable the platform module with `sp-author-meta` and register supported post types explicitly.

```php
sp_register_author_metabox( [ 'blog', 'project' ], with_photo: true, with_position: true );
$author = sp_get_post_author( get_the_ID() );
```

`sp_register_author_metabox()` controls photo and position fields. `sp_get_post_author()` returns normalized author name, photo ID/URL and position data. Saving uses a dedicated nonce and post-edit capability checks.
