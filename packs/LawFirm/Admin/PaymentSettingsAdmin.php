<?php
namespace BusinessBuilderCore\Packs\LawFirm\Admin;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\Currencies;
use BusinessBuilderCore\Core\Payments\PaymentGatewayInterface;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Settings\SiteSettings;
use BusinessBuilderCore\Packs\LawFirm\Admin\DashboardMenu;

defined( 'ABSPATH' ) || exit;

/**
 * Payment management center.
 *
 * A professional, multi-gateway payment settings screen. Unlike the
 * former single-gateway workflow (select one, save, reload, configure),
 * EVERY supported gateway is shown at once as its own card and the
 * administrator can enable and configure several gateways in a single
 * submission.
 *
 * Responsibilities:
 *   - a structured currency selector driven by the Currencies catalogue
 *     (never a free-text currency field);
 *   - one card per gateway: local logo, description, enable/disable
 *     toggle, status badges (Enabled / Disabled / Configured / Needs
 *     setup / Manual / Live-API) and a collapsible panel of exactly the
 *     fields that gateway declares;
 *   - a single "Save Payment Settings" action that persists the currency,
 *     the full list of enabled gateways and the credentials of every
 *     submitted gateway.
 *
 * Architecture reuse (no duplicate systems):
 *   - gateway settings  -> PaymentManager::save_settings()
 *   - enabled list      -> PaymentManager::set_enabled_gateways()
 *   - currency + fee    -> SiteSettings
 * All of these already exist and are per-site on Multisite.
 *
 * Security:
 *   - capability check + nonce on every save;
 *   - secrets are masked in the UI and never rendered in cleartext;
 *     leaving a secret field empty preserves the stored value
 *     (handled inside PaymentManager::save_settings());
 *   - all input sanitized server-side by PaymentManager::save_settings()
 *     and SiteSettings::update().
 */
class PaymentSettingsAdmin {

    /**
     * Submenu slug (owned by DashboardMenu; used for screen detection).
     */
    private const PAGE_SLUG = 'bb-law-firm-payments';

    /**
     * admin-post action for the save.
     */
    private const ACTION = 'bb_save_payment_settings';

    /**
     * Nonce action.
     */
    private const NONCE_ACTION = 'bb_save_payment_settings';

    /**
     * Style handle.
     */
    private const STYLE_HANDLE = 'bb-payment-center';

    /**
     * Script handle.
     */
    private const SCRIPT_HANDLE = 'bb-payment-settings';

    /**
     * Payment manager.
     */
    protected PaymentManager $payments;

    /**
     * Audit log.
     */
    protected AuditLog $audit;

    /**
     * Site settings.
     */
    protected SiteSettings $settings;

    /**
     * Constructor.
     *
     * @param PaymentManager $payments Payments.
     * @param AuditLog       $audit    Audit log.
     * @param SiteSettings   $settings Site settings.
     */
    public function __construct(
        PaymentManager $payments,
        AuditLog $audit,
        SiteSettings $settings
    ) {
        $this->payments = $payments;
        $this->audit    = $audit;
        $this->settings = $settings;
    }

    /**
     * Register hooks.
     */
    public function register(): void {

        /*
         * The menu itself is owned by DashboardMenu; this class only
         * attaches its screen there and handles the save action, so no
         * duplicate menu is ever registered.
         */
        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_save' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Attach this screen to the Law Firm menu.
     *
     * @param DashboardMenu $menu Menu owner.
     */
    public function attach_screen( DashboardMenu $menu ): void {

        $menu->add_screen( self::PAGE_SLUG, array( $this, 'render_page' ) );
    }

    /**
     * Enqueue the payment-center styles and behaviour on our screen only.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( string $hook ): void {

        $page = isset( $_GET['page'] )
            ? sanitize_key( wp_unslash( $_GET['page'] ) )
            : '';

        if ( self::PAGE_SLUG !== $page ) {
            return;
        }

        wp_enqueue_style(
            self::STYLE_HANDLE,
            BB_CORE_URL . 'assets/css/admin/payment-center.css',
            array(),
            BB_CORE_VERSION
        );

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            BB_CORE_URL . 'assets/js/admin/payment-settings.js',
            array(),
            BB_CORE_VERSION,
            true
        );

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'BBPaymentSettings',
            array(
                /* No secrets are ever localized. Only UI strings. */
                'currencyPlaceholder' => __( 'Search currencies…', 'business-builder' ),
                'unsavedNotice'       => __( 'You have unsaved payment settings.', 'business-builder' ),
                'currencies'          => $this->currencies_for_js(),
            )
        );
    }

    /**
     * Currency catalogue for the (optional) combobox enhancement.
     *
     * Only public, non-sensitive display data is exposed.
     *
     * @return array<int, array<string, string>>
     */
    private function currencies_for_js(): array {

        $out = array();

        foreach ( Currencies::all() as $entry ) {
            $out[] = array(
                'code'   => (string) $entry['code'],
                'name'   => (string) $entry['name'],
                'symbol' => (string) $entry['symbol'],
            );
        }

        return $out;
    }

    /**
     * Render the payment management center.
     */
    public function render_page(): void {

        if ( ! current_user_can( DashboardMenu::capability() )) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'business-builder' ) );
        }

        $all_settings = $this->settings->get_all();

        $currency            = (string) $all_settings['consultation_currency'];
        $require_payment     = ! empty( $all_settings['require_consultation_payment'] );
        $require_appt        = ! empty( $all_settings['require_appointment_payment'] );
        $fee                 = (string) $all_settings['consultation_fee'];

        $gateways = $this->payments->gateways();
        $enabled  = $this->payments->enabled_gateways();

        $configured = 0;

        foreach ( $gateways as $gateway ) {

            if ( $gateway->is_configured() ) {
                $configured++;
            }
        }

        $rtl_class = is_rtl() ? ' bb-rtl' : ' bb-ltr';

        $saved = isset( $_GET['updated'] );

        ?>
        <div class="wrap bb-payments<?php echo esc_attr( $rtl_class ); ?>">

            <header class="bb-payments-header">
                <div class="bb-payments-header-copy">
                    <h1><?php esc_html_e( 'Payment Management', 'business-builder' ); ?></h1>
                    <p>
                        <?php esc_html_e( 'Choose your currency, enable one or more gateways, and configure each independently. Only enabled and fully configured gateways are ever offered to customers.', 'business-builder' ); ?>
                    </p>
                </div>
                <div class="bb-payments-header-badge">
                    <span class="dashicons dashicons-shield-alt"></span>
                    <?php esc_html_e( 'Credentials are stored per site and never exposed to visitors.', 'business-builder' ); ?>
                </div>
            </header>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Payment settings saved.', 'business-builder' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="bb-pc-status <?php echo count( $enabled ) > 0 ? 'is-ok' : 'is-warn'; ?>">
                <span class="dashicons <?php echo count( $enabled ) > 0 ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                <?php
                printf(
                    /* translators: 1: enabled gateway count, 2: configured gateway count */
                    esc_html__( '%1$d gateway(s) enabled · %2$d fully configured.', 'business-builder' ),
                    count( $enabled ),
                    (int) $configured
                );
                ?>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bb-payments-form">

                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
                <?php wp_nonce_field( self::NONCE_ACTION ); ?>

                <section class="bb-pc-card">
                    <h2>
                        <span class="dashicons dashicons-money-alt"></span>
                        <?php esc_html_e( 'Currency & Defaults', 'business-builder' ); ?>
                    </h2>

                    <div class="bb-pc-currency-row">

                        <div class="bb-pc-field">
                            <label for="bb_payment_currency_search"><?php esc_html_e( 'Site Currency', 'business-builder' ); ?></label>

                            <?php
                            /*
                             * Progressive enhancement: a plain <select>
                             * works with no JavaScript; the surrounding
                             * combobox wraps it with a searchable input
                             * that JS activates. The <select> remains the
                             * single submitted field (name="currency").
                             */
                            ?>
                            <div class="bb-combobox" data-bb-combobox>
                                <input
                                    type="text"
                                    class="bb-combobox-input"
                                    id="bb_payment_currency_search"
                                    placeholder="<?php echo esc_attr__( 'Search currencies…', 'business-builder' ); ?>"
                                    autocomplete="off"
                                    aria-controls="bb_payment_currency"
                                    aria-expanded="false"
                                />
                                <select name="currency" id="bb_payment_currency" class="bb-combobox-select">
                                    <?php foreach ( Currencies::select_options( $currency ) as $entry ) : ?>
                                        <option
                                            value="<?php echo esc_attr( $entry['code'] ); ?>"
                                            data-symbol="<?php echo esc_attr( $entry['symbol'] ); ?>"
                                            data-name="<?php echo esc_attr( $entry['name'] ); ?>"
                                            <?php selected( $currency, $entry['code'] ); ?>
                                        >
                                            <?php
                                            echo esc_html(
                                                $entry['name'] . ' (' . $entry['code'] . ') — ' . $entry['symbol']
                                                . ( ! empty( $entry['legacy'] ) ? ' · ' . __( 'saved', 'business-builder' ) : '' )
                                            );
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <p class="description">
                                <?php esc_html_e( 'Used across consultations and appointments for this site.', 'business-builder' ); ?>
                            </p>
                        </div>

                        <div class="bb-pc-field">
                            <label for="bb_consultation_fee"><?php esc_html_e( 'Default Consultation Fee', 'business-builder' ); ?></label>
                            <input
                                type="text"
                                id="bb_consultation_fee"
                                name="consultation_fee"
                                value="<?php echo esc_attr( $fee ); ?>"
                                class="regular-text"
                                inputmode="decimal"
                            />
                            <p class="description">
                                <?php esc_html_e( 'A section can override this. Numeric amount only.', 'business-builder' ); ?>
                            </p>
                        </div>

                        <div class="bb-pc-field bb-pc-field-toggles">
                            <label><?php esc_html_e( 'Require Payment by Default', 'business-builder' ); ?></label>

                            <label class="bb-toggle">
                                <input type="checkbox" name="require_payment" value="1" <?php checked( $require_payment ); ?> />
                                <span class="bb-toggle-track" aria-hidden="true"></span>
                                <span><?php esc_html_e( 'Consultations', 'business-builder' ); ?></span>
                            </label>

                            <label class="bb-toggle">
                                <input type="checkbox" name="require_appointment_payment" value="1" <?php checked( $require_appt ); ?> />
                                <span class="bb-toggle-track" aria-hidden="true"></span>
                                <span><?php esc_html_e( 'Appointments', 'business-builder' ); ?></span>
                            </label>

                            <p class="description">
                                <?php esc_html_e( 'A default only. Each Consultation / Appointment section can override this in the Page Builder.', 'business-builder' ); ?>
                            </p>
                        </div>

                    </div>
                </section>

                <section class="bb-pc-card">
                    <h2>
                        <span class="dashicons dashicons-cart"></span>
                        <?php esc_html_e( 'Payment Gateways', 'business-builder' ); ?>
                    </h2>

                    <p class="description bb-pc-section-hint">
                        <?php esc_html_e( 'Toggle a gateway on to accept it, then open its configuration. Enabling a gateway only offers it to customers once its required fields are complete.', 'business-builder' ); ?>
                    </p>

                    <div class="bb-gateway-grid">
                        <?php foreach ( $gateways as $id => $gateway ) : ?>
                            <?php $this->render_gateway_card( $id, $gateway, in_array( $id, $enabled, true ) ); ?>
                        <?php endforeach; ?>
                    </div>
                </section>

                <div class="bb-payments-save">
                    <?php if ( function_exists( 'submit_button' )) : ?>
                        <?php submit_button( __( 'Save Payment Settings', 'business-builder' ), 'primary large', 'submit', false ); ?>
                    <?php else : ?>
                        <button type="submit" class="button button-primary button-large">
                            <?php esc_html_e( 'Save Payment Settings', 'business-builder' ); ?>
                        </button>
                    <?php endif; ?>
                    <span class="bb-payments-save-hint">
                        <?php esc_html_e( 'All enabled gateways and their credentials are saved together.', 'business-builder' ); ?>
                    </span>
                </div>

            </form>

        </div>
        <?php
    }

    /**
     * Render a single gateway card.
     *
     * @param string                  $id      Gateway id.
     * @param PaymentGatewayInterface $gateway Gateway.
     * @param bool                    $enabled Whether currently enabled.
     */
    protected function render_gateway_card( string $id, PaymentGatewayInterface $gateway, bool $enabled ): void {

        $is_configured = $gateway->is_configured();
        $is_manual     = $gateway->is_manual();
        $is_ready      = $gateway->is_integration_ready();
        $logo          = $gateway->get_logo_url();
        $currencies    = $gateway->get_supported_currencies();

        $card_classes = array(
            'bb-gateway-card',
            $enabled ? 'is-enabled' : 'is-disabled',
            $is_configured ? 'is-configured' : 'is-unconfigured',
        );

        $card_id = 'bb-gateway-' . sanitize_key( $id );

        ?>
        <article
            class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>"
            id="<?php echo esc_attr( $card_id ); ?>"
            data-gateway="<?php echo esc_attr( $id ); ?>"
        >

            <div class="bb-gateway-head">

                <div class="bb-gateway-logo">
                    <?php if ( '' !== $logo ) : ?>
                        <img
                            src="<?php echo esc_url( $logo ); ?>"
                            alt="<?php echo esc_attr( $gateway->get_name() ); ?>"
                            loading="lazy"
                            width="34"
                            height="34"
                        />
                    <?php else : ?>
                        <span class="dashicons <?php echo $is_manual ? 'dashicons-admin-users' : 'dashicons-card'; ?>"></span>
                    <?php endif; ?>
                </div>

                <div class="bb-gateway-name">
                    <strong><?php echo esc_html( $gateway->get_name() ); ?></strong>
                    <span><?php echo esc_html( $id ); ?></span>
                </div>

                <label class="bb-toggle bb-toggle-gateway" title="<?php echo esc_attr__( 'Enable or disable this gateway', 'business-builder' ); ?>">
                    <input
                        type="checkbox"
                        class="bb-gateway-enable"
                        name="enabled_gateways[]"
                        value="<?php echo esc_attr( $id ); ?>"
                        data-bb-gateway-toggle
                        <?php checked( $enabled ); ?>
                    />
                    <span class="bb-toggle-track" aria-hidden="true"></span>
                    <span class="bb-toggle-state" data-bb-toggle-state>
                        <?php echo $enabled ? esc_html__( 'On', 'business-builder' ) : esc_html__( 'Off', 'business-builder' ); ?>
                    </span>
                </label>

            </div>

            <?php if ( '' !== $gateway->get_description() ) : ?>
                <p class="bb-gateway-desc"><?php echo esc_html( $gateway->get_description() ); ?></p>
            <?php endif; ?>

            <div class="bb-gateway-badges">

                <span class="bb-gtag <?php echo $enabled ? 'bb-gtag-success' : 'bb-gtag-muted'; ?>" data-bb-badge-state>
                    <?php echo $enabled ? esc_html__( 'Enabled', 'business-builder' ) : esc_html__( 'Disabled', 'business-builder' ); ?>
                </span>

                <span class="bb-gtag <?php echo $is_configured ? 'bb-gtag-success' : 'bb-gtag-danger'; ?>" data-bb-badge-config>
                    <?php echo $is_configured ? esc_html__( 'Configured', 'business-builder' ) : esc_html__( 'Not configured', 'business-builder' ); ?>
                </span>

                <?php if ( $is_manual ) : ?>
                    <span class="bb-gtag bb-gtag-muted"><?php esc_html_e( 'Manual verification', 'business-builder' ); ?></span>
                <?php else : ?>
                    <span class="bb-gtag <?php echo $is_ready ? 'bb-gtag-success' : 'bb-gtag-warning'; ?>">
                        <?php echo $is_ready ? esc_html__( 'Live API ready', 'business-builder' ) : esc_html__( 'API not enabled yet', 'business-builder' ); ?>
                    </span>
                <?php endif; ?>

                <?php if ( ! empty( $currencies )) : ?>
                    <span class="bb-gtag bb-gtag-muted">
                        <?php
                        printf(
                            /* translators: %s: comma-separated currency codes */
                            esc_html__( 'Currencies: %s', 'business-builder' ),
                            esc_html( implode( ', ', $currencies ) )
                        );
                        ?>
                    </span>
                <?php endif; ?>

            </div>

            <div class="bb-gateway-config" data-bb-config>
                <button
                    type="button"
                    class="bb-gateway-config-toggle"
                    data-bb-config-toggle
                    aria-expanded="false"
                    aria-controls="<?php echo esc_attr( $card_id . '-fields' ); ?>"
                >
                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    <?php echo $is_configured ? esc_html__( 'Edit configuration', 'business-builder' ) : esc_html__( 'Configure gateway', 'business-builder' ); ?>
                </button>

                <div
                    class="bb-gateway-fields"
                    id="<?php echo esc_attr( $card_id . '-fields' ); ?>"
                    hidden
                >
                    <?php if ( empty( $gateway->get_settings_schema() )) : ?>
                        <p class="description">
                            <?php esc_html_e( 'This gateway has no configurable fields.', 'business-builder' ); ?>
                        </p>
                    <?php else : ?>
                        <?php $this->render_gateway_fields( $id, $gateway ); ?>
                    <?php endif; ?>
                </div>
            </div>

        </article>
        <?php
    }

    /**
     * Render a gateway's configuration fields from its own schema.
     *
     * Only the fields that gateway declares are shown. Secrets render as
     * password inputs with a masked hint and are preserved server-side
     * when left empty.
     *
     * @param string                  $gateway_id Gateway id.
     * @param PaymentGatewayInterface $gateway    Gateway.
     */
    protected function render_gateway_fields( string $gateway_id, PaymentGatewayInterface $gateway ): void {

        $schema = $gateway->get_settings_schema();

        foreach ( $schema as $key => $field ) {

            $key = sanitize_key( (string) $key );

            if ( '' === $key ) {
                continue;
            }

            $type     = isset( $field['type'] ) ? (string) $field['type'] : 'text';
            $is_secret = ! empty( $field['secret'] );
            $label    = isset( $field['label'] ) ? (string) $field['label'] : $key;
            $desc     = isset( $field['description'] ) ? (string) $field['description'] : '';
            $required = ! empty( $field['required'] );
            $current  = (string) $gateway->get_setting( $key, '' );

            $field_id = 'bb_gw_' . sanitize_key( $gateway_id . '_' . $key );
            $name     = 'gateway_settings[' . $gateway_id . '][' . $key . ']';

            ?>
            <div class="bb-gateway-field">

                <label for="<?php echo esc_attr( $field_id ); ?>">
                    <?php echo esc_html( $label ); ?>

                    <?php if ( $required ) : ?>
                        <span class="bb-gtag bb-gtag-warning"><?php esc_html_e( 'Required', 'business-builder' ); ?></span>
                    <?php endif; ?>

                    <?php if ( $is_secret && '' !== $current ) : ?>
                        <span class="bb-gateway-secret-note">
                            <?php
                            printf(
                                /* translators: %s: masked secret value */
                                esc_html__( 'Saved: %s', 'business-builder' ),
                                esc_html( PaymentManager::mask_secret( $current ) )
                            );
                            ?>
                        </span>
                    <?php endif; ?>
                </label>

                <?php if ( $is_secret ) : ?>

                    <input
                        type="password"
                        id="<?php echo esc_attr( $field_id ); ?>"
                        name="<?php echo esc_attr( $name ); ?>"
                        value=""
                        autocomplete="new-password"
                        placeholder="<?php echo '' !== $current ? esc_attr__( 'Leave empty to keep current', 'business-builder' ) : ''; ?>"
                    />

                <?php elseif ( 'select' === $type ) : ?>

                    <?php
                    $options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
                    $default = isset( $field['default'] ) ? (string) $field['default'] : '';

                    if ( '' === $current && '' !== $default ) {
                        $current = $default;
                    }
                    ?>

                    <select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>">
                        <?php foreach ( $options as $opt_value => $opt_label ) : ?>
                            <option value="<?php echo esc_attr( (string) $opt_value ); ?>" <?php selected( $current, (string) $opt_value ); ?>>
                                <?php echo esc_html( (string) $opt_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ( 'textarea' === $type ) : ?>

                    <textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="3"><?php echo esc_textarea( $current ); ?></textarea>

                <?php else : ?>

                    <input
                        type="text"
                        id="<?php echo esc_attr( $field_id ); ?>"
                        name="<?php echo esc_attr( $name ); ?>"
                        value="<?php echo esc_attr( $current ); ?>"
                        <?php echo ! empty( $field['placeholder'] ) ? 'placeholder="' . esc_attr( (string) $field['placeholder'] ) . '"' : ''; ?>
                    />

                <?php endif; ?>

                <?php if ( '' !== $desc ) : ?>
                    <p class="description"><?php echo esc_html( $desc ); ?></p>
                <?php endif; ?>

            </div>
            <?php
        }
    }

    /**
     * Handle the save.
     *
     * Persists, in ONE request:
     *   - the structured currency + default fee + require-payment flag;
     *   - the complete list of enabled gateways;
     *   - the submitted credentials of EVERY gateway (secrets preserved
     *     when their field is left empty).
     */
    public function handle_save(): void {

        if ( ! current_user_can( DashboardMenu::capability() )) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'business-builder' ) );
        }

        check_admin_referer( self::NONCE_ACTION );

        /* Currency: validated against the structured catalogue. */
        $currency = isset( $_POST['currency'] )
            ? sanitize_text_field( wp_unslash( $_POST['currency'] ) )
            : '';

        $currency = Currencies::normalize( $currency );

        if ( '' === $currency || ! Currencies::exists( $currency )) {
            $currency = (string) $this->settings->get( 'consultation_currency' );
        }

        /* Currency + fee + require flag via the existing SiteSettings layer. */
        $this->settings->update(
            array(
                'require_consultation_payment' => isset( $_POST['require_payment'] ),
                'require_appointment_payment'  => isset( $_POST['require_appointment_payment'] ),
                'consultation_fee'             => isset( $_POST['consultation_fee'] )
                    ? sanitize_text_field( wp_unslash( $_POST['consultation_fee'] ) )
                    : '',
                'consultation_currency'        => $currency,
            )
        );

        /* Enabled gateways: the complete multi-select list. */
        $requested = array();

        if ( isset( $_POST['enabled_gateways'] ) && is_array( $_POST['enabled_gateways'] )) {

            $raw_enabled = wp_unslash( $_POST['enabled_gateways'] );

            foreach ( $raw_enabled as $gateway_id ) {
                $requested[] = sanitize_key( (string) $gateway_id );
            }
        }

        $this->payments->set_enabled_gateways( $requested );

        /*
         * Per-gateway credentials. Each gateway is saved independently
         * through the shared settings layer, which validates against the
         * gateway's own schema and preserves existing secrets when a
         * secret field is submitted empty. Iterating the FULL submitted
         * set (not just the enabled ones) means a gateway that was
         * disabled in this submission still keeps its saved settings —
         * disabling never deletes configuration.
         */
        $raw_settings = array();

        if ( isset( $_POST['gateway_settings'] ) && is_array( $_POST['gateway_settings'] )) {
            $raw_settings = wp_unslash( $_POST['gateway_settings'] );
        }

        $saved_gateways = array();

        foreach ( $raw_settings as $gateway_id => $fields ) {

            $gateway_id = sanitize_key( (string) $gateway_id );

            if ( '' === $gateway_id || null === $this->payments->gateway( $gateway_id )) {
                continue;
            }

            if ( ! is_array( $fields )) {
                continue;
            }

            $this->payments->save_settings( $gateway_id, $fields );

            $saved_gateways[] = $gateway_id;
        }

        $this->audit->record(
            'payment.settings_saved',
            'payment',
            0,
            array(
                'enabled'  => $this->payments->enabled_gateways(),
                'currency' => $currency,
                'saved'    => $saved_gateways,
            )
        );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => self::PAGE_SLUG,
                    'updated' => '1',
                ),
                admin_url( 'admin.php' )
            )
        );

        exit;
    }
}
