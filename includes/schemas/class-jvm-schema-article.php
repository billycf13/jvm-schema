<?php
/**
 * Article / BlogPosting schema generator.
 *
 * @package JVM_Schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JVM_Schema_Article {

    /**
     * Build and return the Article schema array.
     *
     * @return array|null
     */
    public function get_schema() {
        if ( '1' !== get_option( 'jvm_schema_enable_article', '1' ) ) {
            return null;
        }

        if ( ! is_singular( 'post' ) ) {
            return null;
        }

        $post_id = get_queried_object_id();
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return null;
        }

        // Article Type override.
        $type = get_post_meta( $post_id, '_jvm_schema_article_type', true );
        if ( empty( $type ) ) {
            $type = get_option( 'jvm_schema_article_default_type', 'BlogPosting' );
        }

        // Custom Headline & Description.
        $headline = get_post_meta( $post_id, '_jvm_schema_article_headline', true );
        if ( empty( $headline ) ) {
            $headline = get_the_title( $post_id );
        }

        $description = get_post_meta( $post_id, '_jvm_schema_article_description', true );
        if ( empty( $description ) ) {
            $description = get_the_excerpt( $post_id );
            if ( empty( $description ) ) {
                $description = wp_trim_words( $post->post_content, 30 );
            }
        }

        $site_url = get_option( 'jvm_schema_site_url', home_url( '/' ) );
        
        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => $type,
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => get_permalink( $post_id ),
            ),
            'headline'      => $headline,
            'description'   => wp_strip_all_tags( $description ),
            'datePublished' => get_the_date( 'c', $post_id ),
            'dateModified'  => get_the_modified_date( 'c', $post_id ),
        );

        // Featured Image.
        if ( has_post_thumbnail( $post_id ) ) {
            $image_url = get_the_post_thumbnail_url( $post_id, 'full' );
            if ( $image_url ) {
                $schema['image'] = $image_url;
            }
        }

        // Author.
        $author_id   = $post->post_author;
        $author_name = get_the_author_meta( 'display_name', $author_id );
        $author_url  = get_author_posts_url( $author_id );
        
        $same_as = array();
        $social_keys = array( 'facebook', 'twitter', 'linkedin', 'instagram' );
        foreach ( $social_keys as $key ) {
            $val = get_user_meta( $author_id, 'jvm_schema_user_' . $key, true );
            if ( ! empty( $val ) ) {
                $same_as[] = esc_url( $val );
            }
        }

        $schema['author'] = array(
            '@type' => 'Person',
            'name'  => $author_name,
            'url'   => $author_url,
        );

        if ( ! empty( $same_as ) ) {
            $schema['author']['sameAs'] = $same_as;
        }

        // Publisher.
        $org_name = get_option( 'jvm_schema_org_name', '' );
        if ( ! empty( $org_name ) || get_option( 'jvm_schema_business_type', '' ) ) {
            $schema['publisher'] = array(
                '@id' => trailingslashit( $site_url ) . '#organization',
            );
        }

        return $schema;
    }
}
