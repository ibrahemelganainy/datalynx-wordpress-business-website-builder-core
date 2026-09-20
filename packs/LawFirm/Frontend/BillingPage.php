<?php

namespace BusinessBuilderCore\Packs\LawFirm\Frontend;

use BusinessBuilderCore\Core\Payments\PaymentManager;
use BusinessBuilderCore\Core\Payments\Currencies;
use BusinessBuilderCore\Core\Payments\Checkout\PaymentCheckout;
use BusinessBuilderCore\Core\Audit\AuditLog;
use BusinessBuilderCore\Packs\LawFirm\Payments\PaymentFlow;

if ( ! defined( 'ABSPATH' ))  {
    exit;
}

/**
 * Dedicated Paymob billing page.
 *
 * A standalone payment portal served at /paymob-billing/{ref}/ (own document,
 * no theme chrome) for the gateway(s) that require billing_data (Paymob).
 *
 * Flow: the main consultation/booking form creates the pending record, stores
 * a payment snapshot + a one-time token, and redirects here. This page
 * verifies the token, collects the billing details, and on submit combines
 * them with the saved order to start the Paymob checkout.
 *
 * Security: token-gated (anti-IDOR), nonce-protected, server-side sanitized;
 * amount/currency are re-read from the saved snapshot, never the browser.
 */
class BillingPage {

    public const QUERY_VAR = 'bb_billing_ref';
    public const ACTION = 'bb_paymob_billing';
    public const NONCE_ACTION = 'bb_paymob_billing';
    public const NONCE_FIELD = 'bb_paymob_billing_nonce';

    /**
     * Register hooks.
     */
    public function register(): void {
        add_action( 'init', array( $this, 'add_rewrite_rule' ) );
        add_filter( 'query_vars', array( $this, 'add_query_var' ) );
        /* Two-layer interception: never fall back to the theme homepage. */
        add_action( 'parse_request', array( $this, 'maybe_render' ), 1 );
        add_action( 'template_redirect', array( $this, 'maybe_render' ) );
        add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_submit' ) );
        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submit' ) );
    }

    /**
     * Add the /paymob-billing/{ref}/ rewrite rule.
     */
    public function add_rewrite_rule(): void {
        add_rewrite_rule(
            '^paymob-billing/([A-Za-z0-9\-]+)/?$',
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
     * Build the public billing page URL.
     *
     * @param string $ref   Public reference.
     * @param string $token One-time token.
     * @return string
     */
    public static function url( string $ref, string $token ): string {

        $ref_enc   = rawurlencode( $ref );
        $token_enc = rawurlencode( $token );
        $permalink = (string) get_option( 'permalink_structure' );

        if ( '' !== $permalink ) {
            return home_url( '/paymob-billing/' . $ref_enc . '/?token=' . $token_enc );
        }

        $args = array(
            self::QUERY_VAR => $ref_enc,
            'token'         => $token_enc,
        );

        return add_query_arg( $args, home_url( '/' ) );
    }

    /**
     * Detect the billing reference from the current request (robust).
     *
     * Works even when the pretty rewrite rule did not fire:
     *   1. the registered query var (normal usage),
     *   2. the raw ?bb_billing_ref=... query string,
     *   3. the /paymob-billing/{ref}/ path parsed from REQUEST_URI.
     *
     * @return string Reference, or '' when not a billing route.
     */
    protected function detect_reference(): string {

        $key = self::QUERY_VAR;

        $candidates = array( $key, 'bb_billing_ref' );

        foreach ( $candidates as $candidate ) {

            $present = isset( $_GET[ $candidate ] );

            if ( $present ) {
                return sanitize_text_field( wp_unslash( $_GET[ $candidate ] ) );
            }
        }

        $qv = get_query_var( $key );

        if ( is_string( $qv ) && '' !== $qv ) {
            return sanitize_text_field( $qv );
        }

        $uri = '';

        if ( isset( $_SERVER['REQUEST_URI'] ))  {
            $uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
        }

        $path = (string) wp_parse_url( $uri, PHP_URL_PATH );

        $matched = preg_match( '#/paymob-billing/([A-Za-z0-9\-]+)#', $path, $m );

        if ( 1 === $matched && isset( $m[1] ))  {
            return sanitize_text_field( $m[1] );
        }

        return '';
    }

    /**
     * Render the standalone page when the route matches.
     */
    public function maybe_render(): void {

        $ref = $this->detect_reference();

        if ( '' === $ref ) {
            return;
        }

        $token = '';

        if ( isset( $_GET['token'] ))  {
            $token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
        }

        $order = $this->resolve_order( $ref, $token );

        status_header( null === $order ? 404 : 200 );
        nocache_headers();

        $this->render_document( $order, $token );

        exit;
    }

    /**
     * Resolve the pending order from a reference + token.
     *
     * @param string $ref   Public reference.
     * @param string $token Ownership token.
     * @return array<string, mixed>|null
     */
    protected function resolve_order( string $ref, string $token ): ?array {

        if ( '' === $ref || '' === $token ) {
            return null;
        }

        $kinds = array( 'consultation', 'appointment' );

        foreach ( $kinds as $kind ) {

            $post = $this->find_by_reference( $kind, $ref );

            if ( null === $post ) {
                continue;
            }

            $prefix = $this->meta_prefix( $kind );
            $stored = (string) get_post_meta( $post->ID, $prefix . 'payment_token', true );

            if ( '' === $stored ) {
                return null;
            }

            if ( ! hash_equals( $stored, $token ))  {
                return null;
            }

            $context = get_post_meta( $post->ID, $prefix . 'payment_context', true );

            if ( ! is_array( $context ))  {
                $context = array();
            }

            return array(
                'kind'    => $kind,
                'id'      => (int) $post->ID,
                'ref'     => $ref,
                'token'   => $token,
                'context' => $context,
            );
        }

        return null;
    }

    /**
     * Find a pending record by its public reference.
     *
     * @param string $kind Kind.
     * @param string $ref  Reference.
     * @return \WP_Post|null
     */
    protected function find_by_reference( string $kind, string $ref ): ?\WP_Post {

        $is_appt = ( 'appointment' === $kind );

        $post_type = $is_appt ? 'bb_appointment' : 'bb_consultation';
        $meta_key  = $this->meta_prefix( $kind ) . 'public_reference';

        $args = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'meta_query'     => array(
                array(
                    'key'   => $meta_key,
                    'value' => $ref,
                ),
            ),
        );

        $query = new \WP_Query( $args );

        if ( ! isset( $query->posts[0] ))  {
            return null;
        }

        return $query->posts[0];
    }

    /**
     * Meta-key prefix for a kind.
     *
     * @param string $kind Kind.
     * @return string
     */
    protected function meta_prefix( string $kind ): string {
        $is_appt = ( 'appointment' === $kind );
        return $is_appt ? '_bb_appointment_' : '_bb_consultation_';
    }

    /**
     * Emit the complete standalone HTML document.
     *
     * @param array<string, mixed>|null $order Order (or null for invalid).
     * @param string                    $token Token.
     */
    protected function render_document( ?array $order, string $token ): void {

        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $charset   = get_bloginfo( 'charset' );
        $rlt_attr  = get_language_attributes();

        header( 'Content-Type: text/html; charset=' . $charset );

        ?>
<!DOCTYPE html>
<html <?php echo $rlt_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP attribute string. ?>>
<head>
<meta charset="<?php echo esc_attr( $charset ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<title><?php echo esc_html( sprintf( __( 'Secure payment - %s', 'business-builder' ), $site_name ) ); ?></title>
<?php $this->print_styles(); ?>
</head>
<body class="bb-billing-portal <?php echo is_rtl() ? 'bb-rtl' : 'bb-ltr'; ?>">
    <main class="bb-portal-main">
        <?php
        if ( null === $order ) {
            $this->print_invalid();
        } else {
            $this->print_form( $order, $token );
        }
        ?>
    </main>
</body>
</html>
        <?php
    }

    /**
     * Print the invalid/expired card.
     */
    protected function print_invalid(): void {

        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $home      = home_url( '/' );

        ?>
        <div class="bb-portal-card bb-portal-invalid" role="alert">
            <div class="bb-portal-brand"><?php echo esc_html( $site_name ); ?></div>
            <h1 class="bb-portal-title"><?php esc_html_e( 'This payment link is no longer valid', 'business-builder' ); ?></h1>
            <p class="bb-portal-text"><?php esc_html_e( 'Please return to the website and start the payment again.', 'business-builder' ); ?></p>
            <a class="bb-portal-button" href="<?php echo esc_url( $home ); ?>"><?php esc_html_e( 'Back to website', 'business-builder' ); ?></a>
        </div>
        <?php
    }

    /**
     * Print the billing form card for a pending order.
     *
     * @param array<string, mixed> $order Order.
     * @param string               $token Token.
     */
    protected function print_form( array $order, string $token ): void {

        $kind    = (string) $order['kind'];
        $ref     = (string) $order['ref'];
        $context = $order['context'];

        $gateway_id = isset( $context['gateway'] ) ? (string) $context['gateway'] : 'paymob';
        $fee        = isset( $context['fee'] ) ? (string) $context['fee'] : '';
        $currency   = isset( $context['currency'] ) ? (string) $context['currency'] : '';

        $manager = new PaymentManager();
        $gateway = $manager->gateway( $gateway_id );

        $gw_name = $gateway_id;
        $gw_logo = '';

        if ( $gateway ) {
            $gw_name = $gateway->get_name();
            $gw_logo = $gateway->get_logo_url();
        }

        $amount    = Currencies::format( (float) $fee, $currency, true );
        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $action    = admin_url( 'admin-post.php' );

        $label = __( 'Consultation payment', 'business-builder' );

        if ( 'appointment' === $kind ) {
            $label = __( 'Appointment payment', 'business-builder' );
        }

        ?>
        <div class="bb-portal-card">

            <div class="bb-portal-brand"><?php echo esc_html( $site_name ); ?></div>

            <div class="bb-portal-head">
                <?php if ( '' !== $gw_logo ) : ?>
                    <span class="bb-portal-logo"><img src="<?php echo esc_url( $gw_logo ); ?>" alt="<?php echo esc_attr( $gw_name ); ?>" width="44" height="44" /></span>
                <?php endif; ?>
                <div>
                    <h1 class="bb-portal-title"><?php echo esc_html( $label ); ?></h1>
                    <p class="bb-portal-sub">
                        <?php
                        printf(
                            /* translators: %s: gateway name */
                            esc_html__( 'Secure payment with %s', 'business-builder' ),
                            esc_html( $gw_name )
                        );
                        ?>
                    </p>
                </div>
            </div>

            <div class="bb-portal-summary">
                <div class="bb-portal-summary-row">
                    <span class="bb-portal-summary-label"><?php esc_html_e( 'Reference', 'business-builder' ); ?></span>
                    <span class="bb-portal-summary-ref"><?php echo esc_html( $ref ); ?></span>
                </div>
                <div class="bb-portal-summary-row">
                    <span class="bb-portal-summary-label"><?php esc_html_e( 'Amount due', 'business-builder' ); ?></span>
                    <span class="bb-portal-summary-amount"><?php echo esc_html( $amount ); ?></span>
                </div>
            </div>

            <form class="bb-portal-form" method="post" action="<?php echo esc_url( $action ); ?>" novalidate>
                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
                <input type="hidden" name="bb_ref" value="<?php echo esc_attr( $ref ); ?>" />
                <input type="hidden" name="bb_token" value="<?php echo esc_attr( $token ); ?>" />
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

                <h2 class="bb-portal-section-title"><?php esc_html_e( 'Billing details', 'business-builder' ); ?></h2>
                <p class="bb-portal-hint"><?php esc_html_e( 'These details are required by the payment provider. We never store your card details.', 'business-builder' ); ?></p>

                <div class="bb-portal-grid">
                    <?php $this->print_field( 'bb_bf_first', 'bb_billing_first_name', 'first_name', __( 'First Name', 'business-builder' ), 'given-name', 'text', 'e.g. Amina', false ); ?>
                    <?php $this->print_field( 'bb_bf_last', 'bb_billing_last_name', 'last_name', __( 'Last Name', 'business-builder' ), 'family-name', 'text', 'e.g. Youssef', false ); ?>
                    <?php $this->print_field( 'bb_bf_email', 'bb_billing_email', 'email', __( 'Email Address', 'business-builder' ), 'email', 'email', 'you@example.com', true ); ?>
                    <?php $this->print_field( 'bb_bf_phone', 'bb_billing_phone', 'phone', __( 'Phone Number', 'business-builder' ), 'tel', 'tel', 'e.g. 01012345678', true ); ?>
                </div>

                <button type="submit" class="bb-portal-button bb-portal-submit" data-bb-portal-submit>
                    <span class="bb-portal-spinner" aria-hidden="true"></span>
                    <span class="bb-portal-submit-text">
                        <?php
                        printf(
                            /* translators: %s: formatted amount */
                            esc_html__( 'Proceed to Pay %s', 'business-builder' ),
                            esc_html( $amount )
                        );
                        ?>
                    </span>
                </button>

                <p class="bb-portal-secure"><span aria-hidden="true">&#128274;</span> <?php esc_html_e( 'Your connection is secure. Card details are entered only at the provider.', 'business-builder' ); ?></p>
            </form>

        </div>
        <?php $this->print_script(); ?>
        <?php
    }

    /**
     * Print one billing field.
     *
     * @param string $id      Input id.
     * @param string $name    Input name.
     * @param string $key     Billing key.
     * @param string $label   Label.
     * @param string $autocap Autocomplete.
     * @param string $type    Input type.
     * @param string $holder  Placeholder.
     * @param bool   $wide    Full width.
     */
    protected function print_field( string $id, string $name, string $key, string $label, string $autocap, string $type, string $holder, bool $wide ): void {

        $class = 'bb-portal-field';
        if ( $wide ) {
            $class .= ' bb-portal-field-wide';
        }

        ?>
        <div class="<?php echo esc_attr( $class ); ?>">
            <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?> <span class="bb-portal-req">*</span></label>
            <input
                type="<?php echo esc_attr( $type ); ?>"
                id="<?php echo esc_attr( $id ); ?>"
                name="<?php echo esc_attr( $name ); ?>"
                autocomplete="<?php echo esc_attr( $autocap ); ?>"
                data-bb-billing-field="<?php echo esc_attr( $key ); ?>"
                placeholder="<?php echo esc_attr( $holder ); ?>"
            />
            <span class="bb-portal-error" data-bb-billing-error="<?php echo esc_attr( $key ); ?>" role="alert" aria-live="polite"></span>
        </div>
        <?php
    }

    /**
     * Print the isolated inline stylesheet.
     */
    protected function print_styles(): void {
        ?>
<style>
*{box-sizing:border-box}
body.bb-billing-portal{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(160deg,#eef3fb 0%,#f7f9fc 60%,#eef1f6 100%);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#0f172a;padding:24px}
.bb-portal-main{width:100%;max-width:560px}
.bb-portal-card{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:20px;padding:30px;box-shadow:0 24px 60px rgba(15,23,42,.12);animation:bbIn .3s ease both}
@keyframes bbIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@media (prefers-reduced-motion:reduce){.bb-portal-card{animation:none}}
.bb-portal-brand{font-weight:800;font-size:.9rem;letter-spacing:.02em;color:#1d4ed8;margin-bottom:16px}
.bb-portal-head{display:flex;align-items:center;gap:14px;padding-bottom:16px;border-bottom:1px solid rgba(15,23,42,.08)}
.bb-portal-logo{display:inline-flex;align-items:center;justify-content:center;width:52px;height:52px;border-radius:12px;background:#f8fafc;border:1px solid rgba(15,23,42,.06)}
.bb-portal-logo img{max-width:36px;max-height:36px;width:auto;height:auto}
.bb-portal-title{margin:0;font-size:1.3rem;font-weight:800}
.bb-portal-sub{margin:3px 0 0;font-size:.9rem;color:#64748b}
.bb-portal-summary{margin:18px 0;padding:14px 16px;border-radius:14px;background:#f0f6ff;border:1px solid rgba(29,78,216,.14);display:flex;flex-direction:column;gap:8px}
.bb-portal-summary-row{display:flex;justify-content:space-between;gap:14px;align-items:baseline}
.bb-portal-summary-label{font-size:.85rem;font-weight:600;color:#475569}
.bb-portal-summary-ref{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.85rem;color:#0f172a}
.bb-portal-summary-amount{font-size:1.25rem;font-weight:800;color:#0f172a}
.bb-portal-section-title{margin:6px 0 4px;font-size:1rem;font-weight:800}
.bb-portal-hint{margin:0 0 16px;font-size:.85rem;color:#64748b;line-height:1.5}
.bb-portal-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.bb-portal-field{display:flex;flex-direction:column;gap:6px}
.bb-portal-field-wide{grid-column:1 / -1}
.bb-portal-field label{font-size:.85rem;font-weight:600}
.bb-portal-req{color:#d63638}
.bb-portal-field input{width:100%;padding:12px 14px;border:1.5px solid rgba(15,23,42,.16);border-radius:11px;font:inherit;background:#fff;transition:border-color .15s,box-shadow .15s}
.bb-portal-field input:hover{border-color:rgba(15,23,42,.3)}
.bb-portal-field input:focus{outline:none;border-color:#1d4ed8;box-shadow:0 0 0 4px rgba(29,78,216,.14)}
.bb-portal-field input[aria-invalid="true"]{border-color:#d63638;background:#fff9f9}
.bb-portal-error{display:none;font-size:.78rem;color:#b32d2e}
.bb-portal-error.is-visible{display:block}
.bb-portal-button{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;margin-top:20px;padding:15px 18px;border:0;border-radius:12px;background:#1d4ed8;color:#fff;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;transition:background .15s,transform .05s}
.bb-portal-button:hover{background:#1a44be}
.bb-portal-button:active{transform:translateY(1px)}
.bb-portal-button[disabled]{opacity:.75;cursor:progress}
.bb-portal-spinner{display:none;width:18px;height:18px;border-radius:50%;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;animation:bbSpin .7s linear infinite}
.bb-portal-submit.is-loading .bb-portal-spinner{display:inline-block}
@keyframes bbSpin{to{transform:rotate(360deg)}}
.bb-portal-secure{margin:14px 0 0;font-size:.8rem;color:#64748b;text-align:center}
.bb-portal-invalid{text-align:center}
.bb-portal-text{color:#64748b}
.bb-rtl{direction:rtl}
@media (max-width:560px){.bb-portal-card{padding:22px 18px}.bb-portal-grid{grid-template-columns:1fr}}
</style>
        <?php
    }

    /**
     * Print the dependency-free validation + loading-state script.
     */
    protected function print_script(): void {
        ?>
<script>
(function(){
 "use strict";
 var form=document.querySelector('.bb-portal-form');
 if(!form){return;}
 var btn=form.querySelector('[data-bb-portal-submit]');
 var inputs={};
 form.querySelectorAll('[data-bb-billing-field]').forEach(function(i){inputs[i.getAttribute('data-bb-billing-field')]=i;});
 var errs={};
 form.querySelectorAll('[data-bb-billing-error]').forEach(function(e){errs[e.getAttribute('data-bb-billing-error')]=e;});
 function validEmail(v){return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v).trim());}
 function validPhone(v){var d=String(v).replace(/[^0-9]/g,'');return d.length>=6;}
 function errFor(k,v){v=String(v).trim();
  if(k==='email'){if(v===''){return 'Please enter your email address.';}if(!validEmail(v)){return 'Please enter a valid email address.';}return '';}
  if(k==='phone'){if(v===''){return 'Please enter your phone number.';}if(!validPhone(v)){return 'Phone number must contain digits only.';}return '';}
  return v===''?'This field is required.':'';}
 function setErr(i,e,m){if(!i){return;}if(m){i.setAttribute('aria-invalid','true');}else{i.removeAttribute('aria-invalid');}
  if(e){e.textContent=m;e.classList.toggle('is-visible',m!=='');}}
 Object.keys(inputs).forEach(function(k){var i=inputs[k];
  i.addEventListener('blur',function(){setErr(i,errs[k],errFor(k,i.value));});
  i.addEventListener('input',function(){if(i.getAttribute('aria-invalid')==='true'){setErr(i,errs[k],errFor(k,i.value));}});});
 form.addEventListener('submit',function(ev){var first=null;
  Object.keys(inputs).forEach(function(k){var i=inputs[k];var m=errFor(k,i.value);setErr(i,errs[k],m);if(m&&!first){first=i;}});
  if(first){ev.preventDefault();first.focus();first.scrollIntoView({behavior:'smooth',block:'center'});return;}
  if(btn){btn.classList.add('is-loading');btn.setAttribute('disabled','disabled');
   var t=btn.querySelector('.bb-portal-submit-text');if(t){t.textContent='Processing...';}}});
})();
</script>
        <?php
    }

    /**
     * Handle the billing submission.
     */
    public function handle_submit(): void {

        $nonce_field = self::NONCE_FIELD;

        if ( ! isset( $_POST[ $nonce_field ] ))  {
            $this->fail();
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );

        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ))  {
            $this->fail();
        }

        $ref   = '';
        $token = '';

        if ( isset( $_POST['bb_ref'] ))  {
            $ref = sanitize_text_field( wp_unslash( $_POST['bb_ref'] ) );
        }

        if ( isset( $_POST['bb_token'] ))  {
            $token = sanitize_text_field( wp_unslash( $_POST['bb_token'] ) );
        }

        $order = $this->resolve_order( $ref, $token );

        if ( null === $order ) {
            $this->fail();
        }

        $billing = $this->collect_billing();

        if ( '' === $billing['first_name'] || '' === $billing['last_name'] ) {
            $this->fail();
        }

        if ( '' === $billing['email'] || '' === $billing['phone'] ) {
            $this->fail();
        }

        $context = $order['context'];

        $kind       = (string) $order['kind'];
        $gateway_id = 'paymob';

        if ( isset( $context['gateway'] ))  {
            $gateway_id = sanitize_key( (string) $context['gateway'] );
        }

        $context_gateways = array();

        if ( isset( $context['gateways'] ) && is_array( $context['gateways'] ))  {
            $context_gateways = $context['gateways'];
        }

        if ( empty( $context_gateways ))  {
            $context_gateways = array( $gateway_id );
        }

        $fee      = isset( $context['fee'] ) ? (string) $context['fee'] : '';
        $currency = isset( $context['currency'] ) ? (string) $context['currency'] : '';

        $config = array(
            'enabled'  => true,
            'payable'  => true,
            'fee'      => $fee,
            'currency' => $currency,
            'gateways' => $context_gateways,
        );

        $payments = new PaymentManager();

        $flow = new PaymentFlow(
            new PaymentCheckout( $payments, new AuditLog() ),
            $payments,
            new AuditLog()
        );

        $label = __( 'Legal Consultation', 'business-builder' );

        if ( 'appointment' === $kind ) {
            $label = __( 'Appointment Booking', 'business-builder' );
        }

        /*
         * Record the originating page path so the gateway callback returns
         * the customer to the same page (permalink-agnostic).
         */
        $billing['origin'] = $this->origin_path();

        $result = $flow->start(
            $kind,
            (int) $order['id'],
            $config,
            $gateway_id,
            $label,
            $billing['email'],
            $billing
        );

        $type = isset( $result['type'] ) ? (string) $result['type'] : '';

        if ( 'redirect' === $type && ! empty( $result['url'] ))  {
            wp_redirect( (string) $result['url'] );
            exit;
        }

        if ( 'manual' === $type || 'reference' === $type ) {
            $home = home_url( '/' );
            wp_safe_redirect( $home );
            exit;
        }

        $this->fail();
    }

    /**
     * The local path of the page the customer came from.
     *
     * @return string
     */
    protected function origin_path(): string {

        $referer = wp_get_referer();

        if ( ! $referer ) {
            return '/';
        }

        $path = (string) wp_parse_url( $referer, PHP_URL_PATH );

        return '' !== $path ? $path : '/';
    }

    /**
     * Sanitize the billing inputs.
     *
     * @return array<string, string>
     */
    protected function collect_billing(): array {

        $first = '';
        $last  = '';
        $email = '';
        $phone = '';

        if ( isset( $_POST['bb_billing_first_name'] ))  {
            $first = sanitize_text_field( wp_unslash( $_POST['bb_billing_first_name'] ) );
        }

        if ( isset( $_POST['bb_billing_last_name'] ))  {
            $last = sanitize_text_field( wp_unslash( $_POST['bb_billing_last_name'] ) );
        }

        if ( isset( $_POST['bb_billing_email'] ))  {
            $email = sanitize_email( wp_unslash( $_POST['bb_billing_email'] ) );
        }

        if ( isset( $_POST['bb_billing_phone'] ))  {
            $phone = sanitize_text_field( wp_unslash( $_POST['bb_billing_phone'] ) );
        }

        $full = trim( $first . ' ' . $last );

        return array(
            'first_name' => $first,
            'last_name'  => $last,
            'name'       => $full,
            'email'      => $email,
            'phone'      => $phone,
            'country'    => 'EG',
        );
    }

    /**
     * Redirect invalid submitters back to the site home.
     */
    protected function fail(): void {

        $target = add_query_arg( 'bb_checkout', 'error', home_url( '/' ) );

        wp_safe_redirect( $target );
        exit;
    }

    /**
     * Store a pending-payment snapshot + one-time token on a record.
     *
     * @param string $kind    'consultation' | 'appointment'.
     * @param int    $id      Record id.
     * @param array  $context Payment context.
     * @return string The one-time token.
     */
    public static function store_pending( string $kind, int $id, array $context ): string {

        $is_appt = ( 'appointment' === $kind );

        $prefix = $is_appt ? '_bb_appointment_' : '_bb_consultation_';

        $token = wp_generate_password( 40, false, false );

        update_post_meta( $id, $prefix . 'payment_context', $context );
        update_post_meta( $id, $prefix . 'payment_token', $token );

        return $token;
    }
}