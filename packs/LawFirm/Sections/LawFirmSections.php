<?php

namespace BusinessBuilderCore\Packs\LawFirm\Sections;

use BusinessBuilderCore\Builder\SectionRegistry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LawFirm dynamic sections.
 *
 * Registers the pack's sections through the existing
 * SectionRegistry and renders them from the database. Each
 * section exposes a full schema (typed content fields + settings)
 * so the builder editor shows real widgets, plus a render callback
 * bound to the generic bb_render_section_{type} action.
 */
class LawFirmSections {

    protected SectionRegistry $registry;

    public function __construct(
        SectionRegistry $registry
    ) {
        $this->registry = $registry;
    }

    /**
     * Register all Law Firm sections.
     */
    public function register(): void {

        $this->register_lawyers();
        $this->register_legal_services();
        $this->register_practice_areas();
        $this->register_testimonials();
        $this->register_faq();
        $this->register_consultation();
        $this->register_booking();
        $this->register_lookup();

        add_action( 'bb_render_section_lawyers', array( $this, 'render_lawyers_section' ), 10, 3 );
        add_action( 'bb_render_section_legal_services', array( $this, 'render_legal_services_section' ), 10, 3 );
        add_action( 'bb_render_section_practice_areas', array( $this, 'render_practice_areas_section' ), 10, 3 );
        add_action( 'bb_render_section_testimonials', array( $this, 'render_testimonials_section' ), 10, 3 );
        add_action( 'bb_render_section_faq', array( $this, 'render_faq_section' ), 10, 3 );
    }

    /**
     * Shared content heading fields.
     *
     * @return array
     */
    protected function heading_fields(): array {

        return array(
            'title' => array(
                'type'        => 'text',
                'label'       => __( 'Section Title', 'business-builder' ),
                'default'     => '',
                'placeholder' => __( 'Enter a section title...', 'business-builder' ),
            ),
            'description' => array(
                'type'    => 'textarea',
                'label'   => __( 'Intro Text', 'business-builder' ),
                'default' => '',
                'rows'    => 3,
            ),
        );
    }

    /**
     * Shared columns setting.
     *
     * @param int $default Default column count.
     * @return array
     */
    protected function columns_setting( int $default = 3 ): array {

        return array(
            'type'    => 'select',
            'label'   => __( 'Columns', 'business-builder' ),
            'default' => (string) $default,
            'options' => array(
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
            ),
        );
    }

    /**
     * Shared "limit" setting.
     *
     * @return array
     */
    protected function limit_setting(): array {

        return array(
            'type'        => 'number',
            'label'       => __( 'Maximum Items', 'business-builder' ),
            'default'     => 0,
            'min'         => 0,
            'step'        => 1,
            'description' => __( '0 shows all items.', 'business-builder' ),
        );
    }

    /**
     * Shared "featured only" setting.
     *
     * @return array
     */
    protected function featured_setting(): array {

        return array(
            'type'        => 'checkbox',
            'label'       => __( 'Featured Only', 'business-builder' ),
            'default'     => false,
            'description' => __( 'Show only items marked as featured.', 'business-builder' ),
        );
    }

    /**
     * Practice-area filter setting.
     *
     * @return array
     */
    protected function practice_area_filter_setting(): array {

        $options = array(
            '' => __( 'All Practice Areas', 'business-builder' ),
        );

        $terms = get_terms(
            array(
                'taxonomy'   => LawFirmQueries::PRACTICE_AREA_TAX,
                'hide_empty' => false,
            )
        );

        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $options[ $term->slug ] = $term->name;
            }
        }

        return array(
            'type'    => 'select',
            'label'   => __( 'Practice Area', 'business-builder' ),
            'default' => '',
            'options' => $options,
        );
    }

    /**
     * Order setting.
     *
     * @return array
     */
    protected function order_setting(): array {

        return array(
            'type'    => 'select',
            'label'   => __( 'Order', 'business-builder' ),
            'default' => 'asc',
            'options' => array(
                'asc'  => __( 'Ascending', 'business-builder' ),
                'desc' => __( 'Descending', 'business-builder' ),
            ),
        );
    }

    /**
     * Lawyers section.
     */
    protected function register_lawyers(): void {

        $this->registry->register(
            'lawyers',
            array(
                'name'        => 'Lawyers',
                'label'       => __( 'Lawyers', 'business-builder' ),
                'description' => __( 'Display lawyers from your database, optionally filtered by practice area.', 'business-builder' ),
                'category'    => 'law-firm',
                'icon'        => 'dashicons-businessperson',
                'supports'    => array( 'title', 'description', 'lawyers', 'practice_areas' ),
                'settings'    => array(
                    'columns'       => $this->columns_setting( 3 ),
                    'limit'         => $this->limit_setting(),
                    'featured'      => $this->featured_setting(),
                    'practice_area' => $this->practice_area_filter_setting(),
                    'order'         => $this->order_setting(),
                ),
                'content'     => $this->heading_fields(),
            )
        );
    }

    /**
     * Legal Services section.
     */
    protected function register_legal_services(): void {

        $this->registry->register(
            'legal_services',
            array(
                'name'        => 'Legal Services',
                'label'       => __( 'Legal Services', 'business-builder' ),
                'description' => __( 'Display legal services from your database.', 'business-builder' ),
                'category'    => 'law-firm',
                'icon'        => 'dashicons-portfolio',
                'supports'    => array( 'title', 'description', 'services', 'practice_areas' ),
                'settings'    => array(
                    'columns'       => $this->columns_setting( 3 ),
                    'limit'         => $this->limit_setting(),
                    'featured'      => $this->featured_setting(),
                    'practice_area' => $this->practice_area_filter_setting(),
                    'order'         => $this->order_setting(),
                ),
                'content'     => $this->heading_fields(),
            )
        );
    }

    /**
     * Practice Areas section.
     */
    protected function register_practice_areas(): void {

        $this->registry->register(
            'practice_areas',
            array(
                'name'        => 'Practice Areas',
                'label'       => __( 'Practice Areas', 'business-builder' ),
                'description' => __( 'Display the firm practice areas from the taxonomy.', 'business-builder' ),
                'category'    => 'law-firm',
                'icon'        => 'dashicons-category',
                'supports'    => array( 'title', 'description', 'practice_areas' ),
                'settings'    => array(
                    'columns'  => $this->columns_setting( 4 ),
                    'limit'    => $this->limit_setting(),
                    'featured' => $this->featured_setting(),
                ),
                'content'     => $this->heading_fields(),
            )
        );
    }

    /**
     * Testimonials section.
     */
    protected function register_testimonials(): void {

        $this->registry->register(
            'testimonials',
            array(
                'name'        => 'Testimonials',
                'label'       => __( 'Testimonials', 'business-builder' ),
                'description' => __( 'Display client testimonials from your database.', 'business-builder' ),
                'category'    => 'law-firm',
                'icon'        => 'dashicons-format-quote',
                'supports'    => array( 'title', 'description', 'testimonials' ),
                'settings'    => array(
                    'columns'  => $this->columns_setting( 3 ),
                    'limit'    => $this->limit_setting(),
                    'featured' => $this->featured_setting(),
                    'order'    => $this->order_setting(),
                ),
                'content'     => $this->heading_fields(),
            )
        );
    }

    /**
     * FAQ section.
     */
    protected function register_faq(): void {

        $faq_options = array(
            '' => __( 'All Categories', 'business-builder' ),
        );

        $terms = get_terms(
            array(
                'taxonomy'   => LawFirmQueries::FAQ_CATEGORY_TAX,
                'hide_empty' => false,
            )
        );

        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $faq_options[ $term->slug ] = $term->name;
            }
        }

        $this->registry->register(
            'faq',
            array(
                'name'        => 'FAQ',
                'label'       => __( 'Frequently Asked Questions', 'business-builder' ),
                'description' => __( 'Display frequently asked questions from your database.', 'business-builder' ),
                'category'    => 'law-firm',
                'icon'        => 'dashicons-editor-help',
                'supports'    => array( 'title', 'description', 'faq' ),
                'settings'    => array(
                    'limit'        => $this->limit_setting(),
                    'featured'     => $this->featured_setting(),
                    'faq_category' => array(
                        'type'    => 'select',
                        'label'   => __( 'FAQ Category', 'business-builder' ),
                        'default' => '',
                        'options' => $faq_options,
                    ),
                    'order'        => $this->order_setting(),
                ),
                'content'     => $this->heading_fields(),
            )
        );
    }

    /**
     * Payment schema shared by the Consultation and Booking sections.
     *
     * Rendered entirely by the existing dynamic schema editor — no new
     * editor. The gateway list is built from the registered gateways so
     * it always reflects the current Payment Settings, and the currency
     * list from the structured Currencies catalogue.
     *
     * @param string $object_type 'consultation' | 'appointment'.
     * @return array<string, array<string, mixed>>
     */
    protected function payment_fields( string $object_type ): array {

        $object_type = sanitize_key( $object_type );

        $currency_options = array();

        foreach ( \BusinessBuilderCore\Core\Payments\Currencies::all() as $entry ) {
            $currency_options[ $entry['code'] ] = $entry['name'] . ' (' . $entry['code'] . ') — ' . $entry['symbol'];
        }

        $gateway_options = array();

        $manager = new \BusinessBuilderCore\Core\Payments\PaymentManager();

        foreach ( $manager->gateways() as $id => $gateway ) {
            $label = $gateway->get_name();

            $is_enabled = $manager->is_gateway_enabled( $id );

            if ( $is_enabled ) {
                $label .= ' — ' . __( 'enabled', 'business-builder' );
            }

            $gateway_options[ $id ] = $label;
        }

        return array(
            'payment_enabled' => array(
                'type'        => 'checkbox',
                'label'       => __( 'Enable Payment', 'business-builder' ),
                'default'     => false,
                'description' => __( 'When on, the customer must pay before the request is confirmed.', 'business-builder' ),
            ),
            'payment_fee' => array(
                'type'        => 'number',
                'label'       => __( 'Fee', 'business-builder' ),
                'default'     => 0,
                'min'         => 0,
                'step'        => 0.01,
                'description' => __( 'Numeric amount. Leave 0 to use the site default fee.', 'business-builder' ),
            ),
            'payment_currency' => array(
                'type'    => 'select',
                'label'   => __( 'Currency', 'business-builder' ),
                'default' => '',
                'options' => array_merge(
                    array( '' => __( 'Use site default', 'business-builder' ) ),
                    $currency_options
                ),
            ),
            'payment_gateways' => array(
                'type'        => 'multicheck',
                'label'       => __( 'Payment Gateways', 'business-builder' ),
                'default'     => array(),
                'options'     => $gateway_options,
                'description' => __( 'Only the selected gateways are offered for this section. Leave all unchecked to allow every available gateway.', 'business-builder' ),
            ),
        );
    }

    /**
     * Consultation section.
     *
     * Registered with a custom render callback (not the generic
     * bb_render_section_{type} action) because the consultation
     * form is pack-specific and should not require editing the
     * generic SectionRenderer.
     */
    protected function register_consultation(): void {

        $this->registry->register(
            'consultation',
            array(
                'name'        => 'Consultation',
                'label'       => __( 'Legal Consultation', 'business-builder' ),
                'description' => __( 'Display the legal consultation request form.', 'business-builder' ),
                'category'    => 'law-firm',
                'icon'        => 'dashicons-phone',
                'supports'    => array( 'title', 'description' ),
                'settings'    => $this->payment_fields( 'consultation' ),
                'content'     => $this->heading_fields(),
                'render'      => array( $this, 'render_consultation_section' ),
            )
        );
    }

    /**
     * Booking section.
     */
    protected function register_booking(): void {

        /*
         * Booking settings = the structured availability schedule (days,
         * hours, per-day cap, appointment length) PLUS the shared payment
         * schema. Availability lives on the section so an administrator can
         * run different schedules on different pages without touching site
         * settings.
         */
        $settings = array_merge(
            $this->availability_fields(),
            $this->payment_fields( 'appointment' )
        );

        $this->registry->register(
            'booking',
            array(
                'name'        => 'Booking',
                'label'       => __( 'Appointment Booking', 'business-builder' ),
                'description' => __( 'Display the appointment booking form.', 'business-builder' ),
                'category'    => 'law-firm',
                'icon'        => 'dashicons-calendar-alt',
                'supports'    => array( 'title', 'description' ),
                'settings'    => $settings,
                'content'     => $this->heading_fields(),
                'render'      => array( $this, 'render_booking_section' ),
            )
        );
    }

    /**
     * Structured availability settings for the appointment booking section.
     *
     * Lets an administrator declare, per booking section:
     *   - which weekdays accept appointments (multi-select),
     *   - the daily start / end time window,
     *   - the length of each appointment (slot) in minutes,
     *   - an optional buffer between appointments,
     *   - the maximum number of bookings allowed per day.
     *
     * Values are plain strings/numbers/arrays so the generic schema editor
     * renders them with no bespoke UI. Empty values fall back to the site
     * defaults inside the Availability service.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function availability_fields(): array {

        return array(
            'availability_days' => array(
                'type'        => 'multicheck',
                'label'       => __( 'Available Days', 'business-builder' ),
                'default'     => array( '1', '2', '3', '4', '5' ),
                'options'     => array(
                    '0' => __( 'Sunday', 'business-builder' ),
                    '1' => __( 'Monday', 'business-builder' ),
                    '2' => __( 'Tuesday', 'business-builder' ),
                    '3' => __( 'Wednesday', 'business-builder' ),
                    '4' => __( 'Thursday', 'business-builder' ),
                    '5' => __( 'Friday', 'business-builder' ),
                    '6' => __( 'Saturday', 'business-builder' ),
                ),
                'description' => __( 'Days of the week that accept appointments. Leave all unchecked to use the site default.', 'business-builder' ),
            ),
            'availability_start' => array(
                'type'        => 'text',
                'label'       => __( 'Available From', 'business-builder' ),
                'default'     => '',
                'placeholder' => '09:00',
                'description' => __( 'Daily start time in 24-hour HH:MM format (e.g. 09:00). Empty uses the site default.', 'business-builder' ),
            ),
            'availability_end' => array(
                'type'        => 'text',
                'label'       => __( 'Available To', 'business-builder' ),
                'default'     => '',
                'placeholder' => '17:00',
                'description' => __( 'Daily end time in 24-hour HH:MM format (e.g. 17:00). Empty uses the site default.', 'business-builder' ),
            ),
            'availability_slot' => array(
                'type'        => 'number',
                'label'       => __( 'Appointment Length (minutes)', 'business-builder' ),
                'default'     => 0,
                'min'         => 0,
                'step'        => 5,
                'description' => __( 'Duration of each appointment slot in minutes. Leave 0 to use the site default.', 'business-builder' ),
            ),
            'availability_buffer' => array(
                'type'        => 'number',
                'label'       => __( 'Buffer Between Appointments (minutes)', 'business-builder' ),
                'default'     => 0,
                'min'         => 0,
                'step'        => 5,
                'description' => __( 'Gap kept free between two appointments. Leave 0 to use the site default.', 'business-builder' ),
            ),
            'availability_max_per_day' => array(
                'type'        => 'number',
                'label'       => __( 'Maximum Appointments Per Day', 'business-builder' ),
                'default'     => 0,
                'min'         => 0,
                'description' => __( 'Hard cap on confirmed/pending bookings per day. Leave 0 for no limit.', 'business-builder' ),
            ),
        );
    }

    /**
     * Consultation & Appointment Lookup section.
     *
     * Registered through the existing SectionRegistry so it appears in the
     * Page Builder Meta Box like every other section. The frontend lets a
     * customer verify their own record with reference + phone number.
     */
    protected function register_lookup(): void {

        $this->registry->register(
            'status_lookup',
            array(
                'name'        => 'Status Lookup',
                'label'       => __( 'Consultation & Appointment Lookup', 'business-builder' ),
                'description' => __( 'Let customers check their own consultation or appointment status using their reference number and phone number.', 'business-builder' ),
                'category'    => 'law-firm',
                'icon'        => 'dashicons-search',
                'supports'    => array( 'title', 'description' ),
                'settings'    => array(
                    'show_consultation'    => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Allow Consultation Lookup', 'business-builder' ),
                        'default'     => true,
                        'description' => __( 'Show the "Consultation" option in the lookup form.', 'business-builder' ),
                    ),
                    'show_appointment'     => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Allow Appointment Lookup', 'business-builder' ),
                        'default'     => true,
                        'description' => __( 'Show the "Appointment" option in the lookup form.', 'business-builder' ),
                    ),
                    'show_payment_details' => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Show Payment Details', 'business-builder' ),
                        'default'     => true,
                        'description' => __( 'Display payment status, method and references in the result.', 'business-builder' ),
                    ),
                    'show_receipt_button'  => array(
                        'type'        => 'checkbox',
                        'label'       => __( 'Show Receipt Button', 'business-builder' ),
                        'default'     => true,
                        'description' => __( 'Offer a "View Receipt" link when a payment exists.', 'business-builder' ),
                    ),
                ),
                'content'     => $this->heading_fields(),
                'render'      => array( $this, 'render_lookup_section' ),
            )
        );
    }

    /**
     * Render the Consultation & Appointment Lookup section.
     *
     * @param array $section  Section data.
     * @param array $settings Section settings.
     * @param array $content  Section content.
     */
    public function render_lookup_section( array $section, array $settings, array $content ): void {

        echo '<div class="bb-section-inner">';

        $this->render_heading( $content );

        $template = BB_CORE_PATH . 'templates/status-lookup.php';

        if ( file_exists( $template ) ) {
            include $template;
        }

        echo '</div>';
    }

    /**
     * Render the appointment booking section.
     *
     * @param array $section  Section data.
     * @param array $settings Section settings.
     * @param array $content  Section content.
     */
    public function render_booking_section( array $section, array $settings, array $content ): void {

        echo '<div class="bb-section-inner">';

        $this->render_heading( $content );

        /* Section-scoped payment configuration (see consultation). */
        $bb_payment    = \BusinessBuilderCore\Packs\LawFirm\Payments\SectionPaymentFactory::for_appointment( $settings );
        $bb_section_id = isset( $section['id'] ) ? sanitize_text_field( (string) $section['id'] ) : '';

        /*
         * Section-scoped availability schedule (days, hours, slot length,
         * per-day cap). Resolved from this section's settings so the form
         * can DISPLAY the exact window the server will enforce, and so the
         * template can offer the next available slot when one is taken.
         */
        $bb_availability = \BusinessBuilderCore\Packs\LawFirm\Appointments\AvailabilityFactory::config( $settings );
        $bb_avail_svc    = ( new \BusinessBuilderCore\Packs\LawFirm\Appointments\Availability() )->with_config( $bb_availability );

        /* Page id: the submit handler re-reads this section's saved meta. */
        $bb_page_id = (int) get_the_ID();

        if ( $bb_page_id <= 0 ) {
            $bb_page_id = (int) get_queried_object_id();
        }

        $template = BB_CORE_PATH . 'templates/booking-form.php';

        if ( file_exists( $template ) ) {
            include $template;
        }

        echo '</div>';
    }

    /**
     * Render the consultation form section.
     *
     * @param array $section  Section data.
     * @param array $settings Section settings.
     * @param array $content  Section content.
     */
    public function render_consultation_section( array $section, array $settings, array $content ): void {

        echo '<div class="bb-section-inner">';

        $this->render_heading( $content );

        /*
         * Resolve this section's payment configuration so the template
         * can show the fee, currency and allowed gateways. The section id
         * is passed to the form so the submit handler can re-read the
         * SAME stored configuration (never trusting the browser).
         */
        $bb_payment    = \BusinessBuilderCore\Packs\LawFirm\Payments\SectionPaymentFactory::for_consultation( $settings );
        $bb_section_id = isset( $section['id'] ) ? sanitize_text_field( (string) $section['id'] ) : '';
        /*
         * The page id lets the submit handler re-read THIS section's saved
         * payment configuration server-side. It is not sensitive: the form
         * only ever posts ids, never fees/currencies/gateways.
         */
        $bb_page_id = (int) get_the_ID();

        if ( $bb_page_id <= 0 ) {
            $bb_page_id = (int) get_queried_object_id();
        }

        $template = BB_CORE_PATH . 'templates/consultation-form.php';

        if ( file_exists( $template ) ) {
            include $template;
        }

        echo '</div>';
    }

    /**
     * Render a section heading when a title or description exists.
     *
     * @param array $content Section content.
     */
    protected function render_heading( array $content ): void {

        $title = LawFirmQueries::content( $content, 'title' );
        $description = LawFirmQueries::content( $content, 'description' );

        if ( '' === $title && '' === $description ) {
            return;
        }

        echo '<div class="bb-section-heading">';

        if ( '' !== $title ) {
            echo '<h2 class="bb-section-title">' . esc_html( (string) $title ) . '</h2>';
        }

        if ( '' !== $description ) {
            echo '<div class="bb-section-description">' . wp_kses_post( (string) $description ) . '</div>';
        }

        echo '</div>';
    }

    /**
     * Render the empty state.
     *
     * @param string $message Message to display.
     */
    protected function render_empty( string $message ): void {

        echo '<div class="bb-empty-state">' . esc_html( $message ) . '</div>';
    }

    /**
     * Whether an item should be shown on the site.
     *
     * @param int    $post_id Post ID.
     * @param string $meta_key Visibility meta key.
     * @return bool
     */
    protected function is_visible( int $post_id, string $meta_key ): bool {

        return '0' !== get_post_meta( $post_id, $meta_key, true );
    }

    /**
     * Render dynamic lawyers section.
     *
     * @param array $section  Section data.
     * @param array $settings Section settings.
     * @param array $content  Section content.
     */
    public function render_lawyers_section( array $section, array $settings, array $content ): void {

        $columns = isset( $settings['columns'] ) ? absint( $settings['columns'] ) : 3;
        $show_photo = ! isset( $settings['show_photo'] ) || ! empty( $settings['show_photo'] );

        $queries = new LawFirmQueries();
        $items = $queries->lawyers( $settings );

        if ( empty( $items ) ) {
            $this->render_empty( __( 'No lawyers found.', 'business-builder' ) );
            return;
        }

        echo '<div class="bb-section-inner">';

        $this->render_heading( $content );

        echo '<div class="bb-lawyers-grid bb-grid-columns-' . esc_attr( $columns ) . '">';

        foreach ( $items as $item ) {

            if ( ! $this->is_visible( $item->ID, '_bb_lawyer_show_on_website' ) ) {
                continue;
            }

            $photo_id = get_post_thumbnail_id( $item->ID );
            $title = get_post_meta( $item->ID, '_bb_lawyer_title', true );
            $experience = get_post_meta( $item->ID, '_bb_lawyer_experience', true );
            $phone = get_post_meta( $item->ID, '_bb_lawyer_phone', true );
            $email = get_post_meta( $item->ID, '_bb_lawyer_email', true );
            $linkedin = get_post_meta( $item->ID, '_bb_lawyer_linkedin', true );

            echo '<article class="bb-lawyer-card">';

            if ( $show_photo && $photo_id ) {
                echo '<div class="bb-lawyer-photo">';
                echo wp_get_attachment_image( $photo_id, 'medium', false, array( 'class' => 'bb-lawyer-image' ) );
                echo '</div>';
            }

            $profile_url = \BusinessBuilderCore\Packs\LawFirm\Frontend\LawyerProfile::url( (int) $item->ID );
            $is_public = \BusinessBuilderCore\Packs\LawFirm\Frontend\LawyerProfile::is_public( (int) $item->ID );

            echo '<div class="bb-lawyer-body">';

            /*
             * Link the name to the canonical profile URL. Only
             * published lawyers have a working single page, so drafts
             * render the name as plain text instead of a dead link
             * (spec 10 / 14).
             */
            if ( '' !== $profile_url && $is_public ) {
                echo '<h3><a class="bb-lawyer-link" href="' . esc_url( $profile_url ) . '">' . esc_html( $item->post_title ) . '</a></h3>';
            } else {
                echo '<h3>' . esc_html( $item->post_title ) . '</h3>';
            }

            if ( $title ) {
                echo '<p class="bb-lawyer-role">' . esc_html( $title ) . '</p>';
            }

            if ( $experience ) {
                echo '<p class="bb-lawyer-meta">' . esc_html( sprintf( __( '%s years experience', 'business-builder' ), $experience ) ) . '</p>';
            }

            if ( $phone ) {
                echo '<p><a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a></p>';
            }

            if ( $email ) {
                echo '<p><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
            }

            /*
             * Only render the social "Profile" link when the stored value is a
             * real URL. A bare handle (legacy data saved before save-time
             * validation) would otherwise become a dead "http://handle" link.
             */
            $linkedin_is_url = $linkedin && \BusinessBuilderCore\Packs\LawFirm\PostTypes\LawyerFields::is_external_url( (string) $linkedin );

            if ( $linkedin_is_url ) {
                echo '<p><a href="' . esc_url( $linkedin ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Profile', 'business-builder' ) . '</a></p>';
            }

            echo '</div></article>';
        }

        echo '</div></div>';
    }

    /**
     * Render dynamic legal services section.
     *
     * @param array $section  Section data.
     * @param array $settings Section settings.
     * @param array $content  Section content.
     */
    public function render_legal_services_section( array $section, array $settings, array $content ): void {

        $columns = isset( $settings['columns'] ) ? absint( $settings['columns'] ) : 3;

        $queries = new LawFirmQueries();
        $items = $queries->legal_services( $settings );

        if ( empty( $items ) ) {
            $this->render_empty( __( 'No services found.', 'business-builder' ) );
            return;
        }

        echo '<div class="bb-section-inner">';

        $this->render_heading( $content );

        echo '<div class="bb-services-grid bb-grid-columns-' . esc_attr( $columns ) . '">';

        foreach ( $items as $item ) {

            if ( ! $this->is_visible( $item->ID, '_bb_legal_service_show_on_website' ) ) {
                continue;
            }

            $icon = get_post_meta( $item->ID, '_bb_legal_service_icon', true );
            $image_id = get_post_thumbnail_id( $item->ID );
            $text_summary = ! empty( $item->post_excerpt )
                ? $item->post_excerpt
                : wp_trim_words( wp_strip_all_tags( $item->post_content ), 18 );

            echo '<article class="bb-service-card">';

            if ( $image_id ) {
                echo '<div class="bb-service-image">';
                echo wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'bb-service-thumb' ) );
                echo '</div>';
            } elseif ( $icon ) {
                echo '<div class="bb-service-icon">' . esc_html( $icon ) . '</div>';
            }

            echo '<h3>' . esc_html( $item->post_title ) . '</h3>';

            if ( ! empty( $text_summary ) ) {
                echo '<p>' . esc_html( $text_summary ) . '</p>';
            }

            echo '</article>';
        }

        echo '</div></div>';
    }

    /**
     * Render dynamic practice areas section.
     *
     * @param array $section  Section data.
     * @param array $settings Section settings.
     * @param array $content  Section content.
     */
    public function render_practice_areas_section( array $section, array $settings, array $content ): void {

        $columns = isset( $settings['columns'] ) ? absint( $settings['columns'] ) : 4;

        $queries = new LawFirmQueries();
        $terms = $queries->practice_areas( $settings );

        if ( empty( $terms ) ) {
            $this->render_empty( __( 'No practice areas found.', 'business-builder' ) );
            return;
        }

        echo '<div class="bb-section-inner">';

        $this->render_heading( $content );

        echo '<div class="bb-practice-areas-grid bb-grid-columns-' . esc_attr( $columns ) . '">';

        foreach ( $terms as $term ) {

            $image_id = absint(
                get_term_meta( $term->term_id, '_bb_practice_area_image', true )
            );
            $icon = get_term_meta( $term->term_id, '_bb_practice_area_icon', true );

            echo '<article class="bb-practice-area-card">';

            if ( $image_id ) {
                echo '<div class="bb-practice-area-image">';
                echo wp_get_attachment_image( $image_id, 'medium' );
                echo '</div>';
            } elseif ( is_string( $icon ) && '' !== $icon ) {
                echo '<div class="bb-practice-area-icon">' . esc_html( $icon ) . '</div>';
            }

            echo '<h3>' . esc_html( $term->name ) . '</h3>';

            if ( ! empty( $term->description ) ) {
                echo '<p>' . esc_html( wp_trim_words( $term->description, 20 ) ) . '</p>';
            }

            echo '</article>';
        }

        echo '</div></div>';
    }

    /**
     * Render dynamic testimonials section.
     *
     * @param array $section  Section data.
     * @param array $settings Section settings.
     * @param array $content  Section content.
     */
    public function render_testimonials_section( array $section, array $settings, array $content ): void {

        $queries = new LawFirmQueries();
        $items = $queries->testimonials( $settings );

        if ( empty( $items ) ) {
            $this->render_empty( __( 'No testimonials found.', 'business-builder' ) );
            return;
        }

        echo '<div class="bb-section-inner">';

        $this->render_heading( $content );

        echo '<div class="bb-testimonials-grid">';

        foreach ( $items as $item ) {

            if ( ! $this->is_visible( $item->ID, '_bb_testimonial_show_on_website' ) ) {
                continue;
            }

            $client_name = get_post_meta( $item->ID, '_bb_testimonial_client_name', true );
            $client_title = get_post_meta( $item->ID, '_bb_testimonial_client_title', true );
            $rating = absint( get_post_meta( $item->ID, '_bb_testimonial_rating', true ) );
            $image_id = get_post_thumbnail_id( $item->ID );

            $quote = ! empty( $item->post_excerpt )
                ? $item->post_excerpt
                : wp_strip_all_tags( $item->post_content );

            echo '<article class="bb-testimonial-card">';

            if ( $image_id ) {
                echo '<div class="bb-testimonial-image">';
                echo wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'bb-testimonial-thumb' ) );
                echo '</div>';
            }

            if ( $rating ) {
                echo '<div class="bb-testimonial-rating">' . str_repeat( 'â˜…', min( 5, $rating ) ) . '</div>';
            }

            if ( ! empty( $quote ) ) {
                echo '<blockquote>' . esc_html( wp_trim_words( $quote, 30 ) ) . '</blockquote>';
            }

            $display_name = $client_name ? $client_name : $item->post_title;
            echo '<h3>' . esc_html( $display_name ) . '</h3>';

            if ( $client_title ) {
                echo '<p>' . esc_html( $client_title ) . '</p>';
            }

            echo '</article>';
        }

        echo '</div></div>';
    }

    /**
     * Render dynamic FAQ section.
     *
     * @param array $section  Section data.
     * @param array $settings Section settings.
     * @param array $content  Section content.
     */
    public function render_faq_section( array $section, array $settings, array $content ): void {

        $queries = new LawFirmQueries();
        $items = $queries->faqs( $settings );

        if ( empty( $items ) ) {
            $this->render_empty( __( 'No FAQs found.', 'business-builder' ) );
            return;
        }

        echo '<div class="bb-section-inner">';

        $this->render_heading( $content );

        echo '<div class="bb-faq-list">';

        foreach ( $items as $item ) {

            if ( ! $this->is_visible( $item->ID, '_bb_faq_show_on_website' ) ) {
                continue;
            }

            echo '<div class="bb-faq-item">';
            echo '<h3>' . esc_html( $item->post_title ) . '</h3>';
            echo '<div class="bb-faq-answer">' . wp_kses_post( $item->post_content ) . '</div>';
            echo '</div>';
        }

        echo '</div></div>';
    }
}
