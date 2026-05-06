<?php
/**
 * Organization / LocalBusiness schema generator.
 *
 * @package JVM_Schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JVM_Schema_Organization {

    /**
     * Check whether this schema should output on the current page.
     *
     * @return bool
     */
    public function should_output() {
        $output = get_option( 'jvm_schema_org_output', 'homepage' );

        if ( 'all' === $output ) {
            return true;
        }

        if ( 'homepage' === $output ) {
            return is_front_page();
        }

        // "specific" — homepage + listed pages.
        if ( 'specific' === $output ) {
            if ( is_front_page() ) {
                return true;
            }
            $pages_raw = get_option( 'jvm_schema_org_output_pages', '' );
            if ( empty( $pages_raw ) ) {
                return false;
            }
            $pages = array_map( 'trim', explode( ',', $pages_raw ) );
            $obj   = get_queried_object();
            if ( $obj instanceof WP_Post ) {
                foreach ( $pages as $identifier ) {
                    if ( is_numeric( $identifier ) && (int) $identifier === $obj->ID ) {
                        return true;
                    }
                    if ( ! is_numeric( $identifier ) && $identifier === $obj->post_name ) {
                        return true;
                    }
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Build and return the Organization/LocalBusiness schema array.
     *
     * @return array|null
     */
    public function get_schema() {
        if ( ! $this->should_output() ) {
            return null;
        }

        $biz_type = get_option( 'jvm_schema_business_type', 'organization' );
        $site_url = get_option( 'jvm_schema_org_url' );
        if ( empty( $site_url ) ) {
            $site_url = home_url( '/' );
        }

        if ( 'organization' === $biz_type ) {
            $schema_type = 'Organization';
        } else {
            $schema_type = get_option( 'jvm_schema_localbusiness_type', 'Store' );
        }

        $org_name = get_option( 'jvm_schema_org_name' );
        if ( empty( $org_name ) ) {
            $org_name = get_bloginfo( 'name' );
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => $schema_type,
            '@id'      => trailingslashit( $site_url ) . '#organization',
            'name'     => $org_name,
            'url'      => $site_url,
        );

        // Optional fields — only add if non-empty.
        $description = get_option( 'jvm_schema_org_description', '' );
        if ( ! empty( $description ) ) {
            $schema['description'] = $description;
        }

        $email = get_option( 'jvm_schema_org_email', '' );
        if ( ! empty( $email ) ) {
            $schema['email'] = $email;
        }

        $telephone = get_option( 'jvm_schema_org_telephone', '' );
        if ( ! empty( $telephone ) ) {
            $schema['telephone'] = $telephone;
        }

        $founding = get_option( 'jvm_schema_org_founding_date', '' );
        if ( ! empty( $founding ) ) {
            $schema['foundingDate'] = $founding;
        }

        // Logo.
        $logo_url = $this->get_logo_url();
        if ( ! empty( $logo_url ) ) {
            $schema['logo'] = array(
                '@type' => 'ImageObject',
                'url'   => $logo_url,
                '@id'   => trailingslashit( $site_url ) . '#logo',
            );
            $schema['image'] = array( '@id' => trailingslashit( $site_url ) . '#logo' );
        }

        // sameAs.
        $same_as = $this->get_same_as();
        if ( ! empty( $same_as ) ) {
            $schema['sameAs'] = $same_as;
        }

        // LocalBusiness extras.
        if ( 'localbusiness' === $biz_type ) {
            $this->append_local_business( $schema, $site_url );
        }

        return $schema;
    }

    /**
     * Resolve logo URL from media or external URL.
     *
     * @return string
     */
    private function get_logo_url() {
        $source = get_option( 'jvm_schema_org_logo_source', 'media' );
        if ( 'media' === $source ) {
            $id = absint( get_option( 'jvm_schema_org_logo_id', 0 ) );
            if ( $id ) {
                $url = wp_get_attachment_url( $id );
                return $url ? $url : '';
            }
            return '';
        }
        return get_option( 'jvm_schema_org_logo_url', '' );
    }

    /**
     * Collect non-empty sameAs URLs.
     *
     * @return array
     */
    private function get_same_as() {
        $keys = array(
            'jvm_schema_sameas_website',
            'jvm_schema_sameas_facebook',
            'jvm_schema_sameas_instagram',
            'jvm_schema_sameas_youtube',
            'jvm_schema_sameas_linkedin',
        );
        $urls = array();
        foreach ( $keys as $key ) {
            $val = get_option( $key, '' );
            if ( ! empty( $val ) ) {
                $urls[] = $val;
            }
        }
        return $urls;
    }

    /**
     * Append LocalBusiness-specific fields to schema.
     *
     * @param array  &$schema Schema array (by reference).
     * @param string $site_url Site URL.
     * @return void
     */
    private function append_local_business( &$schema, $site_url ) {
        // Address.
        $street  = get_option( 'jvm_schema_address_street', '' );
        $city    = get_option( 'jvm_schema_address_city', '' );
        $region  = get_option( 'jvm_schema_address_region', '' );
        $postal  = get_option( 'jvm_schema_address_postal', '' );
        $country = get_option( 'jvm_schema_address_country', 'ID' );

        if ( $street || $city ) {
            $schema['address'] = array(
                '@type'            => 'PostalAddress',
                'streetAddress'    => $street,
                'addressLocality'  => $city,
                'addressRegion'    => $region,
                'postalCode'       => $postal,
                'addressCountry'   => $country,
            );
        }

        // Geo.
        $lat = get_option( 'jvm_schema_geo_lat', '' );
        $lng = get_option( 'jvm_schema_geo_lng', '' );
        if ( $lat && $lng ) {
            $schema['geo'] = array(
                '@type'     => 'GeoCoordinates',
                'latitude'  => $lat,
                'longitude' => $lng,
            );
        }

        $maps = get_option( 'jvm_schema_maps_url', '' );
        if ( ! empty( $maps ) ) {
            $schema['hasMap'] = $maps;
        }

        $price = get_option( 'jvm_schema_price_range', '' );
        if ( ! empty( $price ) ) {
            $schema['priceRange'] = $price;
        }

        // Opening hours.
        if ( '1' === get_option( 'jvm_schema_hours_enable', '0' ) ) {
            $hours = $this->build_opening_hours();
            if ( ! empty( $hours ) ) {
                $schema['openingHoursSpecification'] = $hours;
            }
        }
    }

    /**
     * Build openingHoursSpecification array.
     *
     * @return array
     */
    private function build_opening_hours() {
        $mode = get_option( 'jvm_schema_hours_mode', 'perday' );

        if ( 'perday' === $mode ) {
            return $this->build_hours_perday();
        }
        return $this->build_hours_pattern();
    }

    /**
     * Build hours from per-day settings.
     *
     * @return array
     */
    private function build_hours_perday() {
        $data = get_option( 'jvm_schema_hours_perday', array() );
        if ( ! is_array( $data ) || empty( $data ) ) {
            return array();
        }

        $specs = array();
        foreach ( $data as $day => $info ) {
            if ( ! empty( $info['closed'] ) ) {
                continue;
            }
            $opens  = isset( $info['opens'] ) ? $info['opens'] : '';
            $closes = isset( $info['closes'] ) ? $info['closes'] : '';
            if ( $opens && $closes ) {
                $specs[] = array(
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => 'https://schema.org/' . $day,
                    'opens'     => $opens,
                    'closes'    => $closes,
                );
            }
        }
        return $specs;
    }

    /**
     * Build hours from pattern textarea.
     *
     * @return array
     */
    private function build_hours_pattern() {
        $raw = get_option( 'jvm_schema_hours_pattern', '' );
        if ( empty( $raw ) ) {
            return array();
        }

        $abbr_map = array(
            'Mo' => 'Monday',
            'Tu' => 'Tuesday',
            'We' => 'Wednesday',
            'Th' => 'Thursday',
            'Fr' => 'Friday',
            'Sa' => 'Saturday',
            'Su' => 'Sunday',
        );
        $abbr_order = array_keys( $abbr_map );

        $lines = preg_split( '/\r\n|\r|\n/', $raw );
        $specs = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) {
                continue;
            }
            // Expected: "Mo-Fr 08:00-17:00" or "Sa 08:00-13:00".
            $parts = preg_split( '/\s+/', $line, 2 );
            if ( count( $parts ) < 2 ) {
                continue;
            }

            $day_part  = $parts[0];
            $time_part = $parts[1];
            $times     = explode( '-', $time_part );
            if ( count( $times ) < 2 ) {
                continue;
            }
            $opens  = trim( $times[0] );
            $closes = trim( $times[1] );

            $days = $this->expand_day_range( $day_part, $abbr_map, $abbr_order );
            foreach ( $days as $full_day ) {
                $specs[] = array(
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => 'https://schema.org/' . $full_day,
                    'opens'     => $opens,
                    'closes'    => $closes,
                );
            }
        }

        return $specs;
    }

    /**
     * Expand a day abbreviation or range (e.g. "Mo-Fr") to full day names.
     *
     * @param string $day_part   Day string like "Mo" or "Mo-Fr".
     * @param array  $abbr_map   Abbreviation => Full name map.
     * @param array  $abbr_order Ordered abbreviations.
     * @return array Full day names.
     */
    private function expand_day_range( $day_part, $abbr_map, $abbr_order ) {
        if ( strpos( $day_part, '-' ) !== false ) {
            $range = explode( '-', $day_part );
            if ( count( $range ) === 2 ) {
                $start = array_search( trim( $range[0] ), $abbr_order, true );
                $end   = array_search( trim( $range[1] ), $abbr_order, true );
                if ( false !== $start && false !== $end && $start <= $end ) {
                    $days = array();
                    for ( $i = $start; $i <= $end; $i++ ) {
                        $days[] = $abbr_map[ $abbr_order[ $i ] ];
                    }
                    return $days;
                }
            }
        }

        // Single day.
        $abbr = trim( $day_part );
        if ( isset( $abbr_map[ $abbr ] ) ) {
            return array( $abbr_map[ $abbr ] );
        }
        return array();
    }
}
