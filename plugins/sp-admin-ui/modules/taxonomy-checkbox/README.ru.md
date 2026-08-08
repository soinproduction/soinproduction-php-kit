# Taxonomy Checkbox

Предоставляет callback `sp_taxonomy_checklist_all_terms()` для taxonomy metabox с множественным выбором, иерархией parent/child, переключателем «Select All» и счётчиком выбранных терминов.

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

Перед заменой связей терминов обработчик проверяет autosave/revision, nonce, регистрацию таксономии, её привязку к post type и право `edit_post`.
