<?php
/**
 * Plugin Name: JVM Schema
 * Plugin URI:  https://github.com/billycf13/jvm-schema
 * Description: Dynamic structured data / JSON-LD schema manager for WordPress & WooCommerce
 * Version:     1.1.1
 * Author:      JVM
 * Author URI:  https://github.com/billycf13
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jvm-schema
 * Domain Path: /languages
 *
 * @package JVM_Schema
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants.
 */
define( 'JVM_SCHEMA_VERSION', '1.1.0' );
define( 'JVM_SCHEMA_FILE', __FILE__ );
define( 'JVM_SCHEMA_DIR', plugin_dir_path( __FILE__ ) );
define( 'JVM_SCHEMA_URL', plugin_dir_url( __FILE__ ) );
define( 'JVM_SCHEMA_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Initialize Plugin Update Checker.
 */
require_once JVM_SCHEMA_DIR . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$jvm_schema_updater = PucFactory::buildUpdateChecker(
    'https://github.com/billycf13/jvm-schema/',
    __FILE__,
    'jvm-schema'
);
$jvm_schema_updater->setBranch( 'main' );

/**
 * Load plugin classes.
 */
require_once JVM_SCHEMA_DIR . 'includes/class-jvm-schema-loader.php';
require_once JVM_SCHEMA_DIR . 'includes/class-jvm-schema-settings.php';
require_once JVM_SCHEMA_DIR . 'includes/class-jvm-schema-output.php';
require_once JVM_SCHEMA_DIR . 'includes/schemas/class-jvm-schema-website.php';
require_once JVM_SCHEMA_DIR . 'includes/schemas/class-jvm-schema-webpage.php';
require_once JVM_SCHEMA_DIR . 'includes/schemas/class-jvm-schema-organization.php';
require_once JVM_SCHEMA_DIR . 'includes/schemas/class-jvm-schema-breadcrumb.php';
require_once JVM_SCHEMA_DIR . 'includes/schemas/class-jvm-schema-product.php';
require_once JVM_SCHEMA_DIR . 'includes/schemas/class-jvm-schema-article.php';
require_once JVM_SCHEMA_DIR . 'includes/schemas/class-jvm-schema-faq.php';

/**
 * Initialize plugin.
 */
function jvm_schema_init() {
    $loader = new JVM_Schema_Loader();
    $loader->init();

    // Initialize schema modules that need early hooks.
    $product = new JVM_Schema_Product();
    $product->init();
}
add_action( 'plugins_loaded', 'jvm_schema_init' );

/**
 * Register default options on activation.
 */
function jvm_schema_activate() {
    $defaults = array(
        'jvm_schema_enable_output'       => '1',
        'jvm_schema_head_priority'       => 10,
        'jvm_schema_site_name'           => '',
        'jvm_schema_site_url'            => '',
        'jvm_schema_site_description'    => '',
        'jvm_schema_language'            => '',
        'jvm_schema_enable_searchaction' => '0',
        'jvm_schema_enable_webpage'      => '1',
        'jvm_schema_webpage_dates'       => '1',
        'jvm_schema_webpage_detection'   => 'auto',
        'jvm_schema_business_type'       => 'organization',
        'jvm_schema_localbusiness_type'  => 'Store',
        'jvm_schema_org_name'            => '',
        'jvm_schema_org_url'             => '',
        'jvm_schema_org_description'     => '',
        'jvm_schema_org_email'           => '',
        'jvm_schema_org_telephone'       => '',
        'jvm_schema_org_founding_date'   => '',
        'jvm_schema_org_logo_source'     => 'media',
        'jvm_schema_org_logo_id'         => 0,
        'jvm_schema_org_logo_url'        => '',
        'jvm_schema_sameas_website'      => '',
        'jvm_schema_sameas_facebook'     => '',
        'jvm_schema_sameas_instagram'    => '',
        'jvm_schema_sameas_youtube'      => '',
        'jvm_schema_sameas_linkedin'     => '',
        'jvm_schema_address_street'      => '',
        'jvm_schema_address_city'        => '',
        'jvm_schema_address_region'      => '',
        'jvm_schema_address_postal'      => '',
        'jvm_schema_address_country'     => 'ID',
        'jvm_schema_geo_lat'             => '',
        'jvm_schema_geo_lng'             => '',
        'jvm_schema_maps_url'            => '',
        'jvm_schema_price_range'         => '',
        'jvm_schema_hours_enable'        => '0',
        'jvm_schema_hours_mode'          => 'perday',
        'jvm_schema_hours_pattern'       => '',
        'jvm_schema_org_output'          => 'homepage',
        'jvm_schema_org_output_pages'    => '',
        'jvm_schema_enable_breadcrumb'   => '1',
        'jvm_schema_breadcrumb_show_home' => '1',
        'jvm_schema_breadcrumb_home_text' => 'Home',
        'jvm_schema_enable_product'       => '1',
        'jvm_schema_product_default_rating' => '5',
        'jvm_schema_product_default_review_count' => '10',
        'jvm_schema_product_default_brand' => '',
        'jvm_schema_product_price_valid_days' => 365,
        'jvm_schema_enable_article'        => '1',
        'jvm_schema_article_default_type'  => 'BlogPosting',
        'jvm_schema_article_disable_webpage' => '1',
        'jvm_schema_enable_faq'            => '1',
        'jvm_schema_faq_autodetect'        => '1',
    );

    foreach ( $defaults as $option => $value ) {
        if ( false === get_option( $option ) ) {
            add_option( $option, $value );
        }
    }
}
register_activation_hook( __FILE__, 'jvm_schema_activate' );
