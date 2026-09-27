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
     * Register the pack's section layout variants.
     *
     * @param \BusinessBuilderCore\Builder\SectionVariants $variants Registry.
     */
    public function register_section_variants( $variants ): void {

        if ( ! is_object( $variants ) || ! method_exists( $variants, 'register_many' ) ) {
            return;
        }

        $base = __DIR__ . '/variants';

        $variants->register_many(
            'lawyers',
            array(
                'default'  => $base . '/lawyers/default.php',
                'list'     => $base . '/lawyers/list.php',
                'featured' => $base . '/lawyers/featured.php',
            )
        );

        $variants->register_many(
            'legal_services',
            array(
                'default' => $base . '/services/default.php',
                'list'    => $base . '/services/list.php',
            )
        );

        $variants->register_many(
            'practice_areas',
            array(
                'default' => $base . '/practice-areas/default.php',
                'list'    => $base . '/practice-areas/list.php',
            )
        );
    }

    /**
     * Shared section "variant" setting (a whitelisted layout choice).
     *
     * Returns an empty array when no variants are registered for the section,
     * so the builder never shows a pointless single-choice dropdown.
     *
     * @param string $section_type Section slug.
     * @return array
     */
    protected function variant_setting( string $section_type ): array {

        if ( ! function_exists( 'bb_section_variant_options' ) ) {
            return array();
        }

        $options = bb_section_variant_options( $section_type );

        if ( count( $options ) < 2 ) {
            return array();
        }

        return array(
            'type'    => 'select',
            'label'   => __( 'Layout', 'business-builder' ),
            'default' => 'default',
            'options' => $options,
        );
    }

    /**
     * Shared section "card_variant" setting (a whitelisted CARD design choice).
     *
     * This is ORTHOGONAL to the section "Layout" setting: layout controls how
     * the section arranges its items, card_variant controls the visual design of
     * an individual card (Phase 12 §5/§27).
     *
     * The option whitelist is owned by the pack (it knows its own card variants)
     * and is enforced by the existing builder `select` sanitizer. Returns an empty
     * array when a component has no non-default variant, so the builder never
     * shows a pointless single-choice dropdown.
     *
     * @param string                $component           Component path (e.g. 'lawyer/card').
     * @param array<string, string> $variants            variant slug => translated label.
     * @return array
     */
    protected function card_variant_setting( string $component, array $variants ): array {

        if ( count( $variants ) < 2 ) {
            return array();
        }

        /*
         * Keep only variants that actually resolve to a template, so a
         * mis-declared option can never be offered. bb_component_path() is the
         * canonical (whitelist + traversal-safe) resolver from Phase 10.
         */
        $options = array();

        if ( function_exists( 'bb_component_path' ) ) {
            foreach ( $variants as $slug => $label ) {
                $slug = sanitize_key( (string) $slug );
                if ( '' === $slug ) {
                    continue;
                }
                if ( 'default' === $slug || '' !== bb_component_path( $component, $slug ) ) {
                    $options[ $slug ] = $label;
                }
            }
        } else {
            $options = $variants;
        }

        if ( count( $options ) < 2 ) {
            return array();
        }

        return array(
            'type'    => 'select',
            'label'   => __( 'Card design', 'business-builder' ),
            'default' => 'default',
            'options' => $options,
        );
    }

    /**
     * Like card_variant_setting(), but attaches each option's description as
     * schema metadata (`option_titles`). The existing builder schema renderer
     * simply ignores unknown keys, so this is additive UX metadata for Phase 13
     * (used as the option's `title` attribute) rather than a new mechanism.
     *
     * @param string $component Component path.
     * @return array
     */
    protected function card_setting_with_descriptions( string $component ): array {

        $setting = $this->card_setting_for( $component );

        if ( empty( $setting ) ) {
            return array();
        }

        $descriptions = $this->card_variant_descriptions( $component );

        $titles = array();

        foreach ( array_keys( $setting['options'] ) as $slug ) {
            if ( isset( $descriptions[ $slug ] ) && '' !== $descriptions[ $slug ] ) {
                $titles[ $slug ] = $descriptions[ $slug ];
            }
        }

        if ( ! empty( $titles ) ) {
            $setting['option_titles'] = $titles;
        }

        return $setting;
    }

    /**
     * The card-design catalog: component => variant slug => presentation metadata.
     *
     * This is the pack's declaration of the visual designs it ships (Phase 13
     * §14-§15). It is intentionally LIGHTWEIGHT: it is presentation metadata
     * (a translated label + a short description used as the option's title
     * attribute), never a second rendering engine. The resolver still owns
     * which FILE renders a variant (card-{variant}.php); this catalog only
     * describes what is offered to the administrator.
     *
     * @return array<string, array<string, array{label: string, description: string}>>
     */
    public function card_design_catalog(): array {

        $catalog = array(
            'lawyer/card'        => array(
                'default'    => array(
                    'label'       => __( 'Standard card', 'business-builder' ),
                    'description' => __( 'Balanced profile card with photo, role and full contact details.', 'business-builder' ),
                ),
                'compact'    => array(
                    'label'       => __( 'Compact card', 'business-builder' ),
                    'description' => __( 'Dense card with a shorter photo — suits large grids.', 'business-builder' ),
                ),
                'featured'   => array(
                    'label'       => __( 'Featured card', 'business-builder' ),
                    'description' => __( 'High-emphasis profile with a prominent contact button.', 'business-builder' ),
                ),
                'minimal'    => array(
                    'label'       => __( 'Minimal card', 'business-builder' ),
                    'description' => __( 'Typography-first, no photo or card chrome — the single key contact line.', 'business-builder' ),
                ),
                'horizontal' => array(
                    'label'       => __( 'Horizontal card', 'business-builder' ),
                    'description' => __( 'Directory row: photo beside the details. Pairs well with the List layout.', 'business-builder' ),
                ),
            ),
            'service/card'       => array(
                'default'    => array(
                    'label'       => __( 'Standard card', 'business-builder' ),
                    'description' => __( 'Balanced service card with image or icon and a summary.', 'business-builder' ),
                ),
                'compact'    => array(
                    'label'       => __( 'Compact card', 'business-builder' ),
                    'description' => __( 'Denser service card — reduced padding and type.', 'business-builder' ),
                ),
                'featured'   => array(
                    'label'       => __( 'Featured card', 'business-builder' ),
                    'description' => __( 'Emphasised service with a larger media treatment.', 'business-builder' ),
                ),
                'minimal'    => array(
                    'label'       => __( 'Minimal card', 'business-builder' ),
                    'description' => __( 'Text-first service with an inline icon, no card chrome.', 'business-builder' ),
                ),
                'horizontal' => array(
                    'label'       => __( 'Horizontal card', 'business-builder' ),
                    'description' => __( 'Row layout: image or icon beside the copy.', 'business-builder' ),
                ),
            ),
            'practice-area/card' => array(
                'default'    => array(
                    'label'       => __( 'Standard card', 'business-builder' ),
                    'description' => __( 'Balanced practice-area card with image or icon and a summary.', 'business-builder' ),
                ),
                'compact'    => array(
                    'label'       => __( 'Compact card', 'business-builder' ),
                    'description' => __( 'Denser practice-area card.', 'business-builder' ),
                ),
                'featured'   => array(
                    'label'       => __( 'Featured card', 'business-builder' ),
                    'description' => __( 'Emphasised practice area with a larger media treatment.', 'business-builder' ),
                ),
                'minimal'    => array(
                    'label'       => __( 'Minimal card', 'business-builder' ),
                    'description' => __( 'Text-first practice area with an inline icon.', 'business-builder' ),
                ),
                'horizontal' => array(
                    'label'       => __( 'Horizontal card', 'business-builder' ),
                    'description' => __( 'Row layout: image or icon beside the copy.', 'business-builder' ),
                ),
            ),
        );

        /**
         * Filter the LawFirm card-design catalog.
         *
         * @param array $catalog Component => variant => {label, description}.
         */
        return (array) apply_filters( 'bb_lawfirm_card_design_catalog', $catalog );
    }

    /**
     * The card-variant choices each LawFirm card component supports
     * (variant slug => translated label), derived from the catalog.
     *
     * @return array<string, array<string, string>>
     */
    protected function card_variant_choices(): array {

        $choices = array();

        foreach ( $this->card_design_catalog() as $component => $variants ) {

            $choices[ $component ] = array();

            foreach ( $variants as $slug => $meta ) {
                $choices[ $component ][ $slug ] = isset( $meta['label'] ) ? (string) $meta['label'] : (string) $slug;
            }
        }

        return $choices;
    }

    /**
     * The description for a (component, variant) pair, or '' when unknown.
     *
     * Used as the option's `title` attribute so the administrator can read what
     * each design does without leaving the builder.
     *
     * @param string $component Component path.
     * @return array<string, string> variant slug => description.
     */
    protected function card_variant_descriptions( string $component ): array {

        $catalog = $this->card_design_catalog();

        if ( ! isset( $catalog[ $component ] ) ) {
            return array();
        }

        $out = array();

        foreach ( $catalog[ $component ] as $slug => $meta ) {
            $out[ $slug ] = isset( $meta['description'] ) ? (string) $meta['description'] : '';
        }

        return $out;
    }

    /**
     * The card_variant setting for a component (or [] when it has no variants).
     *
     * @param string $component Component path.
     * @return array
     */
    protected function card_setting_for( string $component ): array {

        $choices = $this->card_variant_choices();

        if ( ! isset( $choices[ $component ] ) ) {
            return array();
        }

        return $this->card_variant_setting( $component, $choices[ $component ] );
    }

    /**
     * Register all Law Firm sections.
     */
    public function register(): void {

        /*
         * Register this pack's component directory with the theme's component
         * resolver. The theme owns generic presentation components; the pack
         * owns its domain components (lawyer/service/card...). The dependency
         * direction stays one-way: the pack consumes theme primitives, the
         * theme never depends on pack domain objects (Phase 10 §34).
         */
        add_filter( 'bb_component_roots', array( $this, 'register_component_root' ) );

        /*
         * Register this pack's SECTION LAYOUT VARIANTS with the core
         * SectionVariants registry. Section variants are presentation
         * strategies for a section (same data, same components); the pack owns
         * its own domain variants, so core never learns about LawFirm
         * (Phase 11 §22-§23).
         */
        add_action( 'bb_register_section_variants', array( $this, 'register_section_variants' ) );

        $this->register_lawyers();
        $this->register_legal_services();
        $this->register_practice_areas();
        $this->register_testimonials();
        $this->register_faq();
        $this->register_consultation();
        $this->register_booking();
        $this->register_lookup();

        /*
         * Claim this pack's dynamic sections on the generic, type-agnostic
         * `bb_render_section` hook. The type is compared here (in the pack),
         * not in core, so the core renderer never learns any business type
         * (Phase 18 §11: Theme/Core must not reference business concepts).
         */
        add_filter( 'bb_render_section', array( $this, 'render_section' ), 10, 4 );
    }

    /**
     * Render one of this pack's dynamic sections.
     *
     * Returns true only for the section types this pack owns, so an
     * unclaimed type falls through to core's generic renderer.
     *
     * @param bool  $claimed  Whether a renderer already claimed the type.
     * @param string $type     Section type slug.
     * @param array $section  Section data.
     * @param array $settings Section settings.
     * @param array $content  Section content.
     * @return bool True when this pack rendered the section.
     */
    public function render_section( $claimed, $type, $section = array(), $settings = array(), $content = array() ): bool {

        switch ( sanitize_key( (string) $type ) ) {
            case 'lawyers':
                $this->render_lawyers_section( $section, $settings, $content );
                return true;

            case 'legal_services':
                $this->render_legal_services_section( $section, $settings, $content );
                return true;

            case 'practice_areas':
                $this->render_practice_areas_section( $section, $settings, $content );
                return true;

            case 'testimonials':
                $this->render_testimonials_section( $section, $settings, $content );
                return true;

            case 'faq':
                $this->render_faq_section( $section, $settings, $content );
                return true;
        }

        return (bool) $claimed;
    }

    /**
     * Register the pack's component template directory.
     *
     * @param string[] $roots Existing component roots.
     * @return string[]
     */
    public function register_component_root( $roots ): array {

        $roots   = is_array( $roots ) ? $roots : array();
        $roots[] = __DIR__ . '/components';

        return $roots;
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
                'settings'    => array_merge(
                    array(
                        'variant'      => $this->variant_setting( 'lawyers' ),
                        'card_variant' => $this->card_setting_with_descriptions( 'lawyer/card' ),
                    ),
                    array(
                        'columns'       => $this->columns_setting( 3 ),
                        'limit'         => $this->limit_setting(),
                        'featured'      => $this->featured_setting(),
                        'practice_area' => $this->practice_area_filter_setting(),
                        'order'         => $this->order_setting(),
                    )
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
                'settings'    => array_merge(
                    array(
                        'variant'      => $this->variant_setting( 'legal_services' ),
                        'card_variant' => $this->card_setting_with_descriptions( 'service/card' ),
                    ),
                    array(
                        'columns'       => $this->columns_setting( 3 ),
                        'limit'         => $this->limit_setting(),
                        'featured'      => $this->featured_setting(),
                        'practice_area' => $this->practice_area_filter_setting(),
                        'order'         => $this->order_setting(),
                    )
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
                'settings'    => array_merge(
                    array(
                        'variant'      => $this->variant_setting( 'practice_areas' ),
                        'card_variant' => $this->card_setting_with_descriptions( 'practice-area/card' ),
                    ),
                    array(
                        'columns'  => $this->columns_setting( 4 ),
                        'limit'    => $this->limit_setting(),
                        'featured' => $this->featured_setting(),
                    )
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

        /*
         * Prefer the theme's generic heading component. Fall back to the
         * original inline markup when the theme component API is unavailable,
         * so the plugin keeps working without the canonical theme.
         */
        if ( function_exists( 'bb_component' ) ) {
            bb_component(
                'section-heading',
                array(
                    'title'       => (string) $title,
                    'description' => (string) $description,
                )
            );
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

        if ( function_exists( 'bb_component' ) ) {
            bb_component( 'empty-state', array( 'message' => $message ) );
            return;
        }

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

        /*
         * Prepare the presentation data ONCE (Phase 11 §4: query + preparation
         * happen here, never in a variant template). Each entry is the exact
         * arg array the Phase-10 'lawyer/card' component expects.
         */
        $card_args = array();

        foreach ( $items as $item ) {

            if ( ! $this->is_visible( $item->ID, '_bb_lawyer_show_on_website' ) ) {
                continue;
            }

            $photo_id = get_post_thumbnail_id( $item->ID );

            $photo_html = $photo_id
                ? wp_get_attachment_image( $photo_id, 'medium', false, array( 'class' => 'bb-lawyer-image' ) )
                : '';

            $profile_url = \BusinessBuilderCore\Packs\LawFirm\Frontend\LawyerProfile::url( (int) $item->ID );
            $is_public   = \BusinessBuilderCore\Packs\LawFirm\Frontend\LawyerProfile::is_public( (int) $item->ID );

            $linkedin = (string) get_post_meta( $item->ID, '_bb_lawyer_linkedin', true );

            /*
             * Only pass the social "Profile" link when the stored value is a
             * real URL. A bare handle (legacy data saved before save-time
             * validation) would otherwise become a dead "http://handle" link.
             */
            $profile_link = ( $linkedin && \BusinessBuilderCore\Packs\LawFirm\PostTypes\LawyerFields::is_external_url( $linkedin ) )
                ? $linkedin
                : '';

            $card_args[] = array(
                'name'         => (string) $item->post_title,
                'profile_url'  => (string) $profile_url,
                'is_public'    => (bool) $is_public,
                'photo_html'   => (string) $photo_html,
                'show_photo'   => (bool) $show_photo,
                'role'         => (string) get_post_meta( $item->ID, '_bb_lawyer_title', true ),
                'experience'   => (string) get_post_meta( $item->ID, '_bb_lawyer_experience', true ),
                'phone'        => (string) get_post_meta( $item->ID, '_bb_lawyer_phone', true ),
                'email'        => (string) get_post_meta( $item->ID, '_bb_lawyer_email', true ),
                'profile_link' => (string) $profile_link,
            );
        }

        /*
         * Resolve + render the section layout variant. Falls back to inline
         * default markup if the variant system is unavailable (e.g. the plugin
         * running without the core loader), so behaviour never regresses.
         */
        $variant = isset( $settings['variant'] ) ? (string) $settings['variant'] : '';

        /* Phase 12: the independent CARD design for every item in this section. */
        $card_variant = isset( $settings['card_variant'] ) ? (string) $settings['card_variant'] : '';

        $rendered = function_exists( 'bb_render_section_variant' )
            && bb_render_section_variant(
                'lawyers',
                $variant,
                array(
                    'items'            => $card_args,
                    'columns'          => $columns,
                    'settings'         => $settings,
                    'content'          => $content,
                    'component'        => 'lawyer/card',
                    'component_variant' => $card_variant,
                )
            );

        if ( ! $rendered ) {
            echo '<div class="bb-lawyers-grid bb-grid-columns-' . esc_attr( $columns ) . '">';
            foreach ( $card_args as $one ) {
                bb_component( 'lawyer/card', $one, $card_variant );
            }
            echo '</div>';
        }

        echo '</div>';
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

        /* Prepare the presentation data ONCE (Phase 11 §4-§5). */
        $card_args = array();

        foreach ( $items as $item ) {

            if ( ! $this->is_visible( $item->ID, '_bb_legal_service_show_on_website' ) ) {
                continue;
            }

            $image_id = get_post_thumbnail_id( $item->ID );

            $image_html = $image_id
                ? wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'bb-service-thumb' ) )
                : '';

            $text_summary = ! empty( $item->post_excerpt )
                ? $item->post_excerpt
                : wp_trim_words( wp_strip_all_tags( $item->post_content ), 18 );

            $card_args[] = array(
                'title'      => (string) $item->post_title,
                'summary'    => (string) $text_summary,
                'image_html' => (string) $image_html,
                'icon'       => (string) get_post_meta( $item->ID, '_bb_legal_service_icon', true ),
            );
        }

        $variant = isset( $settings['variant'] ) ? (string) $settings['variant'] : '';

        /* Phase 12: independent CARD design for this section's items. */
        $card_variant = isset( $settings['card_variant'] ) ? (string) $settings['card_variant'] : '';

        $rendered = function_exists( 'bb_render_section_variant' )
            && bb_render_section_variant(
                'legal_services',
                $variant,
                array(
                    'items'            => $card_args,
                    'columns'          => $columns,
                    'settings'         => $settings,
                    'content'          => $content,
                    'component'        => 'service/card',
                    'component_variant' => $card_variant,
                )
            );

        if ( ! $rendered ) {
            echo '<div class="bb-services-grid bb-grid-columns-' . esc_attr( $columns ) . '">';
            foreach ( $card_args as $one ) {
                bb_component( 'service/card', $one, $card_variant );
            }
            echo '</div>';
        }

        echo '</div>';
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

        /* Prepare the presentation data ONCE (Phase 11 §4-§5). */
        $card_args = array();

        foreach ( $terms as $term ) {

            $image_id = absint(
                get_term_meta( $term->term_id, '_bb_practice_area_image', true )
            );

            $image_html = $image_id ? wp_get_attachment_image( $image_id, 'medium' ) : '';

            $summary = ! empty( $term->description )
                ? wp_trim_words( $term->description, 20 )
                : '';

            $card_args[] = array(
                'title'      => (string) $term->name,
                'summary'    => (string) $summary,
                'image_html' => (string) $image_html,
                'icon'       => (string) get_term_meta( $term->term_id, '_bb_practice_area_icon', true ),
            );
        }

        $variant = isset( $settings['variant'] ) ? (string) $settings['variant'] : '';

        /* Phase 12: independent CARD design for this section's items. */
        $card_variant = isset( $settings['card_variant'] ) ? (string) $settings['card_variant'] : '';

        $rendered = function_exists( 'bb_render_section_variant' )
            && bb_render_section_variant(
                'practice_areas',
                $variant,
                array(
                    'items'            => $card_args,
                    'columns'          => $columns,
                    'settings'         => $settings,
                    'content'          => $content,
                    'component'        => 'practice-area/card',
                    'component_variant' => $card_variant,
                )
            );

        if ( ! $rendered ) {
            echo '<div class="bb-practice-areas-grid bb-grid-columns-' . esc_attr( $columns ) . '">';
            foreach ( $card_args as $one ) {
                bb_component( 'practice-area/card', $one, $card_variant );
            }
            echo '</div>';
        }

        echo '</div>';
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

            $client_name  = get_post_meta( $item->ID, '_bb_testimonial_client_name', true );
            $image_id     = get_post_thumbnail_id( $item->ID );

            $image_html = $image_id
                ? wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'bb-testimonial-thumb' ) )
                : '';

            $quote = ! empty( $item->post_excerpt )
                ? $item->post_excerpt
                : wp_strip_all_tags( $item->post_content );

            bb_component(
                'testimonial/card',
                array(
                    'author'       => (string) ( $client_name ? $client_name : $item->post_title ),
                    'author_title' => (string) get_post_meta( $item->ID, '_bb_testimonial_client_title', true ),
                    'quote'        => (string) $quote,
                    'rating'       => absint( get_post_meta( $item->ID, '_bb_testimonial_rating', true ) ),
                    'image_html'   => (string) $image_html,
                )
            );
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

            bb_component(
                'faq/item',
                array(
                    'question' => (string) $item->post_title,
                    'answer'   => (string) $item->post_content,
                )
            );
        }

        echo '</div></div>';
    }
}
