<?php
/**
 * WebSite schema generator.
 *
 * @package JVM_Schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JVM_Schema_WebSite {

    public function get_schema() {
        $site_url = get_option( 'jvm_schema_site_url' );
        if ( empty( $site_url ) ) {
            $site_url = home_url( '/' );
        }

        $site_name = get_option( 'jvm_schema_site_name' );
        if ( empty( $site_name ) ) {
            $site_name = get_bloginfo( 'name' );
        }

        $description = get_option( 'jvm_schema_site_description' );
        if ( empty( $description ) ) {
            $description = get_bloginfo( 'description' );
        }

        $language = get_option( 'jvm_schema_language' );
        if ( empty( $language ) ) {
            $language = get_bloginfo( 'language' );
        }

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'WebSite',
            '@id'         => trailingslashit( $site_url ) . '#website',
            'name'        => $site_name,
            'url'         => $site_url,
            'description' => $description,
            'inLanguage'  => $language,
        );

        if ( '1' === get_option( 'jvm_schema_enable_searchaction', '0' ) ) {
            $schema['potentialAction'] = array(
                '@type'       => 'SearchAction',
                'target'      => array(
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => trailingslashit( $site_url ) . '?s={search_term_string}',
                ),
                'query-input' => 'required name=search_term_string',
            );
        }

        // Link to Organization if active.
        $org_name = get_option( 'jvm_schema_org_name', '' );
        if ( ! empty( $org_name ) || get_option( 'jvm_schema_business_type', '' ) ) {
            $schema['publisher'] = array(
                '@id' => trailingslashit( $site_url ) . '#organization',
            );
        }

        return $schema;
    }
}
