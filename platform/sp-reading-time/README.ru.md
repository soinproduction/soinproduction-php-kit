# SP Reading Time

Рассчитывает время чтения по контенту записи и рекурсивно собранным значениям ACF. Подключается именем `sp-reading-time`.

Основной API:

- `sp_reading_time_for_post( int $post_id = 0, string $label = 'min read', array $acf_fields = [] ): string`
- `sp_reading_time( $content = null, string $label = 'min read' ): string`

Фильтры `sp_reading_time_skip_keys` и `sp_reading_time_acf_fields` управляют ACF-данными, которые участвуют в расчёте.
