<?php

namespace BusinessBuilderCore\Packs\LawFirm\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LawyerFields {

    /**
     * Meta box ID.
     */
    private const META_BOX_ID = 'bb_lawyer_details';

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action(
            'add_meta_boxes',
            array( $this, 'register_meta_box' )
        );

        add_action(
            'save_post_bb_lawyer',
            array( $this, 'save' ),
            10,
            2
        );
    }

    /**
     * Register lawyer details meta box.
     */
    public function register_meta_box(): void {

        add_meta_box(
            self::META_BOX_ID,
            __(
                'Lawyer Details',
                'business-builder'
            ),
            array( $this, 'render_meta_box' ),
            'bb_lawyer',
            'normal',
            'high'
        );
    }

    /**
     * Render lawyer details meta box.
     */
    public function render_meta_box( $post ): void {

        wp_nonce_field(
            'bb_save_lawyer_details',
            'bb_lawyer_details_nonce'
        );

        $fields = array(

            'title' => array(
                'label'       => __( 'Professional Title', 'business-builder' ),
                'type'        => 'text',
                'description' => __( 'For example: Senior Lawyer, Partner, Legal Consultant.', 'business-builder' ),
            ),

            'experience' => array(
                'label'       => __( 'Years of Experience', 'business-builder' ),
                'type'        => 'number',
                'description' => __( 'Number of years of professional experience.', 'business-builder' ),
            ),

            'education' => array(
                'label'       => __( 'Education', 'business-builder' ),
                'type'        => 'textarea',
                'description' => __( 'Degrees, universities, and qualifications. One per line is fine.', 'business-builder' ),
            ),

            'languages' => array(
                'label'       => __( 'Languages', 'business-builder' ),
                'type'        => 'text',
                'description' => __( 'Spoken languages, for example: Arabic, English, French.', 'business-builder' ),
            ),

            'license_number' => array(
                'label'       => __( 'License Number', 'business-builder' ),
                'type'        => 'text',
                'description' => __( 'Professional license or registration number.', 'business-builder' ),
            ),

            'phone' => array(
                'label'       => __( 'Phone', 'business-builder' ),
                'type'        => 'text',
                'description' => __( 'Lawyer phone number.', 'business-builder' ),
            ),

            'whatsapp' => array(
                'label'       => __( 'WhatsApp', 'business-builder' ),
                'type'        => 'text',
                'description' => __( 'WhatsApp number including country code.', 'business-builder' ),
            ),

            'email' => array(
                'label'       => __( 'Email', 'business-builder' ),
                'type'        => 'email',
                'description' => __( 'Lawyer email address.', 'business-builder' ),
            ),

            'linkedin' => array(
                'label'       => __( 'LinkedIn', 'business-builder' ),
                'type'        => 'url',
                'description' => __( 'LinkedIn profile URL.', 'business-builder' ),
            ),

            'facebook' => array(
                'label'       => __( 'Facebook', 'business-builder' ),
                'type'        => 'url',
                'description' => __( 'Facebook profile URL.', 'business-builder' ),
            ),

            'x' => array(
                'label'       => __( 'X', 'business-builder' ),
                'type'        => 'url',
                'description' => __( 'X profile URL.', 'business-builder' ),
            ),

            'display_order' => array(
                'label'       => __( 'Display Order', 'business-builder' ),
                'type'        => 'number',
                'description' => __( 'Lower numbers appear first on the website.', 'business-builder' ),
            ),
        );

        ?>

        <div class="bb-lawyer-fields">

            <?php foreach ( $fields as $field_key => $field ) : ?>

                <?php

                $meta_key = '_bb_lawyer_' . $field_key;

                $value = get_post_meta(
                    $post->ID,
                    $meta_key,
                    true
                );

                ?>

                <div
                    style="
                        margin-bottom: 20px;
                        padding-bottom: 15px;
                        border-bottom: 1px solid #ddd;
                    "
                >

                    <p>
                        <label
                            for="<?php echo esc_attr( $meta_key ); ?>"
                        >
                            <strong>
                                <?php echo esc_html( $field['label'] ); ?>
                            </strong>
                        </label>
                    </p>

                    <p>

                        <?php if ( 'textarea' === $field['type'] ) : ?>

                            <textarea
                                name="<?php echo esc_attr( $meta_key ); ?>"
                                id="<?php echo esc_attr( $meta_key ); ?>"
                                class="widefat"
                                rows="4"
                            ><?php echo esc_textarea( $value ); ?></textarea>

                        <?php else : ?>

                            <input
                                type="<?php echo esc_attr( $field['type'] ); ?>"
                                name="<?php echo esc_attr( $meta_key ); ?>"
                                id="<?php echo esc_attr( $meta_key ); ?>"
                                value="<?php echo esc_attr( $value ); ?>"
                                class="widefat"
                                <?php if ( 'number' === $field['type'] ) : ?>
                                    min="0"
                                    step="1"
                                <?php endif; ?>
                            />

                        <?php endif; ?>

                    </p>

                    <?php if ( ! empty( $field['description'] ) ) : ?>

                        <p class="description">
                            <?php echo esc_html( $field['description'] ); ?>
                        </p>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>


            <?php

            $show_on_website = get_post_meta(
                $post->ID,
                '_bb_lawyer_show_on_website',
                true
            );

            $show_on_website = '' === $show_on_website
                ? '1'
                : $show_on_website;

            $featured = get_post_meta(
                $post->ID,
                '_bb_lawyer_featured',
                true
            );

            ?>

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
                            name="_bb_lawyer_featured"
                            value="1"
                            <?php checked( $featured, '1' ); ?>
                        />

                        <strong>
                            <?php
                            echo esc_html(
                                __(
                                    'Featured',
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
                            'Featured lawyers can be highlighted by sections that only show featured profiles.',
                            'business-builder'
                        )
                    );
                    ?>

                </p>

                <p>

                    <label>

                        <input
                            type="checkbox"
                            name="_bb_lawyer_show_on_website"
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
                            'If disabled, this lawyer will not be displayed on the website.',
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
     * Save lawyer fields.
     */
    public function save(
        int $post_id,
        $post
    ): void {

        if (
            ! isset(
                $_POST['bb_lawyer_details_nonce']
            )
        ) {
            return;
        }

        if (
            ! wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['bb_lawyer_details_nonce']
                    )
                ),
                'bb_save_lawyer_details'
            )
        ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $text_fields = array(
            'title',
            'license_number',
            'phone',
            'whatsapp',
            'languages',
        );

        foreach ( $text_fields as $field ) {

            $meta_key = '_bb_lawyer_' . $field;

            if ( isset( $_POST[ $meta_key ] ) ) {

                $value = sanitize_text_field(
                    wp_unslash(
                        $_POST[ $meta_key ]
                    )
                );

                update_post_meta(
                    $post_id,
                    $meta_key,
                    $value
                );
            }
        }

        if ( isset( $_POST['_bb_lawyer_experience'] ) ) {

            $experience = absint(
                $_POST['_bb_lawyer_experience']
            );

            update_post_meta(
                $post_id,
                '_bb_lawyer_experience',
                $experience
            );
        }

        if ( isset( $_POST['_bb_lawyer_display_order'] ) ) {

            $display_order = absint(
                $_POST['_bb_lawyer_display_order']
            );

            update_post_meta(
                $post_id,
                '_bb_lawyer_display_order',
                $display_order
            );
        }

        if ( isset( $_POST['_bb_lawyer_email'] ) ) {

            $email = sanitize_email(
                wp_unslash(
                    $_POST['_bb_lawyer_email']
                )
            );

            update_post_meta(
                $post_id,
                '_bb_lawyer_email',
                $email
            );
        }

        $url_fields = array(
            'linkedin',
            'facebook',
            'x',
        );

        foreach ( $url_fields as $field ) {

            $meta_key = '_bb_lawyer_' . $field;

            if ( isset( $_POST[ $meta_key ] ) ) {

                $value = esc_url_raw(
                    wp_unslash(
                        $_POST[ $meta_key ]
                    )
                );

                update_post_meta(
                    $post_id,
                    $meta_key,
                    $value
                );
            }
        }

        /*
         * Education is a multi-line textarea and needs
         * textarea-safe sanitization (sanitize_text_field
         * would collapse newlines).
         */
        if ( isset( $_POST['_bb_lawyer_education'] ) ) {

            $education = sanitize_textarea_field(
                wp_unslash(
                    $_POST['_bb_lawyer_education']
                )
            );

            update_post_meta(
                $post_id,
                '_bb_lawyer_education',
                $education
            );
        }

        $show_on_website = isset(
            $_POST['_bb_lawyer_show_on_website']
        )
            ? '1'
            : '0';

        update_post_meta(
            $post_id,
            '_bb_lawyer_show_on_website',
            $show_on_website
        );

        $featured = isset(
            $_POST['_bb_lawyer_featured']
        )
            ? '1'
            : '0';

        update_post_meta(
            $post_id,
            '_bb_lawyer_featured',
            $featured
        );
    }

    /**
     * Get lawyer field.
     */
    public function get_field(
        int $post_id,
        string $field,
        mixed $default = ''
    ): mixed {

        $field = sanitize_key( $field );

        $value = get_post_meta(
            $post_id,
            '_bb_lawyer_' . $field,
            true
        );

        if ( '' === $value || null === $value ) {
            return $default;
        }

        return $value;
    }

    /**
     * Check whether lawyer should be displayed.
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
}
