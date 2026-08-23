<?php

if (! defined('ABSPATH')) {
    exit;
}

final class SP_Decor_Toggle_Plugin
{
    private const VERSION = '1.0.0';
    private const BUTTON  = 'decor_toggle';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'serve_tinymce_script'], 0);
    }

    public static function get_plugin_url(): string
    {
        return add_query_arg(
            [
                'sp_decor_span_tag_js' => '1',
                'ver'                  => self::VERSION,
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
        if (! isset($_GET['sp_decor_span_tag_js']) || '1' !== (string) $_GET['sp_decor_span_tag_js']) {
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
        return '';
    }
}

SP_Decor_Toggle_Plugin::init();
