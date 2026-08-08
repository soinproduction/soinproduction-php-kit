# SP Author Meta

Добавляет переиспользуемый metabox автора для выбранных post types. Подключите platform-модуль `sp-author-meta`, затем явно зарегистрируйте нужные типы записей.

```php
sp_register_author_metabox( [ 'blog', 'project' ], with_photo: true, with_position: true );
$author = sp_get_post_author( get_the_ID() );
```

`sp_register_author_metabox()` управляет полями фотографии и должности. `sp_get_post_author()` возвращает нормализованные имя, ID/URL фотографии и должность. При сохранении проверяются nonce и право редактирования записи.
