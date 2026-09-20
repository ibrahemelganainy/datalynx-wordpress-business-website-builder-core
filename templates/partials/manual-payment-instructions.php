<?php
/**
 * Manual payment instructions partial.
 *
 * Rendered inside a checkout form for EACH manual gateway (wallet /
 * InstaPay / bank transfer). It displays the administrator's REAL
 * configured payment details (wallet number, InstaPay address, bank
 * account / IBAN, ...) — never hardcoded values — together with a
 * transaction-reference input and an optional receipt upload.
 *
 * The block is a sibling of the gateway radio option and is revealed by
 * assets/js/frontend/manual-payment.js only while its gateway is selected,
 * so exactly one set of instructions is ever shown. With JavaScript
 * disabled every block stays hidden and the server still records the
 * pending manual payment correctly.
 *
 * Expected vars (set by the including template):
 *   $bb_manual_gateway  (PaymentGatewayInterface) The gateway.
 *   $bb_manual_gw_id    (string) Gateway id.
 *   $bb_manual_amount   (string) Decimal amount.
 *   $bb_manual_currency (string) Currency code.
 *   $bb_manual_uid      (string) Unique id fragment (avoids id collisions).
 *
 * @package BusinessBuilderCore
 */

use BusinessBuilderCore\Core\Payments\Currencies;

defined( 'ABSPATH' ) || exit;

$bb_manual_gw_id    = isset( $bb_manual_gw_id ) ? sanitize_key( (string) $bb_manual_gw_id ) : '';
$bb_manual_uid      = isset( $bb_manual_uid ) ? sanitize_key( (string) $bb_manual_uid ) : $bb_manual_gw_id;
$bb_manual_amount   = isset( $bb_manual_amount ) ? (string) $bb_manual_amount : '';
$bb_manual_currency = isset( $bb_manual_currency ) ? (string) $bb_manual_currency : '';

$bb_manual_ok = ( '' !== $bb_manual_gw_id );
$bb_manual_ok = $bb_manual_ok && isset( $bb_manual_gateway );
$bb_manual_ok = $bb_manual_ok && is_object( $bb_manual_gateway );

if ( ! $bb_manual_ok ) {
    return;
}

$bb_manual_info = $bb_manual_gateway->get_public_instructions();

$bb_manual_rows         = isset( $bb_manual_info['rows'] ) && is_array( $bb_manual_info['rows'] ) ? $bb_manual_info['rows'] : array();
$bb_manual_instructions = isset( $bb_manual_info['instructions'] ) ? (string) $bb_manual_info['instructions'] : '';

/*
 * The amount is always shown so the customer knows exactly what to send,
 * even when a gateway has no other configured rows yet.
 */
if ( '' !== $bb_manual_amount ) {
    $bb_manual_rows[] = array(
        'label' => __( 'Amount', 'business-builder' ),
        'value' => Currencies::format( (float) $bb_manual_amount, $bb_manual_currency, true ),
        'copy'  => false,
    );
}

$bb_manual_field_id = 'bb_manual_ref_' . $bb_manual_uid;
$bb_manual_file_id  = 'bb_manual_receipt_' . $bb_manual_uid;

?>
<div
    class="bb-manual-instructions"
    id="bb-manual-<?php echo esc_attr( $bb_manual_uid ); ?>"
    data-bb-manual
    data-bb-manual-gateway="<?php echo esc_attr( $bb_manual_gw_id ); ?>"
    hidden
    aria-hidden="true"
>
    <div class="bb-manual-head">
        <span class="bb-manual-icon" aria-hidden="true">&#128179;</span>
        <div>
            <h4 class="bb-manual-title"><?php esc_html_e( 'Manual Payment Instructions', 'business-builder' ); ?></h4>
            <p class="bb-manual-note">
                <?php esc_html_e( 'Transfer the amount externally, then enter the transaction reference below. An administrator will verify your payment.', 'business-builder' ); ?>
            </p>
        </div>
    </div>

    <?php if ( count( $bb_manual_rows ) > 0 ) : ?>
        <dl class="bb-manual-rows">
            <?php foreach ( $bb_manual_rows as $bb_manual_row ) : ?>
                <?php
                $bb_manual_label = isset( $bb_manual_row['label'] ) ? (string) $bb_manual_row['label'] : '';
                $bb_manual_value = isset( $bb_manual_row['value'] ) ? (string) $bb_manual_row['value'] : '';
                $bb_manual_copy  = ! empty( $bb_manual_row['copy'] );

                if ( '' === $bb_manual_label || '' === $bb_manual_value ) {
                    continue;
                }
                ?>
                <div class="bb-manual-row">
                    <dt class="bb-manual-row-label"><?php echo esc_html( $bb_manual_label ); ?></dt>
                    <dd class="bb-manual-row-value">
                        <span class="bb-manual-value"><?php echo esc_html( $bb_manual_value ); ?></span>
                        <?php if ( $bb_manual_copy ) : ?>
                            <button
                                type="button"
                                class="bb-manual-copy"
                                data-bb-copy="<?php echo esc_attr( $bb_manual_value ); ?>"
                                data-bb-copy-label="<?php echo esc_attr__( 'Copied', 'business-builder' ); ?>"
                                aria-label="<?php echo esc_attr__( 'Copy to clipboard', 'business-builder' ); ?>"
                            ><?php esc_html_e( 'Copy', 'business-builder' ); ?></button>
                        <?php endif; ?>
                    </dd>
                </div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>

    <?php if ( '' !== $bb_manual_instructions ) : ?>
        <div class="bb-manual-help">
            <?php echo wp_kses_post( wpautop( $bb_manual_instructions ) ); ?>
        </div>
    <?php endif; ?>

    <div class="bb-manual-fields">
        <p class="bb-manual-field">
            <label for="<?php echo esc_attr( $bb_manual_field_id ); ?>">
                <?php esc_html_e( 'Transaction Reference', 'business-builder' ); ?>
                <span class="bb-required" aria-hidden="true">*</span>
            </label>
            <input
                type="text"
                id="<?php echo esc_attr( $bb_manual_field_id ); ?>"
                name="bb_manual_reference"
                class="bb-manual-input"
                data-bb-manual-field
                autocomplete="off"
                placeholder="<?php esc_attr_e( 'e.g. transfer ID / receipt number', 'business-builder' ); ?>"
            />
            <span class="bb-manual-error" data-bb-manual-error role="alert" aria-live="polite"></span>
        </p>

        <p class="bb-manual-field">
            <label for="<?php echo esc_attr( $bb_manual_file_id ); ?>">
                <?php esc_html_e( 'Upload Receipt (optional)', 'business-builder' ); ?>
            </label>
            <input
                type="file"
                id="<?php echo esc_attr( $bb_manual_file_id ); ?>"
                name="bb_manual_receipt"
                class="bb-manual-file"
                accept="image/png,image/jpeg,image/webp,application/pdf"
            />
            <span class="bb-manual-hint"><?php esc_html_e( 'PNG, JPG, WEBP or PDF. Max 5 MB.', 'business-builder' ); ?></span>
        </p>
    </div>

    <p class="bb-manual-verified">
        <span class="bb-manual-verified-icon" aria-hidden="true">&#9989;</span>
        <?php esc_html_e( 'This payment is completed only after an administrator verifies it.', 'business-builder' ); ?>
    </p>
</div>
