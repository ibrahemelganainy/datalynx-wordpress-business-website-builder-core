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

    /**
     * Network control plane (Phase 20).
     *
     * Only instantiated meaningfully on Multisite; both classes no-op otherwise, so the
     * plugin stays a singleton-network / non-multisite safe.
     */
    protected \BusinessBuilderCore\Network\NetworkProvisioning $network_provisioning;

    protected \BusinessBuilderCore\Network\SiteDomainPanel $site_domain_panel;

    /**
     * Site design catalogue surface (Phase 21).
     *
     * The catalogue is the registry-driven, business-agnostic discovery layer; the page is the
     * site-admin UI that consumes it; the preview is a read-only front-end preview.
     */
    protected \BusinessBuilderCore\Design\DesignPage $design_page;

    protected \BusinessBuilderCore\Design\DesignPreview $design_preview;

    /**
     * Phase 21 design-identity layer.
     *
     * `DesignSchema` EXTENDS the Theme's own customization schema (through its documented
     * `bb_theme_design_schema` filter) with the gradients / density / shape / component /
     * shell-behaviour / motion controls a Design needs to be a complete visual identity
     * rather than a colour preset. It adds no second schema, no second storage and no token
     * outside `--bb-*`.
     *
     * `DesignShell` lets a Design select the header / navigation / footer variant it was
     * authored for, resolved through the EXISTING Phase 15 shell resolver.
     */
    protected \BusinessBuilderCore\Design\DesignSchema $design_schema;

    protected \BusinessBuilderCore\Design\DesignShell $design_shell;

    protected \BusinessBuilderCore\Design\DesignAssets $design_assets;

    /**
     * Loads ONLY the remote font families the active design actually uses (§25, §59), so a
     * design's typographic identity reaches the browser without shipping every font globally.
     */
    protected \BusinessBuilderCore\Design\DesignFonts $design_fonts;

    /**
     * Teaches the Theme's font-choice filter the curated typefaces, so a font the Studio offers
     * is a font the Theme's OWN sanitizer will accept. No new validator is introduced.
     */
    protected \BusinessBuilderCore\Design\DesignTypography $design_typography;

    /**
     * Phase 22 section-level design layer.
     *
     * `SectionStyleSchema` is the SITE-WIDE, PER-SECTION-TYPE visual customization layer. It
     * discovers the registered sections from the EXISTING `SectionRegistry` (so a pack's sections
     * appear with no change here) and emits one scoped `--bb-*` block per customized section. It
     * owns no business knowledge and no second token namespace.
     */
    protected \BusinessBuilderCore\Design\SectionStyleSchema $section_styles;

    /**
     * Phase 22 background/glass shell flags: publishes the class and attributes the new
     * background and glass rules key off, without the Theme learning anything new.
     */
    protected \BusinessBuilderCore\Design\DesignShellState $design_shell_state;

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
         * Network control plane (Phase 20). These consume the EXISTING registries —
         * BusinessType, PackManager and the theme preset registry — and never hardcode a
         * business type or a design.
         */
        $this->network_provisioning = new \BusinessBuilderCore\Network\NetworkProvisioning( $this );

        $this->site_domain_panel = new \BusinessBuilderCore\Network\SiteDomainPanel( $this );

        /**
         * Design catalogue surface (Phase 21).
         */
        $this->design_page = new \BusinessBuilderCore\Design\DesignPage( $this );

        $this->design_preview = new \BusinessBuilderCore\Design\DesignPreview();

        /*
         * Phase 21 design-identity layer: schema extension + design-driven shell selection.
         * Both EXTEND the existing Phase 14 / Phase 15 architecture through its own filters.
         */
        $this->design_schema = new \BusinessBuilderCore\Design\DesignSchema();

        $this->design_shell = new \BusinessBuilderCore\Design\DesignShell();

        $this->design_assets = new \BusinessBuilderCore\Design\DesignAssets();

        $this->design_fonts = new \BusinessBuilderCore\Design\DesignFonts();

        $this->design_typography = new \BusinessBuilderCore\Design\DesignTypography();

        /**
         * Phase 22 section-level design layer + shell state.
         */
        $this->section_styles = new \BusinessBuilderCore\Design\SectionStyleSchema(
            $this->service_provider->get_section_registry()
        );

        $this->design_shell_state = new \BusinessBuilderCore\Design\DesignShellState();

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

        /**
         * Network Administration + the site-admin Custom Domain panel (Phase 20).
         * Both register themselves only on Multisite.
         */
        $this->network_provisioning->register();

        $this->site_domain_panel->register();

        /**
         * Site design catalogue (Phase 21). Registered on every site (not only Multisite),
         * because a single-site installation also has a design to choose.
         */
        $this->design_page->register();

        $this->design_preview->register();

        $this->design_schema->register();

        $this->design_shell->register();

        $this->design_assets->register();

        $this->design_fonts->register();

        $this->design_typography->register();

        /*
         * Phase 22: the section-scoped style block and the shell state flags. Both are
         * registered on every site, because a section's visual treatment is site-wide
         * presentation rather than an admin-only feature.
         */
        $this->section_styles->register();

        $this->design_shell_state->register();
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

        /*
         * Theme integration (Phase 9): tell the canonical Business Builder
         * Theme when the current page is rendered by the Page Builder, so the
         * Theme shell can avoid printing a duplicate page title. This is the
         * smallest possible compatibility hook; it changes no plugin behaviour.
         */
        add_filter(
            'bb_theme_is_builder_page',
            array(
                $this,
                'filter_theme_is_builder_page',
            ),
            10,
            2
        );
    }

    /**
     * Report whether a page is rendered by the Page Builder (theme filter).
     *
     * @param bool $is_builder Incoming value.
     * @param int  $post_id    Post id (0 = current).
     * @return bool
     */
    public function filter_theme_is_builder_page( $is_builder = false, $post_id = 0 ): bool {

        $post_id = absint( $post_id );

        if ( $post_id <= 0 ) {
            $post_id = (int) get_queried_object_id();
        }

        if ( $post_id <= 0 ) {
            return (bool) $is_builder;
        }

        return (bool) $this->service_provider->get_page_manager()->is_builder_page( $post_id );
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
         *
         * PHASE 23 §2/§34 — THE DEPENDENCY IS LOAD-BEARING.
         * -------------------------------------------------
         * `design-tokens.css` declares three ALIASES on `:root`
         * (`--bb-border-width`, `--bb-container-padding`,
         * `--bb-section-spacing`) that bridge the Studio's controls onto the
         * token names the THEME consumes.
         *
         * Those aliases must resolve AFTER the Theme has published the
         * customer's saved overrides, which it emits as an inline `:root` block
         * attached to the `bb-theme-tokens` handle
         * (`themes/business-builder/inc/enqueue.php:100`).
         *
         * This was previously enqueued with NO dependencies, so the order was
         * whatever the enqueue queue happened to produce. When the plugin's
         * sheet landed first, the Theme's later `:root` override won and the
         * Studio's slider had no effect — the exact §34 defect. Declaring the
         * Theme handle as a dependency makes the order deterministic and
         * one-directional, without editing the Theme.
         *
         * WordPress IGNORES a dependency whose handle is not registered, so
         * this is a no-op on Astra and any non-builder site.
         */
        wp_enqueue_style(
            'bb-frontend',
            BB_CORE_URL . 'assets/css/frontend.css',
            array( 'bb-theme-tokens' ),
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