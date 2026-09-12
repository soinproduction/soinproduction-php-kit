# SP Media

ACF field `sp_universal_media` для выбора источника и режима вывода media в одном значении. Поддерживает Media Library image/video, YouTube, Vimeo, inline, Fancybox и background.

```php
->addField( 'media', 'sp_universal_media', [
	'sources'  => [ 'library', 'youtube', 'vimeo' ],
	'displays' => [ 'inline', 'fancybox', 'background' ],
	'responsive' => 1,
] )
```

`responsive` по умолчанию выключен. После включения поле показывает три вкладки
по модели SP Background Media: Desktop, Tablet и Mobile. Tablet/Mobile наследуют
ближайшее более широкое media, если оставлены пустыми. Набор изображений выводится
через `<picture><source media="…">`; video, embed и смешанные варианты переключаются
scoped CSS media queries без дополнительного JS. Старое одиночное значение
переносится в `desktop` при первом сохранении responsive-поля.

Публичные helpers `sp_get_universal_media()` и `display_universal_media()` нормализуют и выводят значение. Полный формат и опции описаны в `README.en.md`.
