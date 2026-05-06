<?php
/**
 * Schema output — hooks into wp_head and prints JSON-LD blocks.
 *
 * @package JVM_Schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JVM_Schema_Output {

    public function init() {
        add_action( 'wp', array( $this, 'register_head_hook' ) );
    }

    public function register_head_hook() {
        if ( '1' !== get_option( 'jvm_schema_enable_output', '1' ) ) {
            return;
        }
        if ( is_404() ) {
            return;
        }
        $priority = absint( get_option( 'jvm_schema_head_priority', 10 ) );
        add_action( 'wp_head', array( $this, 'output_schemas' ), $priority );
    }

    public function output_schemas() {
        $schemas = array();

        $website = new JVM_Schema_WebSite();
        $data    = $website->get_schema();
        if ( $data ) {
            $schemas[] = $data;
        }

        $webpage = new JVM_Schema_WebPage();
        $data    = $webpage->get_schema();
        if ( $data ) {
            $schemas[] = $data;
        }

        // Organization schema.
        $organization = new JVM_Schema_Organization();
        $data         = $organization->get_schema();
        if ( $data ) {
            $schemas[] = $data;
        }

        // Breadcrumb schema.
        $breadcrumb = new JVM_Schema_Breadcrumb();
        $data       = $breadcrumb->get_schema();
        if ( $data ) {
            $schemas[] = $data;
        }

        // Product schema.
        $product = new JVM_Schema_Product();
        $data    = $product->get_schema();
        if ( $data ) {
            $schemas[] = $data;
        }

        // Article schema.
        $article = new JVM_Schema_Article();
        $data    = $article->get_schema();
        if ( $data ) {
            $schemas[] = $data;
        }

        // FAQ schema.
        $faq  = new JVM_Schema_FAQ();
        $data = $faq->get_schema();
        if ( $data ) {
            $schemas[] = $data;
        }

        $schemas = apply_filters( 'jvm_schema_schemas', $schemas );

        foreach ( $schemas as $schema ) {
            $this->print_json_ld( $schema );
        }
    }

    private function print_json_ld( $schema ) {
        $json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        if ( ! $json ) {
            return;
        }
        echo "\n<!-- JVM Schema -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo $json . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</script>' . "\n";
    }
}
