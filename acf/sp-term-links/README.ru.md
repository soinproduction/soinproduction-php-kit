# SP Term Links

ACF field `taxonomy_urls`, который выводит отдельный URL для каждого term назначенной таксономии текущей записи.

```php
->addField( 'industry_urls', 'taxonomy_urls', [
	'taxonomy'   => 'case_study_industry',
	'icon_field' => 'icon',
] )
```

`taxonomy` задаёт источник terms, `icon_field` — необязательное ACF image field термина. Значение хранится как соответствие term ID → URL. Полный контракт описан в `README.en.md`.

AJAX проверяет taxonomy и требует её capability `assign_terms`.
