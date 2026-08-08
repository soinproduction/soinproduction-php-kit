# SP Admin UI Taxonomy Radio

Предоставляет callback `sp_taxonomy_radio_all_terms()` для taxonomy metabox с одиночным выбором. Он выводит все термины, позволяет очистить связь и показывает авторизованным пользователям ссылку на управление таксономией.

```php
add_meta_box(
	'taxonomy-genre',
	'Genre',
	'sp_taxonomy_radio_all_terms',
	'book',
	'side',
	'default',
	[ 'taxonomy' => 'genre' ],
);
```

Обработчик проверяет контекст запроса и permissions, затем сохраняет не более одного связанного термина.
