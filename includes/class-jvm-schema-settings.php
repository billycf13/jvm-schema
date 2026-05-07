<?php
/**
 * Admin settings — menu page, tabbed UI, WordPress Settings API.
 *
 * @package JVM_Schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JVM_Schema_Settings {

    /**
     * Valid tab slugs.
     *
     * @var string[]
     */
    private $tabs = array(
        'general'      => 'General',
        'website'      => 'WebSite',
        'webpage'      => 'WebPage',
        'organization' => 'Organization',
        'breadcrumb'   => 'Breadcrumb',
        'product'      => 'Product (Woo)',
        'article'      => 'Article',
        'faq'          => 'FAQ',
    );

    /**
     * Register hooks.
     *
     * @return void
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'add_meta_boxes', array( $this, 'maybe_add_metabox' ) );
        add_action( 'add_meta_boxes_product', array( $this, 'add_product_metabox' ) );
        add_action( 'add_meta_boxes_post', array( $this, 'add_article_metabox' ) );
        add_action( 'save_post', array( $this, 'save_metabox' ), 10, 2 );
        add_action( 'save_post_product', array( $this, 'save_product_metabox' ), 10, 2 );
        add_action( 'save_post_post', array( $this, 'save_article_metabox' ), 10, 2 );

        // User profile fields.
        add_action( 'show_user_profile', array( $this, 'add_user_profile_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'add_user_profile_fields' ) );
        add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );
    }

    /* ──────────────────────────────────────────────
     *  Admin menu
     * ────────────────────────────────────────────── */

    /**
     * Add top-level menu page.
     *
     * @return void
     */
    public function add_menu_page() {
        add_menu_page(
            __( 'JVM Schema Settings', 'jvm-schema' ),
            __( 'JVM Schema', 'jvm-schema' ),
            'manage_options',
            'jvm-schema',
            array( $this, 'render_settings_page' ),
            'dashicons-database-view',
            81
        );
    }

    /**
     * Render the settings page (delegates to view file).
     *
     * @return void
     */
    public function render_settings_page() {
        $tabs        = $this->tabs;
        $current_tab = $this->get_current_tab();
        include JVM_SCHEMA_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Enqueue admin CSS on plugin page only.
     *
     * @param string $hook_suffix Current admin page hook.
     * @return void
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( 'toplevel_page_jvm-schema' !== $hook_suffix ) {
            return;
        }
        wp_enqueue_style(
            'jvm-schema-admin',
            JVM_SCHEMA_URL . 'assets/admin.css',
            array(),
            JVM_SCHEMA_VERSION
        );
        // Media uploader for logo.
        wp_enqueue_media();
        wp_enqueue_script( 'thickbox' );
        wp_enqueue_style( 'thickbox' );
    }

    /* ──────────────────────────────────────────────
     *  Settings API registration
     * ────────────────────────────────────────────── */

    /**
     * Register all settings, sections and fields.
     *
     * @return void
     */
    public function register_settings() {
        $this->register_general_settings();
        $this->register_website_settings();
        $this->register_webpage_settings();
        $this->register_organization_settings();
        $this->register_breadcrumb_settings();
        $this->register_product_settings();
        $this->register_article_settings();
        $this->register_faq_settings();
    }

    /* --- General -------------------------------- */

    /**
     * Register General tab settings.
     *
     * @return void
     */
    private function register_general_settings() {
        $section = 'jvm_schema_general_section';
        $page    = 'jvm-schema-general';

        add_settings_section( $section, '', '__return_false', $page );

        // Enable Schema Output.
        register_setting( $page, 'jvm_schema_enable_output', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );
        add_settings_field( 'jvm_schema_enable_output', __( 'Enable Schema Output', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $section, array(
            'label_for' => 'jvm_schema_enable_output',
            'option'    => 'jvm_schema_enable_output',
        ) );

        // wp_head priority.
        register_setting( $page, 'jvm_schema_head_priority', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 10,
        ) );
        add_settings_field( 'jvm_schema_head_priority', __( 'wp_head Priority', 'jvm-schema' ), array( $this, 'render_number' ), $page, $section, array(
            'label_for' => 'jvm_schema_head_priority',
            'option'    => 'jvm_schema_head_priority',
        ) );
    }

    /* --- WebSite -------------------------------- */

    /**
     * Register WebSite tab settings.
     *
     * @return void
     */
    private function register_website_settings() {
        $section = 'jvm_schema_website_section';
        $page    = 'jvm-schema-website';

        add_settings_section( $section, '', '__return_false', $page );

        $fields = array(
            'jvm_schema_site_name'        => __( 'Site Name', 'jvm-schema' ),
            'jvm_schema_site_url'         => __( 'Site URL', 'jvm-schema' ),
            'jvm_schema_site_description' => __( 'Site Description', 'jvm-schema' ),
            'jvm_schema_language'         => __( 'Language', 'jvm-schema' ),
        );

        foreach ( $fields as $option => $label ) {
            register_setting( $page, $option, array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ) );
            add_settings_field( $option, $label, array( $this, 'render_text' ), $page, $section, array(
                'label_for'   => $option,
                'option'      => $option,
                'placeholder' => $this->get_website_placeholder( $option ),
            ) );
        }

        // Enable SearchAction.
        register_setting( $page, 'jvm_schema_enable_searchaction', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '0',
        ) );
        add_settings_field( 'jvm_schema_enable_searchaction', __( 'Enable SearchAction (Sitelinks Searchbox)', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $section, array(
            'label_for' => 'jvm_schema_enable_searchaction',
            'option'    => 'jvm_schema_enable_searchaction',
        ) );
    }

    /* --- WebPage -------------------------------- */

    /**
     * Register WebPage tab settings.
     *
     * @return void
     */
    private function register_webpage_settings() {
        $section = 'jvm_schema_webpage_section';
        $page    = 'jvm-schema-webpage';

        add_settings_section( $section, '', '__return_false', $page );

        // Enable WebPage Schema.
        register_setting( $page, 'jvm_schema_enable_webpage', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );
        add_settings_field( 'jvm_schema_enable_webpage', __( 'Enable WebPage Schema', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $section, array(
            'label_for' => 'jvm_schema_enable_webpage',
            'option'    => 'jvm_schema_enable_webpage',
        ) );

        // Include datePublished & dateModified.
        register_setting( $page, 'jvm_schema_webpage_dates', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );
        add_settings_field( 'jvm_schema_webpage_dates', __( 'Include datePublished & dateModified', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $section, array(
            'label_for' => 'jvm_schema_webpage_dates',
            'option'    => 'jvm_schema_webpage_dates',
        ) );

        // Page Type Detection (radio).
        register_setting( $page, 'jvm_schema_webpage_detection', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_detection' ),
            'default'           => 'auto',
        ) );
        add_settings_field( 'jvm_schema_webpage_detection', __( 'Page Type Detection', 'jvm-schema' ), array( $this, 'render_detection_radio' ), $page, $section, array(
            'label_for' => 'jvm_schema_webpage_detection',
            'option'    => 'jvm_schema_webpage_detection',
        ) );
    }

    /* --- Organization --------------------------- */

    private function register_organization_settings() {
        $page = 'jvm-schema-organization';

        // ── Business Identity ──
        $sec_identity = 'jvm_schema_org_identity_section';
        add_settings_section( $sec_identity, __( 'Business Identity', 'jvm-schema' ), '__return_false', $page );

        register_setting( $page, 'jvm_schema_business_type', array(
            'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_business_type' ), 'default' => 'organization',
        ) );
        add_settings_field( 'jvm_schema_business_type', __( 'Business Type', 'jvm-schema' ), array( $this, 'render_radio_generic' ), $page, $sec_identity, array(
            'option' => 'jvm_schema_business_type', 'choices' => array( 'organization' => __( 'Organization', 'jvm-schema' ), 'localbusiness' => __( 'Local Business', 'jvm-schema' ) ), 'default' => 'organization',
        ) );

        register_setting( $page, 'jvm_schema_localbusiness_type', array(
            'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Store',
        ) );
        add_settings_field( 'jvm_schema_localbusiness_type', __( 'Local Business Type', 'jvm-schema' ), array( $this, 'render_select' ), $page, $sec_identity, array(
            'option' => 'jvm_schema_localbusiness_type', 'choices' => $this->get_localbusiness_types(), 'default' => 'Store', 'class' => 'jvm-row-localbusiness',
        ) );

        $identity_fields = array(
            'jvm_schema_org_name'         => array( 'label' => __( 'Organization Name', 'jvm-schema' ), 'placeholder' => get_bloginfo( 'name' ) ),
            'jvm_schema_org_url'          => array( 'label' => __( 'Organization URL', 'jvm-schema' ), 'placeholder' => home_url( '/' ) ),
            'jvm_schema_org_email'        => array( 'label' => __( 'Email', 'jvm-schema' ), 'placeholder' => '' ),
            'jvm_schema_org_telephone'    => array( 'label' => __( 'Telephone', 'jvm-schema' ), 'placeholder' => '+62xxx' ),
            'jvm_schema_org_founding_date' => array( 'label' => __( 'Founding Date', 'jvm-schema' ), 'placeholder' => 'YYYY-MM-DD' ),
        );
        foreach ( $identity_fields as $opt => $cfg ) {
            register_setting( $page, $opt, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
            add_settings_field( $opt, $cfg['label'], array( $this, 'render_text' ), $page, $sec_identity, array(
                'label_for' => $opt, 'option' => $opt, 'placeholder' => $cfg['placeholder'],
            ) );
        }

        register_setting( $page, 'jvm_schema_org_description', array(
            'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '',
        ) );
        add_settings_field( 'jvm_schema_org_description', __( 'Description', 'jvm-schema' ), array( $this, 'render_textarea' ), $page, $sec_identity, array(
            'option' => 'jvm_schema_org_description',
        ) );

        // ── Logo ──
        $sec_logo = 'jvm_schema_org_logo_section';
        add_settings_section( $sec_logo, __( 'Logo', 'jvm-schema' ), '__return_false', $page );

        register_setting( $page, 'jvm_schema_org_logo_source', array(
            'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_logo_source' ), 'default' => 'media',
        ) );
        add_settings_field( 'jvm_schema_org_logo_source', __( 'Logo Source', 'jvm-schema' ), array( $this, 'render_radio_generic' ), $page, $sec_logo, array(
            'option' => 'jvm_schema_org_logo_source', 'choices' => array( 'media' => __( 'WordPress Media Library', 'jvm-schema' ), 'url' => __( 'External URL', 'jvm-schema' ) ), 'default' => 'media',
        ) );

        register_setting( $page, 'jvm_schema_org_logo_id', array(
            'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0,
        ) );
        add_settings_field( 'jvm_schema_org_logo_id', __( 'Logo (Media)', 'jvm-schema' ), array( $this, 'render_logo_upload' ), $page, $sec_logo, array(
            'option' => 'jvm_schema_org_logo_id', 'class' => 'jvm-row-logo-media',
        ) );

        register_setting( $page, 'jvm_schema_org_logo_url', array(
            'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '',
        ) );
        add_settings_field( 'jvm_schema_org_logo_url', __( 'Logo URL', 'jvm-schema' ), array( $this, 'render_text' ), $page, $sec_logo, array(
            'label_for' => 'jvm_schema_org_logo_url', 'option' => 'jvm_schema_org_logo_url', 'placeholder' => 'https://example.com/logo.png', 'class' => 'jvm-row-logo-url',
        ) );

        // ── Social Profiles ──
        $sec_social = 'jvm_schema_org_social_section';
        add_settings_section( $sec_social, __( 'Social Profiles (sameAs)', 'jvm-schema' ), '__return_false', $page );

        $socials = array(
            'jvm_schema_sameas_website'   => __( 'Website', 'jvm-schema' ),
            'jvm_schema_sameas_facebook'  => __( 'Facebook', 'jvm-schema' ),
            'jvm_schema_sameas_instagram' => __( 'Instagram', 'jvm-schema' ),
            'jvm_schema_sameas_youtube'   => __( 'YouTube', 'jvm-schema' ),
            'jvm_schema_sameas_linkedin'  => __( 'LinkedIn', 'jvm-schema' ),
        );
        foreach ( $socials as $opt => $label ) {
            register_setting( $page, $opt, array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '' ) );
            add_settings_field( $opt, $label, array( $this, 'render_text' ), $page, $sec_social, array(
                'label_for' => $opt, 'option' => $opt, 'placeholder' => 'https://',
            ) );
        }

        // ── Local Business Fields ──
        $sec_local = 'jvm_schema_org_local_section';
        add_settings_section( $sec_local, __( 'Local Business Details', 'jvm-schema' ), '__return_false', $page );

        $address_fields = array(
            'jvm_schema_address_street'  => __( 'Street Address', 'jvm-schema' ),
            'jvm_schema_address_city'    => __( 'City (addressLocality)', 'jvm-schema' ),
            'jvm_schema_address_region'  => __( 'Province (addressRegion)', 'jvm-schema' ),
            'jvm_schema_address_postal'  => __( 'Postal Code', 'jvm-schema' ),
        );
        foreach ( $address_fields as $opt => $label ) {
            register_setting( $page, $opt, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
            add_settings_field( $opt, $label, array( $this, 'render_text' ), $page, $sec_local, array(
                'label_for' => $opt, 'option' => $opt, 'placeholder' => '', 'class' => 'jvm-row-localbusiness',
            ) );
        }

        register_setting( $page, 'jvm_schema_address_country', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'ID' ) );
        add_settings_field( 'jvm_schema_address_country', __( 'Country', 'jvm-schema' ), array( $this, 'render_text' ), $page, $sec_local, array(
            'label_for' => 'jvm_schema_address_country', 'option' => 'jvm_schema_address_country', 'placeholder' => 'ID', 'class' => 'jvm-row-localbusiness',
        ) );

        $geo_fields = array(
            'jvm_schema_geo_lat'  => __( 'Latitude', 'jvm-schema' ),
            'jvm_schema_geo_lng'  => __( 'Longitude', 'jvm-schema' ),
            'jvm_schema_maps_url' => __( 'Google Maps URL', 'jvm-schema' ),
        );
        foreach ( $geo_fields as $opt => $label ) {
            $cb = ( $opt === 'jvm_schema_maps_url' ) ? 'esc_url_raw' : 'sanitize_text_field';
            register_setting( $page, $opt, array( 'type' => 'string', 'sanitize_callback' => $cb, 'default' => '' ) );
            add_settings_field( $opt, $label, array( $this, 'render_text' ), $page, $sec_local, array(
                'label_for' => $opt, 'option' => $opt, 'placeholder' => '', 'class' => 'jvm-row-localbusiness',
            ) );
        }

        register_setting( $page, 'jvm_schema_price_range', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
        add_settings_field( 'jvm_schema_price_range', __( 'Price Range', 'jvm-schema' ), array( $this, 'render_text' ), $page, $sec_local, array(
            'label_for' => 'jvm_schema_price_range', 'option' => 'jvm_schema_price_range', 'placeholder' => 'Rp - Rp', 'class' => 'jvm-row-localbusiness',
        ) );

        // ── Opening Hours ──
        $sec_hours = 'jvm_schema_org_hours_section';
        add_settings_section( $sec_hours, __( 'Opening Hours', 'jvm-schema' ), '__return_false', $page );

        register_setting( $page, 'jvm_schema_hours_enable', array(
            'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => '0',
        ) );
        add_settings_field( 'jvm_schema_hours_enable', __( 'Enable Opening Hours', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $sec_hours, array(
            'label_for' => 'jvm_schema_hours_enable', 'option' => 'jvm_schema_hours_enable', 'class' => 'jvm-row-localbusiness',
        ) );

        register_setting( $page, 'jvm_schema_hours_mode', array(
            'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_hours_mode' ), 'default' => 'perday',
        ) );
        add_settings_field( 'jvm_schema_hours_mode', __( 'Hours Mode', 'jvm-schema' ), array( $this, 'render_radio_generic' ), $page, $sec_hours, array(
            'option' => 'jvm_schema_hours_mode', 'choices' => array( 'perday' => __( 'Per Day', 'jvm-schema' ), 'pattern' => __( 'Pattern (advanced)', 'jvm-schema' ) ), 'default' => 'perday', 'class' => 'jvm-row-hours',
        ) );

        register_setting( $page, 'jvm_schema_hours_perday', array(
            'type' => 'array', 'sanitize_callback' => array( $this, 'sanitize_hours_perday' ), 'default' => array(),
        ) );
        add_settings_field( 'jvm_schema_hours_perday', __( 'Per Day Schedule', 'jvm-schema' ), array( $this, 'render_hours_perday' ), $page, $sec_hours, array(
            'option' => 'jvm_schema_hours_perday', 'class' => 'jvm-row-hours-perday',
        ) );

        register_setting( $page, 'jvm_schema_hours_pattern', array(
            'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '',
        ) );
        add_settings_field( 'jvm_schema_hours_pattern', __( 'Hours Pattern', 'jvm-schema' ), array( $this, 'render_textarea' ), $page, $sec_hours, array(
            'option' => 'jvm_schema_hours_pattern', 'placeholder' => "Mo-Fr 08:00-17:00\nSa 08:00-13:00", 'class' => 'jvm-row-hours-pattern',
        ) );

        // ── Output Location ──
        $sec_output = 'jvm_schema_org_output_section';
        add_settings_section( $sec_output, __( 'Output Location', 'jvm-schema' ), '__return_false', $page );

        register_setting( $page, 'jvm_schema_org_output', array(
            'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_org_output' ), 'default' => 'homepage',
        ) );
        add_settings_field( 'jvm_schema_org_output', __( 'Output On', 'jvm-schema' ), array( $this, 'render_radio_generic' ), $page, $sec_output, array(
            'option' => 'jvm_schema_org_output', 'choices' => array( 'homepage' => __( 'Homepage only', 'jvm-schema' ), 'all' => __( 'All pages', 'jvm-schema' ), 'specific' => __( 'Specific pages', 'jvm-schema' ) ), 'default' => 'homepage',
        ) );

        register_setting( $page, 'jvm_schema_org_output_pages', array(
            'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '',
        ) );
        add_settings_field( 'jvm_schema_org_output_pages', __( 'Page Slugs / IDs', 'jvm-schema' ), array( $this, 'render_text' ), $page, $sec_output, array(
            'label_for' => 'jvm_schema_org_output_pages', 'option' => 'jvm_schema_org_output_pages', 'placeholder' => 'about, contact, 42', 'class' => 'jvm-row-output-specific',
        ) );
    }

    /* --- Breadcrumb ----------------------------- */

    private function register_breadcrumb_settings() {
        $page = 'jvm-schema-breadcrumb';
        $section = 'jvm_schema_breadcrumb_section';

        add_settings_section( $section, __( 'Breadcrumb Settings', 'jvm-schema' ), '__return_false', $page );

        // Enable Breadcrumb Schema.
        register_setting( $page, 'jvm_schema_enable_breadcrumb', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );
        add_settings_field( 'jvm_schema_enable_breadcrumb', __( 'Enable Breadcrumb Schema', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $section, array(
            'label_for' => 'jvm_schema_enable_breadcrumb',
            'option'    => 'jvm_schema_enable_breadcrumb',
        ) );

        // Show Home Link.
        register_setting( $page, 'jvm_schema_breadcrumb_show_home', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );
        add_settings_field( 'jvm_schema_breadcrumb_show_home', __( 'Show Home Link', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $section, array(
            'label_for' => 'jvm_schema_breadcrumb_show_home',
            'option'    => 'jvm_schema_breadcrumb_show_home',
        ) );

        // Home Link Text.
        register_setting( $page, 'jvm_schema_breadcrumb_home_text', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Home',
        ) );
        add_settings_field( 'jvm_schema_breadcrumb_home_text', __( 'Home Link Text', 'jvm-schema' ), array( $this, 'render_text' ), $page, $section, array(
            'label_for'   => 'jvm_schema_breadcrumb_home_text',
            'option'      => 'jvm_schema_breadcrumb_home_text',
            'placeholder' => 'Home',
        ) );
    }

    /* --- Product -------------------------------- */

    private function register_product_settings() {
        $page = 'jvm-schema-product';
        $section = 'jvm_schema_product_section';

        add_settings_section( $section, __( 'WooCommerce Product Schema', 'jvm-schema' ), '__return_false', $page );

        register_setting( $page, 'jvm_schema_enable_product', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => '1' ) );
        add_settings_field( 'jvm_schema_enable_product', __( 'Enable Product Schema', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $section, array( 'label_for' => 'jvm_schema_enable_product', 'option' => 'jvm_schema_enable_product' ) );

        register_setting( $page, 'jvm_schema_product_default_rating', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '5' ) );
        add_settings_field( 'jvm_schema_product_default_rating', __( 'Default Rating Value', 'jvm-schema' ), array( $this, 'render_text' ), $page, $section, array( 'label_for' => 'jvm_schema_product_default_rating', 'option' => 'jvm_schema_product_default_rating', 'placeholder' => '5' ) );

        register_setting( $page, 'jvm_schema_product_default_review_count', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '10' ) );
        add_settings_field( 'jvm_schema_product_default_review_count', __( 'Default Review Count', 'jvm-schema' ), array( $this, 'render_text' ), $page, $section, array( 'label_for' => 'jvm_schema_product_default_review_count', 'option' => 'jvm_schema_product_default_review_count', 'placeholder' => '10' ) );

        register_setting( $page, 'jvm_schema_product_default_brand', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
        add_settings_field( 'jvm_schema_product_default_brand', __( 'Default Brand', 'jvm-schema' ), array( $this, 'render_text' ), $page, $section, array( 'label_for' => 'jvm_schema_product_default_brand', 'option' => 'jvm_schema_product_default_brand', 'placeholder' => 'My Brand' ) );
    }

    /* --- Article -------------------------------- */

    private function register_article_settings() {
        $page = 'jvm-schema-article';
        $section = 'jvm_schema_article_section';

        add_settings_section( $section, __( 'Article / BlogPosting Schema', 'jvm-schema' ), '__return_false', $page );

        register_setting( $page, 'jvm_schema_enable_article', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => '1' ) );
        add_settings_field( 'jvm_schema_enable_article', __( 'Enable Article Schema', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $section, array( 'label_for' => 'jvm_schema_enable_article', 'option' => 'jvm_schema_enable_article' ) );

        register_setting( $page, 'jvm_schema_article_default_type', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'BlogPosting' ) );
        add_settings_field( 'jvm_schema_article_default_type', __( 'Default Article Type', 'jvm-schema' ), array( $this, 'render_select' ), $page, $section, array(
            'option'  => 'jvm_schema_article_default_type',
            'choices' => array( 'Article' => 'Article', 'BlogPosting' => 'BlogPosting', 'NewsArticle' => 'NewsArticle' ),
            'default' => 'BlogPosting',
        ) );

        register_setting( $page, 'jvm_schema_article_disable_webpage', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => '1' ) );
        add_settings_field( 'jvm_schema_article_disable_webpage', __( 'Disable WebPage on Single Posts', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $section, array( 'label_for' => 'jvm_schema_article_disable_webpage', 'option' => 'jvm_schema_article_disable_webpage' ) );
    }

    /* --- FAQ ------------------------------------ */

    private function register_faq_settings() {
        $page = 'jvm-schema-faq';
        $section = 'jvm_schema_faq_section';

        add_settings_section( $section, __( 'FAQ Schema Settings', 'jvm-schema' ), '__return_false', $page );

        register_setting( $page, 'jvm_schema_enable_faq', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => '1' ) );
        add_settings_field( 'jvm_schema_enable_faq', __( 'Enable FAQ Schema', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $section, array( 'label_for' => 'jvm_schema_enable_faq', 'option' => 'jvm_schema_enable_faq' ) );

        register_setting( $page, 'jvm_schema_faq_autodetect', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => '1' ) );
        add_settings_field( 'jvm_schema_faq_autodetect', __( 'Enable Auto-Detection', 'jvm-schema' ), array( $this, 'render_toggle' ), $page, $section, array( 'label_for' => 'jvm_schema_faq_autodetect', 'option' => 'jvm_schema_faq_autodetect' ) );

        add_settings_field( 'jvm_schema_faq_info', __( 'Detection Methods', 'jvm-schema' ), function() {
            echo '<div style="line-height:1.8;">';
            echo '<p><strong>' . esc_html__( '1. Explicit wrapper (always active):', 'jvm-schema' ) . '</strong></p>';
            echo '<pre style="background:#eee;padding:10px;margin:5px 0 15px;"><code>' . esc_html( '<div class="jvm-faq">
  <h3>Your Question?</h3>
  <p>Your Answer...</p>
</div>' ) . '</code></pre>';
            echo '<p><strong>' . esc_html__( '2. Question headings (auto-detect):', 'jvm-schema' ) . '</strong><br>';
            echo esc_html__( 'Any heading (h1-h6) ending with "?" will be treated as a question, content after it as the answer.', 'jvm-schema' ) . '</p>';
            echo '<p><strong>' . esc_html__( '3. Accordion pattern (auto-detect):', 'jvm-schema' ) . '</strong><br>';
            echo esc_html__( 'HTML <details>/<summary> elements are automatically detected.', 'jvm-schema' ) . '</p>';
            echo '<hr style="margin:12px 0;">';
            echo '<p class="description">' . esc_html__( 'Content sources: post/page content, and WooCommerce product custom tabs.', 'jvm-schema' ) . '</p>';
            echo '</div>';
        }, $page, $section );
    }

    public function add_article_metabox() {
        add_meta_box( 'jvm_schema_article_meta', __( 'JVM Schema — Article Overrides', 'jvm-schema' ), array( $this, 'render_article_metabox' ), 'post', 'normal', 'high' );
    }

    public function render_article_metabox( $post ) {
        wp_nonce_field( 'jvm_schema_article_meta_nonce', 'jvm_schema_article_meta_nonce_field' );
        $type     = get_post_meta( $post->ID, '_jvm_schema_article_type', true );
        $headline = get_post_meta( $post->ID, '_jvm_schema_article_headline', true );
        $desc     = get_post_meta( $post->ID, '_jvm_schema_article_description', true );

        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__( 'Article Type', 'jvm-schema' ) . '</th><td><select name="_jvm_schema_article_type">';
        $types = array( '' => __( '— Default —', 'jvm-schema' ), 'Article' => 'Article', 'BlogPosting' => 'BlogPosting', 'NewsArticle' => 'NewsArticle' );
        foreach ( $types as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $type, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>' . esc_html__( 'Custom Headline', 'jvm-schema' ) . '</th><td><input type="text" name="_jvm_schema_article_headline" value="' . esc_attr( $headline ) . '" class="large-text" placeholder="' . esc_attr( get_the_title( $post->ID ) ) . '" /></td></tr>';
        echo '<tr><th>' . esc_html__( 'Custom Description', 'jvm-schema' ) . '</th><td><textarea name="_jvm_schema_article_description" class="large-text" rows="3">' . esc_textarea( $desc ) . '</textarea></td></tr>';
        echo '</table>';
    }

    public function save_article_metabox( $post_id ) {
        if ( ! isset( $_POST['jvm_schema_article_meta_nonce_field'] ) || ! wp_verify_nonce( $_POST['jvm_schema_article_meta_nonce_field'], 'jvm_schema_article_meta_nonce' ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        $fields = array( '_jvm_schema_article_type', '_jvm_schema_article_headline', '_jvm_schema_article_description' );
        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }
    }

    /* --- User Profile --------------------------- */

    public function add_user_profile_fields( $user ) {
        ?>
        <h3><?php esc_html_e( 'JVM Schema — Author Social Profiles', 'jvm-schema' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="jvm_schema_user_facebook">Facebook URL</label></th>
                <td><input type="url" name="jvm_schema_user_facebook" id="jvm_schema_user_facebook" value="<?php echo esc_url( get_user_meta( $user->ID, 'jvm_schema_user_facebook', true ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="jvm_schema_user_twitter">Twitter URL</label></th>
                <td><input type="url" name="jvm_schema_user_twitter" id="jvm_schema_user_twitter" value="<?php echo esc_url( get_user_meta( $user->ID, 'jvm_schema_user_twitter', true ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="jvm_schema_user_instagram">Instagram URL</label></th>
                <td><input type="url" name="jvm_schema_user_instagram" id="jvm_schema_user_instagram" value="<?php echo esc_url( get_user_meta( $user->ID, 'jvm_schema_user_instagram', true ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="jvm_schema_user_linkedin">LinkedIn URL</label></th>
                <td><input type="url" name="jvm_schema_user_linkedin" id="jvm_schema_user_linkedin" value="<?php echo esc_url( get_user_meta( $user->ID, 'jvm_schema_user_linkedin', true ) ); ?>" class="regular-text" /></td>
            </tr>
        </table>
        <?php
    }

    public function save_user_profile_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;
        $keys = array( 'facebook', 'twitter', 'instagram', 'linkedin' );
        foreach ( $keys as $key ) {
            if ( isset( $_POST[ 'jvm_schema_user_' . $key ] ) ) {
                update_user_meta( $user_id, 'jvm_schema_user_' . $key, esc_url_raw( $_POST[ 'jvm_schema_user_' . $key ] ) );
            }
        }
    }

    public function add_product_metabox() {
        add_meta_box( 'jvm_schema_product_meta', __( 'JVM Schema — Product Overrides', 'jvm-schema' ), array( $this, 'render_product_metabox' ), 'product', 'normal', 'high' );
    }

    public function render_product_metabox( $post ) {
        wp_nonce_field( 'jvm_schema_product_meta_nonce', 'jvm_schema_product_meta_nonce_field' );
        $rating       = get_post_meta( $post->ID, '_jvm_schema_product_rating', true );
        $review_count = get_post_meta( $post->ID, '_jvm_schema_product_review_count', true );
        $brand        = get_post_meta( $post->ID, '_jvm_schema_product_brand', true );
        $condition    = get_post_meta( $post->ID, '_jvm_schema_product_condition', true );
        if ( empty( $condition ) ) $condition = 'NewCondition';

        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__( 'Custom Rating (e.g. 4.8)', 'jvm-schema' ) . '</th><td><input type="text" name="_jvm_schema_product_rating" value="' . esc_attr( $rating ) . '" class="small-text" /></td></tr>';
        echo '<tr><th>' . esc_html__( 'Custom Review Count', 'jvm-schema' ) . '</th><td><input type="number" name="_jvm_schema_product_review_count" value="' . esc_attr( $review_count ) . '" class="small-text" /></td></tr>';
        echo '<tr><th>' . esc_html__( 'Brand Name', 'jvm-schema' ) . '</th><td><input type="text" name="_jvm_schema_product_brand" value="' . esc_attr( $brand ) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>' . esc_html__( 'Product Condition', 'jvm-schema' ) . '</th><td><select name="_jvm_schema_product_condition">';
        $conditions = array( 'NewCondition' => 'New', 'UsedCondition' => 'Used', 'RefurbishedCondition' => 'Refurbished' );
        foreach ( $conditions as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $condition, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';
        echo '</table>';
    }

    public function save_product_metabox( $post_id ) {
        if ( ! isset( $_POST['jvm_schema_product_meta_nonce_field'] ) || ! wp_verify_nonce( $_POST['jvm_schema_product_meta_nonce_field'], 'jvm_schema_product_meta_nonce' ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        $fields = array( '_jvm_schema_product_rating', '_jvm_schema_product_review_count', '_jvm_schema_product_brand', '_jvm_schema_product_condition' );
        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }
    }

    /* ──────────────────────────────────────────────
     *  Field renderers
     * ────────────────────────────────────────────── */

    /**
     * Render a toggle/checkbox field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_toggle( $args ) {
        $option = $args['option'];
        $value  = get_option( $option );
        ?>
        <label class="jvm-toggle" for="<?php echo esc_attr( $option ); ?>">
            <input type="hidden" name="<?php echo esc_attr( $option ); ?>" value="0" />
            <input
                type="checkbox"
                id="<?php echo esc_attr( $option ); ?>"
                name="<?php echo esc_attr( $option ); ?>"
                value="1"
                <?php checked( $value, '1' ); ?>
            />
            <span class="jvm-toggle__slider"></span>
        </label>
        <?php
    }

    /**
     * Render a number input field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_number( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, 10 );
        ?>
        <input
            type="number"
            id="<?php echo esc_attr( $option ); ?>"
            name="<?php echo esc_attr( $option ); ?>"
            value="<?php echo esc_attr( $value ); ?>"
            class="small-text"
            min="1"
            max="100"
        />
        <?php
    }

    /**
     * Render a text input field with placeholder.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_text( $args ) {
        $option      = $args['option'];
        $value       = get_option( $option, '' );
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
        ?>
        <input
            type="text"
            id="<?php echo esc_attr( $option ); ?>"
            name="<?php echo esc_attr( $option ); ?>"
            value="<?php echo esc_attr( $value ); ?>"
            placeholder="<?php echo esc_attr( $placeholder ); ?>"
            class="regular-text"
        />
        <?php if ( $placeholder ) : ?>
            <p class="description">
                <?php
                /* translators: %s: placeholder / default value */
                printf( esc_html__( 'Default: %s', 'jvm-schema' ), '<code>' . esc_html( $placeholder ) . '</code>' );
                ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render radio buttons for page-type detection mode.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_detection_radio( $args ) {
        $option  = $args['option'];
        $current = get_option( $option, 'auto' );
        $choices = array(
            'auto'   => __( 'Auto-detect based on WordPress conditionals', 'jvm-schema' ),
            'manual' => __( 'Manual override per post/page (metabox)', 'jvm-schema' ),
        );
        foreach ( $choices as $val => $label ) {
            ?>
            <label style="display:block;margin-bottom:6px;">
                <input
                    type="radio"
                    name="<?php echo esc_attr( $option ); ?>"
                    value="<?php echo esc_attr( $val ); ?>"
                    <?php checked( $current, $val ); ?>
                />
                <?php echo esc_html( $label ); ?>
            </label>
            <?php
        }
    }

    /**
     * Render a textarea field.
     */
    public function render_textarea( $args ) {
        $option      = $args['option'];
        $value       = get_option( $option, '' );
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
        ?>
        <textarea
            id="<?php echo esc_attr( $option ); ?>"
            name="<?php echo esc_attr( $option ); ?>"
            rows="4"
            cols="50"
            class="large-text"
            placeholder="<?php echo esc_attr( $placeholder ); ?>"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <?php
    }

    /**
     * Render a select dropdown.
     */
    public function render_select( $args ) {
        $option  = $args['option'];
        $current = get_option( $option, isset( $args['default'] ) ? $args['default'] : '' );
        $choices = $args['choices'];
        ?>
        <select id="<?php echo esc_attr( $option ); ?>" name="<?php echo esc_attr( $option ); ?>">
            <?php foreach ( $choices as $val => $label ) : ?>
                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current, $val ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render generic radio buttons.
     */
    public function render_radio_generic( $args ) {
        $option  = $args['option'];
        $current = get_option( $option, isset( $args['default'] ) ? $args['default'] : '' );
        $choices = $args['choices'];
        foreach ( $choices as $val => $label ) {
            ?>
            <label style="display:block;margin-bottom:6px;">
                <input
                    type="radio"
                    name="<?php echo esc_attr( $option ); ?>"
                    value="<?php echo esc_attr( $val ); ?>"
                    <?php checked( $current, $val ); ?>
                />
                <?php echo esc_html( $label ); ?>
            </label>
            <?php
        }
    }

    /**
     * Render logo upload field using WP Media Library.
     */
    public function render_logo_upload( $args ) {
        $option = $args['option'];
        $value  = absint( get_option( $option ) );
        $url    = $value ? wp_get_attachment_url( $value ) : '';
        ?>
        <div class="jvm-logo-uploader">
            <div class="jvm-logo-preview" style="margin-bottom:10px;">
                <?php if ( $url ) : ?>
                    <img src="<?php echo esc_url( $url ); ?>" style="max-width:150px;max-height:150px;display:block;border:1px solid #ccc;padding:5px;" />
                <?php endif; ?>
            </div>
            <input type="hidden" name="<?php echo esc_attr( $option ); ?>" id="<?php echo esc_attr( $option ); ?>" value="<?php echo esc_attr( $value ); ?>" />
            <button type="button" class="button jvm-upload-button" data-input="#<?php echo esc_attr( $option ); ?>"><?php esc_html_e( 'Select Image', 'jvm-schema' ); ?></button>
            <button type="button" class="button jvm-remove-button <?php echo $value ? '' : 'hidden'; ?>"><?php esc_html_e( 'Remove', 'jvm-schema' ); ?></button>
        </div>
        <?php
    }

    /**
     * Render opening hours table for "perday" mode.
     */
    public function render_hours_perday( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, array() );
        $days   = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
        ?>
        <table class="jvm-hours-table widefat" style="max-width:500px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Day', 'jvm-schema' ); ?></th>
                    <th><?php esc_html_e( 'Closed', 'jvm-schema' ); ?></th>
                    <th><?php esc_html_e( 'Opens', 'jvm-schema' ); ?></th>
                    <th><?php esc_html_e( 'Closes', 'jvm-schema' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $days as $day ) :
                    $closed = isset( $value[ $day ]['closed'] ) && $value[ $day ]['closed'];
                    $opens  = isset( $value[ $day ]['opens'] ) ? $value[ $day ]['opens'] : '08:00';
                    $closes = isset( $value[ $day ]['closes'] ) ? $value[ $day ]['closes'] : '17:00';
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $day ); ?></strong></td>
                        <td>
                            <input type="checkbox" name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $day ); ?>][closed]" value="1" <?php checked( $closed ); ?> />
                        </td>
                        <td>
                            <input type="time" name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $day ); ?>][opens]" value="<?php echo esc_attr( $opens ); ?>" />
                        </td>
                        <td>
                            <input type="time" name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $day ); ?>][closes]" value="<?php echo esc_attr( $closes ); ?>" />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /* ──────────────────────────────────────────────
     *  Metabox (manual page-type override)
     * ────────────────────────────────────────────── */

    /**
     * Conditionally register the page-type metabox.
     *
     * @return void
     */
    public function maybe_add_metabox() {
        if ( 'manual' !== get_option( 'jvm_schema_webpage_detection', 'auto' ) ) {
            return;
        }

        $post_types = get_post_types( array( 'public' => true ), 'names' );
        foreach ( $post_types as $pt ) {
            add_meta_box(
                'jvm_schema_page_type',
                __( 'JVM Schema — Page Type', 'jvm-schema' ),
                array( $this, 'render_metabox' ),
                $pt,
                'side',
                'default'
            );
        }
    }

    /**
     * Render metabox content.
     *
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public function render_metabox( $post ) {
        wp_nonce_field( 'jvm_schema_page_type_nonce', 'jvm_schema_page_type_nonce_field' );

        $current = get_post_meta( $post->ID, '_jvm_schema_page_type', true );
        $types   = array(
            ''               => __( '— Default (WebPage) —', 'jvm-schema' ),
            'WebPage'        => 'WebPage',
            'CollectionPage' => 'CollectionPage',
            'AboutPage'      => 'AboutPage',
            'ContactPage'    => 'ContactPage',
            'FAQPage'        => 'FAQPage',
        );
        ?>
        <select id="jvm_schema_page_type" name="_jvm_schema_page_type" style="width:100%;">
            <?php foreach ( $types as $val => $label ) : ?>
                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current, $val ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Save metabox value.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @return void
     */
    public function save_metabox( $post_id, $post ) {
        // Verify nonce.
        if ( ! isset( $_POST['jvm_schema_page_type_nonce_field'] ) ||
             ! wp_verify_nonce( $_POST['jvm_schema_page_type_nonce_field'], 'jvm_schema_page_type_nonce' ) ) {
            return;
        }

        // Check permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Skip auto-save.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $allowed = array( '', 'WebPage', 'CollectionPage', 'AboutPage', 'ContactPage', 'FAQPage' );
        $value   = isset( $_POST['_jvm_schema_page_type'] ) ? sanitize_text_field( wp_unslash( $_POST['_jvm_schema_page_type'] ) ) : '';

        if ( in_array( $value, $allowed, true ) ) {
            update_post_meta( $post_id, '_jvm_schema_page_type', $value );
        }
    }

    /* ──────────────────────────────────────────────
     *  Sanitize helpers
     * ────────────────────────────────────────────── */

    /**
     * Sanitize a checkbox value (always '0' or '1').
     *
     * @param mixed $value Raw value.
     * @return string
     */
    public function sanitize_checkbox( $value ) {
        return ( '1' === (string) $value ) ? '1' : '0';
    }

    /**
     * Sanitize detection mode value.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    public function sanitize_detection( $value ) {
        return in_array( $value, array( 'auto', 'manual' ), true ) ? $value : 'auto';
    }

    public function sanitize_business_type( $value ) {
        return in_array( $value, array( 'organization', 'localbusiness' ), true ) ? $value : 'organization';
    }

    public function sanitize_logo_source( $value ) {
        return in_array( $value, array( 'media', 'url' ), true ) ? $value : 'media';
    }

    public function sanitize_hours_mode( $value ) {
        return in_array( $value, array( 'perday', 'pattern' ), true ) ? $value : 'perday';
    }

    public function sanitize_org_output( $value ) {
        return in_array( $value, array( 'homepage', 'all', 'specific' ), true ) ? $value : 'homepage';
    }

    public function sanitize_hours_perday( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }
        $sanitized = array();
        $days      = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
        foreach ( $days as $day ) {
            if ( isset( $value[ $day ] ) ) {
                $sanitized[ $day ] = array(
                    'closed' => isset( $value[ $day ]['closed'] ) ? 1 : 0,
                    'opens'  => sanitize_text_field( $value[ $day ]['opens'] ),
                    'closes' => sanitize_text_field( $value[ $day ]['closes'] ),
                );
            }
        }
        return $sanitized;
    }

    /* ──────────────────────────────────────────────
     *  Helpers
     * ────────────────────────────────────────────── */

    /**
     * Return the current active tab slug.
     *
     * @return string
     */
    private function get_current_tab() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        return array_key_exists( $tab, $this->tabs ) ? $tab : 'general';
    }

    /**
     * Return a sensible placeholder for WebSite text fields.
     *
     * @param string $option Option name.
     * @return string
     */
    private function get_website_placeholder( $option ) {
        switch ( $option ) {
            case 'jvm_schema_site_name':
                return get_bloginfo( 'name' );
            case 'jvm_schema_site_url':
                return home_url( '/' );
            case 'jvm_schema_site_description':
                return get_bloginfo( 'description' );
            case 'jvm_schema_language':
                return get_bloginfo( 'language' );
        }
    }

    /**
     * Get a list of LocalBusiness subtypes for the dropdown.
     */
    private function get_localbusiness_types() {
        $types = array(
            'AnimalShelter', 'ArchiveOrganization', 'AutomotiveBusiness', 'AutoBodyShop', 'AutoDealer',
            'AutoPartsStore', 'AutoRental', 'AutoRepair', 'AutoWash', 'GasStation', 'MotorcycleDealer',
            'MotorcycleRepair', 'ChildCare', 'Dentist', 'DryCleaningOrLaundry', 'EmergencyService',
            'EmploymentAgency', 'EntertainmentBusiness', 'FinancialService', 'FoodEstablishment',
            'GovernmentOffice', 'HealthAndBeautyBusiness', 'HomeAndConstructionBusiness',
            'InternetCafe', 'LegalService', 'Library', 'LodgingBusiness', 'MedicalBusiness',
            'ProfessionalService', 'RadioStation', 'RealEstateAgent', 'RecyclingCenter',
            'SelfStorage', 'ShoppingCenter', 'SportsActivityLocation', 'Store',
            'ElectronicsStore', 'HardwareStore', 'HobbyShop', 'HomeGoodsStore',
            'ComputerStore', 'TouristInformationCenter', 'TravelAgency'
        );
        return array_combine( $types, $types );
    }
}
