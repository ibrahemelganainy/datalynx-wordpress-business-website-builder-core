<?php

namespace BusinessBuilderCore\Packs\LawFirm\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TestimonialFields {

    /**
     * Meta box ID.
     */
    private const META_BOX_ID = 'bb_testimonial_details';

    /**
     * Register hooks.
     */
    public function register(): void {
        add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
        add_action( 'save_post_bb_testimonial', array( $this, 'save' ), 10, 2 );
    }

    /**
     * Register meta box.
     */
    public function register_meta_box(): void {
        add_meta_box(
            self::META_BOX_ID,
            __( 'Testimonial Details', 'business-builder' ),
            array( $this, 'render_meta_box' ),
            'bb_testimonial',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box.
     */
    public function render_meta_box( $post ): void {
        wp_nonce_field( 'bb_save_testimonial_details', 'bb_testimonial_details_nonce' );

        $client_name = get_post_meta( $post->ID, '_bb_testimonial_client_name', true );
        $client_title = get_post_meta( $post->ID, '_bb_testimonial_client_title', true );
        $rating = get_post_meta( $post->ID, '_bb_testimonial_rating', true );
        $display_order = get_post_meta( $post->ID, '_bb_testimonial_display_order', true );
        $show_on_website = get_post_meta( $post->ID, '_bb_testimonial_show_on_website', true );
        $featured = get_post_meta( $post->ID, '_bb_testimonial_featured', true );
        if ( '' === $show_on_website ) {
            $show_on_website = '1';
        }
        ?>
        <div class="bb-testimonial-fields">
            <p>
                <label for="_bb_testimonial_client_name"><strong><?php esc_html_e( 'Client Name', 'business-builder' ); ?></strong></label>
                <input type="text" name="_bb_testimonial_client_name" id="_bb_testimonial_client_name" value="<?php echo esc_attr( $client_name ); ?>" class="widefat" />
            </p>
            <p>
                <label for="_bb_testimonial_client_title"><strong><?php esc_html_e( 'Client Title', 'business-builder' ); ?></strong></label>
                <input type="text" name="_bb_testimonial_client_title" id="_bb_testimonial_client_title" value="<?php echo esc_attr( $client_title ); ?>" class="widefat" />
            </p>
            <p>
                <label for="_bb_testimonial_rating"><strong><?php esc_html_e( 'Rating', 'business-builder' ); ?></strong></label>
                <input type="number" name="_bb_testimonial_rating" id="_bb_testimonial_rating" value="<?php echo esc_attr( $rating ); ?>" min="1" max="5" step="1" class="small-text" />
            </p>
            <p>
                <label for="_bb_testimonial_display_order"><strong><?php esc_html_e( 'Display Order', 'business-builder' ); ?></strong></label>
                <input type="number" name="_bb_testimonial_display_order" id="_bb_testimonial_display_order" value="<?php echo esc_attr( $display_order ); ?>" min="0" step="1" class="small-text" />
            </p>
            <p>
                <label>
                    <input type="checkbox" name="_bb_testimonial_featured" value="1" <?php checked( $featured, '1' ); ?> />
                    <strong><?php esc_html_e( 'Featured', 'business-builder' ); ?></strong>
                </label>
            </p>
            <p class="description">
                <?php esc_html_e( 'Featured testimonials can be highlighted by sections that only show featured items.', 'business-builder' ); ?>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="_bb_testimonial_show_on_website" value="1" <?php checked( $show_on_website, '1' ); ?> />
                    <strong><?php esc_html_e( 'Show on Website', 'business-builder' ); ?></strong>
                </label>
            </p>
        </div>
        <?php
    }

    /**
     * Save testimonial fields.
     */
    public function save( int $post_id, $post ): void {
        if ( ! isset( $_POST['bb_testimonial_details_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bb_testimonial_details_nonce'] ) ), 'bb_save_testimonial_details' ) ) {
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

        $fields = array(
            'client_name' => 'sanitize_text_field',
            'client_title' => 'sanitize_text_field',
            'rating' => 'absint',
            'display_order' => 'absint',
        );

        foreach ( $fields as $key => $callback ) {
            if ( ! isset( $_POST[ '_bb_testimonial_' . $key ] ) ) {
                continue;
            }

            $value = call_user_func( $callback, wp_unslash( $_POST[ '_bb_testimonial_' . $key ] ) );
            update_post_meta( $post_id, '_bb_testimonial_' . $key, $value );
        }

        $show_on_website = isset( $_POST['_bb_testimonial_show_on_website'] ) ? '1' : '0';
        update_post_meta( $post_id, '_bb_testimonial_show_on_website', $show_on_website );

        $featured = isset( $_POST['_bb_testimonial_featured'] ) ? '1' : '0';
        update_post_meta( $post_id, '_bb_testimonial_featured', $featured );
    }
}
