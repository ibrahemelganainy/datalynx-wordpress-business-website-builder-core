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

        <div class="bb-lawyer-field bb-lawyer-status-field">
            <p>
                <label for="_bb_lawyer_status">
                    <strong><?php esc_html_e( 'Status', 'business-builder' ); ?></strong>
                </label>
            </p>
            <p>
                <?php
                $status_value = self::get_status( (int) $post->ID );
                ?>
                <select name="_bb_lawyer_status" id="_bb_lawyer_status" class="widefat">
                    <option value="active" <?php selected( $status_value, 'active' ); ?>>
                        <?php esc_html_e( 'Active', 'business-builder' ); ?>
                    </option>
                    <option value="inactive" <?php selected( $status_value, 'inactive' ); ?>>
                        <?php esc_html_e( 'Inactive', 'business-builder' ); ?>
                    </option>
                </select>
            </p>
            <p class="description">
                <?php esc_html_e( 'Inactive lawyers are hidden from the website but keep all their data.', 'business-builder' ); ?>
            </p>
        </div>

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

                $raw = trim(
                    (string) wp_unslash( $_POST[ $meta_key ] )
                );

                /*
                 * Only keep values that are REAL web URLs. A bare handle
                 * (e.g. "ibrahemelganainy") must not be stored: esc_url_raw()
                 * would silently prepend http:// and turn it into the bogus
                 * link "http://ibrahemelganainy", which then renders as a dead
                 * "Profile" link on the website. Rejecting it here keeps the
                 * single source of truth clean (an empty value renders no link).
                 */
                $value = self::is_external_url( $raw )
                    ? esc_url_raw( $raw )
                    : '';

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

        /*
         * Lawyer status. Only the two known values are ever stored; an
         * unexpected value falls back to 'active' so the field can never be
         * used to store arbitrary input.
         */
        $previous_status = self::get_status( $post_id );
        $new_status      = self::normalize_status(
            isset( $_POST['_bb_lawyer_status'] )
                ? wp_unslash( $_POST['_bb_lawyer_status'] )
                : ''
        );

        update_post_meta(
            $post_id,
            '_bb_lawyer_status',
            $new_status
        );

        /*
         * Activity + notification integration, using the EXISTING generic
         * infrastructure (AuditLog + NotificationManager). This never
         * creates a second notification/activity system and never emits a
         * booking-related event.
         */
        $this->record_activity( $post_id, $previous_status, $new_status, $post );
    }

    /**
     * Whether a value is a real, publicly usable web URL.
     *
     * Used both when saving the social-profile fields and when rendering
     * them, so a value that is not a genuine URL (a bare handle such as
     * "ibrahemelganainy", or a host without a dot) is never turned into a
     * broken link. Accepts http(s):// and scheme-less hostnames, and requires
     * a dotted host so single-word entries are rejected.
     *
     * @param string $url Candidate value.
     * @return bool
     */
    public static function is_external_url( string $url ): bool {

        $url = trim( $url );

        if ( '' === $url ) {
            return false;
        }

        /* Reject whitespace / control characters before parsing. */
        $has_bad_chars = (bool) preg_match( '/[\s\x00-\x1F\x7F]/', $url );

        if ( $has_bad_chars ) {
            return false;
        }

        $candidate = $url;

        $has_scheme = (bool) preg_match( '#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $candidate );

        if ( $has_scheme ) {

            $parsed_scheme = (string) wp_parse_url( $candidate, PHP_URL_SCHEME );
            $scheme        = strtolower( $parsed_scheme );
            $is_web        = in_array( $scheme, array( 'http', 'https' ), true );

            if ( ! $is_web ) {
                return false;
            }
        } else {

            /* Scheme-less value: treat it as a host for validation. */
            $candidate = 'http://' . $candidate;
        }

        $host    = (string) wp_parse_url( $candidate, PHP_URL_HOST );
        $has_dot = str_contains( $host, '.' );

        if ( '' === $host || ! $has_dot ) {
            return false;
        }

        return false !== filter_var( $candidate, FILTER_VALIDATE_URL );
    }

    /**
     * The known lawyer statuses.
     *
     * @return array<string, string>
     */
    public static function statuses(): array {

        return array(
            'active'   => __( 'Active', 'business-builder' ),
            'inactive' => __( 'Inactive', 'business-builder' ),
        );
    }

    /**
     * Normalize a raw status value to a known status (default 'active').
     *
     * @param mixed $value Raw value.
     * @return string
     */
    public static function normalize_status( $value ): string {

        $value = sanitize_key( (string) $value );

        return array_key_exists( $value, self::statuses() ) ? $value : 'active';
    }

    /**
     * Get a lawyer's status (default 'active' when unset).
     *
     * @param int $post_id Lawyer post ID.
     * @return string
     */
    public static function get_status( int $post_id ): string {

        $stored = get_post_meta( $post_id, '_bb_lawyer_status', true );

        return self::normalize_status( '' === $stored ? 'active' : $stored );
    }

    /**
     * Whether a lawyer is currently active (shown on the website).
     *
     * A lawyer must BOTH be status=active AND have show_on_website enabled;
     * this keeps the two independent controls meaningful.
     *
     * @param int $post_id Lawyer post ID.
     * @return bool
     */
    public static function is_active( int $post_id ): bool {

        if ( 'active' !== self::get_status( $post_id )) {
            return false;
        }

        return '1' === (string) get_post_meta( $post_id, '_bb_lawyer_show_on_website', true );
    }

    /**
     * Record a lawyer change in the existing activity + notification systems.
     *
     * Uses AuditLog for the activity trail and NotificationManager for the
     * dashboard notification. Only fires a notification for the events that
     * genuinely need an administrator's attention (a status change to
     * inactive, or a newly created lawyer); routine edits are recorded as
     * activity only, so the notification feed is not spammed.
     *
     * @param int     $post_id         Lawyer post ID.
     * @param string  $previous_status Status before the save.
     * @param string  $new_status      Status after the save.
     * @param \WP_Post $post            The saved post object.
     */
    protected function record_activity( int $post_id, string $previous_status, string $new_status, $post ): void {

        $name = $post instanceof \WP_Post && '' !== (string) $post->post_title
            ? (string) $post->post_title
            : sprintf(
                /* translators: %d: lawyer id */
                __( 'Lawyer #%d', 'business-builder' ),
                $post_id
            );

        $reference = 'LAW-' . $post_id;

        /*
         * A lawyer is "new" for activity purposes the first time it is
         * saved through this handler. The sentinel meta records that a
         * create event has already been logged, so subsequent saves are
         * updates (and a re-save can never duplicate the "created" entry).
         */
        $seen   = (string) get_post_meta( $post_id, '_bb_lawyer_activity_seen', true );
        $is_new = ( '' === $seen );

        $audit = new \BusinessBuilderCore\Core\Audit\AuditLog();

        if ( $is_new ) {

            $audit->record(
                'lawyer.created',
                'lawyer',
                $post_id,
                array( 'reference' => $reference, 'name' => $name )
            );
        } else {

            $audit->record(
                'lawyer.updated',
                'lawyer',
                $post_id,
                array( 'reference' => $reference, 'name' => $name )
            );
        }

        if ( $previous_status !== $new_status ) {

            $audit->record(
                'lawyer.status_changed',
                'lawyer',
                $post_id,
                array(
                    'reference' => $reference,
                    'from'      => $previous_status,
                    'to'        => $new_status,
                )
            );
        }

        /* Mark the lawyer as seen so the next save is an 'updated' event. */
        update_post_meta( $post_id, '_bb_lawyer_activity_seen', '1' );

        /*
         * Notify the administrator only for genuinely attention-worthy
         * events — a newly created lawyer, or a move to inactive.
         */
        $notify = $is_new || ( 'inactive' === $new_status && 'inactive' !== $previous_status );

        if ( ! $notify ) {
            return;
        }

        $manager = new \BusinessBuilderCore\Core\Notifications\NotificationManager();

        $manager->dispatch(
            new \BusinessBuilderCore\Core\Notifications\Notification(
                $is_new ? 'lawyer.created' : 'lawyer.status_changed',
                $is_new
                    ? sprintf(
                        /* translators: %s: lawyer name */
                        __( 'New lawyer added: %s', 'business-builder' ),
                        $name
                    )
                    : sprintf(
                        /* translators: %s: lawyer name */
                        __( 'Lawyer set to inactive: %s', 'business-builder' ),
                        $name
                    ),
                '',
                '',
                $post_id,
                /*
                 * A stable dedupe key per event so a double-save (or two
                 * concurrent requests) cannot produce duplicate entries.
                 */
                'lawyer:' . ( $is_new ? 'created' : 'inactive' ) . ':' . $post_id,
                array(
                    'category'     => 'lawyer',
                    'entity_type'  => 'lawyer',
                    'entity_id'    => $post_id,
                    'entity_label' => __( 'Lawyer', 'business-builder' ),
                    'reference'    => $reference,
                    'customer'     => $name,
                    'actionable'   => true,
                )
            )
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

    /**
     * Whether a lawyer is active AND shown on the website.
     *
     * This is the single public gate every listing/profile query should use,
     * so the two independent controls (Status + Show on Website) are always
     * applied together and can never drift apart.
     *
     * @param int $post_id Lawyer post ID.
     * @return bool
     */
    public function is_publicly_visible( int $post_id ): bool {

        return self::is_active( $post_id ) && $this->is_visible( $post_id );
    }
}
