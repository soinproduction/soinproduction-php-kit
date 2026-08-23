<?php

if (! defined('ABSPATH')) {
    exit;
}

final class SP_List_Columns_Plugin
{
    private const VERSION = '1.0.3';
    private const BUTTON  = 'list_columns';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'serve_tinymce_script'], 0);
    }

    public static function get_plugin_url(): string
    {
        return add_query_arg(
            [
                'sp_list_columns_js' => '1',
                'ver'                => self::VERSION,
            ],
            home_url('/')
        );
    }

    public static function register_tinymce_plugin(array $plugins): array
    {
        $plugins[self::BUTTON] = self::get_plugin_url();

        return $plugins;
    }

    public static function configure_tinymce(array $init): array
    {
        $valid = 'ul[data-column|class|style|id|*],ol[data-column|class|style|id|*]';
        if (! empty($init['extended_valid_elements'])) {
            $init['extended_valid_elements'] .= ',' . $valid;
        } else {
            $init['extended_valid_elements'] = $valid;
        }

        return $init;
    }

    public static function toolbar_builder_button(): array
    {
        return [
            'icon'     => '2 columns',
            'icon_svg' => self::icon_svg(),
            'title'    => '2-column list',
        ];
    }

    private static function icon_svg(): string
    {
        return '<svg class="dd-style-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#111827" stroke-width="1.7" stroke-linecap="round"><path d="M12 4v16" stroke="#f35422"/><circle cx="3.5" cy="7" r="1.1" fill="#f35422" stroke="none"/><circle cx="3.5" cy="12" r="1.1" fill="#f35422" stroke="none"/><circle cx="3.5" cy="17" r="1.1" fill="#f35422" stroke="none"/><path d="M6.2 7h3.2M6.2 12h3.2M6.2 17h3.2"/><circle cx="15" cy="7" r="1.1" fill="#f35422" stroke="none"/><circle cx="15" cy="12" r="1.1" fill="#f35422" stroke="none"/><circle cx="15" cy="17" r="1.1" fill="#f35422" stroke="none"/><path d="M17.7 7H21M17.7 12H21M17.7 17H21"/></svg>';
    }

    private static function icon_svg_3(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#111827" stroke-linecap="round" stroke-width="1.7" class="dd-style-icon" viewBox="0 0 24 24"><circle cx="2" cy="7" r="1" fill="#f35422" stroke="none"/><circle cx="2" cy="12" r="1" fill="#f35422" stroke="none"/><circle cx="2" cy="17" r="1" fill="#f35422" stroke="none"/><path d="M4.5 7H6m-1.5 5H6m-1.5 5H6"/><circle cx="10" cy="7" r="1" fill="#f35422" stroke="none"/><circle cx="10" cy="12" r="1" fill="#f35422" stroke="none"/><circle cx="10" cy="17" r="1" fill="#f35422" stroke="none"/><path d="M12.5 7H14m-1.5 5H14m-1.5 5H14"/><circle cx="18" cy="7" r="1" fill="#f35422" stroke="none"/><circle cx="18" cy="12" r="1" fill="#f35422" stroke="none"/><circle cx="18" cy="17" r="1" fill="#f35422" stroke="none"/><path d="M20.5 7H22m-1.5 5H22m-1.5 5H22"/></svg>';
    }

    private static function icon_svg_4(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#111827" stroke-linecap="round" stroke-width="1.7" class="dd-style-icon" viewBox="0 0 24 24"><circle cx="1.5" cy="7" r=".8" fill="#f35422" stroke="none"/><circle cx="1.5" cy="12" r=".8" fill="#f35422" stroke="none"/><circle cx="1.5" cy="17" r=".8" fill="#f35422" stroke="none"/><path d="M3.2 7h1.3m-1.3 5h1.3m-1.3 5h1.3"/><circle cx="7.5" cy="7" r=".8" fill="#f35422" stroke="none"/><circle cx="7.5" cy="12" r=".8" fill="#f35422" stroke="none"/><circle cx="7.5" cy="17" r=".8" fill="#f35422" stroke="none"/><path d="M9.2 7h1.3m-1.3 5h1.3m-1.3 5h1.3"/><circle cx="13.5" cy="7" r=".8" fill="#f35422" stroke="none"/><circle cx="13.5" cy="12" r=".8" fill="#f35422" stroke="none"/><circle cx="13.5" cy="17" r=".8" fill="#f35422" stroke="none"/><path d="M15.2 7h1.3m-1.3 5h1.3m-1.3 5h1.3"/><circle cx="19.5" cy="7" r=".8" fill="#f35422" stroke="none"/><circle cx="19.5" cy="12" r=".8" fill="#f35422" stroke="none"/><circle cx="19.5" cy="17" r=".8" fill="#f35422" stroke="none"/><path d="M21.2 7h1.3m-1.3 5h1.3m-1.3 5h1.3"/></svg>';
    }

	public static function register_editor_styles(string $styles): string
	{
		$url = class_exists(\SoinProduction\Kit\Bootstrapper::class)
			? \SoinProduction\Kit\Bootstrapper::pathToUrl(__DIR__ . '/style.css')
			: '';
		if ($url === '') {
			return $styles;
		}
		$url = add_query_arg('ver', self::VERSION, $url);
		return $styles !== '' ? $styles . ',' . $url : $url;
	}

    public static function serve_tinymce_script(): void
    {
        if (! isset($_GET['sp_list_columns_js']) || '1' !== (string) $_GET['sp_list_columns_js']) {
            return;
        }

        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: public, max-age=31536000');
        echo self::tinymce_script();
        exit;
    }

    private static function tinymce_script(): string
    {
        $file_path = __DIR__ . '/script.js';
        if (file_exists($file_path)) {
            $js = file_get_contents($file_path);
            $icon = wp_json_encode('data:image/svg+xml;utf8,' . rawurlencode(self::icon_svg()));
            $icon3 = wp_json_encode('data:image/svg+xml;utf8,' . rawurlencode(self::icon_svg_3()));
            $icon4 = wp_json_encode('data:image/svg+xml;utf8,' . rawurlencode(self::icon_svg_4()));
            $js = str_replace('{$icon}', $icon, $js);
            $js = str_replace('{$icon3}', $icon3, $js);
            $js = str_replace('{$icon4}', $icon4, $js);
            return $js;
        }
        return '';
    }
}

SP_List_Columns_Plugin::init();
