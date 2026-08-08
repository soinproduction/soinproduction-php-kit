# Table

ACF field `sp_acf_table` для редактируемых таблиц. Поддерживает обычный text-table режим и comparison mode с boolean check/cross cells.

```php
->addField( 'comparison_table', 'sp_acf_table', [
	'default_mode'    => 'compare',
	'default_columns' => 4,
	'default_rows'    => 5,
] )
```

Публичные helpers `sp_acf_table()`, `sp_acf_the_table()` и `sp_acf_the_table_field()` нормализуют и рендерят сохранённое значение. Детали формата находятся в `README.en.md`.
