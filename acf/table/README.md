# SP Table

`sp_acf_table` is an ACF content field for editable tables. It supports a text table mode and a comparison mode with boolean check/cross cells. Columns are managed inside the field UI and can vary per table.

The field implementation lives in:

```text
core/acf/table/index.php
```

## Field Config

Use a raw ACF field:

```php
->addField( 'comparison_table', 'sp_acf_table', [
    'label'           => __( 'Comparison Table', 'ACF' ),
    'default_mode'    => 'compare',
    'default_columns' => 4,
    'default_rows'    => 5,
] )
```

Or local field array:

```php
[
    'key'             => 'field_comparison_table',
    'label'           => 'Comparison Table',
    'name'            => 'comparison_table',
    'type'            => 'sp_acf_table',
    'default_mode'    => 'compare',
    'default_columns' => 4,
    'default_rows'    => 5,
]
```

## Config Options

`default_mode`:
`table` or `compare`.

`default_columns`:
Initial column count. Editors can add/remove/reorder columns in the field UI. The field caps columns at `12`.

`default_rows`:
Initial row count. The field caps rows at `200`.

## Returned Value

```php
[
    'mode'    => 'table',
    'columns' => [
        [ 'id' => 'col_1', 'label' => 'Title 1', 'width' => 1, 'align' => 'left' ],
    ],
    'rows' => [
        [
            'id'    => 'row_1',
            'cells' => [
                [ 'type' => 'text',  'text' => 'Feature name' ],
                [ 'type' => 'check', 'text' => '' ],
                [ 'type' => 'cross', 'text' => '' ],
            ],
        ],
    ],
]
```

Cell types:

```php
'text'
'check'
'cross'
```

In `table` mode every cell is normalized to `text`. In `compare` mode the first column is always text and other columns are normalized to boolean `check`/`cross` cells.

Column `width` is a relative weight from `1` to `12`. The frontend renderer converts weights to a `<colgroup>` with percentage widths.

Column `align` can be `left`, `center`, or `right`. It is applied to header and body cells on the frontend and previewed in the admin UI.

## Markup Variants

Simple/text tables render with:

```html
<div class="sp-table-wrapper sp-table-wrapper--table" style="--sp-table-columns: 4">
    <table class="sp-table sp-table--table sp-table--simple sp-table--cols-4" data-columns="4">
```

Comparison tables render with:

```html
<div class="sp-table-wrapper sp-table-wrapper--compare" style="--sp-table-columns: 4">
    <table class="sp-table sp-table--compare sp-table--cols-4" data-columns="4">
```

The frontend can style the text-table variants from the same markup with any column count. The comparison variant uses row headers in the first column and the built-in check/cross SVG markers in the other columns.

## Frontend Helpers

Return rendered table HTML:

```php
echo sp_acf_render_table( get_field( 'comparison_table' ), [
    'class'       => 'pricing-table',
    'caption'     => 'Plan comparison',
    'empty_value' => '-',
] );
```

Render by field name:

```php
echo sp_acf_table( 'comparison_table' );
```

Echo helpers:

```php
sp_acf_the_table( get_field( 'comparison_table' ) );
sp_acf_the_table_field( 'comparison_table' );
```

Normalize without rendering:

```php
$table = sp_acf_table_normalize( get_field( 'comparison_table' ) );
```

## Render Arguments

`class`:
Extra classes added to the `<table>` element.

`caption`:
Optional table caption.

`empty_value`:
Text rendered for `empty` cells.

`render_empty`:
If false, empty field values return an empty string.
