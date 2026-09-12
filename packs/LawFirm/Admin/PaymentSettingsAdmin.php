<?php

namespace BusinessBuilderCore\Packs\LawFirm\Admin;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Settings\SiteSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Payment settings administration.
 *
 * Renders a dynamic settings form driven by each gateway's own settings
 * schema (spec 7/8): the admin picks a gateway + mode and fills only the
 * fields that gateway declares. Secrets are masked and are never sent to
 * the browser in cleartext; an empty secret field preserves the stored
 * value (spec 9).
 *
 * All values are sanitized server-side by PaymentManager::save_settings().
 * Multisite: settings live in per-site options (spec 10).
 */
class PaymentSettingsAdmin {

    /**
     * Submenu slug.
     */
    private const PAGE_SLUG = 'business-builder-payments';

    /**
     * admin-post action.
     */
    private const ACTION = 'bb_save_payment_settings';

    /**
     * Nonce action.
     */
    private const NONCE_ACTION = 'bb_save_payment_settings';

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

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_save' ) );
    }

    /**
     * Register the submenu page.
     */
    public function register_menu(): void {

        add_submenu_page(
            'business-builder',
            __( 'Payment Settings', 'business-builder' ),
            __( 'Payment Settings', 'business-builder' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    /**
     * Render the settings page.
     */
    public function render_page(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'business-builder' ) );
        }

        $all_settings = $this->settings->get_all();

        $payment_enabled = ! empty( $all_settings['require_consultation_payment'] );
        $fee             = (string) $all_settings['consultation_fee'];
        $currency        = (string) $all_settings['consultation_currency'];

        $active = $this->payments->active_gateway_id();
        $gateways = $this->payments->gateways();

        $active_gateway = $this->payments->active_gateway();

        ?>
        <div class="wrap">

            <h1><?php esc_html_e( 'Payment Settings', 'business-builder' ); ?></h1>

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Payment settings saved.', 'business-builder' ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
                <?php wp_nonce_field( self::NONCE_ACTION ); ?>

                <h2><?php esc_html_e( 'Consultation Payment', 'business-builder' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Paid Consultations', 'business-builder' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_payment" value="1" <?php checked( $payment_enabled ); ?> />
                                <?php esc_html_e( 'Require payment for consultation requests', 'business-builder' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bb_consultation_fee"><?php esc_html_e( 'Consultation Fee', 'business-builder' ); ?></label></th>
                        <td>
                            <input type="text" id="bb_consultation_fee" name="consultation_fee" value="<?php echo esc_attr( $fee ); ?>" class="regular-text" inputmode="decimal" />
                            <p class="description"><?php esc_html_e( 'Numeric amount only. Shown on the form when payment is required.', 'business-builder' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bb_consultation_currency"><?php esc_html_e( 'Currency', 'business-builder' ); ?></label></th>
                        <td>
                            <input type="text" id="bb_consultation_currency" name="consultation_currency" value="<?php echo esc_attr( $currency ); ?>" class="small-text" maxlength="3" />
                            <p class="description"><?php esc_html_e( 'Three-letter currency code, e.g. USD, EGP.', 'business-builder' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="bb_payment_gateway"><?php esc_html_e( 'Payment Gateway', 'business-builder' ); ?></label></th>
                        <td>
                            <select name="gateway" id="bb_payment_gateway">
                                <option value=""><?php esc_html_e( '- Select a gateway -', 'business-builder' ); ?></option>
                                <?php foreach ( $gateways as $id => $gateway ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $active, $id ); ?>>
                                        <?php echo esc_html( $gateway->get_name() ); ?>
                                        <?php echo $gateway->is_manual() ? esc_html__( '(manual)', 'business-builder' ) : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose the gateway used for paid consultations.', 'business-builder' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php if ( $active_gateway ) : ?>

                    <h2><?php echo esc_html( $active_gateway->get_name() ); ?></h2>

                    <p class="description">
                        <?php if ( $active_gateway->is_manual() ) : ?>
                            <?php esc_html_e( 'This is a manual method: payments are verified by an administrator.', 'business-builder' ); ?>
                        <?php else : ?>
                            <?php esc_html_e( 'This gateway is configuration-ready. Its live API must be enabled before payments can complete.', 'business-builder' ); ?>
                        <?php endif; ?>
                    </p>

                    <table class="form-table">

                        <?php foreach ( $active_gateway->get_settings_schema() as $key => $field ) : ?>

                            <?php
                            $type     = isset( $field['type'] ) ? (string) $field['type'] : 'text';
                            $is_secret = ! empty( $field['secret'] );
                            $label    = isset( $field['label'] ) ? (string) $field['label'] : $key;
                            $desc     = isset( $field['description'] ) ? (string) $field['description'] : '';
                            $required = ! empty( $field['required'] );
                            $current  = (string) $active_gateway->get_setting( $key, '' );

                            $field_id = 'bb_gateway_' . sanitize_key( $key );
                            ?>

                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr( $field_id ); ?>">
                                        <?php echo esc_html( $label ); ?>
                                        <?php if ( $is_secret && '' !== $current ) : ?>
                                            <br /><span class="description"><?php echo esc_html( PaymentManager::mask_secret( $current ) ); ?></span>
                                        <?php endif; ?>
                                    </label>
                                </th>
                                <td>

                                    <?php if ( $is_secret ) : ?>

                                        <input
                                            type="password"
                                            id="<?php echo esc_attr( $field_id ); ?>"
                                            name="gateway_settings[<?php echo esc_attr( $key ); ?>]"
                                            value=""
                                            class="regular-text"
                                            autocomplete="new-password"
                                            placeholder="<?php echo $current ? esc_attr__( 'Leave empty to keep current', 'business-builder' ) : ''; ?>"
                                        />

                                    <?php elseif ( 'select' === $type ) : ?>

                                        <select id="<?php echo esc_attr( $field_id ); ?>" name="gateway_settings[<?php echo esc_attr( $key ); ?>]">
                                            <?php $field_options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array(); ?>
                                            <?php foreach ( $field_options as $opt_value => $opt_label ) : ?>
                                                <option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $current, $opt_value ); ?>>
                                                    <?php echo esc_html( $opt_label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                    <?php elseif ( 'textarea' === $type ) : ?>

                                        <textarea id="<?php echo esc_attr( $field_id ); ?>" name="gateway_settings[<?php echo esc_attr( $key ); ?>]" rows="4" class="large-text"><?php echo esc_textarea( $current ); ?></textarea>

                                    <?php else : ?>

                                        <input
                                            type="text"
                                            id="<?php echo esc_attr( $field_id ); ?>"
                                            name="gateway_settings[<?php echo esc_attr( $key ); ?>]"
                                            value="<?php echo esc_attr( $current ); ?>"
                                            class="regular-text"
                                        />

                                    <?php endif; ?>

                                    <?php if ( '' !== $desc ) : ?>
                                        <p class="description"><?php echo esc_html( $desc ); ?></p>
                                    <?php endif; ?>

                                    <?php if ( $required ) : ?>
                                        <p class="description"><?php esc_html_e( 'Required', 'business-builder' ); ?></p>
                                    <?php endif; ?>

                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </table>

                <?php endif; ?>

                <?php
                if ( function_exists( 'submit_button' ) ) {
                    submit_button();
                } else {
                    echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save Changes', 'business-builder' ) . '</button></p>';
                }
                ?>

            </form>

        </div>
        <?php
    }

    /**
     * Handle the save.
     */
    public function handle_save(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'business-builder' ) );
        }

        check_admin_referer( self::NONCE_ACTION );

        /* Save free/paid + fee via the existing SiteSettings layer. */
        $this->settings->update(
            array(
                'require_consultation_payment' => isset( $_POST['require_payment'] ),
                'consultation_fee'             => isset( $_POST['consultation_fee'] )
                    ? sanitize_text_field( wp_unslash( $_POST['consultation_fee'] ) )
                    : '',
                'consultation_currency'        => isset( $_POST['consultation_currency'] )
                    ? sanitize_text_field( wp_unslash( $_POST['consultation_currency'] ) )
                    : 'USD',
            )
        );

        /* Active gateway. */
        $gateway_id = isset( $_POST['gateway'] )
            ? sanitize_key( wp_unslash( $_POST['gateway'] ) )
            : '';

        $this->payments->set_active_gateway( $gateway_id );

        /* Gateway credentials (dynamic schema; secrets preserved if empty). */
        if ( '' !== $gateway_id && isset( $_POST['gateway_settings'] ) && is_array( $_POST['gateway_settings'] ) ) {

            $raw = wp_unslash( $_POST['gateway_settings'] );

            $this->payments->save_settings( $gateway_id, $raw );

            $this->audit->record(
                'payment.gateway_configured',
                'payment',
                0,
                array( 'gateway' => $gateway_id )
            );
        }

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
