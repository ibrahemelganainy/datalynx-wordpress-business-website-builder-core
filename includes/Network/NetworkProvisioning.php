<?php

namespace BusinessBuilderCore\Network;

use BusinessBuilderCore\Core\Plugin;
use BusinessBuilderCore\Settings\BusinessType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Network Administration (Phase 20 §10, §11, §14, §20, §23, §29, §34).
 *
 * The Network control plane. It lives ENTIRELY in the network admin
 * (`network_admin_menu`) and consumes the EXISTING architecture:
 *
 *   BusinessType registry  → the business types on offer
 *   PackManager            → pack resolution (no hardcoded branches)
 *   Theme preset registry  → the assignable designs
 *   wpmu_create_blog()     → real site creation
 *   DomainRegistry         → custom-domain authority
 *
 * It knows NOTHING about sections, layouts, components or pack business data (§25, §26, §27).
 * It is not a second Pack architecture (§4) and not a CRM (§23).
 *
 * All strings use WordPress i18n so the UI follows the active admin language (§34).
 */
class NetworkProvisioning {

	/**
	 * Network menu slug.
	 */
	public const MENU_SLUG = 'business-builder-network';

	/**
	 * Provisioning screen slug.
	 */
	public const PROVISION_SLUG = 'business-builder-provision';

	/**
	 * Domains screen slug.
	 */
	public const DOMAINS_SLUG = 'business-builder-domains';

	/**
	 * Admin-post / nonce actions.
	 */
	public const ACTION_PROVISION = 'bb_network_provision_site';
	public const ACTION_ASSIGN_TYPE = 'bb_network_assign_business_type';
	public const ACTION_DOMAIN_STATUS = 'bb_network_domain_status';
	public const ACTION_DOMAIN_REMOVE = 'bb_network_domain_remove';

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

		add_action( 'network_admin_menu', array( $this, 'register_menu' ) );

		add_action( 'admin_post_' . self::ACTION_PROVISION, array( $this, 'handle_provision' ) );
		add_action( 'admin_post_' . self::ACTION_ASSIGN_TYPE, array( $this, 'handle_assign_type' ) );
		add_action( 'admin_post_' . self::ACTION_DOMAIN_STATUS, array( $this, 'handle_domain_status' ) );
		add_action( 'admin_post_' . self::ACTION_DOMAIN_REMOVE, array( $this, 'handle_domain_remove' ) );

		add_action( 'admin_notices', array( $this, 'provision_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'provision_notice' ) );
	}

	/**
	 * Register the network admin menu (§23).
	 */
	public function register_menu(): void {

		$capability = BusinessTypeGuard::required_capability();

		add_menu_page(
			esc_html__( 'Business Builder Network', 'business-builder' ),
			esc_html__( 'Business Sites', 'business-builder' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_sites' ),
			'dashicons-networking',
			30
		);

		add_submenu_page(
			self::MENU_SLUG,
			esc_html__( 'Business Sites', 'business-builder' ),
			esc_html__( 'All Business Sites', 'business-builder' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_sites' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			esc_html__( 'Add New Business Site', 'business-builder' ),
			esc_html__( 'Add New Site', 'business-builder' ),
			$capability,
			self::PROVISION_SLUG,
			array( $this, 'render_provision' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			esc_html__( 'Custom Domains', 'business-builder' ),
			esc_html__( 'Custom Domains', 'business-builder' ),
			$capability,
			self::DOMAINS_SLUG,
			array( $this, 'render_domains' )
		);
	}

	/**
	 * Guard for every network screen.
	 */
	protected function require_network_authority(): void {

		if ( ! BusinessTypeGuard::current_user_can_assign() ) {
			wp_die(
				esc_html__( 'You do not have permission to manage the network.', 'business-builder' ),
				esc_html__( 'Forbidden', 'business-builder' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Render the provisioning form (§14, §15).
	 */
	public function render_provision(): void {

		$this->require_network_authority();

		$business_type = $this->plugin->get_business_type();
		$pack_manager  = $this->plugin->get_service_provider()->get_pack_manager();

		$types   = SiteProvisioner::available_business_types( $business_type );
		$presets = SiteProvisioner::available_presets();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Add New Business Site', 'business-builder' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Create a WordPress site on this network and assign its business type. The business type determines the site\'s capabilities and is controlled by the network, not by the site administrator.', 'business-builder' ); ?>
			</p>

			<?php $this->render_errors(); ?>

			<form method="post" action="<?php echo esc_url( network_admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_PROVISION ); ?>">
				<?php wp_nonce_field( self::ACTION_PROVISION ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bb_site_title"><?php esc_html_e( 'Site Name', 'business-builder' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="bb_site_title" name="title" required>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bb_site_address"><?php esc_html_e( 'Site Address', 'business-builder' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="bb_site_address" name="address" required>
							<p class="description">
								<?php
								printf(
									/* translators: %s: platform base domain. */
									esc_html__( 'The subdomain only. The site will be created at <code>%s</code>.', 'business-builder' ),
									esc_html( '&lt;address&gt;.' . DomainRegistry::platform_base_domain() )
								);
								?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bb_business_type"><?php esc_html_e( 'Business Type', 'business-builder' ); ?></label></th>
						<td>
							<select id="bb_business_type" name="business_type" required>
								<option value=""><?php esc_html_e( 'Select a business type', 'business-builder' ); ?></option>
								<?php foreach ( $types as $slug => $type ) : ?>
									<?php
									/* Registry-driven: the pack availability is resolved, never hardcoded. */
									$pack = SiteProvisioner::resolve_pack( $pack_manager, (string) $slug );
									?>
									<option value="<?php echo esc_attr( (string) $slug ); ?>">
										<?php
										echo esc_html(
											(string) ( $type['label'] ?? $slug )
											. ( $pack['available'] ? '' : ' — ' . __( 'pack not installed', 'business-builder' ) )
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Business types and their packs are discovered from the platform registry. A future pack appears here automatically once it registers itself.', 'business-builder' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bb_preset"><?php esc_html_e( 'Initial Design', 'business-builder' ); ?></label></th>
						<td>
							<select id="bb_preset" name="preset">
								<option value=""><?php esc_html_e( 'Use the default design', 'business-builder' ); ?></option>
								<?php foreach ( $presets as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Designs come from the theme preset registry. The site administrator can change or customize the design later.', 'business-builder' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="bb_custom_domain"><?php esc_html_e( 'Custom Domain', 'business-builder' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="bb_custom_domain" name="custom_domain" placeholder="example.com">
							<p class="description">
								<?php esc_html_e( 'Optional. Recorded as a pending request for the new site; it does not change the platform address.', 'business-builder' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( esc_html__( 'Create Site', 'business-builder' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the network site list (§23).
	 */
	public function render_sites(): void {

		$this->require_network_authority();

		$business_type = $this->plugin->get_business_type();
		$pack_manager  = $this->plugin->get_service_provider()->get_pack_manager();

		$sites = get_sites( array( 'number' => 500 ) );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Business Sites', 'business-builder' ); ?></h1>
			<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=' . self::PROVISION_SLUG ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'business-builder' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php $this->render_errors(); ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Site', 'business-builder' ); ?></th>
						<th><?php esc_html_e( 'Business Type', 'business-builder' ); ?></th>
						<th><?php esc_html_e( 'Pack', 'business-builder' ); ?></th>
						<th><?php esc_html_e( 'Platform URL', 'business-builder' ); ?></th>
						<th><?php esc_html_e( 'Custom Domain', 'business-builder' ); ?></th>
						<th><?php esc_html_e( 'Design', 'business-builder' ); ?></th>
						<th><?php esc_html_e( 'Status', 'business-builder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sites as $site ) : ?>
						<?php
						$blog_id = (int) $site->blog_id;

						$type_slug = BusinessTypeGuard::read( $business_type, $blog_id );
						$pack      = '' === $type_slug ? array( 'slug' => '', 'class' => '', 'available' => false ) : SiteProvisioner::resolve_pack( $pack_manager, $type_slug );
						$domain    = DomainRegistry::find_for_blog( $blog_id );
						$preset    = self::site_preset( $blog_id );
						$outcome   = SiteProvisioner::outcome_for( $blog_id );

						$status = $site->deleted ? __( 'deleted', 'business-builder' )
							: ( $site->archived ? __( 'archived', 'business-builder' )
							: ( $site->spam ? __( 'spam', 'business-builder' ) : __( 'active', 'business-builder' ) ) );

						if ( $outcome && 'needs_attention' === ( $outcome['status'] ?? '' ) ) {
							$status .= ' — ' . __( 'needs attention', 'business-builder' );
						}
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( get_blog_option( $blog_id, 'blogname' ) ); ?></strong>
								<br><span class="description">#<?php echo esc_html( (string) $blog_id ); ?></span>
							</td>
							<td>
								<?php if ( '' === $type_slug ) : ?>
									<span class="description"><?php esc_html_e( 'not assigned', 'business-builder' ); ?></span>
								<?php else : ?>
									<?php echo esc_html( (string) ( $business_type->get( $type_slug )['label'] ?? $type_slug ) ); ?>
								<?php endif; ?>

								<?php if ( BusinessTypeGuard::current_user_can_assign() && '' !== $type_slug ) : ?>
									<?php $this->render_type_form( $blog_id, $type_slug, $business_type->get_all(), $pack_manager ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $pack['available'] ) : ?>
									<?php echo esc_html( $pack['slug'] ); ?>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'missing', 'business-builder' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( get_site_url( $blog_id ) ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $site->domain . $site->path ); ?>
								</a>
							</td>
							<td>
								<?php if ( $domain ) : ?>
									<?php echo esc_html( (string) $domain['domain'] ); ?>
									<br><span class="description"><?php echo esc_html( (string) ( $domain['status'] ?? '' ) ); ?></span>
								<?php else : ?>
									<span class="description">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo '' !== $preset ? esc_html( $preset ) : '<span class="description">' . esc_html__( 'default', 'business-builder' ) . '</span>'; ?>
							</td>
							<td><?php echo esc_html( $status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * The Business Type assignment form for one site (network authority only).
	 *
	 * @param int                         $blog_id       Site.
	 * @param string                      $current       Current slug.
	 * @param array<string, array>        $types         Registered types.
	 * @param \BusinessBuilderCore\Core\PackManager $pack_manager Pack registry.
	 */
	protected function render_type_form( int $blog_id, string $current, array $types, $pack_manager ): void {

		?>
		<form method="post" action="<?php echo esc_url( network_admin_url( 'admin-post.php' ) ); ?>" style="margin-top:6px;">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_ASSIGN_TYPE ); ?>">
			<input type="hidden" name="blog_id" value="<?php echo esc_attr( (string) $blog_id ); ?>">
			<?php wp_nonce_field( self::ACTION_ASSIGN_TYPE . '_' . $blog_id ); ?>

			<select name="business_type">
				<?php foreach ( $types as $slug => $type ) : ?>
					<?php $pack = SiteProvisioner::resolve_pack( $pack_manager, (string) $slug ); ?>
					<option value="<?php echo esc_attr( (string) $slug ); ?>" <?php selected( $current, (string) $slug ); ?>>
						<?php echo esc_html( (string) ( $type['label'] ?? $slug ) . ( $pack['available'] ? '' : ' (no pack)' ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="button button-small"><?php esc_html_e( 'Change', 'business-builder' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render the custom-domain management screen (§11).
	 */
	public function render_domains(): void {

		$this->require_network_authority();

		$domains = DomainRegistry::all();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Custom Domains', 'business-builder' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Customer-requested domains. The network approves or rejects each request; the platform address of a site is never changed by a custom domain.', 'business-builder' ); ?>
			</p>

			<?php $this->render_errors(); ?>

			<?php if ( empty( $domains ) ) : ?>
				<p><?php esc_html_e( 'No custom domains have been requested yet.', 'business-builder' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Domain', 'business-builder' ); ?></th>
							<th><?php esc_html_e( 'Site', 'business-builder' ); ?></th>
							<th><?php esc_html_e( 'Status', 'business-builder' ); ?></th>
							<th><?php esc_html_e( 'Requested', 'business-builder' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'business-builder' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $domains as $domain => $entry ) : ?>
							<?php $blog_id = (int) ( $entry['blog_id'] ?? 0 ); ?>
							<tr>
								<td><strong><?php echo esc_html( (string) $domain ); ?></strong></td>
								<td>
									<?php if ( $blog_id > 0 && get_blog_details( $blog_id ) ) : ?>
										<?php echo esc_html( get_blog_option( $blog_id, 'blogname' ) ); ?>
										<br><span class="description">#<?php echo esc_html( (string) $blog_id ); ?></span>
									<?php else : ?>
										<span class="description"><?php esc_html_e( 'orphaned', 'business-builder' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( (string) ( $entry['status'] ?? DomainRegistry::STATUS_PENDING ) ); ?></td>
								<td>
									<?php
									echo isset( $entry['requested_at'] )
										? esc_html( gmdate( 'Y-m-d H:i', (int) $entry['requested_at'] ) )
										: '—';
									?>
								</td>
								<td><?php $this->render_domain_actions( (string) $domain ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Approve / reject / remove actions for one domain (network authority).
	 *
	 * @param string $domain Domain.
	 */
	protected function render_domain_actions( string $domain ): void {

		?>
		<form method="post" action="<?php echo esc_url( network_admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:4px;flex-wrap:wrap;">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_DOMAIN_STATUS ); ?>">
			<input type="hidden" name="domain" value="<?php echo esc_attr( $domain ); ?>">
			<?php wp_nonce_field( self::ACTION_DOMAIN_STATUS . '_' . $domain ); ?>

			<button type="submit" class="button button-small" name="status" value="<?php echo esc_attr( DomainRegistry::STATUS_ACTIVE ); ?>">
				<?php esc_html_e( 'Approve', 'business-builder' ); ?>
			</button>
			<button type="submit" class="button button-small" name="status" value="<?php echo esc_attr( DomainRegistry::STATUS_REJECTED ); ?>">
				<?php esc_html_e( 'Reject', 'business-builder' ); ?>
			</button>
			<button type="submit" class="button button-small" name="status" value="<?php echo esc_attr( DomainRegistry::STATUS_PENDING ); ?>">
				<?php esc_html_e( 'Reset', 'business-builder' ); ?>
			</button>
		</form>

		<form method="post" action="<?php echo esc_url( network_admin_url( 'admin-post.php' ) ); ?>" style="margin-top:4px;">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_DOMAIN_REMOVE ); ?>">
			<input type="hidden" name="domain" value="<?php echo esc_attr( $domain ); ?>">
			<?php wp_nonce_field( self::ACTION_DOMAIN_REMOVE . '_' . $domain ); ?>
			<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Remove', 'business-builder' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Current theme preset for a site (read-only display).
	 *
	 * @param int $blog_id Site.
	 * @return string
	 */
	protected static function site_preset( int $blog_id ): string {

		$mods = get_blog_option( $blog_id, 'theme_mods_business-builder' );

		if ( is_array( $mods ) && ! empty( $mods['bb_theme_preset'] ) ) {
			return (string) $mods['bb_theme_preset'];
		}

		return '';
	}

	/**
	 * Handle site provisioning (§14, §15, §20).
	 */
	public function handle_provision(): void {

		if ( ! SiteProvisioner::current_user_can_provision() ) {
			wp_die(
				esc_html__( 'You do not have permission to create sites on this network.', 'business-builder' ),
				esc_html__( 'Forbidden', 'business-builder' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::ACTION_PROVISION );

		$result = SiteProvisioner::provision(
			array(
				'title'         => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'address'       => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
				'business_type' => isset( $_POST['business_type'] ) ? sanitize_key( wp_unslash( $_POST['business_type'] ) ) : '',
				'preset'        => isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : '',
				'custom_domain' => isset( $_POST['custom_domain'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_domain'] ) ) : '',
			),
			$this->plugin->get_business_type(),
			$this->plugin->get_service_provider()->get_pack_manager()
		);

		$args = array( 'page' => self::PROVISION_SLUG );

		if ( $result['ok'] ) {
			$args['bb_provisioned'] = (string) $result['blog_id'];
		} else {
			$args['bb_error'] = rawurlencode( (string) wp_json_encode( $result['errors'] ) );
		}

		wp_safe_redirect( network_admin_url( 'admin.php?' . http_build_query( $args ) ) );
		exit;
	}

	/**
	 * Handle a Business Type assignment (network authority ONLY — §2, §40).
	 */
	public function handle_assign_type(): void {

		$blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;

		/*
		 * Server-side authorization FIRST: never trust the posted blog id, and never rely on
		 * the UI having hidden the control.
		 */
		if ( ! BusinessTypeGuard::current_user_can_assign_for_site( $blog_id ) ) {
			wp_die(
				esc_html__( 'You do not have permission to change the business type of this site.', 'business-builder' ),
				esc_html__( 'Forbidden', 'business-builder' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::ACTION_ASSIGN_TYPE . '_' . $blog_id );

		$slug = isset( $_POST['business_type'] ) ? sanitize_key( wp_unslash( $_POST['business_type'] ) ) : '';

		$ok = BusinessTypeGuard::assign( $this->plugin->get_business_type(), $slug, $blog_id );

		wp_safe_redirect(
			network_admin_url(
				'admin.php?' . http_build_query(
					array(
						'page'      => self::MENU_SLUG,
						'bb_type'   => $ok ? 'saved' : 'failed',
					)
				)
			)
		);
		exit;
	}

	/**
	 * Handle a domain status change (network authority).
	 */
	public function handle_domain_status(): void {

		if ( ! DomainRegistry::current_user_can_manage() ) {
			wp_die(
				esc_html__( 'You do not have permission to manage domains on this network.', 'business-builder' ),
				esc_html__( 'Forbidden', 'business-builder' ),
				array( 'response' => 403 )
			);
		}

		$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';

		check_admin_referer( self::ACTION_DOMAIN_STATUS . '_' . $domain );

		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		$result = DomainRegistry::set_status( $domain, $status );

		wp_safe_redirect(
			network_admin_url(
				'admin.php?' . http_build_query(
					array(
						'page'      => self::DOMAINS_SLUG,
						'bb_domain' => $result['ok'] ? 'saved' : $result['error'],
					)
				)
			)
		);
		exit;
	}

	/**
	 * Handle a domain removal (network authority).
	 */
	public function handle_domain_remove(): void {

		if ( ! DomainRegistry::current_user_can_manage() ) {
			wp_die(
				esc_html__( 'You do not have permission to manage domains on this network.', 'business-builder' ),
				esc_html__( 'Forbidden', 'business-builder' ),
				array( 'response' => 403 )
			);
		}

		$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';

		check_admin_referer( self::ACTION_DOMAIN_REMOVE . '_' . $domain );

		$ok = DomainRegistry::remove( $domain );

		wp_safe_redirect(
			network_admin_url(
				'admin.php?' . http_build_query(
					array(
						'page'      => self::DOMAINS_SLUG,
						'bb_domain' => $ok ? 'removed' : 'failed',
					)
				)
			)
		);
		exit;
	}

	/**
	 * Render success/error notices.
	 */
	public function provision_notice(): void {

		if ( isset( $_GET['bb_provisioned'] ) ) {

			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'The business site was created successfully.', 'business-builder' )
			);
		}

		if ( isset( $_GET['bb_type'] ) && 'saved' === $_GET['bb_type'] ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'The business type was updated.', 'business-builder' )
			);
		}

		if ( isset( $_GET['bb_domain'] ) && 'removed' === $_GET['bb_domain'] ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'The custom domain was removed.', 'business-builder' )
			);
		}
	}

	/**
	 * Render the errors passed back from a handler.
	 */
	protected function render_errors(): void {

		if ( ! isset( $_GET['bb_error'] ) ) {
			return;
		}

		$raw    = rawurldecode( (string) wp_unslash( $_GET['bb_error'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded then re-mapped below.
		$errors = json_decode( $raw, true );

		if ( ! is_array( $errors ) || empty( $errors ) ) {
			$errors = array( 'general' => 'failed' );
		}

		$labels = array(
			'title'         => __( 'A site name is required.', 'business-builder' ),
			'address'       => __( 'That site address is invalid, reserved or already taken.', 'business-builder' ),
			'business_type' => __( 'That business type is not registered.', 'business-builder' ),
			'pack'          => __( 'No pack is installed for that business type.', 'business-builder' ),
			'preset'        => __( 'That design is not registered by the theme.', 'business-builder' ),
			'custom_domain' => __( 'That custom domain is invalid, reserved, or already in use.', 'business-builder' ),
			'create'        => __( 'WordPress could not create the site.', 'business-builder' ),
			'auth'          => __( 'You are not allowed to perform this action.', 'business-builder' ),
		);

		echo '<div class="notice notice-error"><ul>';

		foreach ( array_keys( $errors ) as $key ) {

			$key = sanitize_key( (string) $key );

			printf(
				'<li>%s</li>',
				esc_html( $labels[ $key ] ?? __( 'The request could not be completed.', 'business-builder' ) )
			);
		}

		echo '</ul></div>';
	}
}