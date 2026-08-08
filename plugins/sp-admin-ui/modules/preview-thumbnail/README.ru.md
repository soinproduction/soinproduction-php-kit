# Preview Thumbnail

Предоставляет `register_acf_thumb_column()` для редактируемых изображений в таблицах записей и терминов WordPress. Источником может быть ACF image field или нативная featured image.

```php
add_action( 'after_setup_theme', static function (): void {
	register_acf_thumb_column(
		type: 'post',
		object: 'book',
		column_label: 'Cover',
		after: 'title',
		acf_field: 'cover',
		size: '60x80',
	);
} );
```

`type` принимает `post` или `term`. Пустой `acf_field` включает нативную featured image. Для редактирования нужна media library WordPress, а для ACF-поля — функции ACF `get_field()` и `update_field()`.
