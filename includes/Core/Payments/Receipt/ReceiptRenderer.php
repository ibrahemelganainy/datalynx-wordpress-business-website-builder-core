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

        $status_class = $receipt->is_paid
            ? 'is-paid'
            : ( 'failed' === $receipt->status || 'cancelled' === $receipt->status || 'expired' === $receipt->status
                ? 'is-failed'
                : 'is-pending' );

        $site_name = $receipt->site_name;

        $type_label = ( 'appointment' === $receipt->object_type )
            ? __( 'Appointment', 'business-builder' )
            : __( 'Consultation', 'business-builder' );

        $payment_rows = $this->payment_rows( $receipt );

        ob_start();
        ?>
        <div class="bb-receipt" id="bb-payment-receipt" data-receipt-ref="<?php echo esc_attr( $receipt->reference ); ?>">

            <div class="bb-receipt-brand">
                <div class="bb-receipt-brand-left">
                    <?php if ( '' !== $receipt->logo_url ) : ?>
                        <span class="bb-receipt-logo">
                            <img src="<?php echo esc_url( $receipt->logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" />
                        </span>
                    <?php endif; ?>
                    <div class="bb-receipt-brand-text">
                        <span class="bb-receipt-business"><?php echo esc_html( $site_name ); ?></span>
                        <span class="bb-receipt-doc-title"><?php esc_html_e( 'PAYMENT RECEIPT', 'business-builder' ); ?></span>
                    </div>
                </div>
            </div>

            <div class="bb-receipt-banner <?php echo esc_attr( $status_class ); ?>">
                <span class="bb-receipt-banner-status"><?php echo esc_html( strtoupper( $receipt->status_label ) ); ?></span>
                <?php if ( '' !== $receipt->receipt_number ) : ?>
                    <span class="bb-receipt-banner-no">
                        <span class="bb-receipt-banner-no-label"><?php esc_html_e( 'Receipt No:', 'business-builder' ); ?></span>
                        <span class="bb-receipt-banner-no-value"><?php echo esc_html( $receipt->receipt_number ); ?></span>
                    </span>
                <?php endif; ?>
            </div>

            <div class="bb-receipt-section">
                <h3 class="bb-receipt-section-title"><?php esc_html_e( 'Customer', 'business-builder' ); ?></h3>
                <div class="bb-receipt-section-grid bb-receipt-section-grid-2">
                    <div class="bb-receipt-meta-block">
                        <span class="bb-receipt-meta-label"><?php esc_html_e( 'Name', 'business-builder' ); ?></span>
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
                        <span class="bb-receipt-meta-label"><?php esc_html_e( 'Phone', 'business-builder' ); ?></span>
                        <span class="bb-receipt-meta-value">
                            <?php
                            echo esc_html(
                                '' !== $receipt->customer_phone
                                    ? $receipt->customer_phone
                                    : __( '—', 'business-builder' )
                            );
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="bb-receipt-section">
                <h3 class="bb-receipt-section-title"><?php esc_html_e( 'Service', 'business-builder' ); ?></h3>
                <div class="bb-receipt-rows">
                    <div class="bb-receipt-row">
                        <dt class="bb-receipt-row-label"><?php esc_html_e( 'Service', 'business-builder' ); ?></dt>
                        <dd class="bb-receipt-row-value"><?php echo esc_html( $receipt->description ); ?></dd>
                    </div>
                    <div class="bb-receipt-row">
                        <dt class="bb-receipt-row-label"><?php esc_html_e( 'Type', 'business-builder' ); ?></dt>
                        <dd class="bb-receipt-row-value"><?php echo esc_html( $type_label ); ?></dd>
                    </div>
                    <?php if ( '' !== $receipt->object_reference ) : ?>
                        <div class="bb-receipt-row">
                            <dt class="bb-receipt-row-label">
                                <?php
                                echo esc_html(
                                    'appointment' === $receipt->object_type
                                        ? __( 'Appointment No', 'business-builder' )
                                        : __( 'Consultation No', 'business-builder' )
                                );
                                ?>
                            </dt>
                            <dd class="bb-receipt-row-value"><?php echo esc_html( $receipt->object_reference ); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php foreach ( $receipt->extra_rows as $extra ) : ?>
                        <?php
                        $extra_label = isset( $extra['label'] ) ? (string) $extra['label'] : '';
                        $extra_value = isset( $extra['value'] ) ? (string) $extra['value'] : '';

                        if ( '' === $extra_label || '' === $extra_value ) {
                            continue;
                        }
                        ?>
                        <div class="bb-receipt-row">
                            <dt class="bb-receipt-row-label"><?php echo esc_html( $extra_label ); ?></dt>
                            <dd class="bb-receipt-row-value"><?php echo esc_html( $extra_value ); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bb-receipt-section">
                <h3 class="bb-receipt-section-title"><?php esc_html_e( 'Payment Information', 'business-builder' ); ?></h3>
                <div class="bb-receipt-rows">
                    <?php foreach ( $payment_rows as $label => $value ) : ?>
                        <?php if ( '' === (string) $value ) { continue; } ?>
                        <div class="bb-receipt-row">
                            <dt class="bb-receipt-row-label"><?php echo esc_html( $label ); ?></dt>
                            <dd class="bb-receipt-row-value"><?php echo esc_html( (string) $value ); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ( '' !== $receipt->proof_url ) : ?>
                <div class="bb-receipt-proof">
                    <span class="bb-receipt-meta-label"><?php esc_html_e( 'Payment Proof', 'business-builder' ); ?></span>
                    <?php if ( 0 === strpos( $receipt->proof_mime, 'image/' )) : ?>
                        <a href="<?php echo esc_url( $receipt->proof_url ); ?>" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo esc_url( $receipt->proof_url ); ?>" alt="<?php esc_attr_e( 'Uploaded payment proof', 'business-builder' ); ?>" loading="lazy" />
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url( $receipt->proof_url ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e( 'Open uploaded payment proof', 'business-builder' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p class="bb-receipt-note">
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
     * Build the PAYMENT INFORMATION rows (label => value).
     *
     * A dedicated, ordered view of the payment block that keeps the new
     * receipt layout's information hierarchy (method -> references -> amount
     * -> currency -> date -> status). Empty values are dropped by the
     * renderer, so the block stays clean for every gateway.
     *
     * @param Receipt $receipt Receipt.
     * @return array<string, string>
     */
    protected function payment_rows( Receipt $receipt ): array {

        $rows = array();

        $rows[ __( 'Payment Method', 'business-builder' ) ] = $receipt->gateway_name;

        if ( '' !== $receipt->reference ) {
            $rows[ __( 'Payment Reference', 'business-builder' ) ] = $receipt->reference;
        }

        /*
         * A manual gateway's customer-supplied transaction reference is
         * carried in the receipt's extra rows; surface it here as the
         * "Customer Transaction Reference" so the receipt matches the
         * dashboard wording while keeping the value single-sourced.
         */
        foreach ( $receipt->extra_rows as $extra ) {

            $extra_label = isset( $extra['label'] ) ? (string) $extra['label'] : '';
            $extra_value = isset( $extra['value'] ) ? (string) $extra['value'] : '';

            if ( '' === $extra_label || '' === $extra_value ) {
                continue;
            }

            if ( __( 'Transaction Reference', 'business-builder' ) === $extra_label ) {
                $rows[ __( 'Customer Transaction Reference', 'business-builder' ) ] = $extra_value;
            }
        }

        if ( '' !== $receipt->gateway_reference ) {
            $rows[ __( 'Gateway Reference', 'business-builder' ) ] = $receipt->gateway_reference;
        }

        $rows[ __( 'Amount', 'business-builder' ) ]   = $receipt->amount_display;
        $rows[ __( 'Currency', 'business-builder' ) ] = $receipt->currency;
        $rows[ __( 'Payment Date', 'business-builder' ) ] = $receipt->created_at;
        $rows[ __( 'Status', 'business-builder' ) ]   = $receipt->status_label;

        /**
         * Filter the receipt PAYMENT rows (label => value).
         *
         * @param array<string, string> $rows    Rows.
         * @param Receipt               $receipt Receipt.
         */
        return apply_filters( 'bb_payment_receipt_payment_rows', $rows, $receipt );
    }


    /**
     * Inline, print-friendly styles.
     *
     * @return string
     */
    protected function styles(): string {

        return '<style class="bb-receipt-style">'
            . '.bb-receipt{max-width:760px;margin:0 auto;padding:0;border:1px solid #e5e7eb;'
            . 'border-radius:16px;background:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;'
            . 'color:#0f172a;overflow:hidden;box-shadow:0 18px 44px rgba(15,23,42,.08);}'
            /* Brand header */
            . '.bb-receipt-brand{display:flex;align-items:center;gap:14px;'
            . 'padding:24px 28px;background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);color:#fff;}'
            . '.bb-receipt-brand-left{display:flex;align-items:center;gap:14px;min-width:0;}'
            . '.bb-receipt-logo{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;'
            . 'border-radius:12px;background:#fff;overflow:hidden;flex:0 0 auto;}'
            . '.bb-receipt-logo img{max-width:46px;max-height:46px;width:auto;height:auto;}'
            . '.bb-receipt-brand-text{display:flex;flex-direction:column;gap:3px;min-width:0;}'
            . '.bb-receipt-business{font-weight:800;font-size:1.15rem;letter-spacing:.01em;}'
            . '.bb-receipt-doc-title{font-size:.76rem;letter-spacing:.16em;color:#cbd5e1;font-weight:700;}'
            /* Status banner */
            . '.bb-receipt-banner{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;'
            . 'padding:14px 28px;background:#f8fafc;border-bottom:1px solid #e5e7eb;}'
            . '.bb-receipt-banner-status{padding:.34rem .9rem;border-radius:999px;font-weight:800;font-size:.78rem;'
            . 'letter-spacing:.06em;background:#f1f5f9;color:#334155;}'
            . '.bb-receipt-banner.is-paid .bb-receipt-banner-status{background:#dcfce7;color:#166534;}'
            . '.bb-receipt-banner.is-pending .bb-receipt-banner-status{background:#fef3c7;color:#92400e;}'
            . '.bb-receipt-banner.is-failed .bb-receipt-banner-status{background:#fee2e2;color:#991b1b;}'
            . '.bb-receipt-banner-no{display:inline-flex;align-items:baseline;gap:8px;}'
            . '.bb-receipt-banner-no-label{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:#64748b;font-weight:700;}'
            . '.bb-receipt-banner-no-value{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.98rem;font-weight:800;color:#0f172a;}'
            /* Sections */
            . '.bb-receipt-section{padding:18px 28px 4px;border-bottom:1px solid #f1f5f9;}'
            . '.bb-receipt-section:last-of-type{border-bottom:0;}'
            . '.bb-receipt-section-title{margin:0 0 10px;font-size:.74rem;letter-spacing:.12em;text-transform:uppercase;'
            . 'color:#1d4ed8;font-weight:800;}'
            . '.bb-receipt-section-grid{display:grid;gap:1px;background:#e5e7eb;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;}'
            . '.bb-receipt-section-grid-2{grid-template-columns:repeat(2,1fr);}'
            . '.bb-receipt-meta-block{background:#fff;padding:14px 18px;display:flex;flex-direction:column;gap:4px;}'
            . '.bb-receipt-meta-label{font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:#64748b;font-weight:700;}'
            . '.bb-receipt-meta-value{font-size:.95rem;font-weight:700;color:#0f172a;word-break:break-word;}'
            . '.bb-receipt-rows{display:grid;}'
            . '.bb-receipt-row{display:flex;justify-content:space-between;gap:16px;align-items:baseline;'
            . 'padding:11px 0;border-bottom:1px solid #f1f5f9;}'
            . '.bb-receipt-row:last-child{border-bottom:0;}'
            . '.bb-receipt-row-label{margin:0;font-size:.85rem;color:#64748b;font-weight:600;}'
            . '.bb-receipt-row-value{margin:0;font-size:.9rem;font-weight:700;color:#0f172a;text-align:end;'
            . 'font-family:ui-monospace,Menlo,Consolas,monospace;word-break:break-all;}'
            . '.bb-receipt-proof{margin:14px 28px 0;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;display:flex;flex-direction:column;gap:8px;align-items:flex-start;}'
            . '.bb-receipt-proof img{display:block;max-width:260px;max-height:220px;border:1px solid #cbd5e1;border-radius:8px;}'
            . '.bb-receipt-note{font-size:.85rem;color:#334155;margin:16px 28px 0;padding:12px 14px;'
            . 'background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;line-height:1.55;}'
            . '.bb-receipt-actions{margin:0;padding:20px 28px 26px;display:flex;gap:12px;flex-wrap:wrap;}'
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
            . '#bb-payment-receipt .bb-receipt-banner{background:#fff!important;border-bottom:1px solid #0f172a;}'
            . '.bb-receipt-banner.is-paid .bb-receipt-banner-status{background:#fff!important;color:#166534!important;border:1px solid #166534;}'
            . '.bb-receipt-banner.is-pending .bb-receipt-banner-status{background:#fff!important;color:#92400e!important;border:1px solid #92400e;}'
            . '.bb-receipt-banner.is-failed .bb-receipt-banner-status{background:#fff!important;color:#991b1b!important;border:1px solid #991b1b;}'
            . 'header,footer,nav,.bb-header-bar,.bb-footer-bar,.bb-section-heading[data-bb-nav],'
            . '.wp-admin-bar,.bb-page-controls,.bb-pay-step-back{display:none!important;}'
            . '}'
            . '@media (max-width:560px){.bb-receipt-section-grid-2{grid-template-columns:1fr;}}'
            . '</style>';
    }
}
