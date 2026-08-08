# Universal Media

`sp_universal_media` is an ACF content field for choosing a media source and display mode in one value. It supports Media Library image/video, YouTube, Vimeo, inline rendering, Fancybox triggers, and background media.

The field implementation lives in:

```text
acf/universal-media/index.php
```

## Field Config

Use a raw ACF field:

```php
->addField( 'media', 'sp_universal_media', [
    'label'    => __( 'Media', 'ACF' ),
    'required' => 0,
    'sources'  => [ 'library', 'youtube', 'vimeo' ],
    'displays' => [ 'inline', 'fancybox', 'background' ],
] )
```

Restrict sources or display modes:

```php
->addField( 'video', 'sp_universal_media', [
    'label'    => __( 'Video', 'ACF' ),
    'sources'  => [ 'youtube', 'vimeo' ],
    'displays' => [ 'fancybox' ],
] )
```

## Config Options

`sources`:
Allowed media sources:

```php
'library'
'youtube'
'vimeo'
```

`displays`:
Allowed display modes:

```php
'inline'
'fancybox'
'background'
```

## Saved Value

Media Library image/video:

```php
[
    'source'        => 'library',
    'attachment_id' => 123,
    'display'       => 'inline',
    'poster_id'     => 456,
    'autoplay'      => 0,
    'muted'         => 0,
    'loop'          => 0,
    'controls'      => 1,
    'playsinline'   => 1,
    'custom_play'   => 1,
]
```

Remote video:

```php
[
    'source'      => 'youtube',
    'url'         => 'https://www.youtube.com/watch?v=...',
    'display'     => 'fancybox',
    'poster_id'   => 456,
    'autoplay'    => 0,
    'muted'       => 0,
    'loop'        => 0,
    'controls'    => 1,
    'playsinline' => 1,
    'custom_play' => 1,
]
```

## Normalized Value

Use `sp_get_universal_media()` when you need structured media data without rendering:

```php
$media = sp_get_universal_media( get_field( 'media' ) );
```

For Media Library values it returns:

```php
[
    'source'        => 'library',
    'attachment_id' => 123,
    'url'           => 'https://...',
    'mime_type'     => 'image/jpeg',
    'media_type'    => 'image',
    'alt'           => '',
    'title'         => 'Image title',
    'display'       => 'inline',
    'poster_id'     => 0,
    'playback'      => [ ... ],
]
```

For YouTube/Vimeo values it returns:

```php
[
    'source'     => 'youtube',
    'url'        => 'https://...',
    'embed_url'  => 'https://www.youtube-nocookie.com/embed/...',
    'media_type' => 'embed',
    'display'    => 'inline',
    'poster_id'  => 0,
    'playback'   => [ ... ],
]
```

## Frontend Render Helper

```php
display_universal_media( get_field( 'media' ), [
    'class'       => 'hero__media',
    'loading'     => 'eager',
    'button_text' => __( 'Play video', 'targetized' ),
] );
```

Render args can override playback:

```php
display_universal_media( get_field( 'media' ), [
    'autoplay'  => true,
    'muted'     => true,
    'loop'      => true,
    'controls'  => false,
] );
```

## Display Modes

`inline`:
Renders an image, `<video>`, or embedded iframe in place. Uploaded videos can use the custom play button when `custom_play` is enabled and autoplay is disabled.

`fancybox`:
Renders a clickable trigger. Images open directly; uploaded videos render an inline hidden popup; YouTube/Vimeo open as iframe.

`background`:
Renders a background media wrapper for image, video, or embed.

## Validation

Uploaded library media must be image or video. Uploaded videos require a poster/cover image. Remote URLs must match the selected provider and produce a valid embed URL.

