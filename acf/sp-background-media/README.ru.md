# SP Background Media

`sp_background_media` — переиспользуемый ACF field type для адаптивного фона:

- изображение или видео с отдельными MP4, WEBM и poster;
- отдельные варианты Desktop, Tablet и Mobile;
- `cover` / `contain` и focal point по X/Y для каждого breakpoint;
- solid overlay или градиент из 2–8 color stops;
- overlay может сохраняться и выводиться без media attachment;
- frontend-хелперы для нормализации, рендера и определения representative overlay color.

```php
->addField( 'background', 'sp_background_media', [
	'label'         => __( 'Background', 'acf' ),
	'responsive'    => 1,
	'allow_video'   => 1,
	'allow_overlay' => 1,
] )
```

Для вывода используйте `display_background_media( $value )`, для нормализованных данных — `sp_get_background_media( $value )`, для выбора светлой/тёмной темы текста — `sp_background_media_overlay_color( $value )`.

Если все responsive-варианты — изображения, браузер выбирает их нативно из одного `<picture>` с `<source media>` для Mobile и Tablet. Video/mixed-варианты выводятся отдельными слоями и переключаются CSS media queries. Frontend-JavaScript для этого поля не подключается.

В video mode WEBM выводится первым, MP4 служит совместимым fallback, видео использует нативный muted autoplay, а полноразмерный poster показывается при `prefers-reduced-motion` средствами CSS. Старые значения с одним video attachment мигрируют автоматически.

Если тема определяет `sp_theme_breakpoint()`, модуль использует её breakpoint-конфигурацию. В остальных проектах значения можно передать фильтром `sp_background_media_breakpoints`; fallback — `576px` и `1024px`.

Полный контракт значения и примеры находятся в [README.en.md](README.en.md).
