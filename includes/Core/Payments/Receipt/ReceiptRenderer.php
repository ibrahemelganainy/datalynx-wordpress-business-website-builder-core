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

            $rows_html .= '<div class="bb-receipt-row"><dt class="bb-receipt-row-label">' . esc_html( $label ) . '</dt>'
                . '<dd class="bb-receipt-row-value">' . esc_html( $value ) . '</dd></div>';
        }

        $status_class = $receipt->is_paid ? 'is-paid' : 'is-pending';

        $site_name = $receipt->site_name;

        $type_label = ( 'appointment' === $receipt->object_type )
            ? __( 'Appointment', 'business-builder' )
            : __( 'Consultation', 'business-builder' );

        ob_start();
        ?>
        <div class="bb-receipt" id="bb-payment-receipt" data-receipt-ref="<?php echo esc_attr( $receipt->reference ); ?>">

            <div class="bb-receipt-brand">
                <?php if ( '' !== $receipt->logo_url ) : ?>
                    <span class="bb-receipt-logo">
                        <img src="<?php echo esc_url( $receipt->logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" />
                    </span>
                <?php endif; ?>
                <div class="bb-receipt-brand-text">
                    <span class="bb-receipt-business"><?php echo esc_html( $site_name ); ?></span>
                    <span class="bb-receipt-doc-title"><?php esc_html_e( 'PAYMENT RECEIPT', 'business-builder' ); ?></span>
                </div>
                <span class="bb-receipt-status <?php echo esc_attr( $status_class ); ?>">
                    <?php echo esc_html( $receipt->status_label ); ?>
                </span>
            </div>

            <div class="bb-receipt-meta">
                <div class="bb-receipt-meta-block">
                    <span class="bb-receipt-meta-label"><?php esc_html_e( 'Customer', 'business-builder' ); ?></span>
                    <span class="bb-receipt-meta-value">
                        <?php
                        echo esc_html(
                            '' !== $receipt->customer_name
                                ? $receipt->customer_name
                                : __( '—', 'business-builder' )
                        );
                        ?>
                    </span>
                </div>
                <div class="bb-receipt-meta-block">
                    <span class="bb-receipt-meta-label"><?php esc_html_e( 'Type', 'business-builder' ); ?></span>
                    <span class="bb-receipt-meta-value"><?php echo esc_html( $type_label ); ?></span>
                </div>
                <div class="bb-receipt-meta-block bb-receipt-meta-amount">
                    <span class="bb-receipt-meta-label"><?php esc_html_e( 'Amount', 'business-builder' ); ?></span>
                    <span class="bb-receipt-meta-value"><?php echo esc_html( $receipt->amount_display ); ?></span>
                </div>
            </div>

            <dl class="bb-receipt-rows">
                <?php
                /* $rows_html is built above with each cell escaped. */
                echo $rows_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </dl>

            <p class="bb-receipt-note">
                <strong><?php esc_html_e( 'Important:', 'business-builder' ); ?></strong>
                <?php esc_html_e( 'Please keep this receipt for your records.', 'business-builder' ); ?>
            </p>

            <p class="bb-receipt-actions">
                <button type="button" class="bb-receipt-btn bb-receipt-print" data-bb-receipt-print>
                    <?php esc_html_e( 'Print Receipt', 'business-builder' ); ?>
                </button>
                <button type="button" class="bb-receipt-btn bb-receipt-download" data-bb-receipt-download>
                    <?php esc_html_e( 'Save Receipt', 'business-builder' ); ?>
                </button>
            </p>
        </div>
        <?php
        $html = (string) ob_get_clean();

        return $html . $this->styles() . $this->script();
    }

    /**
     * Inline script for the Save / Download button.
     *
     * Serialises the receipt element to a self-contained HTML file so the
     * saved copy preserves the exact layout (no external assets needed). No
     * PDF library is bundled by the project, so this is the honest option.
     *
     * @return string
     */
    protected function script(): string {

        return '<script>'
            . '(function(){'
            . 'var el=document.getElementById("bb-payment-receipt");'
            . 'if(!el){return;}'
            . 'var css=document.querySelector(".bb-receipt-style");'
            . 'var style=css?"<style>"+css.textContent+"</style>":"";'
            . 'var ref=el.getAttribute("data-receipt-ref")||"receipt";'
            . 'var printBtn=document.querySelector("[data-bb-receipt-print]");'
            . 'if(printBtn&&!printBtn.getAttribute("data-bound")){'
            . 'printBtn.setAttribute("data-bound","1");'
            . 'printBtn.addEventListener("click",function(){window.print();});}'
            . 'var btn=document.querySelector("[data-bb-receipt-download]");'
            . 'if(!btn||btn.getAttribute("data-bound")){return;}'
            . 'btn.setAttribute("data-bound","1");'
            . 'btn.addEventListener("click",function(){'
            . 'var clone=el.cloneNode(true);'
            . 'var acts=clone.querySelector(".bb-receipt-actions");'
            . 'if(acts){acts.parentNode.removeChild(acts);}'
            . 'var doc="<!DOCTYPE html><html><head><meta charset=\\"utf-8\\"><title>Receipt</title>"+style+"</head><body>"+el.outerHTML+"</body></html>";'
            . 'var blob=new Blob([doc],{type:"text/html"});'
            . 'var a=document.createElement("a");'
            . 'a.href=URL.createObjectURL(blob);'
            . 'a.download="receipt-"+ref+".html";'
            . 'document.body.appendChild(a);a.click();document.body.removeChild(a);'
            . 'URL.revokeObjectURL(a.href);'
            . '});'
            . '})();'
            . '</script>';
    }

    /**
     * Build the label => value rows for the receipt.
     *
     * @param Receipt $receipt Receipt.
     * @return array<string, string>
     */
    protected function rows( Receipt $receipt ): array {

        $rows = array(
            __( 'Description', 'business-builder' ) => $receipt->description,
        );

        /* The paid-for object's public reference (consultation/appointment). */
        if ( '' !== $receipt->object_reference ) {
            $label = '' !== $receipt->object_label
                ? $receipt->object_label
                : __( 'Service', 'business-builder' );

            /* translators: %s: object type label (e.g. Consultation). */
            $rows[ sprintf( __( '%s Number', 'business-builder' ), $label ) ] = $receipt->object_reference;
        }

        /* Object-specific rows (lawyer/date/time for appointments, area). */
        foreach ( $receipt->extra_rows as $extra ) {

            $extra_label = isset( $extra['label'] ) ? (string) $extra['label'] : '';
            $extra_value = isset( $extra['value'] ) ? (string) $extra['value'] : '';

            if ( '' !== $extra_label && '' !== $extra_value ) {
                $rows[ $extra_label ] = $extra_value;
            }
        }

        $rows[ __( 'Payment Reference', 'business-builder' ) ] = $receipt->reference;
        $rows[ __( 'Payment Method', 'business-builder' ) ]    = $receipt->gateway_name;

        if ( '' !== $receipt->gateway_reference ) {
            $rows[ __( 'Gateway Reference', 'business-builder' ) ] = $receipt->gateway_reference;
        }

        $rows[ __( 'Amount', 'business-builder' ) ]   = $receipt->amount_display;
        $rows[ __( 'Currency', 'business-builder' ) ] = $receipt->currency;
        $rows[ __( 'Status', 'business-builder' ) ]   = $receipt->status_label;
        $rows[ __( 'Payment Date', 'business-builder' ) ] = $receipt->created_at;

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

        return '<style class="bb-receipt-style">'
            . '.bb-receipt{max-width:720px;margin:0 auto;padding:0;border:1px solid #e5e7eb;'
            . 'border-radius:16px;background:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;'
            . 'color:#0f172a;overflow:hidden;box-shadow:0 18px 44px rgba(15,23,42,.08);}'
            . '.bb-receipt-brand{position:relative;display:flex;align-items:center;gap:14px;'
            . 'padding:22px 24px;background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);color:#fff;}'
            . '.bb-receipt-logo{display:inline-flex;align-items:center;justify-content:center;width:54px;height:54px;'
            . 'border-radius:12px;background:#fff;overflow:hidden;flex:0 0 auto;}'
            . '.bb-receipt-logo img{max-width:44px;max-height:44px;width:auto;height:auto;}'
            . '.bb-receipt-brand-text{display:flex;flex-direction:column;gap:2px;min-width:0;}'
            . '.bb-receipt-business{font-weight:800;font-size:1.02rem;letter-spacing:.01em;}'
            . '.bb-receipt-doc-title{font-size:.74rem;letter-spacing:.14em;color:#cbd5e1;font-weight:700;}'
            . '.bb-receipt-status{margin-inline-start:auto;padding:.3rem .8rem;border-radius:999px;'
            . 'font-weight:800;font-size:.78rem;white-space:nowrap;}'
            . '.bb-receipt-status.is-paid{background:#dcfce7;color:#166534'
            . '.bb-receipt-status.is-pending{background:#fef3c7;color:#92400e;}'
            . '.bb-receipt-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:#e5e7eb;}'
            . '.bb-receipt-meta-block{background:#fff;padding:16px 20px;display:flex;flex-direction:column;gap:4px;}'
            . '.bb-receipt-meta-label{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:#64748b;font-weight:700;}'
            . '.bb-receipt-meta-value{font-size:.95rem;font-weight:700;color:#0f172a;}'
            . '.bb-receipt-meta-amount .bb-receipt-meta-value{font-size:1.25rem;color:#166534;}'
            . '.bb-receipt-rows{margin:0;padding:8px 24px 4px;display:grid;}'
            . '.bb-receipt-row{display:flex;justify-content:space-between;gap:16px;align-items:baseline;'
            . 'padding:11px 0;border-bottom:1px solid #f1f5f9;}'
            . '.bb-receipt-row-label{margin:0;font-size:.85rem;color:#64748b;font-weight:600;}'
            . '.bb-receipt-row-value{margin:0;font-size:.9rem;font-weight:700;color:#0f172a;text-align:end;'
            . 'font-family:ui-monospace,Menlo,Consolas,monospace;word-break:break-all;}'
            . '.bb-receipt-note{font-size:.82rem;color:#92400e;margin:16px 24px 0;padding:12px 14px;'
            . 'background:#fffbeb;border:1px solid #fde68a;border-radius:10px;line-height:1.5;}'
            . '.bb-receipt-actions{margin:0;padding:20px 24px 24px;display:flex;gap:12px;flex-wrap:wrap;}'
            . '.bb-receipt-btn{flex:1 1 auto;min-width:150px;padding:13px 18px;border-radius:11px;border:0;'
            . 'font:inherit;font-weight:700;cursor:pointer;background:#0f172a;color:#fff;transition:background .15s;}'
            . '.bb-receipt-btn:hover{background:#1e293b;}'
            . '.bb-receipt-download{background:#1d4ed8;}'
            . '.bb-receipt-download:hover{background:#1a44be;}'
            . 'body.bb-rtl .bb-receipt-row-value{text-align:start;}'
            /* Print: ONLY the receipt is visible and positioned at the page top. */
            . '@media print{'
            . '@page{margin:12mm;}'
            . '.bb-receipt-actions{display:none!important;}'
            . 'body *{visibility:hidden!important;}'
            . '#bb-payment-receipt,#bb-payment-receipt *{visibility:visible!important;}'
            . '#bb-payment-receipt{position:absolute;left:0;top:0;width:100%;max-width:none;'
            . 'border:0;border-radius:0;box-shadow:none;}'
            . '#bb-payment-receipt .bb-receipt-brand{background:#fff!important;color:#0f172a!important;'
            . 'border-bottom:2px solid #0f172a;}'
            . '#bb-payment-receipt .bb-receipt-doc-title{color:#475569!important;}'
            . '.bb-receipt-status.is-paid{background:#fff!important;color:#166534!important;border:1px solid #166534;}'
            . '.bb-receipt-status.is-pending{background:#fff!important;color:#92400e!important;border:1px solid #92400e;}'
            . 'header,footer,nav,.bb-header-bar,.bb-footer-bar,.bb-section-heading[data-bb-nav],'
            . '.wp-admin-bar,.bb-page-controls,.bb-pay-step-back{display:none!important;}'
            . '}'
            . '@media (max-width:560px){.bb-receipt-meta{grid-template-columns:1fr;}}'
            . '</style>';
    }
}
