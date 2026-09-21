<?php
namespace BusinessBuilderCore\Packs\LawFirm\Frontend;

use BusinessBuilderCore\Core\Payments\Receipt\ReceiptPage;

defined( 'ABSPATH' ) || exit;

/**
 * Dedicated front-end receipt route (fallback / direct access).
 *
 * Serves a professional standalone receipt ONLY at:
 *   /receipt/{TXN-XXXXXXXXXX}/
 *
 * IMPORTANT: it deliberately does NOT fire on the ?bb_ref= query string.
 * The payment callback returns the customer to the ORIGIN page with
 * ?bb_checkout=paid&bb_ref=…, and that page must render normally (its own
 * on-screen receipt modal). Treating ?bb_ref= as a receipt request here
 * replaced the whole consultation/appointment page with a bare receipt.
 *
 * Security is inherited from ReceiptPage (public-reference shape check,
 * per-visitor rate limiting, status gate). Only a settled or pending-manual
 * transaction ever renders.
 */
class ReceiptRoute {

    /**
     * Query var carrying the public reference (set by the rewrite rule).
     */
    public const QUERY_VAR = 'bb_receipt_ref';

    /**
     * Receipt page (owns all lookup + security).
     *
     * @var ReceiptPage
     */
    protected ReceiptPage $receipt_page;

    /**
     * Constructor.
     *
     * @param ReceiptPage $receipt_page Receipt page.
     */
    public function __construct( ReceiptPage $receipt_page ) {
        $this->receipt_page = $receipt_page;
    }

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action( 'init', array( $this, 'add_rewrite_rule' ) );
        add_filter( 'query_vars', array( $this, 'add_query_var' ) );
        add_action( 'template_redirect', array( $this, 'maybe_render' ) );

        /*
         * Inline receipt endpoint: lets ANY page (payment success, status
         * lookup, dashboard) fetch a receipt by public reference and show
         * it on the SAME screen, without navigation.
         */
        add_action( 'wp_ajax_nopriv_bb_receipt_inline', array( $this, 'handle_ajax' ) );
        add_action( 'wp_ajax_bb_receipt_inline', array( $this, 'handle_ajax' ) );
    }

    /**
     * AJAX: return the receipt HTML for a public reference (inline display).
     *
     * Security is inherited from ReceiptPage::render_for_reference -
     * public reference shape validation, per-visitor rate limiting, and a
     * status gate (only a settled / pending-manual transaction renders).
     */
    public function handle_ajax(): void {

        check_ajax_referer( 'bb_receipt_inline', 'nonce' );

        $reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] )) : '';

        if ( '' === $reference ) {
            wp_send_json_error( array( 'message' => __( 'No reference supplied.', 'business-builder' ) ), 400 );
        }

        $html = $this->receipt_page->render_for_reference( $reference );

        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * Render the standalone receipt document when the dedicated route is hit.
     */
    public function maybe_render(): void {

        $reference = $this->detect_reference();

        if ( '' === $reference ) {
            return;
        }

        status_header( 200 );
        nocache_headers();

        $this->render_document( $reference );

        exit;
    }

    /**
     * The requested public reference (dedicated route only).
     *
     * @return string Reference, or '' when not the receipt route.
     */
    protected function detect_reference(): string {

        $qv = get_query_var( self::QUERY_VAR );

        if ( is_string( $qv ) && '' !== $qv ) {
            return sanitize_text_field( $qv );
        }

        /*
         * Fallback for sites where the /receipt/{ref}/ rewrite rule has not
         * been flushed yet: serve the standalone receipt for a direct
         * ?bb_ref= visit.
         *
         * It is DELIBERATELY suppressed whenever the request carries an
         * in-page payment/return context flag. That is the case for BOTH:
         *
         *   - a gateway return  : ?bb_checkout=paid|pending&bb_ref=…
         *   - a manual return   : ?bb_consult=pending&bb_ref=… (consultation)
         *                         ?bb_booking=pending&bb_ref=… (appointment)
         *
         * Those requests must render the ORIGINAL consultation/appointment
         * page — where the in-page receipt modal opens automatically — and
         * must NEVER be replaced by this standalone document. Only a truly
         * bare ?bb_ref= (no other payment context) is treated as a direct
         * receipt visit.
         */
        $in_page_context = isset( $_GET['bb_checkout'] )
            || isset( $_GET['bb_consult'] )
            || isset( $_GET['bb_booking'] );

        if ( ! $in_page_context && isset( $_GET['bb_ref'] )) {
            $candidate = sanitize_text_field( wp_unslash( $_GET['bb_ref'] ));

            if ( \BusinessBuilderCore\Core\Payments\Transaction\Reference::is_valid( $candidate )) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Emit a minimal, self-contained receipt document.
     *
     * @param string $reference Public reference.
     */
    protected function render_document( string $reference ): void {

        $charset = get_bloginfo( 'charset' );
        $lang    = get_language_attributes();
        $site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $home    = home_url( '/' );

        $body = $this->receipt_page->render_for_reference( $reference );

        header( 'Content-Type: text/html; charset=' . $charset );

        ?>
<!DOCTYPE html>
<html <?php echo $lang; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP attribute string. ?>>
<head>
<meta charset="<?php echo esc_attr( $charset ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<title><?php echo esc_html( sprintf( __( 'Receipt - %s', 'business-builder' ), $site ) ); ?></title>
<style>
body{margin:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#0f172a;padding:28px 16px}
.bb-receipt-standalone{max-width:760px;margin:0 auto}
.bb-receipt-standalone-back{display:inline-block;margin-bottom:14px;color:#1d4ed8;text-decoration:none;font-weight:700;font-size:.85rem}
</style>
</head>
<body class="<?php echo is_rtl() ? 'bb-rtl' : 'bb-ltr'; ?>">
    <div class="bb-receipt-standalone">
        <a class="bb-receipt-standalone-back" href="<?php echo esc_url( $home ); ?>">&larr; <?php echo esc_html( $site ); ?></a>
        <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ReceiptPage escapes all output. ?>
    </div>
</body>
</html>
        <?php
    }

    /**
     * Add the /receipt/{ref}/ rewrite rule.
     */
    public function add_rewrite_rule(): void {

        add_rewrite_rule(
            '^receipt/([A-Za-z0-9\-]+)/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    /**
     * Register the query var.
     *
     * @param string[] $vars Vars.
     * @return string[]
     */
    public function add_query_var( $vars ) {

        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /**
     * The canonical receipt URL for a public reference (on the CURRENT site).
     *
     * @param string $reference Public transaction reference.
     * @return string
     */
    public static function url( string $reference ): string {

        $reference = sanitize_text_field( $reference );

        if ( '' === $reference ) {
            return home_url( '/' );
        }

        /*
         * Use the pretty /receipt/{ref}/ form ONLY when that rewrite rule is
         * actually present; otherwise use ?bb_ref= (which ReceiptRoute also
         * serves as a fallback). This guarantees the dashboard's "View
         * Receipt" link resolves on every site, even before rules are
         * flushed, instead of 404-ing to the theme's default page.
         */
        $permalink = (string) get_option( 'permalink_structure' );

        if ( '' !== $permalink ) {

            $rules = get_option( 'rewrite_rules', array() );
            $has_rule = false;

            if ( is_array( $rules )) {
                foreach ( array_keys( $rules ) as $rule ) {
                    if ( false !== strpos( (string) $rule, 'receipt/' )) {
                        $has_rule = true;
                        break;
                    }
                }
            }

            if ( $has_rule ) {
                return home_url( '/receipt/' . rawurlencode( $reference ) . '/' );
            }
        }

        return add_query_arg( 'bb_ref', $reference, home_url( '/' ) );
    }
}
