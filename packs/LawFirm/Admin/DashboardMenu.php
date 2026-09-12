<?php

namespace BusinessBuilderCore\Packs\LawFirm\Admin;

if ( ! defined( 'ABSPATH' )) {
    exit;
}

/**
 * Law Firm top-level admin menu.
 *
 * Owns the single source of truth for the LawFirm admin menu slug and
 * builds an independent, enterprise-style top-level menu ("Law Firm")
 * that is fully separate from the Business Builder menu (spec: Part 1).
 *
 * The existing LawFirm screens (consultations, appointments, lawyers,
 * legal services, practice areas) are NOT duplicated: their post types
 * / taxonomies are relocated under this menu by pointing their
 * show_in_menu at the dashboard slug (see LawFirmPack / the CPT args).
 * This class only adds the dashboard root, its own first submenu entry,
 * the Payment Settings screen and the Notifications screen.
 *
 * Multisite: an admin menu is per-site by nature (menus are built for
 * the current blog), so this is correctly site-scoped.
 */
class DashboardMenu {

    /**
     * Top-level menu slug.
     */
    public const MENU_SLUG = 'bb-law-firm';

    /**
     * Capability required to view LawFirm admin screens.
     */
    public const CAPABILITY = 'manage_options';

    /**
     * Optional submenu renderers.
     *
     * @var array<string, callable>
     */
    protected array $screens = array();

    /**
     * Constructor.
     */
    public function __construct() {
    }

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action( 'admin_menu', array( $this, 'register_menu' ), 9 );
    }

    /**
     * Attach a renderer for a submenu slug.
     *
     * @param string   $slug     Submenu slug.
     * @param callable $renderer Render callback.
     */
    public function add_screen( string $slug, callable $renderer ): void {

        $this->screens[ $slug ] = $renderer;
    }

    /**
     * Register the top-level menu and its own submenus.
     */
    public function register_menu(): void {

        /*
         * The root page reuses the dashboard renderer so clicking the
         * top-level item lands on the dashboard itself.
         */
        add_menu_page(
            __( 'Law Firm Dashboard', 'business-builder' ),
            __( 'Law Firm', 'business-builder' ),
            self::CAPABILITY,
            self::MENU_SLUG,
            array( $this, 'render_dashboard' ),
            'dashicons-businessman',
            3
        );

        /*
         * WordPress would otherwise create a duplicate submenu entry
         * labelled the same as the top-level item. Re-point the first
         * submenu entry to the dashboard with a clearer label.
         */
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Law Firm Dashboard', 'business-builder' ),
            __( 'Dashboard', 'business-builder' ),
            self::CAPABILITY,
            self::MENU_SLUG,
            array( $this, 'render_dashboard' )
        );

        /*
         * Payment Settings + Notifications/Activity live under this menu.
         * Other registration owners (PaymentSettingsAdmin, Notifications)
         * enqueue themselves here through the screens registry to avoid
         * duplicate menu definitions.
         */
        foreach ( $this->screens as $slug => $renderer ) {

            add_submenu_page(
                self::MENU_SLUG,
                $this->screen_titles()[ $slug ] ?? $slug,
                $this->screen_titles()[ $slug ] ?? $slug,
                self::CAPABILITY,
                $slug,
                $renderer
            );
        }
    }

    /**
     * Titles for the registered auxiliary screens.
     *
     * @return array<string, string>
     */
    protected function screen_titles(): array {

        return array(
            'bb-law-firm-payments'      => __( 'Payment Settings', 'business-builder' ),
            'bb-law-firm-notifications' => __( 'Notifications & Activity', 'business-builder' ),
        );
    }

    /**
     * Render the dashboard (delegated to DashboardAdmin).
     */
    public function render_dashboard(): void {

        $allowed = current_user_can( self::CAPABILITY );

        if ( ! $allowed ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'business-builder' ) );
        }

        /**
         * Render the dashboard view.
         *
         * DashboardAdmin registers the real renderer; this indirection
         * keeps menu slug ownership in one class.
         */
        do_action( 'bb_law_firm_render_dashboard' );
    }

    /**
     * Return the menu slug for other components to attach to.
     *
     * @return string
     */
    public static function slug(): string {

        return self::MENU_SLUG;
    }

    /**
     * Capability shared by all LawFirm admin screens.
     *
     * @return string
     */
    public static function capability(): string {

        return self::CAPABILITY;
    }
}
