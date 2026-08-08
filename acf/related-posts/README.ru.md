# Related Posts

ACF field/factory `sp_related_posts()` для автоматического выбора связанных записей. Кандидаты оцениваются по общим таксономиям, ACF text values, словам заголовка и, опционально, контенту.

```php
->addFields( sp_related_posts( 'related_posts', [
	'count'                => 3,
	'candidate_post_types' => [ 'case_study' ],
	'use_taxonomies'       => 1,
	'use_acf'              => 1,
] ) )
```

Вес каждого источника, minimum score, исключённые fields и предел кандидатов настраиваются аргументами. Полный контракт описан в `README.en.md`.
