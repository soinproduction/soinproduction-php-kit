# Archive Builder

`archive_builder` is an ACF field type for configurable post archives with taxonomy filters, sorting, posts-per-page controls, AJAX pagination, load more / infinite scroll, empty states, confirm/reset buttons, and reusable granular render helpers.

The active field implementation lives in:

```text
acf/archive-builder/index.php
```

PHP Kit loads this entrypoint when `archive-builder` is enabled in the `acf` configuration.

## Field Config

Use the field in section `fields.php`:

```php
->addFields( archive_builder( 'archive', [
    'label'           => __( 'Archive Settings', 'ACF' ),
    'post_type'       => 'case_study',
    'filters_enabled' => 1,
    'confirm'         => 1,
    'reset'           => 1,
    'disable_empty'   => 1,

    'filters' => [
        'case_study_industry' => [ 'enabled' => 1, 'ui' => 'buttons' ],
        'case_study_service'  => [ 'enabled' => 1, 'ui' => 'buttons' ],
        'case_study_location' => [ 'enabled' => 1, 'ui' => 'select' ],
    ],

    'per_page'        => 9,
    'pagination_type' => 'pagination',
    'order_mode'      => 'newest',

    'page_arg'        => 'page',
    'url_page_arg'    => 'page',
    'sort_arg'        => 'case_sort',
    'per_page_arg'    => 'case_per_page',
] ) )
```

## Config Options

`post_type`:
Target post type for the query.

`filters_enabled`:
Enables or disables taxonomy filters globally.

`confirm`:
When enabled, `sp_archive_confirm()` can render a confirm/apply button. In JS, filter/sort/per-page changes do not auto-query while confirm mode is active. The query runs after clicking the confirm button.

`reset`:
When enabled, `sp_archive_reset()` can render a reset button. It clears filters, restores default sorting, restores default per-page value, and queries page 1.

`disable_empty`:
When enabled, filter options that would return zero posts are disabled. Availability is calculated server-side for the current filter combination and refreshed after each AJAX request.

`filters`:
Taxonomy filter definitions. Supported `ui` values:

```php
'buttons'
'select'
'multiselect'
'radio'
'checkbox'
```

`per_page`:
Default number of posts per page.

`pagination_type`:

```php
'pagination'
'load_more'
'infinity_scroll'
```

`order_mode`:
Default sort mode:

```php
'newest'
'oldest'
'az'
'za'
'menu_order'
```

`page_arg`:
Internal AJAX page argument.

`url_page_arg`:
Public URL page argument.

`sort_arg`:
URL/control argument used by `sp_archive_sort()`.

`per_page_arg`:
URL/control argument used by `sp_archive_per_page()`.

## Section Template Example

```php
<?php
    $section_id = get_sub_field( 'section_id' );
    $section_bg = get_sub_field( 'section_bg' );
    $archive    = get_sub_field( 'archive' );

    sp_archive_setup( $archive, 'template_parts/section-archive-cases/card' );
?>

<section class="section-archive-cases" style="--section-bg: <?= esc_attr( $section_bg ); ?>" <?php if ( $section_id ) : ?>id="<?= esc_attr( $section_id ); ?>"<?php endif; ?> <?= sp_archive_attr(); ?>>
    <div class="container">
        <div class="grid-cols-12 small-tablet-grid-cols-1">
            <div class="full-row d-[flex] f-dir-[column] gap-[10px]">
                <div class="d-[flex] gap-[20px] jc-[center]">
                    <?php sp_archive_filters( 'All', '234' ); ?>
                    <?php sp_archive_sort( [], 'Sort', '234' ); ?>
                    <?php sp_archive_per_page( [ 6 => '6', 9 => '9', 12 => '12' ], 'Show', '234' ); ?>
                </div>

                <div class="d-[flex] gap-[20px] jc-[center]">
                    <?php sp_archive_confirm( 'Застосувати фільтри', 'main-button main-button--small' ); ?>
                    <?php sp_archive_reset( 'Видалити фільтри', 'main-button main-button--small' ); ?>
                </div>
            </div>

            <?php sp_archive_cards( 'grid-cols-3 full-row gap-5 laptop-grid-cols-2' ); ?>
            <?php sp_archive_pagination( 'full-row' ); ?>
        </div>
    </div>

    <?php sp_archive_config(); ?>
</section>
```

## Required Template Flow

Call these in order:

```php
sp_archive_setup( $archive, 'template_parts/my-section/card' );
```

Then inside the `<section>`:

```php
<?= sp_archive_attr(); ?>
sp_archive_filters();
sp_archive_sort();
sp_archive_per_page();
sp_archive_confirm();
sp_archive_reset();
sp_archive_cards();
sp_archive_pagination();
sp_archive_config();
```

`sp_archive_config()` must be inside the element with `sp_archive_attr()` because JS reads the JSON config from the section.

## Granular Render Helpers

`sp_archive_attr(): string`
Returns `data-sp-archive`.

`sp_archive_config(): void`
Outputs hidden JSON config for JS.

`sp_archive_filters(string $all_label = '', string $class = ''): void`
Renders configured taxonomy filters. `$class` is passed into each child filter control.

`sp_archive_sort(array $options = [], string $title = '', string $class = ''): void`
Renders a sort select. Requires `sort_arg` in config or setup options.

Default options:

```php
newest      Newest first
oldest      Oldest first
az          A-Z
za          Z-A
menu_order  Featured
```

Custom example:

```php
sp_archive_sort(
    [
        [ 'value' => 'newest', 'label' => 'Newest' ],
        [ 'value' => 'oldest', 'label' => 'Oldest' ],
        [ 'value' => 'az',     'label' => 'A-Z' ],
        [ 'value' => 'za',     'label' => 'Z-A' ],
    ],
    'Sort',
    '234'
);
```

`sp_archive_per_page(array $options = [], string $title = '', string $class = ''): void`
Renders a posts-per-page select. Uses `per_page_arg`.

Example:

```php
sp_archive_per_page(
    [ 6 => '6', 9 => '9', 12 => '12' ],
    'Show',
    '234'
);
```

`sp_archive_confirm(string $label = '', string $class = ''): void`
Renders a confirm/apply button only when `confirm => 1`.

`sp_archive_reset(string $label = '', string $class = ''): void`
Renders a reset button only when `reset => 1`.

`sp_archive_cards(string $class = ''): void`
Renders the card list wrapper with `data-sp-archive-list`.

`sp_archive_pagination(string $class = ''): void`
Renders the pagination wrapper with `data-sp-archive-pagination`. If there is no pagination HTML, the wrapper is rendered empty without whitespace so CSS `:empty { display: none; }` works.

## Confirm And Reset Button Behavior

Confirm button:

- Renders disabled by default.
- Becomes enabled when filters, sort, or per-page differ from currently applied state.
- Runs AJAX query for page 1.
- In confirm mode, changing controls does not query automatically.

Reset button:

- Disabled when filters, sort, and per-page are default/empty.
- Clears all filters.
- Resets sort to `order_mode`.
- Resets per-page to `per_page`.
- Runs AJAX query for page 1.

## AJAX Behavior

The JS module reads:

```html
<script type="application/json" data-sp-archive-config>...</script>
```

AJAX sends:

```text
archive_token
paged
sort
per_page
taxonomy filter values
```

Server-side sensitive config comes from the transient token:

```php
post_type
filters
card_template
empty_template
pagination_type
order_mode
page_arg
url_page_arg
sort_arg
per_page_arg
```

AJAX response includes:

```text
html
pagination
found
max_pages
current_page
has_next
filter_availability
```

## Empty State

By default, `sp_archive_setup()` looks for an empty template next to the card template:

```text
template_parts/my-section/card.php
template_parts/my-section/empty.php
```

For case studies:

```text
template_parts/section-archive-cases/empty.php
```

You can override it:

```php
sp_archive_setup(
    $archive,
    'template_parts/section-archive-cases/card',
    [ 'empty_template' => 'template_parts/section-archive-cases/empty' ]
);
```

## Pagination Scroll

When a numbered pagination link is clicked, JS updates the list by AJAX and scrolls to `[data-sp-archive-list]`.

The scroll offset accounts for:

```text
#wpadminbar
#header
.header.fixed-block
.fixed-block.sticky
```

and subtracts an additional `20px`.

## URL State

The URL can store:

```text
page
taxonomy filter args
sort_arg
per_page_arg
```

On browser back/forward, JS restores filters, sort, per-page and queries the correct page.

## Disable Empty Options

Enable it in the field config:

```php
'disable_empty' => 1,
```

The server calculates an availability map for each taxonomy filter:

```php
[
    'case_study_industry' => [
        'all'     => true,
        'roofing' => true,
        'hvac'    => false,
    ],
]
```

The front-end applies this map to every supported UI mode:

```php
'buttons'
'select'
'multiselect'
'radio'
'checkbox'
```

Options with `false` are disabled because selecting them together with the current active filters would render the empty card. Currently selected options stay enabled so the visitor can remove or reset them.

## Notes

- Use `sp_archive_filters()` for taxonomy controls.
- Use `sp_archive_sort()` for sorting controls.
- Use `sp_archive_per_page()` for posts-per-page controls.
- Use `sp_archive_confirm()` if changes should wait for an explicit apply action.
- Use `sp_archive_reset()` to clear every archive control.
- Keep `sp_archive_config()` inside the archive section.
- Keep `sp_archive_cards()` wrapper present; JS depends on `data-sp-archive-list`.
- Keep `sp_archive_pagination()` wrapper present; JS depends on `data-sp-archive-pagination`.
