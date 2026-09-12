<?php

namespace BusinessBuilderCore\Core\Payments\Receipt;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a professional, escaped HTML payment receipt.
 *
 * Every value is escaped on output (esc_html / esc_attr / esc_url).
 * The receipt intentionally shows only public data (see Receipt). A
 * small print stylesheet is inlined so the receipt prints cleanly, and
 * a short, defensive note explains that no card data is ever stored.
 */
class ReceiptRenderer {

    /**
     * Render the receipt as an HTML string.
     *
     * @param Receipt $receipt Receipt.
     * @return string
     */
    public function render( Receipt $receipt ): string {

        $rows = $this->rows( $receipt );

        $rows_html = '';

        foreach ( $rows as $label => $value ) {

            $rows_html .= '<tr><th scope="row">' . esc_html( $label ) . '</th>'
                . '<td>' . esc_html( $value ) . '</td></tr>';
        }

        $status_class = $receipt->is_paid ? 'bb-receipt-status-paid' : 'bb-receipt-status-pending';

        $site_name = $receipt->site_name;

        ob_start();
        ?>
        <div class="bb-receipt" id="bb-payment-receipt">
            <div class="bb-receipt-header">
                <h2 class="bb-receipt-title"><?php esc_html_e( 'Payment Receipt', 'business-builder' ); ?></h2>
                <p class="bb-receipt-site"><?php echo esc_html( $site_name ); ?></p>
            </div>

            <p class="bb-receipt-status <?php echo esc_attr( $status_class ); ?>">
                <?php echo esc_html( $receipt->status_label ); ?>
            </p>

            <table class="bb-receipt-table">
                <tbody>
                    <?php
                    /*
                     * $rows_html is built above with each cell escaped
                     * via esc_html(), so it is safe to output here.
                     */
                    echo $rows_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </tbody>
            </table>

            <p class="bb-receipt-note">
                <?php esc_html_e( 'This receipt confirms the payment information we hold. For security, no card or account credentials are ever stored.', 'business-builder' ); ?>
            </p>

            <p class="bb-receipt-actions">
                <button type="button" class="bb-primary-button" onclick="window.print();">
                    <?php esc_html_e( 'Print', 'business-builder' ); ?>
                </button>
            </p>
        </div>
        <?php
        $html = (string) ob_get_clean();

        return $html . $this->styles();
    }

    /**
     * Build the label => value rows for the receipt.
     *
     * @param Receipt $receipt Receipt.
     * @return array<string, string>
     */
    protected function rows( Receipt $receipt ): array {

        $rows = array(
            __( 'Reference', 'business-builder' )   => $receipt->reference,
            __( 'Description', 'business-builder' ) => $receipt->description,
            __( 'Payment Method', 'business-builder' ) => $receipt->gateway_name,
            __( 'Amount', 'business-builder' )      => $receipt->amount_display,
            __( 'Status', 'business-builder' )      => $receipt->status_label,
            __( 'Date', 'business-builder' )        => $receipt->created_at,
        );

        /**
         * Filter the receipt rows (label => value).
         *
         * @param array<string, string> $rows    Rows.
         * @param Receipt               $receipt Receipt.
         */
        return apply_filters( 'bb_payment_receipt_rows', $rows, $receipt );
    }

    /**
     * Inline, print-friendly styles.
     *
     * @return string
     */
    protected function styles(): string {

        return '<style>'
            . '.bb-receipt{max-width:640px;margin:0 auto;padding:1.5rem;'
            . 'border:1px solid #e2e2e2;border-radius:8px;background:#fff;'
            . 'font-family:inherit;color:#222;}'
            . '.bb-receipt-header{display:flex;justify-content:space-between;'
            . 'align-items:baseline;border-bottom:2px solid #222;padding-bottom:.5rem;}'
            . '.bb-receipt-title{margin:0;font-size:1.35rem;}'
            . '.bb-receipt-site{margin:0;color:#666;font-size:.9rem;}'
            . '.bb-receipt-status{display:inline-block;margin:1rem 0;padding:.25rem .75rem;'
            . 'border-radius:999px;font-weight:600;font-size:.85rem;}'
            . '.bb-receipt-status-paid{background:#e6f6ec;color:#1a7f37;}'
            . '.bb-receipt-status-pending{background:#fff4e5;color:#a15c00;}'
            . '.bb-receipt-table{width:100%;border-collapse:collapse;margin:1rem 0;}'
            . '.bb-receipt-table th,.bb-receipt-table td{padding:.6rem .25rem;'
            . 'text-align:left;border-bottom:1px solid #eee;vertical-align:top;}'
            . '.bb-receipt-table th{width:40%;color:#555;font-weight:600;}'
            . '.bb-receipt-note{font-size:.8rem;color:#777;margin-top:1rem;}'
            . '.bb-receipt-actions{margin-top:1rem;}'
            . '@media print{.bb-receipt-actions{display:none;}}'
            . '</style>';
    }
}
