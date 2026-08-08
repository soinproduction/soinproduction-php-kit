# SP Media

ACF field `sp_universal_media` для выбора источника и режима вывода media в одном значении. Поддерживает Media Library image/video, YouTube, Vimeo, inline, Fancybox и background.

```php
->addField( 'media', 'sp_universal_media', [
	'sources'  => [ 'library', 'youtube', 'vimeo' ],
	'displays' => [ 'inline', 'fancybox', 'background' ],
] )
```

Публичные helpers `sp_get_universal_media()` и `display_universal_media()` нормализуют и выводят значение. Полный формат и опции описаны в `README.en.md`.
