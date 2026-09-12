<?php

namespace BusinessBuilderCore\Packs\LawFirm\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LegalServiceFields {

    /**
     * Meta box ID.
     */
    private const META_BOX_ID = 'bb_legal_service_details';

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action(
            'add_meta_boxes',
            array( $this, 'register_meta_box' )
        );

        add_action(
            'save_post_bb_legal_service',
            array( $this, 'save' ),
            10,
            2
        );
    }

    /**
     * Register meta box.
     */
    public function register_meta_box(): void {

        add_meta_box(
            self::META_BOX_ID,
            __(
                'Legal Service Details',
                'business-builder'
            ),
            array( $this, 'render_meta_box' ),
            'bb_legal_service',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box.
     */
    public function render_meta_box( $post ): void {

        wp_nonce_field(
            'bb_save_legal_service_details',
            'bb_legal_service_details_nonce'
        );

        $icon = get_post_meta(
            $post->ID,
            '_bb_legal_service_icon',
            true
        );

        $display_order = get_post_meta(
            $post->ID,
            '_bb_legal_service_display_order',
            true
        );

        $show_on_website = get_post_meta(
            $post->ID,
            '_bb_legal_service_show_on_website',
            true
        );

        if ( '' === $show_on_website ) {
            $show_on_website = '1';
        }

        $featured = get_post_meta(
            $post->ID,
            '_bb_legal_service_featured',
            true
        );

        $cta_text = get_post_meta(
            $post->ID,
            '_bb_legal_service_cta_text',
            true
        );

        $cta_url = get_post_meta(
            $post->ID,
            '_bb_legal_service_cta_url',
            true
        );

        ?>

        <div class="bb-legal-service-fields">

            <div
                style="
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #ddd;
                "
            >

                <p>
                    <label for="bb_legal_service_icon">
                        <strong>
                            <?php
                            echo esc_html(
                                __(
                                    'Icon',
                                    'business-builder'
                                )
                            );
                            ?>
                        </strong>
                    </label>
                </p>

                <p>

                    <input
                        type="text"
                        name="bb_legal_service_icon"
                        id="bb_legal_service_icon"
                        value="<?php echo esc_attr( $icon ); ?>"
                        class="widefat"
                    />

                </p>

                <p class="description">

                    <?php
                    echo esc_html(
                        __(
                            'Enter an icon class, icon name, or icon identifier. The Builder will use this value when rendering the service.',
                            'business-builder'
                        )
                    );
                    ?>

                </p>

            </div>


            <div
                style="
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #ddd;
                "
            >

                <p>
                    <label for="bb_legal_service_display_order">
                        <strong>
                            <?php
                            echo esc_html(
                                __(
                                    'Display Order',
                                    'business-builder'
                                )
                            );
                            ?>
                        </strong>
                    </label>
                </p>

                <p>

                    <input
                        type="number"
                        name="bb_legal_service_display_order"
                        id="bb_legal_service_display_order"
                        value="<?php echo esc_attr( $display_order ); ?>"
                        class="small-text"
                        min="0"
                        step="1"
                    />

                </p>

                <p class="description">

                    <?php
                    echo esc_html(
                        __(
                            'Lower numbers appear first on the website.',
                            'business-builder'
                        )
                    );
                    ?>

                </p>

            </div>


            <div
                style="
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #ddd;
                "
            >

                <p>
                    <label for="bb_legal_service_cta_text">
                        <strong>
                            <?php
                            echo esc_html(
                                __( 'Call to Action Text', 'business-builder' )
                            );
                            ?>
                        </strong>
                    </label>
                </p>

                <p>

                    <input
                        type="text"
                        name="bb_legal_service_cta_text"
                        id="bb_legal_service_cta_text"
                        value="<?php echo esc_attr( $cta_text ); ?>"
                        class="widefat"
                    />

                </p>

                <p>
                    <label for="bb_legal_service_cta_url">
                        <strong>
                            <?php
                            echo esc_html(
                                __( 'Call to Action URL', 'business-builder' )
                            );
                            ?>
                        </strong>
                    </label>
                </p>

                <p>

                    <input
                        type="url"
                        name="bb_legal_service_cta_url"
                        id="bb_legal_service_cta_url"
                        value="<?php echo esc_attr( $cta_url ); ?>"
                        class="widefat"
                        placeholder="https://"
                    />

                </p>

                <p class="description">

                    <?php
                    echo esc_html(
                        __(
                            'Optional button shown with this service. Leave the text empty to hide it.',
                            'business-builder'
                        )
                    );
                    ?>

                </p>

            </div>

            <div
                style="
                    margin-top: 20px;
                    padding: 15px;
                    background: #f6f7f7;
                    border: 1px solid #ddd;
                "
            >

                <p>

                    <label>

                        <input
                            type="checkbox"
                            name="bb_legal_service_featured"
                            value="1"
                            <?php checked( $featured, '1' ); ?>
                        />

                        <strong>
                            <?php
                            echo esc_html(
                                __( 'Featured', 'business-builder' )
                            );
                            ?>
                        </strong>

                    </label>

                </p>

                <p class="description">

                    <?php
                    echo esc_html(
                        __(
                            'Featured services can be highlighted by sections that only show featured items.',
                            'business-builder'
                        )
                    );
                    ?>

                </p>

                <p>

                    <label>

                        <input
                            type="checkbox"
                            name="bb_legal_service_show_on_website"
                            value="1"
                            <?php checked( $show_on_website, '1' ); ?>
                        />

                        <strong>
                            <?php
                            echo esc_html(
                                __(
                                    'Show on Website',
                                    'business-builder'
                                )
                            );
                            ?>
                        </strong>

                    </label>

                </p>

                <p class="description">

                    <?php
                    echo esc_html(
                        __(
                            'If disabled, this legal service will not be displayed on the website.',
                            'business-builder'
                        )
                    );
                    ?>

                </p>

            </div>

        </div>

        <?php
    }

    /**
     * Save legal service fields.
     */
    public function save(
        int $post_id,
        $post
    ): void {

        if (
            ! isset(
                $_POST['bb_legal_service_details_nonce']
            )
        ) {
            return;
        }

        if (
            ! wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['bb_legal_service_details_nonce']
                    )
                ),
                'bb_save_legal_service_details'
            )
        ) {
            return;
        }

        if (
            defined( 'DOING_AUTOSAVE' )
            && DOING_AUTOSAVE
        ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }


        if ( isset( $_POST['bb_legal_service_icon'] ) ) {

            $icon = sanitize_text_field(
                wp_unslash(
                    $_POST['bb_legal_service_icon']
                )
            );

            update_post_meta(
                $post_id,
                '_bb_legal_service_icon',
                $icon
            );
        }


        if (
            isset(
                $_POST['bb_legal_service_display_order']
            )
        ) {

            $display_order = absint(
                $_POST['bb_legal_service_display_order']
            );

            update_post_meta(
                $post_id,
                '_bb_legal_service_display_order',
                $display_order
            );
        }


        if ( isset( $_POST['bb_legal_service_cta_text'] ) ) {

            update_post_meta(
                $post_id,
                '_bb_legal_service_cta_text',
                sanitize_text_field(
                    wp_unslash( $_POST['bb_legal_service_cta_text'] )
                )
            );
        }

        if ( isset( $_POST['bb_legal_service_cta_url'] ) ) {

            update_post_meta(
                $post_id,
                '_bb_legal_service_cta_url',
                esc_url_raw(
                    wp_unslash( $_POST['bb_legal_service_cta_url'] )
                )
            );
        }

        $show_on_website = isset(
            $_POST['bb_legal_service_show_on_website']
        )
            ? '1'
            : '0';

        update_post_meta(
            $post_id,
            '_bb_legal_service_show_on_website',
            $show_on_website
        );

        $featured = isset(
            $_POST['bb_legal_service_featured']
        )
            ? '1'
            : '0';

        update_post_meta(
            $post_id,
            '_bb_legal_service_featured',
            $featured
        );
    }

    /**
     * Get a legal service field.
     */
    public function get_field(
        int $post_id,
        string $field,
        mixed $default = ''
    ): mixed {

        $field = sanitize_key( $field );

        $value = get_post_meta(
            $post_id,
            '_bb_legal_service_' . $field,
            true
        );

        if ( '' === $value || null === $value ) {
            return $default;
        }

        return $value;
    }

    /**
     * Check whether the service should be displayed.
     */
    public function is_visible(
        int $post_id
    ): bool {

        return '1' === $this->get_field(
            $post_id,
            'show_on_website',
            '1'
        );
    }

    /**
     * Get the post type.
     */
    public function get_post_type(): string {

        return 'bb_legal_service';
    }
}
