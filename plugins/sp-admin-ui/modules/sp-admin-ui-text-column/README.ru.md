# SP Admin UI Text Column

Предоставляет `register_acf_text_column()` для текстовых колонок с редактированием по месту в таблицах записей и терминов WordPress. Непустой `acf_field` выводит и обновляет скалярное значение ACF. Пустой `acf_field` выводит и обновляет нативный excerpt записи или description термина.

```php
add_action( 'after_setup_theme', static function (): void {
	register_acf_text_column(
		type: 'post',
		object: 'service',
		column_label: 'Price',
		after: 'title',
		acf_field: 'service_price',
		column_key: 'service_price',
		width: 120,
		prefix: 'AED ',
	);

	register_acf_text_column(
		type: 'post',
		object: 'service',
		column_label: 'Excerpt',
		after: 'service_price',
		column_key: 'service_excerpt',
		width: 320,
		max_words: 24,
	);
} );
```

Чтобы одновременно показать ACF-поля и нативный excerpt, вызовите функцию несколько раз с уникальными `column_key`. `type` принимает `post` или `term`; для термина пустой `acf_field` выводит description. `width` задаётся в пикселях, а `max_words: 0` отключает сокращение. `prefix` и `suffix` добавляются только к непустым значениям.

Кликните по значению, чтобы отредактировать его. Тип ACF-поля автоматически включает текстовый или числовой input; нативные excerpt и description используют textarea. Enter сохраняет однострочное поле, Ctrl/Cmd+Enter сохраняет textarea, Escape отменяет редактирование. Автоопределение можно переопределить через `input_type: 'text'`, `'textarea'`, `'number'`, `'email'` или `'url'`. Параметр `editable: false` создаёт колонку только для чтения.
