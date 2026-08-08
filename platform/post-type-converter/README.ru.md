# Post Type Converter

Добавляет одиночные и bulk admin actions для переноса контента между post types с совместимым конструктором. Подключается именем `post-type-converter`.

Targets ограничены типами записей с совместимым ACF flexible field `builder` и настраиваются фильтром `sp_post_type_converter_targets`. Перед изменением `post_type` проверяются nonce и права редактирования.
