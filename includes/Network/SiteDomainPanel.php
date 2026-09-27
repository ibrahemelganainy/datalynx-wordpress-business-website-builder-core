<?php

namespace BusinessBuilderCore\Network;

use BusinessBuilderCore\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site Admin — Custom Domain panel (Phase 20 §9, §10, §18, §41).
 *
 * The customer-facing side of a custom domain. The customer may:
 *   - enter the domain they bought
 *   - submit/attach it to THEIR OWN site
 *   - see the current platform address
 *   - see the status (pending / active / rejected)
 *   - remove their own domain
 *
 * The customer may NOT:
 *   - affect any other site (the blog id is always server-resolved from the current site)
 *   - change the platform address
 *   - alter network domain infrastructure
 *   - approve their own request
 *
 * The UI shows only this site. Nothing here lists other sites or accepts a blog id.
 */
class SiteDomainPanel {

	/**
	 * Admin-post actions.
	 */
	public const ACTION_REQUEST = 'bb_site_request_domain';
	public const ACTION_REMOVE  = 'bb_site_remove_domain';

	protected Plugin $plugin;

	public function __construct( Plugin $plugin ) {

		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {

		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'register_menu' ) );

		add_action( 'admin_post_' . self::ACTION_REQUEST, array( $this, 'handle_request' ) );
		add_action( 'admin_post_' . self::ACTION_REMOVE, array( $this, 'handle_remove' ) );

		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Add the panel under the existing Business Builder site menu.
	 */
	public function register_menu(): void {

		add_submenu_page(
			'business-builder',
			esc_html__( 'Custom Domain', 'business-builder' ),
			esc_html__( 'Custom Domain', 'business-builder' ),
			'manage_options',
			'business-builder-domain',
			array( $this, 'render' )
		);
	}

	/**
	 * Render the customer-facing panel (§18).
	 */
	public function render(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'business-builder' ),
				esc_html__( 'Forbidden', 'business-builder' ),
				array( 'response' => 403 )
			);
		}

		/*
		 * The target site is ALWAYS the current site — never a posted value.
		 */
		$blog_id = get_current_blog_id();

		$platform_address = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		$entry  = DomainRegistry::find_for_blog( $blog_id );
		$domain = $entry ? (string) $entry['domain'] : '';
		$status = $entry ? (string) ( $entry['status'] ?? DomainRegistry::STATUS_PENDING ) : '';

		$status_labels = array(
			DomainRegistry::STATUS_PENDING  => __( 'Pending review', 'business-builder' ),
			DomainRegistry::STATUS_ACTIVE   => __( 'Approved', 'business-builder' ),
			DomainRegistry::STATUS_REJECTED => __( 'Rejected', 'business-builder' ),
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Custom Domain', 'business-builder' ); ?></h1>

			<?php if ( isset( $_GET['bb_domain_result'] ) ) : ?>
				<?php $this->render_result( sanitize_key( (string) wp_unslash( $_GET['bb_domain_result'] ) ) ); ?>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Current platform address', 'business-builder' ); ?></th>
					<td>
						<code><?php echo esc_html( (string) $platform_address ); ?></code>
						<p class="description">
							<?php esc_html_e( 'This is the address the platform provides. It always stays available.', 'business-builder' ); ?>
						</p>
					</td>
				</tr>

				<?php if ( '' !== $domain ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Custom domain', 'business-builder' ); ?></th>
						<td>
							<strong><?php echo esc_html( $domain ); ?></strong>
							<p class="description">
								<?php
								printf(
									/* translators: %s: domain status label. */
									esc_html__( 'Status: %s', 'business-builder' ),
									esc_html( $status_labels[ $status ] ?? $status )
								);
								?>
							</p>
						</td>
					</tr>
				<?php endif; ?>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REQUEST ); ?>">
				<?php wp_nonce_field( self::ACTION_REQUEST ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="bb_custom_domain"><?php esc_html_e( 'Custom domain', 'business-builder' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="bb_custom_domain" name="domain" placeholder="example.com" value="<?php echo esc_attr( $domain ); ?>">
							<p class="description">
								<?php esc_html_e( 'Enter the domain you purchased. Your network administrator reviews and approves it.', 'business-builder' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( '' !== $domain ? esc_html__( 'Update Domain', 'business-builder' ) : esc_html__( 'Connect Domain', 'business-builder' ) ); ?>
			</form>

			<?php if ( '' !== $domain ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REMOVE ); ?>">
					<?php wp_nonce_field( self::ACTION_REMOVE ); ?>
					<button type="submit" class="button button-link-delete">
						<?php esc_html_e( 'Remove custom domain', 'business-builder' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the result of a submitted action.
	 *
	 * @param string $code Result code.
	 */
	protected function render_result( string $code ): void {

		$messages = array(
			'ok'          => array( 'success', __( 'Your custom domain request was saved.', 'business-builder' ) ),
			'removed'     => array( 'success', __( 'Your custom domain was removed.', 'business-builder' ) ),
			'invalid'     => array( 'error', __( 'That does not look like a valid domain.', 'business-builder' ) ),
			'reserved'    => array( 'error', __( 'That domain is reserved by the platform.', 'business-builder' ) ),
			'platform'    => array( 'error', __( 'That domain belongs to the platform itself.', 'business-builder' ) ),
			'duplicate'   => array( 'error', __( 'That domain is already connected to another website.', 'business-builder' ) ),
			'forbidden'   => array( 'error', __( 'You are not allowed to change this site\'s domain.', 'business-builder' ) ),
			'failed'      => array( 'error', __( 'The domain could not be removed.', 'business-builder' ) ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $messages[ $code ][0] ),
			esc_html( $messages[ $code ][1] )
		);
	}

	/**
	 * Handle a customer's domain request.
	 *
	 * The blog id is taken from the CURRENT SITE, so a customer can only ever affect their
	 * own site. Ownership collisions are rejected by the registry (§41).
	 */
	public function handle_request(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to perform this action.', 'business-builder' ),
				esc_html__( 'Forbidden', 'business-builder' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::ACTION_REQUEST );

		$blog_id = get_current_blog_id();

		$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';

		$result = DomainRegistry::request( $blog_id, $domain );

		$this->redirect( $result['ok'] ? 'ok' : $result['error'] );
	}

	/**
	 * Handle removing the customer's own domain.
	 */
	public function handle_remove(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to perform this action.', 'business-builder' ),
				esc_html__( 'Forbidden', 'business-builder' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::ACTION_REMOVE );

		$blog_id = get_current_blog_id();

		$ok = DomainRegistry::remove_for_blog( $blog_id );

		$this->redirect( $ok ? 'removed' : 'failed' );
	}

	/**
	 * Redirect back to the panel.
	 *
	 * @param string $code Result code.
	 */
	protected function redirect( string $code ): void {

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'business-builder-domain',
					'bb_domain_result' => sanitize_key( $code ),
				),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 * Notices on other screens are not used here; the panel renders its own result inline.
	 */
	public function notice(): void {
		// Intentionally empty: results render inline on the panel.
	}
}