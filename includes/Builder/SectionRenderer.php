<?php

namespace BusinessBuilderCore\Builder;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SectionRenderer {

    protected SectionRegistry $registry;

    public function __construct(
        SectionRegistry $registry
    ) {
        $this->registry = $registry;
    }

    /**
     * Render all sections of a page.
     */
    public function render_page(
        int $page_id
    ): void {

        $template = get_post_meta(
            $page_id,
            '_bb_page_template',
            true
        );

        $template = is_string( $template )
            ? sanitize_key( $template )
            : '';

        $template_class = '';

        if ( 'modern' === $template ) {
            $template_class = 'bb-template-modern';
        } elseif ( 'luxury' === $template ) {
            $template_class = 'bb-template-luxury';
        } else {
            $template_class = 'bb-template-default';
        }

        echo '<div class="bb-template ' . esc_attr( $template_class ) . '">';

        $sections = get_post_meta(
            $page_id,
            '_bb_page_sections',
            true
        );

        if ( ! is_array( $sections ) ) {
            echo '</div>';
            return;
        }

        foreach ( $sections as $section ) {

            if ( ! is_array( $section ) ) {
                continue;
            }

            $this->render_section(
                $section
            );
        }

        echo '</div>';
    }

    /**
     * Render a single section.
     */
    public function render_section(
        array $section
    ): void {

        if ( empty( $section['type'] ) ) {
            return;
        }

        $type = sanitize_key(
            $section['type']
        );

        $config = $this->registry->get(
            $type
        );

        if ( null === $config ) {
            return;
        }

        if ( empty( $config['enabled'] ) ) {
            return;
        }

        $section_id = ! empty( $section['id'] )
            ? sanitize_html_class(
                $section['id']
            )
            : wp_generate_uuid4();

        $settings = isset(
            $section['settings']
        ) && is_array(
            $section['settings']
        )
            ? $section['settings']
            : array();

        $content = isset(
            $section['content']
        ) && is_array(
            $section['content']
        )
            ? $section['content']
            : array();

        $classes = array(
            'bb-section',
            'bb-section-' . $type,
        );

        /**
         * Allow extensions to modify section classes.
         */
        $classes = apply_filters(
            'bb_section_classes',
            $classes,
            $section,
            $config
        );

        $classes = array_map(
            'sanitize_html_class',
            $classes
        );

        $attributes = array(
            'id' => 'bb-section-' . $section_id,
            'class' => implode(
                ' ',
                $classes
            ),
            'data-section-id' => $section_id,
            'data-section-type' => $type,
        );

        /*
         * Phase 22 presentation state (glass, hover, reveal).
         *
         * These are READ FROM TOKENS rather than from settings, so a Studio
         * customization and a design's authored value are the same input and
         * cannot drift apart. The resolver lives in the Design namespace and is
         * generic: it knows the token vocabulary, never a section name.
         */
        if ( function_exists( 'bb_section_presentation_state' ) ) {

            $state = bb_section_presentation_state( $type );

            foreach ( $state as $name => $value ) {

                if ( '' === $value ) {
                    continue;
                }

                $attributes[ $name ] = $value;
            }
        }

        /**
         * Allow extensions to modify attributes.
         */
        $attributes = apply_filters(
            'bb_section_attributes',
            $attributes,
            $section,
            $config
        );

        $this->render_open_tag(
            $attributes
        );

        /**
         * Custom renderer registered by a section.
         */
        if (
            isset( $config['render'] )
            && is_callable( $config['render'] )
        ) {

            call_user_func(
                $config['render'],
                $section,
                $settings,
                $content,
                $config
            );

        } else {

            /**
             * Generic renderer.
             */
            $this->render_generic(
                $type,
                $section,
                $settings,
                $content
            );
        }

        echo '</section>';
    }

    /**
     * Render opening section tag.
     */
    protected function render_open_tag(
        array $attributes
    ): void {

        $html = '<section';

        foreach ( $attributes as $name => $value ) {

            if ( '' === $value || null === $value ) {
                continue;
            }

            $html .= ' '
                . esc_attr( $name )
                . '="'
                . esc_attr( $value )
                . '"';
        }

        $html .= '>';

        echo $html;
    }

    /**
     * Generic section renderer.
     */
    protected function render_generic(
        string $type,
        array $section,
        array $settings,
        array $content
    ): void {

        if ( 'hero' === $type ) {
            $this->render_hero_section( $settings, $content );
            return;
        }

        if ( 'header' === $type ) {
            $this->render_header_section( $content );
            return;
        }

        if ( 'about' === $type ) {
            $this->render_about_section( $content );
            return;
        }

        if ( 'features' === $type ) {
            $this->render_features_section( $content );
            return;
        }

        if ( 'services' === $type ) {
            $this->render_services_section( $settings, $content );
            return;
        }

        if ( 'cta' === $type ) {
            $this->render_cta_section( $content );
            return;
        }

        if ( 'contact' === $type ) {
            $this->render_contact_section( $settings, $content );
            return;
        }

        if ( 'slider' === $type ) {
            $this->render_slider_section( $settings, $content );
            return;
        }

        if ( 'footer' === $type ) {
            $this->render_footer_section( $content );
            return;
        }

        /**
         * Domain (pack) sections render through a generic, type-agnostic
         * hook. The core renderer must not know any business type: a section
         * that has no dedicated core renderer is offered to `bb_render_section`
         * with its own type slug and is given priority over the core fallback.
         *
         * A pack (or a child plugin) claims its section types by returning
         * true from the action — the same self-registration pattern used by
         * `bb_register_packs` and `bb_register_section_variants`.
         *
         *   add_action( 'bb_render_section', function ( $type, $section, $settings, $content ) {
         *       if ( 'lawyers' !== $type ) { return; }
         *       // ... render ...
         *       return true;
         *   }, 10, 4 );
         *
         * When nothing claims the type, rendering falls through to the
         * generic content renderer below (unchanged legacy behaviour).
         */
        $claimed = apply_filters(
            'bb_render_section',
            false,
            $type,
            $section,
            $settings,
            $content
        );

        if ( true === $claimed ) {
            return;
        }

        $title = isset(
            $content['title']
        )
            ? $content['title']
            : '';

        $description = isset(
            $content['description']
        )
            ? $content['description']
            : '';

        ?>

        <div class="bb-section-inner">

            <?php if ( ! empty( $title ) ) : ?>

                <h2 class="bb-section-title">
                    <?php
                    echo esc_html(
                        $title
                    );
                    ?>
                </h2>

            <?php endif; ?>

            <?php if ( ! empty( $description ) ) : ?>

                <div class="bb-section-description">
                    <?php
                    echo wp_kses_post(
                        $description
                    );
                    ?>
                </div>

            <?php endif; ?>

            <?php
            if ( isset( $content['items'] ) && is_array( $content['items'] ) ) {

                echo '<div class="bb-section-items">';

                foreach ( $content['items'] as $item ) {

                    if ( ! is_array( $item ) ) {
                        continue;
                    }

                    echo '<div class="bb-section-item-card">';

                    foreach ( $item as $key => $value ) {

                        if ( 'image' === $key && ! empty( $value ) ) {

                            $image = wp_get_attachment_image(
                                absint( $value ),
                                'medium'
                            );

                            if ( $image ) {
                                echo '<div class="bb-section-image">' . $image . '</div>';
                            }

                            continue;
                        }

                        if ( 'title' === $key || 'question' === $key ) {
                            echo '<h3 class="bb-section-card-title">' . esc_html( (string) $value ) . '</h3>';
                            continue;
                        }

                        if ( 'description' === $key || 'answer' === $key ) {
                            echo '<div class="bb-section-card-description">' . wp_kses_post( (string) $value ) . '</div>';
                            continue;
                        }

                        if ( is_scalar( $value ) ) {
                            echo '<div class="bb-section-card-meta">' . esc_html( (string) $value ) . '</div>';
                        }
                    }

                    echo '</div>';
                }

                echo '</div>';
            }
            ?>

            <?php
            /**
             * Allow individual section types
             * to inject their own content.
             */
            do_action(
                'bb_render_section_' . $type,
                $section,
                $settings,
                $content
            );
            ?>

        </div>

        <?php
    }

    /**
     * Render the Global Services section (Phase 23 §7, §11, §26).
     *
     * ONE content model, five presentations. The mode only changes the CLASS the
     * markup carries (`bb-services--<mode>`), so:
     *   - the same prepared items render in every layout;
     *   - the visual work stays in CSS (one stylesheet, no per-mode PHP);
     *   - a NEW mode can be added by shipping CSS, not by touching this method.
     *
     * The markup reuses the same card/heading classes every other section uses
     * (`.bb-section-inner`, `.bb-section-title`, `.bb-service-card`), which is why
     * the Studio's global and per-section tokens reach this section with no extra
     * wiring.
     *
     * @param array $settings Section settings.
     * @param array $content  Section content.
     */
    protected function render_services_section( array $settings, array $content ): void {

        $title       = isset( $content['title'] ) ? (string) $content['title'] : '';
        $description = isset( $content['description'] ) ? (string) $content['description'] : '';

        $modes = array( 'default', 'list', 'featured', 'icon-text', 'image-text' );

        $variant = isset( $settings['variant'] ) ? sanitize_key( (string) $settings['variant'] ) : 'default';

        if ( function_exists( 'bb_resolve_section_variant' ) ) {
            $variant = bb_resolve_section_variant( 'services', $variant );
        } elseif ( ! in_array( $variant, $modes, true ) ) {
            $variant = 'default';
        }

        $styles = array( 'elevated', 'bordered', 'flat', 'minimal' );

        $card_style = isset( $settings['card_style'] ) ? sanitize_key( (string) $settings['card_style'] ) : 'elevated';

        if ( ! in_array( $card_style, $styles, true ) ) {
            $card_style = 'elevated';
        }

        $columns = isset( $settings['columns'] ) ? absint( $settings['columns'] ) : 3;
        $columns = max( 1, min( 4, $columns ) );

        $align = isset( $settings['align'] ) && 'center' === $settings['align'] ? 'center' : 'start';

        $items = isset( $content['items'] ) && is_array( $content['items'] ) ? $content['items'] : array();

        $button_text = isset( $content['button_text'] ) ? (string) $content['button_text'] : '';
        $button_url  = isset( $content['button_url'] ) ? (string) $content['button_url'] : '';

        /*
         * The Phase 11 variant architecture is REUSED, not duplicated: when a
         * template is registered for this mode it renders instead, so a pack can
         * override a layout without editing core.
         */
        if ( function_exists( 'bb_render_section_variant' ) ) {

            $rendered = bb_render_section_variant(
                'services',
                $variant,
                array(
                    'items'    => $items,
                    'settings' => $settings,
                    'content'  => $content,
                    'columns'  => $columns,
                    'type'     => 'services',
                    'style'    => $card_style,
                )
            );

            if ( $rendered ) {
                return;
            }
        }

        $classes = array(
            'bb-services',
            'bb-services--' . $variant,
            'bb-services--' . $card_style,
            'bb-services--align-' . $align,
        );

        ?>
        <div class="bb-section-inner">
            <div
                class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
                style="--bb-services-columns: <?php echo esc_attr( (string) $columns ); ?>;"
            >
                <?php if ( '' !== $title || '' !== $description ) : ?>
                    <div class="bb-section-heading">
                        <?php if ( '' !== $title ) : ?>
                            <h2 class="bb-section-title"><?php echo esc_html( $title ); ?></h2>
                        <?php endif; ?>

                        <?php if ( '' !== $description ) : ?>
                            <div class="bb-section-description"><?php echo wp_kses_post( $description ); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $items ) ) : ?>
                    <div class="bb-services-list bb-grid bb-grid-columns-<?php echo esc_attr( (string) $columns ); ?>">
                        <?php $this->render_service_items( $items ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( '' !== $button_text && '' !== $button_url ) : ?>
                    <div class="bb-services-actions">
                        <a class="bb-button bb-button--primary" href="<?php echo esc_url( $button_url ); ?>">
                            <?php echo esc_html( $button_text ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the prepared items of a services section.
     *
     * Kept separate so the inline layouts and any variant template share ONE
     * definition of what a service item looks like.
     *
     * @param array $items Prepared items.
     */
    protected function render_service_items( array $items ): void {

        /*
         * Delegated to the shared helper so the inline renderer and every
         * registered `services` variant template produce identical cards.
         */
        if ( function_exists( 'bb_render_service_items' ) ) {
            bb_render_service_items( $items );

            return;
        }

        foreach ( $items as $item ) {

            if ( ! is_array( $item ) ) {
                continue;
            }

            $item_title = isset( $item['title'] ) ? (string) $item['title'] : '';
            $item_text  = isset( $item['description'] ) ? (string) $item['description'] : '';
            $link_url   = isset( $item['link_url'] ) ? (string) $item['link_url'] : '';
            $link_text  = isset( $item['link_text'] ) ? (string) $item['link_text'] : '';
            $image_id   = isset( $item['image'] ) ? absint( $item['image'] ) : 0;
            $icon       = isset( $item['icon'] ) ? (string) $item['icon'] : '';

            $icon_html = function_exists( 'bb_render_icon' )
                ? bb_render_icon( $icon, array( 'label' => $item_title ) )
                : '';

            ?>
            <article class="bb-service-card">
                <?php if ( $image_id > 0 ) : ?>
                    <div class="bb-service-card-media">
                        <?php echo wp_get_attachment_image( $image_id, 'medium_large', false, array( 'loading' => 'lazy' ) ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( '' !== $icon_html ) : ?>
                    <div class="bb-service-card-icon">
                        <?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- produced by the validated icon renderer. ?>
                    </div>
                <?php endif; ?>

                <div class="bb-service-card-body">
                    <?php if ( '' !== $item_title ) : ?>
                        <h3 class="bb-service-card-title"><?php echo esc_html( $item_title ); ?></h3>
                    <?php endif; ?>

                    <?php if ( '' !== $item_text ) : ?>
                        <div class="bb-service-card-text"><?php echo wp_kses_post( $item_text ); ?></div>
                    <?php endif; ?>

                    <?php if ( '' !== $link_url ) : ?>
                        <a class="bb-service-card-link" href="<?php echo esc_url( $link_url ); ?>">
                            <?php echo esc_html( '' !== $link_text ? $link_text : __( 'Learn more', 'business-builder' ) ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </article>
            <?php
        }
    }

    /**
     * Render a hero section.
     */
    protected function render_hero_section( array $settings, array $content ): void {


        $title = isset( $content['title'] ) ? (string) $content['title'] : '';
        $subheading = isset( $content['subheading'] ) ? (string) $content['subheading'] : '';
        $description = isset( $content['description'] ) ? (string) $content['description'] : '';
        $button_text = isset( $content['button_text'] ) ? (string) $content['button_text'] : '';
        $button_url = isset( $content['button_url'] ) && is_string( $content['button_url'] ) ? $content['button_url'] : '#';
        $image_id = isset( $content['image_id'] ) ? absint( $content['image_id'] ) : 0;
        $alignment = isset( $settings['alignment'] ) ? sanitize_key( (string) $settings['alignment'] ) : 'center';
        $min_height = isset( $settings['min_height'] ) ? absint( $settings['min_height'] ) : 600;

        /* Phase 23 §11 — secondary CTA, media placement and slider mode. */
        $button_2_text = isset( $content['button_2_text'] ) ? (string) $content['button_2_text'] : '';
        $button_2_url  = isset( $content['button_2_url'] ) ? (string) $content['button_2_url'] : '';
        $slides        = isset( $content['slides'] ) && is_array( $content['slides'] ) ? $content['slides'] : array();

        $positions = array( 'start', 'end', 'above', 'below', 'hidden' );

        $media_position = isset( $settings['media_position'] ) ? sanitize_key( (string) $settings['media_position'] ) : 'end';

        if ( ! in_array( $media_position, $positions, true ) ) {
            $media_position = 'end';
        }

        /*
         * SLIDER MODE REUSES THE EXISTING SLIDER ENGINE.
         *
         * The hero does not grow a second slider implementation: it hands the same
         * settings and the slides to `render_slider_section()`, which is the one
         * place that knows how a slider is laid out, whether it autoplays, and how
         * its overlay works. If there are no slides yet, the standard hero renders
         * instead so the section is never empty.
         */
        if ( 'slider' === sanitize_key( (string) ( $settings['mode'] ?? 'standard' ) ) && ! empty( $slides ) ) {

            $this->render_slider_section(
                array(
                    'layout'   => 'center',
                    'height'   => $min_height,
                    'autoplay' => isset( $settings['autoplay'] ) ? $settings['autoplay'] : true,
                    'overlay'  => 'dark',
                ),
                array( 'slides' => $slides )
            );

            return;
        }

        $show_media = $image_id > 0 && 'hidden' !== $media_position;

        ?>
        <div class="bb-section-inner">
            <div
                class="bb-hero bb-hero-align-<?php echo esc_attr( $alignment ); ?> bb-hero-media-<?php echo esc_attr( $media_position ); ?>"
                style="--bb-hero-min-height: <?php echo esc_attr( $min_height ); ?>px;"
            >
                <div class="bb-hero-copy">
                    <?php if ( $subheading ) : ?>
                        <span class="bb-kicker-label"><?php echo esc_html( $subheading ); ?></span>
                    <?php endif; ?>

                    <?php if ( $title ) : ?>
                        <h1 class="bb-section-title"><?php echo esc_html( $title ); ?></h1>
                    <?php endif; ?>

                    <?php if ( $description ) : ?>
                        <div class="bb-section-description"><?php echo wp_kses_post( $description ); ?></div>
                    <?php endif; ?>

                    <?php if ( $button_text || $button_2_text ) : ?>
                        <div class="bb-hero-actions">
                            <?php if ( $button_text ) : ?>
                                <a class="bb-primary-button" href="<?php echo esc_url( $button_url ); ?>">
                                    <?php echo esc_html( $button_text ); ?>
                                </a>
                            <?php endif; ?>

                            <?php if ( $button_2_text ) : ?>
                                <a class="bb-secondary-button" href="<?php echo esc_url( '' !== $button_2_url ? $button_2_url : '#' ); ?>">
                                    <?php echo esc_html( $button_2_text ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ( $show_media ) : ?>
                    <div class="bb-hero-media">
                        <?php echo wp_get_attachment_image( $image_id, 'large', false, array( 'class' => 'bb-hero-image' ) ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render an about section.
     */
    protected function render_about_section( array $content ): void {

        $title = isset( $content['title'] ) ? (string) $content['title'] : '';
        $description = isset( $content['description'] ) ? (string) $content['description'] : '';
        $button_text = isset( $content['button_text'] ) ? (string) $content['button_text'] : '';
        $button_url = isset( $content['button_url'] ) ? (string) $content['button_url'] : '#';
        $image_id = isset( $content['image_id'] ) ? absint( $content['image_id'] ) : 0;

        ?>
        <div class="bb-section-inner">
            <div class="bb-about-showcase">
                <div class="bb-about-media">
                    <?php if ( $image_id ) : ?>
                        <?php echo wp_get_attachment_image( $image_id, 'large', false, array( 'class' => 'bb-about-image' ) ); ?>
                    <?php else : ?>
                        <div class="bb-about-image-placeholder">
                            <span>Brand Story</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="bb-about-copy">
                    <?php if ( $title ) : ?>
                        <span class="bb-kicker-label">About</span>
                        <h2 class="bb-section-title"><?php echo esc_html( $title ); ?></h2>
                    <?php endif; ?>
                    <?php if ( $description ) : ?>
                        <div class="bb-section-description"><?php echo wp_kses_post( $description ); ?></div>
                    <?php endif; ?>
                    <div class="bb-about-metrics">
                        <div><strong>12+</strong><span>Years</span></div>
                        <div><strong>4.9/5</strong><span>Client Rating</span></div>
                        <div><strong>1.2k</strong><span>Projects</span></div>
                    </div>
                    <?php if ( $button_text ) : ?>
                        <a class="bb-primary-button" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_text ); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a features section.
     */
    protected function render_features_section( array $content ): void {

        $title = isset( $content['title'] ) ? (string) $content['title'] : '';
        $description = isset( $content['description'] ) ? (string) $content['description'] : '';
        $items = isset( $content['items'] ) && is_array( $content['items'] ) ? $content['items'] : array();

        ?>
        <div class="bb-section-inner">
            <?php if ( $title || $description ) : ?>
                <div class="bb-section-heading">
                    <?php if ( $title ) : ?>
                        <span class="bb-kicker-label">Why choose us</span>
                        <h2 class="bb-section-title"><?php echo esc_html( $title ); ?></h2>
                    <?php endif; ?>
                    <?php if ( $description ) : ?>
                        <div class="bb-section-description"><?php echo wp_kses_post( $description ); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $items ) ) : ?>
                <div class="bb-features-grid">
                    <?php foreach ( $items as $item ) : ?>
                        <?php if ( ! is_array( $item ) ) continue; ?>
                        <?php $item_title = isset( $item['title'] ) ? (string) $item['title'] : ''; ?>
                        <?php $item_description = isset( $item['description'] ) ? (string) $item['description'] : ''; ?>
                        <?php $item_image = isset( $item['image'] ) ? absint( $item['image'] ) : 0; ?>
                        <article class="bb-feature-card">
                            <div class="bb-feature-icon">
                                <?php if ( $item_image ) : ?>
                                    <?php echo wp_get_attachment_image( $item_image, 'thumbnail' ); ?>
                                <?php else : ?>
                                    <span>✦</span>
                                <?php endif; ?>
                            </div>
                            <?php if ( $item_title ) : ?>
                                <h3><?php echo esc_html( $item_title ); ?></h3>
                            <?php endif; ?>
                            <?php if ( $item_description ) : ?>
                                <p><?php echo esc_html( $item_description ); ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a CTA section.
     */
    protected function render_cta_section( array $content ): void {

        $title = isset( $content['title'] ) ? (string) $content['title'] : '';
        $description = isset( $content['description'] ) ? (string) $content['description'] : '';
        $button_text = isset( $content['button_text'] ) ? (string) $content['button_text'] : '';
        $button_url = isset( $content['button_url'] ) ? (string) $content['button_url'] : '#';

        ?>
        <div class="bb-section-inner">
            <div class="bb-cta-band">
                <div class="bb-cta-copy">
                    <?php if ( $title ) : ?>
                        <h2><?php echo esc_html( $title ); ?></h2>
                    <?php endif; ?>
                    <?php if ( $description ) : ?>
                        <p><?php echo esc_html( $description ); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ( $button_text ) : ?>
                    <a class="bb-primary-button bb-primary-button-light" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_text ); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a header section.
     */
    protected function render_header_section( array $content ): void {

        $logo_text = isset( $content['logo_text'] ) ? (string) $content['logo_text'] : get_bloginfo( 'name' );
        $cta_text = isset( $content['cta_text'] ) ? (string) $content['cta_text'] : __( 'Book a Consultation', 'business-builder' );
        $cta_url = isset( $content['cta_url'] ) ? (string) $content['cta_url'] : '#';
        $nav_links = isset( $content['nav_links'] ) && is_array( $content['nav_links'] ) ? $content['nav_links'] : array();
        $phone = isset( $content['phone'] ) ? (string) $content['phone'] : '';
        $email = isset( $content['email'] ) ? (string) $content['email'] : '';
        $layout = isset( $content['layout'] ) ? (string) $content['layout'] : 'classic';

        ?>
        <div class="bb-section-inner">
            <header class="bb-header-bar bb-header-<?php echo esc_attr( $layout ); ?>">
                <div class="bb-header-brand">
                    <?php echo esc_html( $logo_text ); ?>
                </div>

                <nav class="bb-header-nav" aria-label="Main navigation">
                    <?php foreach ( $nav_links as $link ) : ?>
                        <?php if ( ! is_array( $link ) ) continue; ?>
                        <?php $label = isset( $link['label'] ) ? (string) $link['label'] : ''; ?>
                        <?php $url = isset( $link['url'] ) ? (string) $link['url'] : '#'; ?>
                        <?php if ( $label ) : ?>
                            <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>

                <div class="bb-header-actions">
                    <?php if ( $phone || $email ) : ?>
                        <div class="bb-header-contact-links">
                            <?php if ( $phone ) : ?><a href="tel:<?php echo esc_attr( $phone ); ?>"><?php echo esc_html( $phone ); ?></a><?php endif; ?>
                            <?php if ( $email ) : ?><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $cta_text ) ) : ?>
                        <a class="bb-header-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_text ); ?></a>
                    <?php endif; ?>
                </div>
            </header>
        </div>
        <?php
    }

    /**
     * Render a contact section.
     */
    protected function render_contact_section( array $settings, array $content ): void {

        $title = isset( $content['title'] ) ? (string) $content['title'] : '';
        $description = isset( $content['description'] ) ? (string) $content['description'] : '';
        $phone = isset( $content['phone'] ) ? (string) $content['phone'] : '';
        $email = isset( $content['email'] ) ? (string) $content['email'] : '';
        $address = isset( $content['address'] ) ? (string) $content['address'] : '';
        $whatsapp = isset( $content['whatsapp'] ) ? (string) $content['whatsapp'] : '';
        $show_map = ! empty( $settings['show_map'] );

        ?>
        <div class="bb-section-inner">
            <div class="bb-contact-card">
                <?php if ( $title ) : ?>
                    <h2 class="bb-section-title"><?php echo esc_html( $title ); ?></h2>
                <?php endif; ?>

                <?php if ( $description ) : ?>
                    <div class="bb-section-description"><?php echo wp_kses_post( $description ); ?></div>
                <?php endif; ?>

                <?php if ( $show_map ) : ?>
                    <div class="bb-contact-map-placeholder" aria-label="Map location">
                        <?php esc_html_e( 'Map preview', 'business-builder' ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( $phone || $email || $address || $whatsapp ) : ?>
                    <div class="bb-contact-details">
                        <?php if ( $phone ) : ?>
                            <p><strong><?php esc_html_e( 'Phone', 'business-builder' ); ?>:</strong> <a href="tel:<?php echo esc_attr( $phone ); ?>"><?php echo esc_html( $phone ); ?></a></p>
                        <?php endif; ?>
                        <?php if ( $email ) : ?>
                            <p><strong><?php esc_html_e( 'Email', 'business-builder' ); ?>:</strong> <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></p>
                        <?php endif; ?>
                        <?php if ( $address ) : ?>
                            <p><strong><?php esc_html_e( 'Address', 'business-builder' ); ?>:</strong> <?php echo esc_html( $address ); ?></p>
                        <?php endif; ?>
                        <?php if ( $whatsapp ) : ?>
                            <p><strong><?php esc_html_e( 'WhatsApp', 'business-builder' ); ?>:</strong> <a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $whatsapp ) ); ?>"><?php echo esc_html( $whatsapp ); ?></a></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render an advanced slider section.
     */
    protected function render_slider_section( array $settings, array $content ): void {

        $slides = isset( $content['slides'] ) && is_array( $content['slides'] ) ? $content['slides'] : array();
        $layout = isset( $settings['layout'] ) ? (string) $settings['layout'] : 'split';
        $height = isset( $settings['height'] ) ? absint( $settings['height'] ) : 620;
        $autoplay = ! empty( $settings['autoplay'] );
        $overlay = isset( $settings['overlay'] ) ? (string) $settings['overlay'] : 'dark';

        if ( empty( $slides ) ) {
            echo '<div class="bb-section-inner"><div class="bb-empty-slider">' . esc_html__( 'Add slider slides to start your hero carousel.', 'business-builder' ) . '</div></div>';
            return;
        }

        ?>
        <div class="bb-section-inner">
            <div class="bb-slider bb-slider-<?php echo esc_attr( $layout ); ?>" data-autoplay="<?php echo esc_attr( $autoplay ? '1' : '0' ); ?>" style="--bb-slider-height: <?php echo esc_attr( $height ); ?>px; --bb-slider-overlay: <?php echo esc_attr( $overlay ); ?>;">
                <?php foreach ( $slides as $index => $slide ) : ?>
                    <?php if ( ! is_array( $slide ) ) continue; ?>
                    <?php $image_id = isset( $slide['image'] ) ? absint( $slide['image'] ) : 0; ?>
                    <?php $title = isset( $slide['title'] ) ? (string) $slide['title'] : ''; ?>
                    <?php $description = isset( $slide['description'] ) ? (string) $slide['description'] : ''; ?>
                    <?php $eyebrow = isset( $slide['eyebrow'] ) ? (string) $slide['eyebrow'] : ''; ?>
                    <?php $button_text = isset( $slide['button_text'] ) ? (string) $slide['button_text'] : ''; ?>
                    <?php $button_url = isset( $slide['button_url'] ) ? (string) $slide['button_url'] : '#'; ?>
                    <div class="bb-slider-slide <?php echo 0 === $index ? 'is-active' : ''; ?>">
                        <?php if ( $image_id ) : ?>
                            <div class="bb-slider-image"><?php echo wp_get_attachment_image( $image_id, 'large' ); ?></div>
                        <?php endif; ?>
                        <div class="bb-slider-overlay bb-slider-overlay-<?php echo esc_attr( $overlay ); ?>"></div>
                        <div class="bb-slider-content">
                            <?php if ( $eyebrow ) : ?>
                                <span class="bb-slider-eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
                            <?php endif; ?>
                            <?php if ( $title ) : ?>
                                <h2><?php echo esc_html( $title ); ?></h2>
                            <?php endif; ?>
                            <?php if ( $description ) : ?>
                                <p><?php echo esc_html( $description ); ?></p>
                            <?php endif; ?>
                            <?php if ( $button_text ) : ?>
                                <a class="bb-slider-button" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_text ); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ( count( $slides ) > 1 ) : ?>
                    <div class="bb-slider-nav-wrap" aria-label="Slider navigation">
                        <button type="button" class="bb-slider-nav bb-slider-prev" aria-label="Previous slide">‹</button>
                        <button type="button" class="bb-slider-nav bb-slider-next" aria-label="Next slide">›</button>
                    </div>
                    <div class="bb-slider-dots" aria-label="Slider control">
                        <?php foreach ( $slides as $index => $slide ) : ?>
                            <button type="button" class="bb-slider-dot <?php echo 0 === $index ? 'is-active' : ''; ?>" aria-label="Show slide <?php echo esc_attr( $index + 1 ); ?>" aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>" data-slide-index="<?php echo esc_attr( $index ); ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a footer section.
     */
    protected function render_footer_section( array $content ): void {

        $title = isset( $content['title'] ) ? (string) $content['title'] : get_bloginfo( 'name' );
        $description = isset( $content['description'] ) ? (string) $content['description'] : '';
        $phone = isset( $content['phone'] ) ? (string) $content['phone'] : '';
        $email = isset( $content['email'] ) ? (string) $content['email'] : '';
        $social_links = isset( $content['social_links'] ) && is_array( $content['social_links'] ) ? $content['social_links'] : array();
        $copyright = isset( $content['copyright'] ) ? (string) $content['copyright'] : sprintf( '© %d %s', date( 'Y' ), get_bloginfo( 'name' ) );

        ?>
        <div class="bb-section-inner">
            <footer class="bb-footer-bar">
                <div class="bb-footer-brand">
                    <h3><?php echo esc_html( $title ); ?></h3>
                    <?php if ( $description ) : ?>
                        <p><?php echo esc_html( $description ); ?></p>
                    <?php endif; ?>
                </div>

                <div class="bb-footer-meta">
                    <?php if ( $phone ) : ?>
                        <span><?php echo esc_html( $phone ); ?></span>
                    <?php endif; ?>
                    <?php if ( $email ) : ?>
                        <span><?php echo esc_html( $email ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty( $social_links ) ) : ?>
                        <div class="bb-footer-socials">
                            <?php foreach ( $social_links as $social ) : ?>
                                <?php if ( ! is_array( $social ) ) continue; ?>
                                <?php $social_label = isset( $social['label'] ) ? (string) $social['label'] : ''; ?>
                                <?php $social_url = isset( $social['url'] ) ? (string) $social['url'] : '#'; ?>
                                <?php if ( $social_label ) : ?>
                                    <a href="<?php echo esc_url( $social_url ); ?>"><?php echo esc_html( $social_label ); ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bb-footer-copyright">
                    <?php echo esc_html( $copyright ); ?>
                </div>
            </footer>
        </div>
        <?php
    }

    /**
     * Render a section by ID.
     */
    public function render_section_by_id(
        int $page_id,
        string $section_id
    ): bool {

        $sections = get_post_meta(
            $page_id,
            '_bb_page_sections',
            true
        );

        if ( ! is_array( $sections ) ) {
            return false;
        }

        foreach ( $sections as $section ) {

            if (
                isset( $section['id'] )
                && $section['id'] === $section_id
            ) {

                $this->render_section(
                    $section
                );

                return true;
            }
        }

        return false;
    }
}