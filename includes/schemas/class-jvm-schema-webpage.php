<?php
/**
 * WebPage schema generator.
 *
 * @package JVM_Schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JVM_Schema_WebPage {

    public function get_schema() {
        if ( '1' !== get_option( 'jvm_schema_enable_webpage', '1' ) ) {
            return null;
        }

        if ( is_404() || ( function_exists( 'is_product' ) && is_product() ) ) {
            return null;
        }

        // Disable WebPage on single posts if Article is active.
        if ( is_singular( 'post' ) && '1' === get_option( 'jvm_schema_enable_article', '1' ) && '1' === get_option( 'jvm_schema_article_disable_webpage', '1' ) ) {
            return null;
        }

        $page_type   = $this->detect_page_type();
        $current_url = $this->get_current_url();
        $site_url    = get_option( 'jvm_schema_site_url' );
        if ( empty( $site_url ) ) {
            $site_url = home_url( '/' );
        }

        $language = get_option( 'jvm_schema_language' );
        if ( empty( $language ) ) {
            $language = get_bloginfo( 'language' );
        }

        // Skip singular posts — handled by Article module later.
        if ( is_singular( 'post' ) && 'auto' === get_option( 'jvm_schema_webpage_detection', 'auto' ) ) {
            return null;
        }

        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => $page_type,
            '@id'        => $current_url . '#webpage',
            'url'        => $current_url,
            'name'       => wp_get_document_title(),
            'isPartOf'   => array(
                '@id' => trailingslashit( $site_url ) . '#website',
            ),
            'inLanguage' => $language,
        );

        // Dates for singular content.
        if ( '1' === get_option( 'jvm_schema_webpage_dates', '1' ) && is_singular() ) {
            $post = get_queried_object();
            if ( $post instanceof WP_Post ) {
                $schema['datePublished'] = get_the_date( 'c', $post );
                $schema['dateModified']  = get_the_modified_date( 'c', $post );
            }
        }

        // Link to Organization if active.
        $org_name = get_option( 'jvm_schema_org_name', '' );
        if ( ! empty( $org_name ) || get_option( 'jvm_schema_business_type', '' ) ) {
            $schema['about'] = array(
                '@id' => trailingslashit( $site_url ) . '#organization',
            );
        }

        return $schema;
    }

    private function detect_page_type() {
        $detection = get_option( 'jvm_schema_webpage_detection', 'auto' );

        if ( 'manual' === $detection && is_singular() ) {
            $post_id = get_queried_object_id();
            $meta    = get_post_meta( $post_id, '_jvm_schema_page_type', true );
            return ! empty( $meta ) ? $meta : 'WebPage';
        }

        // Auto-detection.
        if ( is_front_page() ) {
            return 'WebPage';
        }
        if ( is_home() ) {
            return 'CollectionPage';
        }
        if ( is_category() || is_tax() || is_archive() ) {
            return 'CollectionPage';
        }
        if ( is_page() ) {
            // Check metabox even in auto mode if set.
            $post_id = get_queried_object_id();
            $meta    = get_post_meta( $post_id, '_jvm_schema_page_type', true );
            return ! empty( $meta ) ? $meta : 'WebPage';
        }
        if ( is_singular() ) {
            return 'WebPage';
        }

        return 'WebPage';
    }

    private function get_current_url() {
        global $wp;
        return home_url( add_query_arg( array(), $wp->request ) );
    }
}
