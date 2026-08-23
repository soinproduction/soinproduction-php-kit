<?php

if (! defined('ABSPATH')) {
    exit;
}

final class SP_Table_Builder_Plugin
{
    private const VERSION = '1.0.0';
    private const BUTTON  = 'table';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'serve_tinymce_script'], 0);
        add_filter('content_save_pre', [__CLASS__, 'content_save_pre'], 20);
        add_filter('tiny_mce_before_init', [__CLASS__, 'tiny_mce_before_init'], 10, 2);
    }

    public static function content_save_pre($content)
    {
        if (strpos($content, '<table') !== false) {
            $content = preg_replace("/<td([^>]*)>(.+\r?\n\r?\n)/m", "<td$1>\n\n$2", $content);
            if (substr($content, -8) === '</table>') {
                $content .= "\n<br />";
            }
        }
        return $content;
    }

    public static function tiny_mce_before_init($mce_init, $editor_id)
    {
        $mce_init['table_toolbar'] = '';
        return $mce_init;
    }

    public static function get_plugin_url(): string
    {
        return add_query_arg(
            [
                'sp_table_builder_js' => '1',
                'ver'                 => self::VERSION,
            ],
            home_url('/')
        );
    }

    public static function register_tinymce_plugin(array $plugins): array
    {
        $plugins[self::BUTTON] = self::get_plugin_url();
        return $plugins;
    }

    public static function serve_tinymce_script(): void
    {
        if (! isset($_GET['sp_table_builder_js']) || '1' !== (string) $_GET['sp_table_builder_js']) {
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
            return file_get_contents($file_path);
        }
    }
}

SP_Table_Builder_Plugin::init();
