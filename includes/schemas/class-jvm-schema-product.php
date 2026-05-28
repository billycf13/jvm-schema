<?php
/**
 * WooCommerce Product schema generator.
 *
 * @package JVM_Schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JVM_Schema_Product {

    /**
     * Initialize the module.
     */
    public function init() {
        // Disable WooCommerce default structured data.
        add_filter( 'woocommerce_structured_data_product', '__return_empty_array' );
    }

    /**
     * Build and return the Product schema array.
     *
     * @return array|null
     */
    public function get_schema() {
        if ( '1' !== get_option( 'jvm_schema_enable_product', '1' ) ) {
            return null;
        }

        if ( ! is_product() ) {
            return null;
        }

        $product_id = get_queried_object_id();
        $product    = wc_get_product( $product_id );

        if ( ! $product ) {
            return null;
        }

        $site_url = get_option( 'jvm_schema_site_url', home_url( '/' ) );
        $schema   = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            '@id'         => get_permalink( $product_id ) . '#product',
            'name'        => $product->get_name(),
            'description' => wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ),
            'url'         => get_permalink( $product_id ),
            'sku'         => $product->get_sku(),
        );

        // Images.
        $image_ids = array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() );
        $images    = array();
        foreach ( $image_ids as $id ) {
            $url = wp_get_attachment_url( $id );
            if ( $url ) {
                $images[] = $url;
            }
        }
        if ( ! empty( $images ) ) {
            $schema['image'] = $images;
        }

        // Brand.
        $brand_name = get_post_meta( $product_id, '_jvm_schema_product_brand', true );
        if ( empty( $brand_name ) ) {
            $brand_name = get_option( 'jvm_schema_product_default_brand', '' );
        }
        if ( ! empty( $brand_name ) ) {
            $schema['brand'] = array(
                '@type' => 'Brand',
                'name'  => $brand_name,
            );
        }

        // GTIN.
        $gtin = get_post_meta( $product_id, '_jvm_schema_product_gtin', true );
        if ( ! empty( $gtin ) ) {
            $schema['gtin'] = $gtin;
        }

        // MPN.
        $mpn = get_post_meta( $product_id, '_jvm_schema_product_mpn', true );
        if ( ! empty( $mpn ) ) {
            $schema['mpn'] = $mpn;
        }

        // Aggregate Rating.
        $schema['aggregateRating'] = $this->get_aggregate_rating( $product );

        // Condition.
        $condition = get_post_meta( $product_id, '_jvm_schema_product_condition', true );
        if ( empty( $condition ) ) {
            $condition = 'NewCondition';
        }

        // Seller reference.
        $seller = null;
        $org_name = get_option( 'jvm_schema_org_name', '' );
        if ( ! empty( $org_name ) || get_option( 'jvm_schema_business_type', '' ) ) {
            $seller = array(
                '@id' => trailingslashit( $site_url ) . '#organization',
            );
        }

        // Offers.
        $offers = $this->get_offers( $product, $condition, $seller );
        if ( $offers ) {
            $schema['offers'] = $offers;
        }

        return $schema;
    }

    /**
     * Get aggregate rating data with manual overrides.
     */
    private function get_aggregate_rating( $product ) {
        $product_id = $product->get_id();
        
        $rating_val   = get_post_meta( $product_id, '_jvm_schema_product_rating', true );
        $rating_count = get_post_meta( $product_id, '_jvm_schema_product_review_count', true );

        if ( empty( $rating_val ) ) {
            $rating_val = get_option( 'jvm_schema_product_default_rating', '5' );
        }
        if ( empty( $rating_count ) ) {
            $rating_count = get_option( 'jvm_schema_product_default_review_count', '10' );
        }

        // If still empty and WC has ratings, use WC data.
        if ( empty( $rating_val ) && $product->get_average_rating() > 0 ) {
            $rating_val   = $product->get_average_rating();
            $rating_count = $product->get_review_count();
        }

        if ( empty( $rating_val ) ) {
            return null;
        }

        return array(
            '@type'       => 'AggregateRating',
            'ratingValue' => $rating_val,
            'reviewCount' => $rating_count,
            'bestRating'  => '5',
            'worstRating' => '1',
        );
    }

    /**
     * Get priceValidUntil date string.
     */
    private function get_price_valid_until( $product_id ) {
        $override = get_post_meta( $product_id, '_jvm_schema_product_price_valid_until', true );
        if ( ! empty( $override ) ) {
            return $override;
        }

        $days = absint( get_option( 'jvm_schema_product_price_valid_days', 365 ) );
        if ( $days < 1 ) {
            $days = 365;
        }

        return gmdate( 'Y-m-d', strtotime( "+{$days} days" ) );
    }

    /**
     * Build a single Offer array.
     */
    private function build_offer( $price, $url, $condition, $seller, $price_valid_until, $sku = '' ) {
        $offer = array(
            '@type'         => 'Offer',
            'url'           => $url,
            'priceCurrency' => get_woocommerce_currency(),
            'availability'  => 'https://schema.org/InStock',
            'itemCondition' => 'https://schema.org/' . $condition,
        );

        if ( '' !== $price && null !== $price ) {
            $offer['price'] = $price;
        }

        if ( ! empty( $price_valid_until ) ) {
            $offer['priceValidUntil'] = $price_valid_until;
        }

        if ( ! empty( $sku ) ) {
            $offer['sku'] = $sku;
        }

        if ( $seller ) {
            $offer['seller'] = $seller;
        }

        return $offer;
    }

    /**
     * Get offers data for simple and variable products.
     */
    private function get_offers( $product, $condition, $seller ) {
        $product_id       = $product->get_id();
        $url              = get_permalink( $product_id );
        $price_valid_until = $this->get_price_valid_until( $product_id );

        if ( $product->is_type( 'variable' ) ) {
            $offers     = array();
            $variations = $product->get_available_variations( 'objects' );

            foreach ( $variations as $variation ) {
                $price = $variation->get_price();
                if ( '' === $price || null === $price ) {
                    continue;
                }

                $offer = $this->build_offer(
                    $price,
                    $url,
                    $condition,
                    $seller,
                    $price_valid_until,
                    $variation->get_sku()
                );
                $offer['availability'] = $variation->is_in_stock()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock';

                $offers[] = $offer;
            }

            return ! empty( $offers ) ? $offers : null;
        }

        // Simple product.
        $price = $product->get_price();
        if ( '' === $price || null === $price ) {
            return null;
        }

        $offer = $this->build_offer(
            $price,
            $url,
            $condition,
            $seller,
            $price_valid_until
        );
        $offer['availability'] = $product->is_in_stock()
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        return $offer;
    }
}
