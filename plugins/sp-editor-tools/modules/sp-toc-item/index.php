<?php

if (! defined('ABSPATH')) {
    exit;
}

final class SP_Toc_Item_Plugin
{
    private const VERSION = '1.0.0';
    private const BUTTON  = 'toc_item';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'serve_tinymce_script'], 0);
    }

    public static function get_plugin_url(): string
    {
        return add_query_arg(
            [
                'sp_toc_item_js' => '1',
                'ver'            => self::VERSION,
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
        if (! isset($_GET['sp_toc_item_js']) || '1' !== (string) $_GET['sp_toc_item_js']) {
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

SP_Toc_Item_Plugin::init();

class SP_TOC
{
    public static function process_content_and_generate_toc(string $content): string
    {
        if ( stripos( $content, '[toc' ) === false ) {
            return $content;
        }

        $title = 'Table of Contents';
        if ( preg_match( '/\[toc\s+title=["\'](.*?)["\']\]/i', $content, $m ) ) {
            $title = $m[1];
        }

        if ( ! class_exists( 'DOMDocument' ) ) {

            return preg_replace( '/\[toc.*?\]/i', '', $content );
        }

        $dom  = new DOMDocument( '1.0', 'UTF-8' );
        $prev = libxml_use_internal_errors( true );

        $dom->loadHTML( '<?xml encoding="utf-8" ?><div id="__toc_wrap__">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        $xpath = new DOMXPath( $dom );

        $heading_nodes = [];
        $headings = $xpath->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6][@data-toc-item]');

        if ( ! $headings || $headings->length === 0 ) {
            return preg_replace( '/\[toc.*?\]/i', '', $content );
        }

        $items = [];
        $counter = 0;
        foreach ( $headings as $heading ) {

            $counter++;
            $id = $heading->getAttribute( 'id' );
            if ( ! $id ) {
                $slug = sanitize_title( $heading->textContent );
                $id = $slug ? $slug . '-' . $counter : 'toc-' . $counter;
                $heading->setAttribute( 'id', $id );
            }
            $level = (int) substr( $heading->nodeName, 1 );
            $items[] = [
                'id'    => $id,
                'text'  => trim( $heading->textContent ),
                'level' => $level,
            ];
        }

        $toc_html = '<div class="sp-toc">';
        $toc_html .= '<div class="sp-toc__parent-wrapper">';
        $toc_html .= '<h3 class="sp-toc__title">' . esc_html( $title ) . '</h3>';
        $toc_html .= '</div>';
        $toc_html .= '<ul class="sp-toc__list">';

        $base_level = $items[0]['level'];
        $in_sublist = false;

        foreach ( $items as $idx => $item ) {
            $level = $item['level'];
            if ( $level > $base_level ) {
                if ( ! $in_sublist ) {

                    $toc_html .= '<ul class="sp-toc__sublist">';
                    $in_sublist = true;
                }
                $toc_html .= '<li class="sp-toc__item sp-toc__item--child">';
                $toc_html .= '<a class="sp-toc__link" href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a>';
                $toc_html .= '</li>';
            } else {
                if ( $in_sublist ) {
                    $toc_html .= '</ul></li>';
                    $in_sublist = false;
                }

                $has_children = false;
                if ( isset( $items[ $idx + 1 ] ) && $items[ $idx + 1 ]['level'] > $level ) {
                    $has_children = true;
                }

                $class = 'sp-toc__item';
                if ( $has_children ) {
                    $class .= ' sp-toc__item--has-children';
                }

                if ( $idx > 0 && ! $has_children && ! $in_sublist ) {
                    $toc_html .= '</li>';
                }

                $toc_html .= '<li class="' . esc_attr( $class ) . '">';
                $toc_html .= '<a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a>';
            }
        }

        if ( $in_sublist ) {
            $toc_html .= '</ul>';
        }
        $toc_html .= '</li>';
        $toc_html .= '</ul>';
        $toc_html .= '</div>';

        $body = $dom->getElementById( '__toc_wrap__' );
        if ( ! $body ) {
            return $content;
        }
        $html = '';
        foreach ( $body->childNodes as $node ) {
            $html .= $dom->saveHTML( $node );
        }

        $html = preg_replace( '/\[toc.*?\]/i', $toc_html, $html );

        return $html;
    }
}

add_shortcode('toc', '__return_empty_string');
