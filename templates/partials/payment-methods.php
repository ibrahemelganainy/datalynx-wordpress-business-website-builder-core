<?php
/**
 * Payment method selector partial (Phase F).
 *
 * Renders the enabled + configured gateways as radio options for a
 * checkout form. Renders NOTHING when no gateway is available, so an
 * existing consultation / booking form keeps working unchanged.
 *
 * Security: only public gateway metadata (name/description/logo) is
 * output; credentials are never exposed. All output is escaped.
 *
 * Expected vars (set by the including template):
 *   $bb_payment_object_type (string) consultation|appointment
 *   $bb_payment_object_id   (int)
 *   $bb_payment_amount      (string) decimal
 *   $bb_payment_currency    (string) currency code
 *
 * @package BusinessBuilderCore
 */

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\Checkout\CheckoutHandler;
use BusinessBuilderCore\Core\Payments\Currencies;

defined( 'ABSPATH' ) || exit;

$bb_pm = new PaymentManager();

$bb_gateways = $bb_pm->available_gateways();

if ( count( $bb_gateways ) < 1 ) {
    return;
}

$bb_object_type = isset( $bb_payment_object_type ) ? (string) $bb_payment_object_type : 'consultation';
$bb_object_id   = isset( $bb_payment_object_id ) ? (int) $bb_payment_object_id : 0;
$bb_amount      = isset( $bb_payment_amount ) ? (string) $bb_payment_amount : '';
$bb_currency    = isset( $bb_payment_currency ) ? (string) $bb_payment_currency : '';

if ( $bb_object_id <= 0 || '' === $bb_amount ) {
    return;
}

?>
<div class="bb-payment-methods" id="bb-payment-methods">

    <h3 class="bb-payment-methods-title">
        <?php esc_html_e( 'Payment Method', 'business-builder' ); ?>
    </h3>

    <p class="bb-payment-methods-amount">
        <?php
        echo esc_html(
            sprintf(
                /* translators: %s: formatted amount */
                __( 'Amount due: %s', 'business-builder' ),
                Currencies::format( (float) $bb_amount, $bb_currency )
            )
        );
        ?>
    </p>

    <form
        class="bb-checkout-form"
        method="post"
        action="<?php echo esc_url( CheckoutHandler::action_url() ); ?>"
    >
        <input type="hidden" name="action" value="<?php echo esc_attr( CheckoutHandler::action_name() ); ?>" />
        <input type="hidden" name="object_type" value="<?php echo esc_attr( $bb_object_type ); ?>" />
        <input type="hidden" name="object_id" value="<?php echo esc_attr( (string) $bb_object_id ); ?>" />
        <input type="hidden" name="amount" value="<?php echo esc_attr( $bb_amount ); ?>" />
        <input type="hidden" name="currency" value="<?php echo esc_attr( $bb_currency ); ?>" />

        <?php
        wp_nonce_field(
            CheckoutHandler::nonce_action(),
            CheckoutHandler::nonce_field()
        );
        ?>

        <ul class="bb-payment-methods-list">
            <?php foreach ( $bb_gateways as $bb_id => $bb_gateway ) : ?>
                <li class="bb-payment-method">
                    <label>
                        <input
                            type="radio"
                            name="gateway"
                            value="<?php echo esc_attr( (string) $bb_id ); ?>"
                            <?php checked( false, false ); ?>
                        />
                        <span class="bb-payment-method-name"><?php echo esc_html( $bb_gateway->get_name() ); ?></span>
                        <?php if ( '' !== $bb_gateway->get_description() ) : ?>
                            <span class="bb-payment-method-desc"><?php echo esc_html( $bb_gateway->get_description() ); ?></span>
                        <?php endif; ?>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>

        <p class="bb-payment-methods-submit">
            <button type="submit" class="bb-primary-button">
                <?php esc_html_e( 'Proceed to Payment', 'business-builder' ); ?>
            </button>
        </p>
    </form>

</div>
