# Remove Post Slug

Добавляет в Settings → Reading управление удалением bases выбранных CPT и таксономий из публичных URL. Подключается именем `remove-post-slug`.

Модуль фильтрует ссылки, восстанавливает main query/request parsing и сбрасывает rewrite rules при изменении настроек. Корневые slugs могут конфликтовать со страницами, CPT и terms — перед включением на заполненном сайте проверьте маршруты и rollback.
