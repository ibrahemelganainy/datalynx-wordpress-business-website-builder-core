<?php

namespace BusinessBuilderCore\Packs\LawFirm\PostTypes;

use BusinessBuilderCore\Admin\MetaBoxRenderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lawyer Details meta box (Phase 22 §27, §28).
 *
 * PHASE 22 UPGRADE — what changed and why
 * ---------------------------------------
 * The box used to render 13 unrelated fields as a flat list, each wrapped in an
 * inline `style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: …"`.
 * That produced exactly what §27 and §28 forbid:
 *
 *   - no grouping, so "Education" (a long paragraph) sat beside "X profile URL"
 *     with nothing to say they belong to different concerns;
 *   - presentation in inline attributes, which cannot follow the admin colour
 *     scheme and do not mirror in RTL;
 *   - one control shape for every field, so a URL, a number and a textarea were
 *     visually identical;
 *   - no required-field marking and no accessible description wiring.
 *
 * The fields are now organised into the SIX groups §27 prescribes (Profile,
 * Contact, Professional, Media, Display, and the existing status control), each
 * rendered as a card by the shared `Admin\MetaBoxRenderer`, which owns the
 * layout, the escaping and the accessibility contract. The META KEYS ARE
 * UNCHANGED, so every existing lawyer's data keeps working and `save()` needed
 * no change at all.
 */
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
    /**
     * Render the Lawyer Details meta box.
     *
     * The fields are declared as GROUPS (§27) and rendered by the shared
     * `MetaBoxRenderer`, which owns the cards, the field types, the escaping and
     * the accessibility contract. This method therefore contains DATA (labels,
     * groups, descriptions) and no markup at all.
     *
     * GROUPING RATIONALE (§27 "avoid dumping dozens of unrelated fields into one
     * huge panel")
     *   Profile      who this person is
     *   Contact      how to reach them
     *   Professional the credentials a visitor judges them by
     *   Media        the portrait
     *   Display      how/where they appear
     *   Status       whether the profile is live
     *
     * The META KEYS are identical to the pre-Phase-22 keys (`_bb_lawyer_*`), so
     * existing data, the `save()` handler and every section query keep working.
     *
     * @param \WP_Post $post Post being edited.
     */
    public function render_meta_box( $post ): void {

        $groups = array(

            /* ---------------------------------------------------------- Profile */
            'profile' => array(
                'label'       => __( 'Profile', 'business-builder' ),
                'description' => __( 'How this lawyer is identified across the website.', 'business-builder' ),
                'tab'         => true,
                'fields'      => array(
                    'title' => array(
                        'label'       => __( 'Professional title', 'business-builder' ),
                        'type'        => 'text',
                        'description' => __( 'For example: Senior Lawyer, Partner, Legal Consultant.', 'business-builder' ),
                        'placeholder' => __( 'Senior Lawyer', 'business-builder' ),
                    ),
                    'languages' => array(
                        'label'       => __( 'Languages', 'business-builder' ),
                        'type'        => 'text',
                        'description' => __( 'Separate with commas, for example: Arabic, English, French.', 'business-builder' ),
                    ),
                ),
            ),

            /* ---------------------------------------------------------- Contact */
            'contact' => array(
                'label'       => __( 'Contact', 'business-builder' ),
                'description' => __( 'Only the details you fill in are shown on the website.', 'business-builder' ),
                'tab'         => true,
                'fields'      => array(
                    'phone' => array(
                        'label'       => __( 'Phone', 'business-builder' ),
                        'type'        => 'text',
                        'description' => __( 'Shown on the profile and in the contact section.', 'business-builder' ),
                    ),
                    'whatsapp' => array(
                        'label'       => __( 'WhatsApp', 'business-builder' ),
                        'type'        => 'text',
                        'description' => __( 'Include the country code, for example +971…', 'business-builder' ),
                    ),
                    'email' => array(
                        'label'       => __( 'Email', 'business-builder' ),
                        'type'        => 'email',
                        'description' => __( 'A valid address is required before it can be shown.', 'business-builder' ),
                    ),
                ),
            ),

            /* ----------------------------------------------------- Professional */
            'professional' => array(
                'label'       => __( 'Professional information', 'business-builder' ),
                'description' => __( 'Credentials that help a visitor judge this lawyer\'s experience.', 'business-builder' ),
                'tab'         => true,
                'fields'      => array(
                    'experience' => array(
                        'label'       => __( 'Years of experience', 'business-builder' ),
                        'type'        => 'number',
                        'min'         => 0,
                        'max'         => 80,
                        'step'        => 1,
                        'description' => __( 'Whole years. Leave empty to hide the figure.', 'business-builder' ),
                    ),
                    'license_number' => array(
                        'label'       => __( 'License number', 'business-builder' ),
                        'type'        => 'text',
                        'description' => __( 'Professional license or bar registration number.', 'business-builder' ),
                    ),
                    'education' => array(
                        'label'       => __( 'Education', 'business-builder' ),
                        'type'        => 'textarea',
                        'rows'        => 4,
                        'description' => __( 'Degrees and universities. One per line reads best.', 'business-builder' ),
                    ),
                ),
            ),

            /* ------------------------------------------------------------- Media */
            'media' => array(
                'label'       => __( 'Portrait', 'business-builder' ),
                'description' => __( 'A square or portrait image works best. It is cropped to fit.', 'business-builder' ),
                'fields'      => array(
                    'photo' => array(
                        'label'       => __( 'Profile photo', 'business-builder' ),
                        'type'        => 'media',
                        'description' => __( 'Shown on cards and on the profile page.', 'business-builder' ),
                    ),
                ),
            ),

            /* ----------------------------------------------------------- Social */
            'social' => array(
                'label'       => __( 'Social profiles', 'business-builder' ),
                'description' => __( 'Optional. Each link is only shown when it is filled in.', 'business-builder' ),
                'fields'      => array(
                    'linkedin' => array(
                        'label'       => __( 'LinkedIn', 'business-builder' ),
                        'type'        => 'url',
                        'description' => __( 'Full profile URL.', 'business-builder' ),
                    ),
                    'facebook' => array(
                        'label'       => __( 'Facebook', 'business-builder' ),
                        'type'        => 'url',
                        'description' => __( 'Full profile URL.', 'business-builder' ),
                    ),
                    'x' => array(
                        'label'       => __( 'X', 'business-builder' ),
                        'type'        => 'url',
                        'description' => __( 'Full profile URL.', 'business-builder' ),
                    ),
                ),
            ),

            /* ---------------------------------------------------------- Display */
            'display' => array(
                'label'       => __( 'Display', 'business-builder' ),
                'description' => __( 'Control where and in what order this lawyer appears.', 'business-builder' ),
                'fields'      => array(
                    'display_order' => array(
                        'label'       => __( 'Display order', 'business-builder' ),
                        'type'        => 'number',
                        'min'         => 0,
                        'max'         => 9999,
                        'step'        => 1,
                        'description' => __( 'Lower numbers appear first. Leave empty to sort by name.', 'business-builder' ),
                    ),
                    'featured' => array(
                        'label'       => __( 'Featured', 'business-builder' ),
                        'type'        => 'checkbox',
                        'toggle_label' => __( 'Highlight this lawyer', 'business-builder' ),
                        'description' => __( 'Sections set to “featured only” show featured lawyers.', 'business-builder' ),
                    ),
                    'show_on_website' => array(
                        'label'       => __( 'Show on website', 'business-builder' ),
                        'type'        => 'checkbox',
                        'toggle_label' => __( 'Visible to visitors', 'business-builder' ),
                        'default'     => '1',
                        'description' => __( 'Turn this off to hide the profile without deleting anything.', 'business-builder' ),
                    ),
                ),
            ),

            /* ----------------------------------------------------------- Status */
            'status' => array(
                'label'       => __( 'Status', 'business-builder' ),
                'description' => __( 'Inactive lawyers keep all their data but are hidden from the website.', 'business-builder' ),
                'fields'      => array(
                    'status' => array(
                        'label'       => __( 'Availability', 'business-builder' ),
                        'type'        => 'select',
                        'meta_key'    => '_bb_lawyer_status',
                        'default'     => 'active',
                        'options'     => array(
                            'active'   => __( 'Active — taking clients', 'business-builder' ),
                            'inactive' => __( 'Inactive — hidden from the website', 'business-builder' ),
                        ),
                        'description' => __( 'Inactive profiles are excluded from every section.', 'business-builder' ),
                    ),
                ),
            ),
        );

        MetaBoxRenderer::render(
            array(
                'prefix'       => '_bb_lawyer_',
                'nonce_action' => 'bb_save_lawyer_details',
                'nonce_name'   => 'bb_lawyer_details_nonce',
                'intro'        => __( 'Everything on this page is used by the Lawyers and Practice Areas sections. Nothing here is required to save the profile.', 'business-builder' ),
                'groups'       => $groups,
            ),
            (int) $post->ID
        );
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
