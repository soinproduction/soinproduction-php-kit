# SP Admin UI Taxonomy Checklist

Provides `sp_taxonomy_checklist_all_terms()` as a taxonomy metabox callback with multi-select checkboxes, hierarchical parent/child handling, select-all control, and a selected-term counter.

```php
add_meta_box(
	'taxonomy-topic',
	'Topics',
	'sp_taxonomy_checklist_all_terms',
	'book',
	'side',
	'default',
	[ 'taxonomy' => 'topic' ],
);
```

The save handler verifies autosave/revision state, nonce, taxonomy registration, post-type assignment, and `edit_post` permission before replacing term relationships.
