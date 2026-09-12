<?php

namespace BusinessBuilderCore\Packs\LawFirm\Starter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin entry point for the LawFirm starter site.
 *
 * Adds a submenu under the Business Builder menu that lets an admin
 * build the starter pages on demand. Opt-in by design (spec 35):
 * nothing runs automatically, existing pages are never overwritten.
 *
 * Security: capability check (manage_options), nonce verification,
 * and a redirect-with-status pattern. No user input is trusted.
 */
class StarterAdmin {

    /**
     * Page slug.
     */
    private const PAGE_SLUG = 'business-builder-lawfirm-starter';

    /**
     * Admin post action.
     */
    private const ACTION = 'bb_build_lawfirm_starter';

    /**
     * Nonce action.
     */
    private const NONCE_ACTION = 'bb_build_lawfirm_starter';

    /**
     * Starter site service.
     */
    protected StarterSite $starter;

    /**
     * Constructor.
     *
     * @param StarterSite $starter Starter site service.
     */
    public function __construct( StarterSite $starter ) {

        $this->starter = $starter;
    }

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action(
            'admin_menu',
            array( $this, 'register_menu' )
        );

        add_action(
            'admin_post_' . self::ACTION,
            array( $this, 'handle_build' )
        );
    }

    /**
     * Register the admin submenu page.
     */
    public function register_menu(): void {

        add_submenu_page(
            'business-builder',
            __( 'Law Firm Starter', 'business-builder' ),
            __( 'Law Firm Starter', 'business-builder' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    /**
     * the starter admin page.
     */
    public function render_page(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'You do not have permission to access this page.', 'business-builder' )
            );
        }

        $built = $this->starter->is_built();
        $status = isset( $_GET['bb_starter'] )
            ? sanitize_key( wp_unslash( $_GET['bb_starter'] ) )
            : '';

        ?>

        <div class="wrap">

            <h1><?php esc_html_e( 'Law Firm Starter Site', 'business-builder' ); ?></h1>

            <?php if ( 'done' === $status ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Starter pages created successfully.', 'business-builder' ); ?></p>
                </div>
            <?php elseif ( 'skipped' === $status ) : ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php esc_html_e( 'Starter pages already existed, so nothing was created. Existing content was not changed.', 'business-builder' ); ?></p>
                </div>
            <?php endif; ?>

            <p>
                <?php
                esc_html_e(
                    'Create a ready-made set of Law Firm pages (Home, About, Practice Areas, Services, Lawyers, Contact) using the Builder. Existing pages are never overwritten.',
                    'business-builder'
                );
                ?>
            </p>

            <h2><?php esc_html_e( 'Pages to be created', 'business-builder' ); ?></h2>

            <table class="widefat striped" style="max-width:720px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Page', 'business-builder' ); ?></th>
                        <th><?php esc_html_e( 'Slug', 'business-builder' ); ?></th>
                        <th><?php esc_html_e( 'Template', 'business-builder' ); ?></th>
                        <th><?php esc_html_e( 'Sections', 'business-builder' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $this->starter->blueprint() as $slug => $config ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $config['title'] ); ?></strong></td>
                            <td><code><?php echo esc_html( $slug ); ?></code></td>
                            <td><?php echo esc_html( $config['template'] ); ?></td>
                            <td><?php echo esc_html( implode( ', ', $config['sections'] ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <form
                method="post"
                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                style="margin-top:20px;"
            >
                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
                <?php wp_nonce_field( self::NONCE_ACTION ); ?>

                <button type="submit" class="button button-primary">
                    <?php echo esc_html( $built
                        ? __( 'Run Starter Again', 'business-builder' )
                        : __( 'Build Law Firm Starter Site', 'business-builder' )
                    ); ?>
                </button>
            </form>

        </div>

        <?php
    }

    /**
     * Handle the build request.
     */
    public function handle_build(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'You do not have permission to perform this action.', 'business-builder' )
            );
        }

        check_admin_referer( self::NONCE_ACTION );

        $result = $this->starter->build();

        if ( ! empty( $result['created'] ) ) {
            $this->starter->mark_built();
            $status = 'done';
        } else {
            $status = 'skipped';
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'       => self::PAGE_SLUG,
                    'bb_starter' => $status,
                ),
                admin_url( 'admin.php' )
            )
        );

        exit;
    }
}
