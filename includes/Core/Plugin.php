<?php

namespace BusinessBuilderCore\Core;

use BusinessBuilderCore\Admin\SiteSettingsPage;
use BusinessBuilderCore\Admin\PageAdmin;

use BusinessBuilderCore\Settings\BusinessType;
use BusinessBuilderCore\Settings\SiteSettings;
use BusinessBuilderCore\Settings\Language;

use BusinessBuilderCore\Builder\SectionManager;
use BusinessBuilderCore\Builder\SectionRegistry;
use BusinessBuilderCore\Builder\SectionRenderer;
use BusinessBuilderCore\Builder\PageManager;
use BusinessBuilderCore\REST\PageBuilderAjax;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    protected Loader $loader;

    protected ServiceProvider $service_provider;

    protected SiteSettingsPage $site_settings_page;

    protected PageAdmin $page_admin;

    protected PageBuilderAjax $page_builder_ajax;

    public function __construct() {

        /**
         * Core Loader.
         */
        $this->loader = new Loader();

        /**
         * Service Provider.
         */
        $this->service_provider = new ServiceProvider();

        $this->service_provider->register();

        /**
         * Admin Services.
         */
        $this->site_settings_page = new SiteSettingsPage(
            $this
        );

        $this->page_admin =
            $this->service_provider->get_page_admin();

        $this->page_builder_ajax =
             $this->service_provider->get_page_builder_ajax();

        /**
         * Register Hooks.
         */
        $this->define_admin_hooks();

        $this->define_public_hooks();
    }

    /**
     * Register Admin Hooks.
     */
    protected function define_admin_hooks(): void {

        /**
         * Site Settings.
         */
        $this->site_settings_page->register();

        /**
         * Page Builder Admin.
         */
        $this->page_admin->register();

        $this->page_builder_ajax->register();
    }

    /**
     * Register Public Hooks.
     */
    protected function define_public_hooks(): void {

        add_action(
            'wp_enqueue_scripts',
            array(
                $this,
                'enqueue_builder_frontend_assets',
            )
        );

        add_filter(
            'the_content',
            array(
                $this,
                'render_builder_content',
            )
        );
    }

    /**
     * Enqueue builder assets on builder pages and preview mode.
     */
    public function enqueue_builder_frontend_assets(): void {

        if ( ! is_singular( 'page' ) ) {
            return;
        }

        $page_id = get_queried_object_id();

        if ( ! $page_id ) {
            return;
        }

        if ( ! $this->service_provider->get_page_manager()->is_builder_page( $page_id ) ) {
            return;
        }

        /*
         * Front-end section styles (dedicated stylesheet, not the
         * admin builder UI). Each pack section has its own file
         * aggregated through assets/css/frontend.css.
         */
        wp_enqueue_style(
            'bb-frontend',
            BB_CORE_URL . 'assets/css/frontend.css',
            array(),
            BB_CORE_VERSION
        );

        wp_enqueue_script(
            'bb-page-admin',
            BB_CORE_URL . 'assets/js/page-admin.js',
            array( 'jquery' ),
            BB_CORE_VERSION,
            true
        );

        wp_localize_script(
            'bb-page-admin',
            'BBPageAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'bb_page_builder_ajax' ),
            )
        );

        /*
         * Front-end payment billing behaviour: reveals the billing form
         * only for gateways that require it (e.g. Paymob) and validates it
         * client-side. Vanilla JS, no dependencies.
         */
        wp_enqueue_script(
            'bb-payment-billing',
            BB_CORE_URL . 'assets/js/frontend/payment-billing.js',
            array(),
            BB_CORE_VERSION,
            true
        );

        /*
         * Front-end manual payment instructions: reveals the selected
         * manual gateway's REAL configured details (wallet / InstaPay / bank)
         * and validates the transaction reference. Vanilla JS.
         */
        wp_enqueue_script(
            'bb-manual-payment',
            BB_CORE_URL . 'assets/js/frontend/manual-payment.js',
            array(),
            BB_CORE_VERSION,
            true
        );

        /*
         * Front-end in-page receipt modal: shows the receipt on the SAME
         * screen after payment (no navigation to a generic ?bb_ref= page).
         */
        wp_enqueue_style(
            'bb-receipt-modal',
            BB_CORE_URL . 'assets/css/frontend/receipt-modal.css',
            array(),
            BB_CORE_VERSION
        );

        wp_enqueue_script(
            'bb-receipt-modal',
            BB_CORE_URL . 'assets/js/frontend/receipt-modal.js',
            array(),
            BB_CORE_VERSION,
            true
        );

        wp_localize_script(
            'bb-receipt-modal',
            'BBReceipt',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'action'  => 'bb_receipt_inline',
                'nonce'   => wp_create_nonce( 'bb_receipt_inline' ),
                'label'   => __( 'Payment receipt', 'business-builder' ),
                'loading' => __( 'Loading receipt…', 'business-builder' ),
                'error'   => __( 'The receipt could not be loaded.', 'business-builder' ),
            )
        );

        /*
         * Front-end consultation / appointment status lookup behaviour
         * (AJAX to admin-ajax.php with a nonce).
         */
        wp_enqueue_script(
            'bb-status-lookup',
            BB_CORE_URL . 'assets/js/frontend/status-lookup.js',
            array(),
            BB_CORE_VERSION,
            true
        );

        wp_localize_script(
            'bb-status-lookup',
            'BBLookup',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'newSearch' => __( 'New Search', 'business-builder' ),
                'closeLabel' => __( 'Close', 'business-builder' ),
            )
        );
    }

    /**
     * Render the business-builder sections before the page content.
     */
    public function render_builder_content(
        string $content
    ): string {

        if ( is_admin() ) {
            return $content;
        }

        if ( ! is_singular( 'page' ) ) {
            return $content;
        }

        $page_id = get_queried_object_id();

        if ( $page_id <= 0 ) {
            return $content;
        }

        if ( ! $this->service_provider->get_page_manager()->is_builder_page( $page_id ) ) {
            return $content;
        }

        ob_start();

        $this->service_provider->get_section_renderer()->render_page( $page_id );

        $builder_output = ob_get_clean();

        if ( empty( $builder_output ) ) {
            return $content;
        }

        if ( isset( $_GET['bb_preview'] ) && '1' === $_GET['bb_preview'] ) {
            return '<div class="bb-preview-shell">' . $builder_output . '</div>';
        }

        return $builder_output . $content;
    }

    /**
     * Load plugin translations.
     */
    public function load_textdomain(): void {

        load_plugin_textdomain(
            'business-builder',
            false,
            dirname(
                plugin_basename( BB_CORE_FILE )
            ) . '/languages'
        );
    }

    /**
     * Run the plugin.
     */
    public function run(): void {

        /**
         * Translation loading is intentionally deferred
         * until WordPress init.
         */
        add_action(
            'init',
            array(
                $this,
                'load_textdomain',
            )
        );

        /**
         * Boot services.
         */
        $this->service_provider->boot();

        /**
         * Run Loader.
         */
        $this->loader->run();
    }

    /**
     * Get Business Type service.
     */
    public function get_business_type(): BusinessType {

        return $this->service_provider->get_business_type();
    }

    /**
     * Get Site Settings service.
     */
    public function get_site_settings(): SiteSettings {

        return $this->service_provider->get_site_settings();
    }

    /**
     * Get Language service.
     */
    public function get_language(): Language {

        return $this->service_provider->get_language();
    }

    /**
     * Get Section Manager.
     */
    public function get_section_manager(): SectionManager {

        return $this->service_provider->get_section_manager();
    }

    /**
     * Get Section Registry.
     */
    public function get_section_registry(): SectionRegistry {

        return $this->service_provider->get_section_registry();
    }

    /**
     * Get Section Renderer.
     */
    public function get_section_renderer(): SectionRenderer {

        return $this->service_provider->get_section_renderer();
    }

    /**
     * Get Page Manager.
     */
    public function get_page_manager(): PageManager {

        return $this->service_provider->get_page_manager();
    }

    /**
     * Get Page Admin.
     */
    public function get_page_admin(): PageAdmin {

        return $this->service_provider->get_page_admin();
    }

    /**
     * Get Service Provider.
     */
    public function get_service_provider(): ServiceProvider {

        return $this->service_provider;
    }
}