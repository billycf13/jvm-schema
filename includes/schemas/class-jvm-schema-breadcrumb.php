<?php
/**
 * BreadcrumbList schema generator.
 *
 * @package JVM_Schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JVM_Schema_Breadcrumb {

    /**
     * Build and return the BreadcrumbList schema array.
     *
     * @return array|null
     */
    public function get_schema() {
        if ( '1' !== get_option( 'jvm_schema_enable_breadcrumb', '1' ) ) {
            return null;
        }

        if ( is_front_page() || is_404() || ( function_exists( 'is_product' ) && is_product() ) ) {
            return null;
        }

        $items = $this->get_breadcrumb_items();

        if ( empty( $items ) ) {
            return null;
        }

        $list_items = array();
        $position   = 1;

        foreach ( $items as $item ) {
            $list_items[] = array(
                '@type'    => 'ListItem',
                'position' => $position,
                'name'     => $item['name'],
                'item'     => $item['url'],
            );
            $position++;
        }

        return array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $list_items,
        );
    }

    /**
     * Collect breadcrumb items based on current page context.
     *
     * @return array
     */
    private function get_breadcrumb_items() {
        $items = array();

        // 1. Home.
        if ( '1' === get_option( 'jvm_schema_breadcrumb_show_home', '1' ) ) {
            $home_text = get_option( 'jvm_schema_breadcrumb_home_text', __( 'Home', 'jvm-schema' ) );
            if ( empty( $home_text ) ) {
                $home_text = __( 'Home', 'jvm-schema' );
            }
            $items[] = array(
                'name' => $home_text,
                'url'  => home_url( '/' ),
            );
        }

        // 2. Ancestors / Intermediate items.
        if ( is_singular() ) {
            $post = get_queried_object();

            // Post types with hierarchies (like Pages).
            if ( is_post_type_hierarchical( $post->post_type ) ) {
                $ancestors = get_post_ancestors( $post->ID );
                if ( ! empty( $ancestors ) ) {
                    $ancestors = array_reverse( $ancestors );
                    foreach ( $ancestors as $ancestor_id ) {
                        $items[] = array(
                            'name' => get_the_title( $ancestor_id ),
                            'url'  => get_permalink( $ancestor_id ),
                        );
                    }
                }
            } else {
                // Post types without hierarchies but with categories (like Posts).
                $taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
                $main_tax   = '';
                foreach ( $taxonomies as $tax_slug => $tax_obj ) {
                    if ( $tax_obj->hierarchical ) {
                        $main_tax = $tax_slug;
                        break;
                    }
                }

                if ( $main_tax ) {
                    $terms = get_the_terms( $post->ID, $main_tax );
                    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                        // Pick first term for simplicity.
                        $term = $terms[0];
                        // Get ancestors of the term.
                        $term_ancestors = get_ancestors( $term->term_id, $main_tax, 'taxonomy' );
                        if ( ! empty( $term_ancestors ) ) {
                            $term_ancestors = array_reverse( $term_ancestors );
                            foreach ( $term_ancestors as $t_id ) {
                                $t_obj = get_term( $t_id, $main_tax );
                                $items[] = array(
                                    'name' => $t_obj->name,
                                    'url'  => get_term_link( $t_obj ),
                                );
                            }
                        }
                        $items[] = array(
                            'name' => $term->name,
                            'url'  => get_term_link( $term ),
                        );
                    }
                }
            }

            // 3. Current item.
            $items[] = array(
                'name' => get_the_title( $post->ID ),
                'url'  => get_permalink( $post->ID ),
            );

        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            
            // Term ancestors.
            $term_ancestors = get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' );
            if ( ! empty( $term_ancestors ) ) {
                $term_ancestors = array_reverse( $term_ancestors );
                foreach ( $term_ancestors as $t_id ) {
                    $t_obj = get_term( $t_id, $term->taxonomy );
                    $items[] = array(
                        'name' => $t_obj->name,
                        'url'  => get_term_link( $t_obj ),
                    );
                }
            }

            $items[] = array(
                'name' => $term->name,
                'url'  => get_term_link( $term ),
            );

        } elseif ( is_archive() ) {
            if ( is_day() ) {
                $items[] = array( 'name' => get_the_date(), 'url' => get_day_link( get_the_time( 'Y' ), get_the_time( 'm' ), get_the_time( 'd' ) ) );
            } elseif ( is_month() ) {
                $items[] = array( 'name' => get_the_date( 'F Y' ), 'url' => get_month_link( get_the_time( 'Y' ), get_the_time( 'm' ) ) );
            } elseif ( is_year() ) {
                $items[] = array( 'name' => get_the_date( 'Y' ), 'url' => get_year_link( get_the_time( 'Y' ) ) );
            } elseif ( is_author() ) {
                $author = get_queried_object();
                $items[] = array( 'name' => $author->display_name, 'url' => get_author_posts_url( $author->ID ) );
            } elseif ( is_post_type_archive() ) {
                $post_type = get_queried_object();
                $items[] = array( 'name' => $post_type->labels->name, 'url' => get_post_type_archive_link( $post_type->name ) );
            }
        } elseif ( is_search() ) {
            $items[] = array(
                'name' => sprintf( __( 'Search Results for: %s', 'jvm-schema' ), get_search_query() ),
                'url'  => home_url( '/?s=' . get_search_query() ),
            );
        }

        return $items;
    }
}
