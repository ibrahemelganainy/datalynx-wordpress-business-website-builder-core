<?php

namespace BusinessBuilderCore\Builder;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CoreSections {

    protected SectionRegistry $registry;

    public function __construct(
        SectionRegistry $registry
    ) {

        $this->registry = $registry;
    }

    /**
     * Register core sections.
     */
    public function register(): void {

        $this->register_header();
        $this->register_hero();
        $this->register_slider();
        $this->register_about();
        $this->register_features();
        $this->register_services();
        $this->register_cta();
        $this->register_contact();
        $this->register_footer();
    }

    /**
     * Register Header section.
     */
    protected function register_header(): void {

        $this->registry->register(
            'header',
            array(
                'name' => 'Header',
                'label' => __(
                    'Header',
                    'business-builder'
                ),
                'description' => __(
                    'Top navigation and call-to-action area for the page.',
                    'business-builder'
                ),
                'category' => 'navigation',
                'icon' => 'dashicons-admin-home',
                'content' => array(
                    'logo_text' => array(
                        'type' => 'text',
                        'label' => __(
                            'Logo Text',
                            'business-builder'
                        ),
                        'default' => get_bloginfo( 'name' ),
                    ),
                    'nav_links' => array(
                        'type' => 'repeater',
                        'label' => __(
                            'Navigation Links',
                            'business-builder'
                        ),
                        'default' => array(),
                        'fields' => array(
                            'label' => array(
                                'type' => 'text',
                                'label' => __(
                                    'Label',
                                    'business-builder'
                                ),
                            ),
                            'url' => array(
                                'type' => 'url',
                                'label' => __(
                                    'URL',
                                    'business-builder'
                                ),
                                'placeholder' => 'https://',
                            ),
                        ),
                    ),
                    'cta_text' => array(
                        'type' => 'text',
                        'label' => __(
                            'CTA Text',
                            'business-builder'
                        ),
                        'default' => __(
                            'Book a Consultation',
                            'business-builder'
                        ),
                    ),
                    'cta_url' => array(
                        'type' => 'url',
                        'label' => __(
                            'CTA URL',
                            'business-builder'
                        ),
                        'default' => '#',
                    ),
                    'phone' => array(
                        'type' => 'text',
                        'label' => __(
                            'Phone',
                            'business-builder'
                        ),
                        'default' => '',
                    ),
                    'email' => array(
                        'type' => 'email',
                        'label' => __(
                            'Email',
                            'business-builder'
                        ),
                        'default' => '',
                    ),
                    'layout' => array(
                        'type' => 'select',
                        'label' => __(
                            'Layout',
                            'business-builder'
                        ),
                        'default' => 'classic',
                        'options' => array(
                            'classic' => __(
                                'Classic',
                                'business-builder'
                            ),
                            'enterprise' => __(
                                'Enterprise',
                                'business-builder'
                            ),
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Register Hero section.
     */
    protected function register_hero(): void {

        $this->registry->register(
            'hero',
            array(
                'name' => 'Hero',

                'label' => __(
                    'Hero',
                    'business-builder'
                ),

                'description' => __(
                    'Main introductory section of the website.',
                    'business-builder'
                ),

                'category' => 'content',

                'icon' => 'dashicons-cover-image',

                'supports' => array(
                    'title',
                    'description',
                    'button',
                    'image',
                    'background',
                ),

                /*
                 * Phase 23 §7: the hero's READABLE capabilities, so the Studio can
                 * decide which controls are meaningful for it. `slider` and
                 * `media` are what the new mode / media-position controls need.
                 */
                'capabilities' => array(
                    'title',
                    'description',
                    'button',
                    'buttons',
                    'image',
                    'media',
                    'background',
                    'overlay',
                    'gradient',
                    'container',
                    'slider',
                    'motion',
                    'glass',
                    'typography',
                ),

                'settings' => array(

                    'alignment' => array(
                        'type'    => 'select',
                        'label'   => __(
                            'Alignment',
                            'business-builder'
                        ),
                        'default' => 'center',
                        'options' => array(
                            'left' => __(
                                'Left',
                                'business-builder'
                            ),
                            'center' => __(
                                'Center',
                                'business-builder'
                            ),
                            'right' => __(
                                'Right',
                                'business-builder'
                            ),
                        ),
                    ),

                    'min_height' => array(
                        'type'    => 'number',
                        'label'   => __(
                            'Minimum Height',
                            'business-builder'
                        ),
                        'default' => 600,
                        'min'     => 200,
                        'max'     => 1200,
                        'step'    => 10,
                        'description' => __(
                            'Minimum height of the hero section in pixels.',
                            'business-builder'
                        ),
                    ),

                    /*
                     * Phase 23 §11 — the hero's PRESENTATION controls.
                     *
                     * NOTE ON BACKGROUND: the hero does NOT get its own background
                     * colour/gradient/overlay controls. Those already exist ONCE, in
                     * the Studio's per-section Background group (SectionStyleSchema),
                     * which applies to every section including this one. Adding a
                     * second set here would be two controls for one property — the
                     * thing the specification explicitly forbids.
                     */
                    'mode' => array(
                        'type'    => 'select',
                        'label'   => __(
                            'Hero mode',
                            'business-builder'
                        ),
                        'default' => 'standard',
                        'options' => array(
                            'standard' => __(
                                'Standard',
                                'business-builder'
                            ),
                            'slider'   => __(
                                'Slider (uses the slides below)',
                                'business-builder'
                            ),
                        ),
                        'description' => __(
                            'Slider mode renders the slides with the existing slider engine instead of the single hero.',
                            'business-builder'
                        ),
                    ),

                    'media_position' => array(
                        'type'    => 'select',
                        'label'   => __(
                            'Content image position',
                            'business-builder'
                        ),
                        'default' => 'end',
                        'options' => array(
                            'end'    => __(
                                'Beside the text (after)',
                                'business-builder'
                            ),
                            'start'  => __(
                                'Beside the text (before)',
                                'business-builder'
                            ),
                            'above'  => __(
                                'Above the text',
                                'business-builder'
                            ),
                            'below'  => __(
                                'Below the text',
                                'business-builder'
                            ),
                            'hidden' => __(
                                'Hidden',
                                'business-builder'
                            ),
                        ),
                    ),
                ),

                'content' => array(

                    'title' => array(
                        'type'        => 'text',
                        'label'       => __(
                            'Title',
                            'business-builder'
                        ),
                        'default'     => '',
                        'placeholder' => __(
                            'Enter the main title...',
                            'business-builder'
                        ),
                        'required'    => true,
                    ),

                    'subheading' => array(
                        'type'        => 'text',
                        'label'       => __(
                            'Subheading',
                            'business-builder'
                        ),
                        'default'     => '',
                        'placeholder' => __(
                            'Enter a short subheading...',
                            'business-builder'
                        ),
                    ),

                    'description' => array(
                        'type'        => 'textarea',
                        'label'       => __(
                            'Description',
                            'business-builder'
                        ),
                        'default'     => '',
                        'rows'        => 5,
                        'placeholder' => __(
                            'Enter the hero description...',
                            'business-builder'
                        ),
                    ),

                    'button_text' => array(
                        'type'        => 'text',
                        'label'       => __(
                            'Button Text',
                            'business-builder'
                        ),
                        'default'     => '',
                        'placeholder' => __(
                            'e.g. Contact Us',
                            'business-builder'
                        ),
                    ),

                    'button_url' => array(
                        'type'        => 'url',
                        'label'       => __(
                            'Button URL',
                            'business-builder'
                        ),
                        'default'     => '',
                        'placeholder' => 'https://',
                    ),

                    'image_id' => array(
                        'type'    => 'image',
                        'label'   => __(
                            'Hero Image',
                            'business-builder'
                        ),
                        'default' => 0,
                    ),

                    /* Phase 23 §11: a SECOND call to action, and the hero slides. */
                    'button_2_text' => array(
                        'type'        => 'text',
                        'label'       => __(
                            'Secondary Button Text',
                            'business-builder'
                        ),
                        'default'     => '',
                        'placeholder' => __(
                            'e.g. See our work',
                            'business-builder'
                        ),
                    ),

                    'button_2_url' => array(
                        'type'        => 'url',
                        'label'       => __(
                            'Secondary Button URL',
                            'business-builder'
                        ),
                        'default'     => '',
                        'placeholder' => 'https://',
                    ),

                    /*
                     * Slider mode content. Rendered by the EXISTING slider engine,
                     * so a slide is defined once and the hero does not grow a
                     * parallel slider implementation.
                     */
                    'slides' => array(
                        'type'    => 'repeater',
                        'label'   => __(
                            'Hero slides',
                            'business-builder'
                        ),
                        'default' => array(),
                        'fields'  => array(
                            'image' => array(
                                'type'  => 'image',
                                'label' => __(
                                    'Background image',
                                    'business-builder'
                                ),
                            ),
                            'eyebrow' => array(
                                'type'  => 'text',
                                'label' => __(
                                    'Eyebrow',
                                    'business-builder'
                                ),
                            ),
                            'title' => array(
                                'type'  => 'text',
                                'label' => __(
                                    'Title',
                                    'business-builder'
                                ),
                            ),
                            'description' => array(
                                'type'  => 'textarea',
                                'label' => __(
                                    'Description',
                                    'business-builder'
                                ),
                                'rows'  => 3,
                            ),
                            'button_text' => array(
                                'type'  => 'text',
                                'label' => __(
                                    'Button text',
                                    'business-builder'
                                ),
                            ),
                            'button_url' => array(
                                'type'  => 'url',
                                'label' => __(
                                    'Button URL',
                                    'business-builder'
                                ),
                            ),
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Register About section.
     */
    protected function register_about(): void {

        $this->registry->register(
            'about',
            array(
                'name' => 'About',

                'label' => __(
                    'About',
                    'business-builder'
                ),

                'description' => __(
                    'About the business or organization.',
                    'business-builder'
                ),

                'category' => 'content',

                'icon' => 'dashicons-businessperson',

                'supports' => array(
                    'title',
                    'description',
                    'image',
                    'button',
                ),

                'settings' => array(

                    'image_position' => array(
                        'type'    => 'select',
                        'label'   => __(
                            'Image Position',
                            'business-builder'
                        ),
                        'default' => 'right',
                        'options' => array(
                            'left' => __(
                                'Left',
                                'business-builder'
                            ),
                            'right' => __(
                                'Right',
                                'business-builder'
                            ),
                        ),
                    ),
                ),

                'content' => array(

                    'title' => array(
                        'type'        => 'text',
                        'label'       => __(
                            'Title',
                            'business-builder'
                        ),
                        'default'     => '',
                        'placeholder' => __(
                            'About title...',
                            'business-builder'
                        ),
                    ),

                    'description' => array(
                        'type'        => 'textarea',
                        'label'       => __(
                            'Description',
                            'business-builder'
                        ),
                        'default'     => '',
                        'rows'        => 7,
                    ),

                    'image_id' => array(
                        'type'    => 'image',
                        'label'   => __(
                            'Image',
                            'business-builder'
                        ),
                        'default' => 0,
                    ),

                    'button_text' => array(
                        'type'    => 'text',
                        'label'   => __(
                            'Button Text',
                            'business-builder'
                        ),
                        'default' => '',
                    ),

                    'button_url' => array(
                        'type'    => 'url',
                        'label'   => __(
                            'Button URL',
                            'business-builder'
                        ),
                        'default' => '',
                    ),
                ),
            )
        );
    }

    /**
     * Register Features section.
     */
    protected function register_features(): void {

        $this->registry->register(
            'features',
            array(
                'name' => 'Features',

                'label' => __(
                    'Features',
                    'business-builder'
                ),

                'description' => __(
                    'Display a collection of features or benefits.',
                    'business-builder'
                ),

                'category' => 'content',

                'icon' => 'dashicons-star-filled',

                'supports' => array(
                    'title',
                    'description',
                    'items',
                    'icons',
                ),

                'settings' => array(

                    'columns' => array(
                        'type'    => 'select',
                        'label'   => __(
                            'Columns',
                            'business-builder'
                        ),
                        'default' => '3',
                        'options' => array(
                            '1' => '1',
                            '2' => '2',
                            '3' => '3',
                            '4' => '4',
                        ),
                    ),
                ),

                'content' => array(

                    'title' => array(
                        'type'    => 'text',
                        'label'   => __(
                            'Title',
                            'business-builder'
                        ),
                        'default' => '',
                    ),

                    'description' => array(
                        'type'    => 'textarea',
                        'label'   => __(
                            'Description',
                            'business-builder'
                        ),
                        'default' => '',
                        'rows'    => 4,
                    ),

                    'items' => array(
                        'type'    => 'repeater',
                        'label'   => __(
                            'Features',
                            'business-builder'
                        ),
                        'default' => array(),

                        /*
                         * FIX: this repeater previously had
                         * no `fields` definition, so the
                         * builder fell back to rendering a
                         * single plain "Item value" text box
                         * per item (the scalar-repeater path
                         * in renderRepeaterItem()).
                         *
                         * Declaring `fields` here is what
                         * makes each item render as Image /
                         * Title / Description controls
                         * instead — no JS or PHP changes
                         * were needed, this schema is the
                         * only missing piece.
                         */
                        'fields' => array(

                            'image' => array(
                                'type'  => 'image',
                                'label' => __(
                                    'Icon / Image',
                                    'business-builder'
                                ),
                            ),

                            'title' => array(
                                'type'        => 'text',
                                'label'       => __(
                                    'Title',
                                    'business-builder'
                                ),
                                'placeholder' => __(
                                    'Feature title...',
                                    'business-builder'
                                ),
                            ),

                            'description' => array(
                                'type'  => 'textarea',
                                'label' => __(
                                    'Description',
                                    'business-builder'
                                ),
                                'rows'  => 3,
                            ),
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Register CTA section.
     */
    protected function register_cta(): void {

        $this->registry->register(
            'cta',
            array(
                'name' => 'CTA',

                'label' => __(
                    'Call to Action',
                    'business-builder'
                ),

                'description' => __(
                    'Encourage visitors to take an action.',
                    'business-builder'
                ),

                'category' => 'marketing',

                'icon' => 'dashicons-megaphone',

                'supports' => array(
                    'title',
                    'description',
                    'button',
                    'background',
                ),

                'settings' => array(

                    'alignment' => array(
                        'type'    => 'select',
                        'label'   => __(
                            'Alignment',
                            'business-builder'
                        ),
                        'default' => 'center',
                        'options' => array(
                            'left' => __(
                                'Left',
                                'business-builder'
                            ),
                            'center' => __(
                                'Center',
                                'business-builder'
                            ),
                            'right' => __(
                                'Right',
                                'business-builder'
                            ),
                        ),
                    ),
                ),

                'content' => array(

                    'title' => array(
                        'type'    => 'text',
                        'label'   => __(
                            'Title',
                            'business-builder'
                        ),
                        'default' => '',
                    ),

                    'description' => array(
                        'type'    => 'textarea',
                        'label'   => __(
                            'Description',
                            'business-builder'
                        ),
                        'default' => '',
                        'rows'    => 5,
                    ),

                    'button_text' => array(
                        'type'    => 'text',
                        'label'   => __(
                            'Button Text',
                            'business-builder'
                        ),
                        'default' => '',
                    ),

                    'button_url' => array(
                        'type'    => 'url',
                        'label'   => __(
                            'Button URL',
                            'business-builder'
                        ),
                        'default' => '',
                    ),
                ),
            )
        );
    }

    /**
     * Register advanced slider section.
     */
    protected function register_slider(): void {

        $this->registry->register(
            'slider',
            array(
                'name' => 'Slider',
                'label' => __(
                    'Advanced Slider',
                    'business-builder'
                ),
                'description' => __(
                    'Professional hero slider with image, headline, CTA and layered overlay.',
                    'business-builder'
                ),
                'category' => 'marketing',
                'icon' => 'dashicons-slides',
                'settings' => array(
                    'layout' => array(
                        'type' => 'select',
                        'label' => __(
                            'Layout',
                            'business-builder'
                        ),
                        'default' => 'split',
                        'options' => array(
                            'split' => __(
                                'Split',
                                'business-builder'
                            ),
                            'center' => __(
                                'Center',
                                'business-builder'
                            ),
                        ),
                    ),
                    'height' => array(
                        'type' => 'number',
                        'label' => __(
                            'Height',
                            'business-builder'
                        ),
                        'default' => 620,
                        'min' => 300,
                        'max' => 1000,
                        'step' => 10,
                    ),
                    'autoplay' => array(
                        'type' => 'checkbox',
                        'label' => __(
                            'Autoplay',
                            'business-builder'
                        ),
                        'default' => true,
                    ),
                    'overlay' => array(
                        'type' => 'select',
                        'label' => __(
                            'Overlay',
                            'business-builder'
                        ),
                        'default' => 'dark',
                        'options' => array(
                            'dark' => __(
                                'Dark',
                                'business-builder'
                            ),
                            'light' => __(
                                'Light',
                                'business-builder'
                            ),
                            'none' => __(
                                'None',
                                'business-builder'
                            ),
                        ),
                    ),
                ),
                'content' => array(
                    'slides' => array(
                        'type' => 'repeater',
                        'label' => __(
                            'Slides',
                            'business-builder'
                        ),
                        'default' => array(),
                        'fields' => array(
                            'image' => array(
                                'type' => 'image',
                                'label' => __(
                                    'Background Image',
                                    'business-builder'
                                ),
                            ),
                            'eyebrow' => array(
                                'type' => 'text',
                                'label' => __(
                                    'Eyebrow',
                                    'business-builder'
                                ),
                                'placeholder' => __(
                                    'Trusted by 2,500 companies',
                                    'business-builder'
                                ),
                            ),
                            'title' => array(
                                'type' => 'text',
                                'label' => __(
                                    'Title',
                                    'business-builder'
                                ),
                                'placeholder' => __(
                                    'Business growth at scale',
                                    'business-builder'
                                ),
                            ),
                            'description' => array(
                                'type' => 'textarea',
                                'label' => __(
                                    'Description',
                                    'business-builder'
                                ),
                                'rows' => 4,
                            ),
                            'button_text' => array(
                                'type' => 'text',
                                'label' => __(
                                    'Button Text',
                                    'business-builder'
                                ),
                                'placeholder' => __(
                                    'Get Started',
                                    'business-builder'
                                ),
                            ),
                            'button_url' => array(
                                'type' => 'url',
                                'label' => __(
                                    'Button URL',
                                    'business-builder'
                                ),
                                'placeholder' => 'https://',
                            ),
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Register the Global Services section (Phase 23 §11, §26).
     *
     * WHY A CORE SECTION AND NOT A PACK SECTION
     * -----------------------------------------
     * "Services" is the single most common content block in every business
     * website, in every pack. Implementing it once, generically, means the
     * LawFirm, Medical, Education and RealEstate packs all get it — and a future
     * pack gets it for free — with no duplicated markup and no business type in
     * the generic layer.
     *
     * It is also the reference implementation of the presentation modes required
     * by §7: ONE set of content, FIVE layouts (`presentation`), grid columns per
     * breakpoint, icon AND image per item, and card styling driven by the shared
     * card tokens — so the Studio's Cards/Shape/Glass controls all apply.
     */
    protected function register_services(): void {

        $this->registry->register(
            'services',
            array(
                'name'         => 'Services',
                'label'        => __( 'Services', 'business-builder' ),
                'description'  => __( 'Present what you offer — as a grid, a list, icon cards or image cards.', 'business-builder' ),
                'category'     => 'content',
                'icon'         => 'dashicons-portfolio',

                /*
                 * DECLARED CAPABILITIES (§7). These are READ, not decorative:
                 * `SectionStyleSchema` shows the cards group only for a section
                 * that declares `cards`, and the icon control exists because
                 * `icons` is declared.
                 */
                'capabilities' => array(
                    'title',
                    'description',
                    'items',
                    'cards',
                    'card_image',
                    'icons',
                    'image',
                    'links',
                    'button',
                    'cta',
                    'background',
                    'overlay',
                    'gradient',
                    'container',
                    'columns',
                    'presentation',
                ),

                'settings' => array(

                    /*
                     * ONE layout control, and it is the EXISTING Phase 11 variant
                     * registry — not a second, parallel "presentation" setting. The
                     * options are supplied by the registry, and the Studio's layout
                     * picker reads the very same registry, so a layout can never be
                     * offered in one place and missing in the other.
                     */
                    'variant' => array(
                        'type'    => 'select',
                        'label'   => __( 'Layout', 'business-builder' ),
                        'default' => 'default',
                        'options' => function_exists( 'bb_section_variant_options' )
                            ? bb_section_variant_options( 'services' )
                            : array( 'default' => __( 'Default layout', 'business-builder' ) ),
                    ),

                    'columns' => array(
                        'type'    => 'select',
                        'label'   => __( 'Columns (desktop)', 'business-builder' ),
                        'default' => '3',
                        'options' => array(
                            '1' => '1',
                            '2' => '2',
                            '3' => '3',
                            '4' => '4',
                        ),
                    ),

                    'card_style' => array(
                        'type'    => 'select',
                        'label'   => __( 'Card style', 'business-builder' ),
                        'default' => 'elevated',
                        'options' => array(
                            'elevated' => __( 'Elevated', 'business-builder' ),
                            'bordered' => __( 'Bordered', 'business-builder' ),
                            'flat'     => __( 'Flat', 'business-builder' ),
                            'minimal'  => __( 'Minimal (no card surface)', 'business-builder' ),
                        ),
                    ),

                    'align' => array(
                        'type'    => 'select',
                        'label'   => __( 'Content alignment', 'business-builder' ),
                        'default' => 'start',
                        'options' => array(
                            'start'  => __( 'Left', 'business-builder' ),
                            'center' => __( 'Center', 'business-builder' ),
                        ),
                    ),
                ),

                'content' => array(

                    'title' => array(
                        'type'    => 'text',
                        'label'   => __( 'Title', 'business-builder' ),
                        'default' => '',
                    ),

                    'description' => array(
                        'type'    => 'textarea',
                        'label'   => __( 'Description', 'business-builder' ),
                        'default' => '',
                        'rows'    => 3,
                    ),

                    'items' => array(
                        'type'    => 'repeater',
                        'label'   => __( 'Services', 'business-builder' ),
                        'default' => array(),
                        'fields'  => array(

                            /*
                             * ICON **AND** IMAGE, not "icon instead of image".
                             * Both are declared, so a designer chooses per item;
                             * the renderer shows whichever the layout calls for,
                             * and falls back to the other (§18, §26).
                             */
                            'icon' => function_exists( 'bb_icon_field' )
                                ? bb_icon_field( __( 'Icon', 'business-builder' ) )
                                : array(
                                    'type'    => 'text',
                                    'label'   => __( 'Icon', 'business-builder' ),
                                    'default' => '',
                                ),

                            'image' => array(
                                'type'  => 'image',
                                'label' => __( 'Image', 'business-builder' ),
                            ),

                            'title' => array(
                                'type'        => 'text',
                                'label'       => __( 'Title', 'business-builder' ),
                                'placeholder' => __( 'Service name…', 'business-builder' ),
                            ),

                            'description' => array(
                                'type'  => 'textarea',
                                'label' => __( 'Description', 'business-builder' ),
                                'rows'  => 3,
                            ),

                            'link_url' => array(
                                'type'        => 'url',
                                'label'       => __( 'Link', 'business-builder' ),
                                'placeholder' => 'https://',
                            ),

                            'link_text' => array(
                                'type'        => 'text',
                                'label'       => __( 'Link text', 'business-builder' ),
                                'placeholder' => __( 'Learn more', 'business-builder' ),
                            ),
                        ),
                    ),

                    'button_text' => array(
                        'type'        => 'text',
                        'label'       => __( 'Section button text', 'business-builder' ),
                        'default'     => '',
                        'placeholder' => __( 'Talk to us', 'business-builder' ),
                    ),

                    'button_url' => array(
                        'type'        => 'url',
                        'label'       => __( 'Section button URL', 'business-builder' ),
                        'default'     => '',
                        'placeholder' => 'https://',
                    ),
                ),
            )
        );
    }

    /**
     * Register Footer section.
     */
    protected function register_footer(): void {


        $this->registry->register(
            'footer',
            array(
                'name' => 'Footer',
                'label' => __(
                    'Footer',
                    'business-builder'
                ),
                'description' => __(
                    'Footer area with business details and secondary navigation.',
                    'business-builder'
                ),
                'category' => 'navigation',
                'icon' => 'dashicons-editor-insertmore',
                'content' => array(
                    'title' => array(
                        'type' => 'text',
                        'label' => __(
                            'Title',
                            'business-builder'
                        ),
                        'default' => get_bloginfo( 'name' ),
                    ),
                    'description' => array(
                        'type' => 'textarea',
                        'label' => __(
                            'Description',
                            'business-builder'
                        ),
                        'default' => '',
                        'rows' => 4,
                    ),
                    'phone' => array(
                        'type' => 'text',
                        'label' => __(
                            'Phone',
                            'business-builder'
                        ),
                        'default' => '',
                    ),
                    'email' => array(
                        'type' => 'email',
                        'label' => __(
                            'Email',
                            'business-builder'
                        ),
                        'default' => '',
                    ),
                    'social_links' => array(
                        'type' => 'repeater',
                        'label' => __(
                            'Social Links',
                            'business-builder'
                        ),
                        'default' => array(),
                        'fields' => array(
                            'label' => array(
                                'type' => 'text',
                                'label' => __(
                                    'Label',
                                    'business-builder'
                                ),
                                'placeholder' => __(
                                    'LinkedIn',
                                    'business-builder'
                                ),
                            ),
                            'url' => array(
                                'type' => 'url',
                                'label' => __(
                                    'URL',
                                    'business-builder'
                                ),
                                'placeholder' => 'https://',
                            ),
                        ),
                    ),
                    'copyright' => array(
                        'type' => 'text',
                        'label' => __(
                            'Copyright',
                            'business-builder'
                        ),
                        'default' => '© ' . date( 'Y' ) . ' ' . get_bloginfo( 'name' ),
                    ),
                ),
            )
        );
    }

    /**
     * Register Contact section.
     */
    protected function register_contact(): void {

        $this->registry->register(
            'contact',
            array(
                'name' => 'Contact',

                'label' => __(
                    'Contact',
                    'business-builder'
                ),

                'description' => __(
                    'Display contact information and contact options.',
                    'business-builder'
                ),

                'category' => 'communication',

                'icon' => 'dashicons-email',

                'supports' => array(
                    'title',
                    'description',
                    'phone',
                    'email',
                    'address',
                    'whatsapp',
                    'map',
                ),

                'settings' => array(

                    'show_map' => array(
                        'type'    => 'checkbox',
                        'label'   => __(
                            'Show Map',
                            'business-builder'
                        ),
                        'default' => true,
                    ),
                ),

                'content' => array(

                    'title' => array(
                        'type'    => 'text',
                        'label'   => __(
                            'Title',
                            'business-builder'
                        ),
                        'default' => '',
                    ),

                    'description' => array(
                        'type'    => 'textarea',
                        'label'   => __(
                            'Description',
                            'business-builder'
                        ),
                        'default' => '',
                        'rows'    => 5,
                    ),

                    'phone' => array(
                        'type'    => 'text',
                        'label'   => __(
                            'Phone',
                            'business-builder'
                        ),
                        'default' => '',
                    ),

                    'email' => array(
                        'type'    => 'email',
                        'label'   => __(
                            'Email',
                            'business-builder'
                        ),
                        'default' => '',
                    ),

                    'address' => array(
                        'type'    => 'textarea',
                        'label'   => __(
                            'Address',
                            'business-builder'
                        ),
                        'default' => '',
                        'rows'    => 3,
                    ),

                    'whatsapp' => array(
                        'type'    => 'text',
                        'label'   => __(
                            'WhatsApp',
                            'business-builder'
                        ),
                        'default' => '',
                        'placeholder' => '+966500000000',
                    ),
                ),
            )
        );
    }

    /**
     * Get registry.
     */
    public function get_registry(): SectionRegistry {

        return $this->registry;
    }
}
