<?php
/**
 * Plugin loader — bootstraps settings (admin) and output (front-end).
 *
 * @package JVM_Schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JVM_Schema_Loader {

    /**
     * Initialize hooks.
     *
     * @return void
     */
    public function init() {
        // Admin settings.
        if ( is_admin() ) {
            $settings = new JVM_Schema_Settings();
            $settings->init();
        }

        // Front-end schema output.
        $output = new JVM_Schema_Output();
        $output->init();
    }
}
