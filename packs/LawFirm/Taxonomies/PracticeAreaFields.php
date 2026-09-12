<?php

namespace BusinessBuilderCore\Packs\LawFirm\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Term fields for the Practice Area taxonomy.
 *
 * Adds an optional image, icon and Featured flag to each
 * `bb_practice_area` term so frontend sections can render a
 * visual card per practice area without hard-coding content.
 *
 * Mirrors the pack's existing *Fields class convention
 * (LawyerFields, LegalServiceFields, ...) so the data-entry
 * pattern stays consistent across every LawFirm entity.
 *
 * Term meta keys:
 *   _bb_practice_area_image    (attachment ID)
 *   _bb_practice_area_icon     (string, e.g. a dashicon class)
 *   _bb_practice_area_featured ('1' | '0')
 */
class PracticeAreaFields {

    /**
     * Taxonomy slug.
     */
    private const TAXONOMY = 'bb_practice_area';

    /**
     * Nonce action.
     */
    private const NONCE_ACTION = 'bb_save_practice_area_fields';

    /**
     * Nonce field name.
     */
    private const NONCE_FIELD = 'bb_practice_area_fields_nonce';

    /**
     * Register hooks.
     */
    public function register(): void {

        add_action(
            self::TAXONOMY . '_add_form_fields',
            array( $this, 'render_add_fields' )
        );

        add_action(
            self::TAXONOMY . '_edit_form_fields',
            array( $this, 'render_edit_fields' ),
            10,
            1
        );

        add_action(
            'created_' . self::TAXONOMY,
            array( $this, 'save' )
        );

        add_action(
            'edited_' . self::TAXONOMY,
            array( $this, 'save' )
        );

        /*
         * Load wp.media on the taxonomy screens so the image
         * picker works. Reuses the same Media Library
         * integration the builder already ships - image handling
         * is not rebuilt here.
         */
        add_action(
            'admin_enqueue_scripts',
            array( $this, 'enqueue_assets' )
        );
    }

    /**
     * Load the media library on Practice Area term screens.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( string $hook ): void {

        $screen = function_exists( 'get_current_screen' )
            ? get_current_screen()
            : null;

        if ( ! $screen instanceof \WP_Screen ) {
            return;
        }

        if ( self::TAXONOMY !== $screen->taxonomy ) {
            return;
        }

        if (
            'edit-tags.php' !== $hook
            && 'term.php' !== $hook
        ) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'bb-term-fields',
            BB_CORE_URL . 'assets/js/term-fields.js',
            array( 'jquery' ),
            BB_CORE_VERSION,
            true
        );
    }

    /**
     * Render fields on the "Add New Practice Area" form.
     */
    public function render_add_fields(): void {

        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

        ?>

        <div class="form-field">
            <label for="bb_practice_area_icon">
                <?php esc_html_e( 'Icon', 'business-builder' ); ?>
            </label>
            <input
                type="text"
                name="bb_practice_area_icon"
                id="bb_practice_area_icon"
                value=""
            />
            <p class="description">
                <?php
                esc_html_e(
                    'Optional icon identifier, for example: dashicons-portfolio or a short label.',
                    'business-builder'
                );
                ?>
            </p>
        </div>

        <div class="form-field">
            <label for="bb_practice_area_image">
                <?php esc_html_e( 'Image', 'business-builder' ); ?>
            </label>
            <?php $this->render_image_picker( 0 ); ?>
        </div>

        <div class="form-field">
            <label>
                <input
                    type="checkbox"
                    name="bb_practice_area_featured"
                    value="1"
                />
                <?php esc_html_e( 'Featured', 'business-builder' ); ?>
            </label>
            <p class="description">
                <?php
                esc_html_e(
                    'Featured practice areas can be highlighted by sections that only show featured items.',
                    'business-builder'
                );
                ?>
            </p>
        </div>

        <?php
    }

    /**
     * Render fields on the "Edit Practice Area" form.
     *
     * @param \WP_Term $term Term being edited.
     */
    public function render_edit_fields( $term ): void {

        $icon = get_term_meta(
            $term->term_id,
            '_bb_practice_area_icon',
            true
        );

        $image_id = absint(
            get_term_meta(
                $term->term_id,
                '_bb_practice_area_image',
                true
            )
        );

        $featured = get_term_meta(
            $term->term_id,
            '_bb_practice_area_featured',
            true
        );

        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

        ?>

        <tr class="form-field">
            <th scope="row">
                <label for="bb_practice_area_icon">
                    <?php esc_html_e( 'Icon', 'business-builder' ); ?>
                </label>
            </th>
            <td>
                <input
                    type="text"
                    name="bb_practice_area_icon"
                    id="bb_practice_area_icon"
                    value="<?php echo esc_attr( $icon ); ?>"
                />
                <p class="description">
                    <?php
                    esc_html_e(
                        'Optional icon identifier, for example: dashicons-portfolio or a short label.',
                        'business-builder'
                    );
                    ?>
                </p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="bb_practice_area_image">
                    <?php esc_html_e( 'Image', 'business-builder' ); ?>
                </label>
            </th>
            <td>
                <?php $this->render_image_picker( $image_id ); ?>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <?php esc_html_e( 'Featured', 'business-builder' ); ?>
            </th>
            <td>
                <label>
                    <input
                        type="checkbox"
                        name="bb_practice_area_featured"
                        value="1"
                        <?php checked( $featured, '1' ); ?>
                    />
                    <?php
                    esc_html_e(
                        'Highlight this practice area in featured sections.',
                        'business-builder'
                    );
                    ?>
                </label>
            </td>
        </tr>

        <?php
    }

    /**
     * Render a reusable image picker (attachment ID based).
     *
     * Uses the WordPress Media Library, consistent with the
     * builder's own image field implementation.
     *
     * @param int $image_id Current attachment ID (0 if none).
     */
    private function render_image_picker( int $image_id ): void {

        $preview = '';

        if ( $image_id > 0 ) {
            $preview = wp_get_attachment_image(
                $image_id,
                'medium',
                false,
                array(
                    'style' => 'max-width:150px;height:auto;display:block;margin-bottom:8px;',
                )
            );
        }

        ?>

        <div class="bb-term-image-field">

            <input
                type="hidden"
                name="bb_practice_area_image"
                id="bb_practice_area_image"
                class="bb-term-image-value"
                value="<?php echo esc_attr( (string) $image_id ); ?>"
            />

            <div class="bb-term-image-preview">
                <?php echo $preview; ?>
            </div>

            <p>
                <button
                    type="button"
                    class="button bb-term-image-select"
                >
                    <?php esc_html_e( 'Select Image', 'business-builder' ); ?>
                </button>

                <button
                    type="button"
                    class="button bb-term-image-remove"
                    <?php echo $image_id > 0 ? '' : 'style="display:none;"'; ?>
                >
                    <?php esc_html_e( 'Remove Image', 'business-builder' ); ?>
                </button>
            </p>

        </div>

        <?php
    }

    /**
     * Save Practice Area term fields.
     *
     * @param int $term_id Term ID being saved.
     */
    public function save( int $term_id ): void {

        $nonce_field = self::NONCE_FIELD;

        if ( ! isset( $_POST[ $nonce_field ] ) ) {
            return;
        }

        $nonce = sanitize_text_field(
            wp_unslash( $_POST[ $nonce_field ] )
        );

        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }

        if ( isset( $_POST['bb_practice_area_icon'] ) ) {

            update_term_meta(
                $term_id,
                '_bb_practice_area_icon',
                sanitize_text_field(
                    wp_unslash( $_POST['bb_practice_area_icon'] )
                )
            );
        }

        if ( isset( $_POST['bb_practice_area_image'] ) ) {

            update_term_meta(
                $term_id,
                '_bb_practice_area_image',
                absint( $_POST['bb_practice_area_image'] )
            );
        }

        $featured = isset( $_POST['bb_practice_area_featured'] )
            ? '1'
            : '0';

        update_term_meta(
            $term_id,
            '_bb_practice_area_featured',
            $featured
        );
    }

    /**
     * Get the image attachment ID for a practice area.
     *
     * @param int $term_id Term ID.
     * @return int
     */
    public function get_image_id( int $term_id ): int {

        return absint(
            get_term_meta(
                $term_id,
                '_bb_practice_area_image',
                true
            )
        );
    }

    /**
     * Get the icon for a practice area.
     *
     * @param int $term_id Term ID.
     * @return string
     */
    public function get_icon( int $term_id ): string {

        $icon = get_term_meta(
            $term_id,
            '_bb_practice_area_icon',
            true
        );

        return is_string( $icon ) ? $icon : '';
    }

    /**
     * Whether a practice area is featured.
     *
     * @param int $term_id Term ID.
     * @return bool
     */
    public function is_featured( int $term_id ): bool {

        return '1' === get_term_meta(
            $term_id,
            '_bb_practice_area_featured',
            true
        );
    }
}
