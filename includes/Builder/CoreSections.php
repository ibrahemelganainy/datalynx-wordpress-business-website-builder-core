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
