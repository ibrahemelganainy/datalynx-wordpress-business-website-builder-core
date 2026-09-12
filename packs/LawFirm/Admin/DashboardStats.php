<?php

namespace BusinessBuilderCore\Packs\LawFirm\Admin;

use BusinessBuilderCore\Packs\LawFirm\PostTypes\ConsultationMeta;
use BusinessBuilderCore\Packs\LawFirm\Appointments\Appointment;
use BusinessBuilderCore\Packs\LawFirm\Appointments\AppointmentMeta;
use BusinessBuilderCore\Core\Payments\PaymentManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LawFirm dashboard statistics provider.
 *
 * Produces ONLY real, site-scoped numbers from the stored data
 * (spec: dashboard must never show fake data):
 *   - KPI counters  (get_kpis)
 *   - time-series     (consultations / appointments / revenue over time)
 *   - distributions   (status, practice area, gateway)
 *
 * Everything is read from the current site's posts + options, so on
 * Multisite each site sees its own data and nothing leaks across sites
 * (spec: Part 12).
 *
 * All queries use fields=ids + no_found_rows and are bounded, so the
 * dashboard stays fast even with many records.
 */
class DashboardStats {

    /**
     * Post type: consultations.
     */
    private const CONSULTATIONS = 'bb_consultation';

    /**
     * Post type: lawyers.
     */
    private const LAWYERS = 'bb_lawyer';

    /**
     * Payment manager (for transactions and gateway labels).
     */
    protected PaymentManager $payments;

    /**
     * Constructor.
     *
     * @param PaymentManager $payments Payments.
     */
    public function __construct( PaymentManager $payments ) {

        $this->payments = $payments;
    }

    /**
     * Build every KPI card used by the dashboard.
     *
     * Each entry: ['key','label','value','icon','url','accent'].
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_kpis(): array {

        $consult = $this->consultation_metrics();
        $appts   = $this->appointment_metrics();
        $revenue = $this->revenue_metrics();
        $lawyers = $this->lawyer_metrics();

        $consult_url = admin_url( 'edit.php?post_type=' . self::CONSULTATIONS );
        $appt_url    = admin_url( 'edit.php?post_type=' . Appointment::POST_TYPE );
        $lawyer_url  = admin_url( 'edit.php?post_type=' . self::LAWYERS );
        $pay_url     = admin_url( 'admin.php?page=bb-law-firm-payments' );

        return array(
            array(
                'key'   => 'consultations_total',
                'label' => __( 'Total Consultation Requests', 'business-builder' ),
                'value' => $consult['total'],
                'icon'  => 'dashicons-phone',
                'url'   => $consult_url,
                'accent' => 'primary',
            ),
            array(
                'key'   => 'consultations_new',
                'label' => __( 'New Consultation Requests', 'business-builder' ),
                'value' => $consult['new'],
                'icon'  => 'dashicons-email-alt',
                'url'   => add_query_arg( 'bb_filter', 'new', $consult_url ),
                'accent' => 'info',
            ),
            array(
                'key'   => 'consultations_pending',
                'label' => __( 'Pending Consultations', 'business-builder' ),
                'value' => $consult['pending'],
                'icon'  => 'dashicons-clock',
                'url'   => add_query_arg( 'bb_filter', 'pending', $consult_url ),
                'accent' => 'warning',
            ),
            array(
                'key'   => 'consultations_paid',
                'label' => __( 'Paid Consultations', 'business-builder' ),
                'value' => $consult['paid'],
                'icon'  => 'dashicons-yes-alt',
 'url'   => add_query_arg( 'bb_filter', 'paid', $consult_url ),
                'accent' => 'success',
            ),
            array(
                'key'   => 'consultations_unpaid',
                'label' => __( 'Unpaid Consultations', 'business-builder' ),
                'value' => $consult['unpaid'],
                'icon'  => 'dashicons-warning',
                'url'   => add_query_arg( 'bb_filter', 'unpaid', $consult_url ),
                'accent' => 'danger',
            ),
            array(
                'key'   => 'appointments_total',
                'label' => __( 'Total Appointments', 'business-builder' ),
                'value' => $appts['total'],
                'icon'  => 'dashicons-calendar-alt',
                'url'   => $appt_url,
                'accent' => 'primary',
            ),
            array(
                'key'   => 'appointments_upcoming',
                'label' => __( 'Upcoming Appointments', 'business-builder' ),
                'value' => $appts['upcoming'],
                'icon'  => 'dashicons-calendar',
                'url'   => $appt_url,
                'accent' => 'info',
            ),
            array(
                'key'   => 'appointments_today',
                'label' => __( "Today's Appointments", 'business-builder' ),
                'value' => $appts['today'],
                'icon'  => 'dashicons-clock',
                'url'   => $appt_url,
                'accent' => 'warning',
            ),
            array(
                'key'   => 'lawyers_total',
                'label' => __( 'Total Lawyers', 'business-builder' ),
                'value' => $lawyers['total'],
                'icon'  => 'dashicons-businessperson',
                'url'   => $lawyer_url,
                'accent' => 'primary',
            ),
            array(
                'key'   => 'lawyers_active',
                'label' => __( 'Active Lawyers', 'business-builder' ),
                'value' => $lawyers['active'],
                'icon'  => 'dashicons-groups',
                'url'   => $lawyer_url,
                'accent' => 'success',
            ),
            array(
                'key'   => 'revenue_total',
                'label' => __( 'Total Revenue', 'business-builder' ),
                'value' => $this->format_money( $revenue['total'], $revenue['currency'] ),
                'icon'  => 'dashicons-chart-line',
                'url'   => $pay_url,
                'accent' => 'success',
            ),
            array(
                'key'   => 'revenue_month',
                'label' => __( 'Revenue This Month', 'business-builder' ),
                'value' => $this->format_money( $revenue['month'], $revenue['currency'] ),
                'icon'  => 'dashicons-money-alt',
                'url'   => $pay_url,
                'accent' => 'info',
            ),
        );
    }

    /**
     * Consultation counters (real, site-scoped).
     *
     * @return array<string, int>
     */
    public function consultation_metrics(): array {

        $out = array(
            'total'   => 0,
            'new'     => 0,
            'pending' => 0,
            'paid'    => 0,
            'unpaid'  => 0,
        );

        if ( ! post_type_exists( self::CONSULTATIONS ) ) {
            return $out;
        }

        $ids = $this->all_ids( self::CONSULTATIONS );

        $out['total'] = count( $ids );

        foreach ( $ids as $id ) {

            $status = (string) get_post_meta( $id, ConsultationMeta::key( 'status' ), true );

            if ( '' === $status ) {
                $status = ConsultationMeta::default_status();
            }

            if ( 'new' === $status ) {
                $out['new']++;
            } elseif ( in_array( $status, array( 'pending', 'contacted' ), true ) ) {
                $out['pending']++;
            }

            $payment = (string) get_post_meta( $id, ConsultationMeta::key( 'payment_status' ), true );

            if ( 'paid' === $payment ) {
                $out['paid']++;
            } elseif ( in_array( $payment, array( 'pending', 'failed', 'expired', 'cancelled' ), true ) ) {
                $out['unpaid']++;
            }
        }

        return $out;
    }

    /**
     * Appointment counters (real, site-scoped).
     *
     * @return array<string, int>
     */
    public function appointment_metrics(): array {

        $out = array(
            'total'    => 0,
            'upcoming' => 0,
            'today'    => 0,
        );

        if ( ! post_type_exists( Appointment::POST_TYPE ) ) {
            return $out;
        }

        $ids = $this->all_ids( Appointment::POST_TYPE );

        $out['total'] = count( $ids );

        $today = current_time( 'Y-m-d' );

        foreach ( $ids as $id ) {

            $status = (string) get_post_meta( $id, AppointmentMeta::key( 'status' ), true );

            if ( in_array( $status, array( 'cancelled', 'no_show' ), true ) ) {
                continue;
            }

            $date = (string) get_post_meta( $id, AppointmentMeta::key( 'date' ), true );

            if ( $date === $today ) {
                $out['today']++;
            }

            if ( $date >= $today ) {
                $out['upcoming']++;
            }
        }

        return $out;
    }

    /**
     * Lawyer counters (real, site-scoped).
     *
     * @return array<string, int>
     */
    public function lawyer_metrics(): array {

        $out = array(
            'total'  => 0,
            'active' => 0,
        );

        if ( ! post_type_exists( self::LAWYERS ) ) {
            return $out;
        }

        $total = wp_count_posts( self::LAWYERS );

        if ( isset( $total->publish ) ) {
            $out['total'] = (int) $total->publish;
        }

        foreach ( $this->all_ids( self::LAWYERS ) as $id ) {

            $visible = '0' !== get_post_meta( $id, '_bb_lawyer_show_on_website', true );

            if ( $visible ) {
                $out['active']++;
            }
        }

        return $out;
    }

    /**
     * Revenue metrics from paid transactions (real, site-scoped).
     *
     * @return array{total:float,month:float,currency:string}
     */
    public function revenue_metrics(): array {

        $currency = $this->default_currency();

        $total = 0.0;
        $month = 0.0;

        $month_prefix = current_time( 'Y-m' );

        foreach ( $this->paid_transactions() as $txn ) {

            $amount = isset( $txn['amount'] ) ? (float) $txn['amount'] : 0.0;

            if ( $amount <= 0 ) {
                continue;
            }

            if ( ! empty( $txn['currency'] ) ) {
                $currency = (string) $txn['currency'];
            }

            $total += $amount;

            $created = isset( $txn['created_at'] ) ? (string) $txn['created_at'] : '';

            if ( '' !== $created && strpos( $created, $month_prefix ) === 0 ) {
                $month += $amount;
            }
        }

        return array(
            'total'    => $total,
            'month'    => $month,
            'currency' => $currency,
        );
    }

    /**
     * Consultations over the last N months.
     *
     * @param int $months Number of months.
     * @return array{labels: string[], values: int[]}
     */
    public function consultations_over_time( int $months = 6 ): array {

        return $this->posts_over_time( self::CONSULTATIONS, $months );
    }

    /**
     * Appointments over the last N months.
     *
     * @param int $months Number of months.
     * @return array{labels: string[], values: int[]}
     */
    public function appointments_over_time( int $months = 6 ): array {

        return $this->posts_over_time( Appointment::POST_TYPE, $months );
    }

    /**
     * Revenue over the last N months (paid transactions only).
     *
     * @param int $months Number of months.
     * @return array{labels: string[], values: float[], currency: string}
     */
    public function revenue_over_time( int $months = 6 ): array {

        $months = max( 1, $months );

        $buckets = $this->month_buckets( $months );

        foreach ( $this->paid_transactions() as $txn ) {

            $created = isset( $txn['created_at'] ) ? (string) $txn['created_at'] : '';
            $amount  = isset( $txn['amount'] ) ? (float) $txn['amount'] : 0.0;

            if ( '' === $created || $amount <= 0 ) {
                continue;
            }

            $key = substr( $created, 0, 7 );

            if ( isset( $buckets['values'][ $key ] ) ) {
                $buckets['values'][ $key ] += $amount;
            }
        }

        return array(
            'labels'   => array_values( $buckets['labels'] ),
            'values'   => array_values( $buckets['values'] ),
            'currency' => $this->default_currency(),
        );
    }

    /**
     * Consultation status distribution.
     *
     * @return array{labels: string[], values: int[]}
     */
    public function consultations_by_status(): array {

        return $this->posts_by_meta(
            self::CONSULTATIONS,
            ConsultationMeta::key( 'status' ),
            ConsultationMeta::statuses(),
            ConsultationMeta::default_status()
        );
    }

    /**
     * Consultation distribution by practice area (real term names).
     *
     * Bug-fix aware: resolves each record's area through the same
     * normalization used everywhere else, so Arabic areas are grouped
     * under the human-readable name, never an encoded slug.
     *
     * @param int $limit Max areas.
     * @return array{labels: string[], values: int[]}
     */
    public function consultations_by_practice_area( int $limit = 8 ): array {

        if ( ! post_type_exists( self::CONSULTATIONS ) ) {
            return array( 'labels' => array(), 'values' => array() );
        }

        $counts = array();

        foreach ( $this->all_ids( self::CONSULTATIONS ) as $id ) {

            $name = ConsultationMeta::practice_area_name( $id );

            if ( '' === $name ) {
                $name = __( 'Not specified', 'business-builder' );
            }

            if ( ! isset( $counts[ $name ] ) ) {
                $counts[ $name ] = 0;
            }

            $counts[ $name ]++;
        }

        arsort( $counts );

        $counts = array_slice( $counts, 0, max( 1, $limit ), true );

        return array(
            'labels' => array_map( 'strval', array_keys( $counts ) ),
            'values' => array_map( 'intval', array_values( $counts ) ),
        );
    }

    /**
     * Appointment status distribution.
     *
     * @return array{labels: string[], values: int[]}
     */
    public function appointments_by_status(): array {

        return $this->posts_by_meta(
            Appointment::POST_TYPE,
            AppointmentMeta::key( 'status' ),
            AppointmentMeta::statuses(),
            AppointmentMeta::default_status()
        );
    }

    /**
     * Revenue grouped by payment gateway (paid transactions only).
     *
     * @return array{labels: string[], values: float[]}
     */
    public function revenue_by_gateway(): array {

        $counts = array();

        foreach ( $this->paid_transactions() as $txn ) {

            $amount  = isset( $txn['amount'] ) ? (float) $txn['amount'] : 0.0;
            $gateway = isset( $txn['gateway'] ) ? (string) $txn['gateway'] : '';

            if ( $amount <= 0 || '' === $gateway ) {
                continue;
            }

            $label = $this->gateway_label( $gateway );

            if ( ! isset( $counts[ $label ] ) ) {
                $counts[ $label ] = 0.0;
            }

            $counts[ $label ] += $amount;
        }

        arsort( $counts );

        return array(
            'labels' => array_map( 'strval', array_keys( $counts ) ),
            'values' => array_map( 'floatval', array_values( $counts ) ),
        );
    }

    /**
     * Paid vs unpaid consultations (a two-slice distribution).
     *
     * @return array{labels: string[], values: int[]}
     */
    public function paid_vs_unpaid_consultations(): array {

        $m = $this->consultation_metrics();

        return array(
            'labels' => array(
                __( 'Paid', 'business-builder' ),
                __( 'Unpaid', 'business-builder' ),
            ),
            'values' => array( $m['paid'], $m['unpaid'] ),
        );
    }

    /**
     * Paid vs unpaid appointments.
     *
     * @return array{labels: string[], values: int[]}
     */
    public function paid_vs_unpaid_appointments(): array {

        $paid   = 0;
        $unpaid = 0;

        if ( post_type_exists( Appointment::POST_TYPE ) ) {
            foreach ( $this->all_ids( Appointment::POST_TYPE ) as $id ) {

                $state = (string) get_post_meta( $id, AppointmentMeta::key( 'payment_status' ), true );

                if ( 'paid' === $state ) {
                    $paid++;
                } elseif ( in_array( $state, array( 'pending', 'failed', 'expired', 'cancelled' ), true ) ) {
                    $unpaid++;
                }
            }
        }

        return array(
            'labels' => array(
                __( 'Paid', 'business-builder' ),
                __( 'Unpaid', 'business-builder' ),
            ),
            'values' => array( $paid, $unpaid ),
        );
    }

    /**
     * Action Center items requiring administrator attention.
     *
     * Each item: ['id','type','title','meta','url','actions'=>[...]].
     * All URLs point at real screens/actions (no decorative buttons).
     *
     * @param int $limit Max items per group.
     * @return array<int, array<string, mixed>>
     */
    public function action_center( int $limit = 6 ): array {

        $items = array();

        $this->collect_consultation_actions( $items, $limit );
        $this->collect_appointment_actions( $items, $limit );

        return $items;
    }

    /**
     * Recently received consultations (for the activity/action list).
     *
     * @param int $limit Max items.
     * @return array<int, array<string, mixed>>
     */
    public function recent_consultations( int $limit = 6 ): array {

        if ( ! post_type_exists( self::CONSULTATIONS ) ) {
            return array();
        }

        $posts = get_posts(
            array(
                'post_type'      => self::CONSULTATIONS,
                'post_status'    => 'publish',
                'posts_per_page' => max( 1, $limit ),
                'no_found_rows'  => true,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        $out = array();

        foreach ( $posts as $post ) {

            $out[] = array(
                'id'            => (int) $post->ID,
                'name'          => (string) get_post_meta( $post->ID, ConsultationMeta::key( 'name' ), true ),
                'practice_area' => ConsultationMeta::practice_area_name( (int) $post->ID ),
                'status'        => ConsultationMeta::status_label( $this->consultation_status( (int) $post->ID ) ),
                'payment'       => ConsultationMeta::payment_label( $this->consultation_payment( (int) $post->ID ) ),
                'reference'     => (string) get_post_meta( $post->ID, ConsultationMeta::key( 'public_reference' ), true ),
                'created'       => (string) $post->post_date,
                'url'           => get_edit_post_link( (int) $post->ID, 'raw' ),
            );
        }

        return $out;
    }

    /* ------------------------------------------------------------------
     * Internals
     * ------------------------------------------------------------------ */

    /**
     * Collect consultation action items.
     *
     * @param array $items Items (by reference).
     * @param int   $limit Limit per group.
     */
    protected function collect_consultation_actions( array &$items, int $limit ): void {

        if ( ! post_type_exists( self::CONSULTATIONS ) ) {
            return;
        }

        $new    = array();
        $unpaid = array();

        foreach ( get_posts(
            array(
                'post_type'      => self::CONSULTATIONS,
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'no_found_rows'  => true,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        ) as $post ) {

            $status  = $this->consultation_status( (int) $post->ID );
            $payment = $this->consultation_payment( (int) $post->ID );

            if ( 'new' === $status && count( $new ) < $limit ) {
                $new[] = $post;
            }

            if ( in_array( $payment, array( 'pending', 'failed' ), true ) && count( $unpaid ) < $limit ) {
                $unpaid[] = $post;
            }
        }

        foreach ( $new as $post ) {
            $items[] = $this->consultation_action_item(
                $post,
                'consultation_new',
                __( 'New consultation request awaiting review', 'business-builder' ),
                array( 'review' )
            );
        }

        foreach ( $unpaid as $post ) {
            $items[] = $this->consultation_action_item(
                $post,
                'consultation_payment',
                __( 'Consultation waiting for payment', 'business-builder' ),
                array( 'review', 'manage_payment', 'mark_paid' )
            );
        }
    }

    /**
     * Build a consultation action item.
     *
     * @param \WP_Post $post   Consultation post.
     * @param string   $type   Item type.
     * @param string   $title  Title.
     * @param string[] $groups Action groups to expose.
     * @return array<string, mixed>
     */
    protected function consultation_action_item( $post, string $type, string $title, array $groups ): array {

        $id      = (int) $post->ID;
        $url     = get_edit_post_link( $id, 'raw' );
        $actions = array();

        $actions[] = array(
            'label' => __( 'View Details', 'business-builder' ),
            'url'   => $url,
            'style' => 'secondary',
        );

        if ( in_array( 'mark_paid', $groups, true ) ) {
            $actions[] = array(
                'label'  => __( 'Mark as Paid', 'business-builder' ),
                'action' => 'bb_consultation_action',
                'op'     => 'mark_paid',
                'id'     => $id,
                'nonce'  => wp_create_nonce( 'bb_consultation_action' ),
                'style'  => 'primary',
            );
        }

        return array(
            'id'    => $type . ':' . $id,
            'type'  => $type,
            'title' => $title,
            'meta'  => trim(
                (string) get_post_meta( $id, ConsultationMeta::key( 'name' ), true )
                . ' · '
                . ConsultationMeta::practice_area_name( $id )
            ),
            'badge' => ConsultationMeta::payment_label( $this->consultation_payment( $id ) ),
            'url'   => $url,
            'actions' => $actions,
        );
    }

    /**
     * Collect appointment action items.
     *
     * @param array $items Items (by reference).
     * @param int   $limit Limit per group.
     */
    protected function collect_appointment_actions( array &$items, int $limit ): void {

        if ( ! post_type_exists( Appointment::POST_TYPE ) ) {
            return;
        }

        $pending = array();

        $today = current_time( 'Y-m-d' );

        foreach ( get_posts(
            array(
                'post_type'      => Appointment::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'no_found_rows'  => true,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        ) as $post ) {

            $status = (string) get_post_meta( $post->ID, AppointmentMeta::key( 'status' ), true );
            $date   = (string) get_post_meta( $post->ID, AppointmentMeta::key( 'date' ), true );

            if ( 'pending' === $status && $date >= $today && count( $pending ) < $limit ) {
                $pending[] = $post;
            }
        }

        foreach ( $pending as $post ) {

            $id  = (int) $post->ID;
            $url = get_edit_post_link( $id, 'raw' );

            $items[] = array(
                'id'    => 'appointment_pending:' . $id,
                'type'  => 'appointment_pending',
                'title' => __( 'Unconfirmed appointment', 'business-builder' ),
                'meta'  => trim(
                    (string) get_post_meta( $id, AppointmentMeta::key( 'client_name' ), true )
                    . ' · '
                    . (string) get_post_meta( $id, AppointmentMeta::key( 'date' ), true )
                    . ' '
                    . (string) get_post_meta( $id, AppointmentMeta::key( 'start' ), true )
                ),
                'badge' => AppointmentMeta::status_label( 'pending' ),
                'url'   => $url,
                'actions' => array(
                    array(
                        'label' => __( 'View Appointment', 'business-builder' ),
                        'url'   => $url,
                        'style' => 'secondary',
                    ),
                    array(
                        'label'  => __( 'Confirm', 'business-builder' ),
                        'action' => 'bb_appointment_action',
                        'id'     => $id,
                        'status' => 'confirmed',
                        'nonce'  => wp_create_nonce( 'bb_appointment_action' ),
                        'style'  => 'primary',
                    ),
                ),
            );
        }
    }

    /**
     * Current status of a consultation (with default).
     *
     * @param int $id Consultation id.
     * @return string
     */
    protected function consultation_status( int $id ): string {

        $status = (string) get_post_meta( $id, ConsultationMeta::key( 'status' ), true );

        return '' === $status ? ConsultationMeta::default_status() : $status;
    }

    /**
     * Current payment state of a consultation (with default).
     *
     * @param int $id Consultation id.
     * @return string
     */
    protected function consultation_payment( int $id ): string {

        $state = (string) get_post_meta( $id, ConsultationMeta::key( 'payment_status' ), true );

        return '' === $state ? 'not_required' : $state;
    }

    /**
     * All published ids for a post type (site-scoped, bounded read).
     *
     * @param string $post_type Post type.
     * @return int[]
     */
    protected function all_ids( string $post_type ): array {

        $ids = get_posts(
            array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
                'fields'         => 'ids',
            )
        );

        return array_map( 'intval', $ids );
    }

    /**
     * Posts over time grouped by month.
     *
     * @param string $post_type Post type.
     * @param int    $months    Months.
     * @return array{labels: string[], values: int[]}
     */
    protected function posts_over_time( string $post_type, int $months ): array {

        $months = max( 1, $months );

        $buckets = $this->month_buckets( $months );

        if ( post_type_exists( $post_type ) ) {
            foreach ( $this->all_ids( $post_type ) as $id ) {

                $date = (string) get_post_field( 'post_date', $id );
                $key  = substr( $date, 0, 7 );

                if ( isset( $buckets['values'][ $key ] ) ) {
                    $buckets['values'][ $key ]++;
                }
            }
        }

        return array(
            'labels' => array_values( $buckets['labels'] ),
            'values' => array_map( 'intval', array_values( $buckets['values'] ) ),
        );
    }

    /**
     * Build month buckets for the last N months (label + zero value).
     *
     * @param int $months Months.
     * @return array{labels: array<string,string>, values: array<string,float>}
     */
    protected function month_buckets( int $months ): array {

        $labels = array();
        $values = array();

        $now = current_time( 'timestamp' );

        for ( $i = $months - 1; $i >= 0; $i-- ) {

            $stamp = strtotime( '-' . $i . ' month', $now );
            $key   = gmdate( 'Y-m', $stamp );
            $label = date_i18n( 'M', $stamp );

            $labels[ $key ] = $label;
            $values[ $key ] = 0;
        }

        return array( 'labels' => $labels, 'values' => $values );
    }

    /**
     * Posts grouped by a meta value, mapped to labels.
     *
     * @param string $post_type     Post type.
     * @param string $meta_key      Meta key.
     * @param array  $labels        Meta value => label map.
     * @param string $default_value Default meta value.
     * @return array{labels: string[], values: int[]}
     */
    protected function posts_by_meta( string $post_type, string $meta_key, array $labels, string $default_value ): array {

        if ( ! post_type_exists( $post_type ) ) {
            return array( 'labels' => array(), 'values' => array() );
        }

        $counts = array();

        foreach ( $labels as $slug => $label ) {
            $counts[ $slug ] = 0;
        }

        foreach ( $this->all_ids( $post_type ) as $id ) {

            $value = (string) get_post_meta( $id, $meta_key, true );

            if ( '' === $value ) {
                $value = $default_value;
            }

            if ( ! isset( $counts[ $value ] ) ) {
                $counts[ $value ] = 0;
            }

            $counts[ $value ]++;
        }

        $out_labels = array();
        $out_values = array();

        foreach ( $counts as $slug => $count ) {
            $out_labels[] = $labels[ $slug ] ?? $slug;
            $out_values[] = (int) $count;
        }

        return array( 'labels' => $out_labels, 'values' => $out_values );
    }

    /**
     * Paid transactions for the current site.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function paid_transactions(): array {

        $out = array();

        foreach ( $this->payments->transactions( 500 ) as $txn ) {

            if ( ! is_array( $txn ) ) {
                continue;
            }

            if ( isset( $txn['status'] ) && 'paid' === $txn['status'] ) {
                $out[] = $txn;
            }
        }

        return $out;
    }

    /**
     * Human label for a gateway id.
     *
     * @param string $gateway_id Gateway id.
     * @return string
     */
    protected function gateway_label( string $gateway_id ): string {

        $gateway = $this->payments->gateway( $gateway_id );

        return $gateway ? $gateway->get_name() : $gateway_id;
    }

    /**
     * Default currency configured for this site.
     *
     * @return string
     */
    protected function default_currency(): string {

        $settings = get_option( 'bb_site_settings', array() );

        $code = is_array( $settings ) && ! empty( $settings['consultation_currency'] )
            ? (string) $settings['consultation_currency']
            : 'USD';

        return strtoupper( $code );
    }

    /**
     * Format a money amount with its currency symbol/name.
     *
     * Uses the structured currency catalogue when available, so a
     * display symbol (E£, ر.س, ...) is shown rather than a bare code.
     *
     * @param float  $amount   Amount.
     * @param string $currency Currency code.
     * @return string
     */
    protected function format_money( float $amount, string $currency ): string {

        $formatted = number_format_i18n( $amount, 2 );

        if ( class_exists( '\BusinessBuilderCore\Core\Payments\Currencies' ) ) {
            $symbol = \BusinessBuilderCore\Core\Payments\Currencies::symbol( $currency );

            if ( '' !== $symbol ) {
                $sep = chr( 32 );

                return $formatted . $sep . $symbol;
            }
        }

        $sep = chr( 32 );

        return $formatted . $sep . strtoupper( $currency );
    }
}
