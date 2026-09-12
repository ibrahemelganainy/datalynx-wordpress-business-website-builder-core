<?php

namespace BusinessBuilderCore\Packs\LawFirm\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FaqFields {

    /**
     * Meta box ID.
     */
    private const META_BOX_ID = 'bb_faq_details';

    /**
     * Register hooks.
     */
    public function register(): void {
        add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
        add_action( 'save_post_bb_faq', array( $this, 'save' ), 10, 2 );
    }

    /**
     * Register meta box.
     */
    public function register_meta_box(): void {
        add_meta_box(
            self::META_BOX_ID,
            __( 'FAQ Details', 'business-builder' ),
            array( $this, 'render_meta_box' ),
            'bb_faq',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box.
     */
    public function render_meta_box( $post ): void {
        wp_nonce_field( 'bb_save_faq_details', 'bb_faq_details_nonce' );

        $display_order = get_post_meta( $post->ID, '_bb_faq_display_order', true );
        $show_on_website = get_post_meta( $post->ID, '_bb_faq_show_on_website', true );
        $featured = get_post_meta( $post->ID, '_bb_faq_featured', true );
        if ( '' === $show_on_website ) {
            $show_on_website = '1';
        }
        ?>
        <div class="bb-faq-fields">
            <p>
                <label for="_bb_faq_display_order"><strong><?php esc_html_e( 'Display Order', 'business-builder' ); ?></strong></label>
                <input type="number" name="_bb_faq_display_order" id="_bb_faq_display_order" value="<?php echo esc_attr( $display_order ); ?>" min="0" step="1" class="small-text" />
            </p>
            <p>
                <label>
                    <input type="checkbox" name="_bb_faq_featured" value="1" <?php checked( $featured, '1' ); ?> />
                    <strong><?php esc_html_e( 'Featured', 'business-builder' ); ?></strong>
                </label>
            </p>
            <p class="description">
                <?php esc_html_e( 'Featured FAQs can be highlighted by sections that only show featured items.', 'business-builder' ); ?>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="_bb_faq_show_on_website" value="1" <?php checked( $show_on_website, '1' ); ?> />
                    <strong><?php esc_html_e( 'Show on Website', 'business-builder' ); ?></strong>
                </label>
            </p>
        </div>
        <?php
    }

    /**
     * Save FAQ fields.
     */
    public function save( int $post_id, $post ): void {
        if ( ! isset( $_POST['bb_faq_details_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bb_faq_details_nonce'] ) ), 'bb_save_faq_details' ) ) {
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

        if ( isset( $_POST['_bb_faq_display_order'] ) ) {
            update_post_meta( $post_id, '_bb_faq_display_order', absint( $_POST['_bb_faq_display_order'] ) );
        }

        $show_on_website = isset( $_POST['_bb_faq_show_on_website'] ) ? '1' : '0';
        update_post_meta( $post_id, '_bb_faq_show_on_website', $show_on_website );

        $featured = isset( $_POST['_bb_faq_featured'] ) ? '1' : '0';
        update_post_meta( $post_id, '_bb_faq_featured', $featured );
    }
}
