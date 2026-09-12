<?php

namespace BusinessBuilderCore\REST;

use BusinessBuilderCore\Builder\PageManager;
use BusinessBuilderCore\Builder\SectionRegistry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PageBuilderAjax {

    protected PageManager $page_manager;

    protected SectionRegistry $section_registry;

    public function __construct(
        PageManager $page_manager,
        SectionRegistry $section_registry
    ) {

        $this->page_manager    = $page_manager;
        $this->section_registry = $section_registry;
    }

    /**
     * Register AJAX hooks.
     */
    public function register(): void {

        add_action(
            'wp_ajax_bb_add_section',
            array(
                $this,
                'add_section',
            )
        );

        add_action(
            'wp_ajax_bb_delete_section',
            array(
                $this,
                'delete_section',
            )
        );

        add_action(
            'wp_ajax_bb_update_section',
            array(
                $this,
                'update_section',
            )
        );

        add_action(
            'wp_ajax_bb_get_section',
            array(
                $this,
                'get_section',
            )
        );

        add_action(
            'wp_ajax_bb_move_section',
            array(
                $this,
                'move_section',
            )
        );

        add_action(
            'wp_ajax_bb_duplicate_section',
            array(
                $this,
                'duplicate_section',
            )
        );
    }

    /**
     * Add a new section to a page.
     */
    public function add_section(): void {

        if ( ! current_user_can( 'edit_pages' ) ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        check_ajax_referer(
            'bb_page_builder_ajax',
            'nonce'
        );

        $page_id = isset( $_POST['page_id'] )
            ? absint( $_POST['page_id'] )
            : 0;

        $type = isset( $_POST['type'] )
            ? sanitize_key(
                wp_unslash(
                    $_POST['type']
                )
            )
            : '';

        if ( $page_id <= 0 ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid page.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        if (
            ! current_user_can(
                'edit_post',
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        if (
            'page' !== get_post_type(
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid page.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        if (
            empty( $type )
            || ! $this->section_registry->exists(
                $type
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid section type.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        $section_id = $this->page_manager->add_section(
            $page_id,
            $type
        );

        if ( null === $section_id ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'The section could not be added.',
                        'business-builder'
                    ),
                ),
                500
            );
        }

        $section = $this->page_manager->get_section(
            $page_id,
            $section_id
        );

        if ( null === $section ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'The section was added but could not be loaded.',
                        'business-builder'
                    ),
                ),
                500
            );
        }

        $schema = $this->get_section_schema(
            $type
        );

        wp_send_json_success(
            array(
                'message' => __(
                    'Section added successfully.',
                    'business-builder'
                ),
                'section_id' => $section_id,
                'section'    => $section,
                'schema'     => $schema,
            )
        );
    }

    /**
     * Delete a section from a page.
     */
    public function delete_section(): void {

        if ( ! current_user_can( 'edit_pages' ) ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        check_ajax_referer(
            'bb_page_builder_ajax',
            'nonce'
        );

        $page_id = isset( $_POST['page_id'] )
            ? absint( $_POST['page_id'] )
            : 0;

        $section_id = isset( $_POST['section_id'] )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['section_id']
                )
            )
            : '';

        if (
            $page_id <= 0
            || empty( $section_id )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid section data.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        /*
         * FIX: this check existed in add/update/get_section
         * but was missing here, which allowed deleting a
         * "section" from any post type, not just pages.
         */
        if (
            'page' !== get_post_type(
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid page.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        if (
            ! current_user_can(
                'edit_post',
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        $deleted = $this->page_manager->remove_section(
            $page_id,
            $section_id
        );

        if ( ! $deleted ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'The section could not be deleted.',
                        'business-builder'
                    ),
                ),
                500
            );
        }

        wp_send_json_success(
            array(
                'message' => __(
                    'Section deleted successfully.',
                    'business-builder'
                ),
                'section_id' => $section_id,
            )
        );
    }

    /**
     * Update an existing section.
     */
    public function update_section(): void {

        if ( ! current_user_can( 'edit_pages' ) ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        check_ajax_referer(
            'bb_page_builder_ajax',
            'nonce'
        );

        $page_id = isset( $_POST['page_id'] )
            ? absint( $_POST['page_id'] )
            : 0;

        $section_id = isset( $_POST['section_id'] )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['section_id']
                )
            )
            : '';

        if (
            $page_id <= 0
            || empty( $section_id )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid section data.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        if (
            'page' !== get_post_type(
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid page.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        if (
            ! current_user_can(
                'edit_post',
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        $section = $this->page_manager->get_section(
            $page_id,
            $section_id
        );

        if ( null === $section ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Section not found.',
                        'business-builder'
                    ),
                ),
                404
            );
        }

        /*
         * Get the section type from the existing section.
         */
        $section_type = '';

        if (
            isset( $section['type'] )
            && is_string( $section['type'] )
        ) {

            $section_type = sanitize_key(
                $section['type']
            );
        }

        /*
         * Decode settings.
         */
        $settings = array();

        if ( isset( $_POST['settings'] ) ) {

            $raw_settings = wp_unslash(
                $_POST['settings']
            );

            if ( is_string( $raw_settings ) ) {

                $decoded = json_decode(
                    $raw_settings,
                    true
                );

                if ( is_array( $decoded ) ) {
                    $settings = $decoded;
                }
            }
        }

        /*
         * Decode content.
         */
        $content = array();

        if ( isset( $_POST['content'] ) ) {

            $raw_content = wp_unslash(
                $_POST['content']
            );

            if ( is_string( $raw_content ) ) {

                $decoded = json_decode(
                    $raw_content,
                    true
                );

                if ( is_array( $decoded ) ) {
                    $content = $decoded;
                }
            }
        }

        /*
         * Sanitize values according to the section schema.
         *
         * This keeps the AJAX layer generic and prevents
         * section-specific hard-coded logic.
         */
        $schema = $this->get_section_schema(
            $section_type
        );

        $settings = $this->sanitize_fields(
            $settings,
            $schema['settings'] ?? array()
        );

        $content = $this->sanitize_fields(
            $content,
            $schema['content'] ?? array()
        );

        /*
         * FIX: required-field validation previously only
         * existed on the client (page-admin.js). A direct
         * POST to this endpoint could bypass it entirely.
         * Enforce it here too, after sanitization.
         */
        $validation_error = $this->validate_required(
            $content,
            $schema['content'] ?? array()
        );

        if ( null === $validation_error ) {

            $validation_error = $this->validate_required(
                $settings,
                $schema['settings'] ?? array()
            );
        }

        if ( null !== $validation_error ) {

            wp_send_json_error(
                array(
                    'message' => $validation_error,
                ),
                400
            );
        }

        $updated = $this->page_manager->update_section(
            $page_id,
            $section_id,
            array(
                'settings' => $settings,
                'content'  => $content,
            )
        );

        if ( ! $updated ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'The section could not be updated.',
                        'business-builder'
                    ),
                ),
                500
            );
        }

        $updated_section = $this->page_manager->get_section(
            $page_id,
            $section_id
        );

        wp_send_json_success(
            array(
                'message' => __(
                    'Section updated successfully.',
                    'business-builder'
                ),
                'section' => $updated_section,
                'schema'  => $schema,
            )
        );
    }

    /**
     * Move a section to a new order.
     */
    public function move_section(): void {

        if ( ! current_user_can( 'edit_pages' ) ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        check_ajax_referer(
            'bb_page_builder_ajax',
            'nonce'
        );

        $page_id = isset( $_POST['page_id'] )
            ? absint( $_POST['page_id'] )
            : 0;

        $section_id = isset( $_POST['section_id'] )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['section_id']
                )
            )
            : '';

        $new_order = isset( $_POST['new_order'] )
            ? absint( $_POST['new_order'] )
            : 0;

        if (
            $page_id <= 0
            || empty( $section_id )
            || $new_order <= 0
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid section order.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        if (
            'page' !== get_post_type(
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid page.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        if (
            ! current_user_can(
                'edit_post',
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        $moved = $this->page_manager->move_section(
            $page_id,
            $section_id,
            $new_order
        );

        if ( ! $moved ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'The section order could not be updated.',
                        'business-builder'
                    ),
                ),
                500
            );
        }

        wp_send_json_success(
            array(
                'message' => __(
                    'Section order updated successfully.',
                    'business-builder'
                ),
                'new_order' => $new_order,
            )
        );
    }

    /**
     * Duplicate a section.
     */
    public function duplicate_section(): void {

        if ( ! current_user_can( 'edit_pages' ) ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        check_ajax_referer(
            'bb_page_builder_ajax',
            'nonce'
        );

        $page_id = isset( $_POST['page_id'] )
            ? absint( $_POST['page_id'] )
            : 0;

        $section_id = isset( $_POST['section_id'] )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['section_id']
                )
            )
            : '';

        if (
            $page_id <= 0
            || empty( $section_id )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid section data.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        if (
            'page' !== get_post_type(
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid page.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        if (
            ! current_user_can(
                'edit_post',
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        $duplicated = $this->page_manager->duplicate_section(
            $page_id,
            $section_id
        );

        if ( null === $duplicated ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'The section could not be duplicated.',
                        'business-builder'
                    ),
                ),
                500
            );
        }

        wp_send_json_success(
            array(
                'message' => __(
                    'Section duplicated successfully.',
                    'business-builder'
                ),
                'section_id' => $duplicated,
            )
        );
    }

    /**
     * Get a section.
     */
    public function get_section(): void {

        if ( ! current_user_can( 'edit_pages' ) ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        check_ajax_referer(
            'bb_page_builder_ajax',
            'nonce'
        );

        $page_id = isset( $_POST['page_id'] )
            ? absint( $_POST['page_id'] )
            : 0;

        $section_id = isset( $_POST['section_id'] )
            ? sanitize_text_field(
                wp_unslash(
                    $_POST['section_id'] )
            )
            : '';

        if (
            $page_id <= 0
            || empty( $section_id )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid section data.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        if (
            'page' !== get_post_type(
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid page.',
                        'business-builder'
                    ),
                ),
                400
            );
        }

        if (
            ! current_user_can(
                'edit_post',
                $page_id
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'You do not have permission to edit this page.',
                        'business-builder'
                    ),
                ),
                403
            );
        }

        $section = $this->page_manager->get_section(
            $page_id,
            $section_id
        );

        if ( null === $section ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Section not found.',
                        'business-builder'
                    ),
                ),
                404
            );
        }

        $section_type = '';

        if (
            isset( $section['type'] )
            && is_string( $section['type'] )
        ) {

            $section_type = sanitize_key(
                $section['type']
            );
        }

        $schema = $this->get_section_schema(
            $section_type
        );

        wp_send_json_success(
            array(
                'section' => $section,
                'schema'  => $schema,
            )
        );
    }

    /**
     * Get editor schema for a section.
     */
    protected function get_section_schema(
        string $type
    ): array {

        $type = sanitize_key(
            $type
        );

        if ( empty( $type ) ) {

            return array(
                'content'  => array(),
                'settings' => array(),
            );
        }

        /*
         * SectionRegistry is the single source of truth
         * for editor fields.
         */
        $schema = $this->section_registry->get_editor_schema(
            $type
        );

        if ( ! is_array( $schema ) ) {

            return array(
                'content'  => array(),
                'settings' => array(),
            );
        }

        return array(
            'content' => isset( $schema['content'] )
                && is_array( $schema['content'] )
                    ? $schema['content']
                    : array(),

            'settings' => isset( $schema['settings'] )
                && is_array( $schema['settings'] )
                    ? $schema['settings']
                    : array(),
        );
    }

    /**
     * Sanitize submitted fields using their schema definitions.
     *
     * @param array $values Submitted values.
     * @param array $schema Field schema.
     * @return array
     */
    protected function sanitize_fields(
        array $values,
        array $schema
    ): array {

        $sanitized = array();

        foreach ( $schema as $field_key => $field ) {

            $field_key = sanitize_key(
                (string) $field_key
            );

            if ( empty( $field_key ) ) {
                continue;
            }

            $field = is_array( $field )
                ? $field
                : array();

            $type = isset( $field['type'] )
                ? sanitize_key(
                    (string) $field['type']
                )
                : 'text';

            $value = $values[ $field_key ] ?? null;

            /*
             * Repeater data is kept as an array, sanitized
             * per its own sub-field schema (see FIX below).
             */
            if ( 'repeater' === $type ) {

                if ( is_string( $value ) ) {

                    $decoded = json_decode(
                        $value,
                        true
                    );

                    $value = is_array( $decoded )
                        ? $decoded
                        : array();
                }

                if ( ! is_array( $value ) ) {
                    $value = array();
                }

                $sub_schema = isset( $field['fields'] )
                    && is_array( $field['fields'] )
                        ? $field['fields']
                        : array();

                $sanitized[ $field_key ] =
                    $this->sanitize_repeater(
                        $value,
                        $sub_schema
                    );

                continue;
            }

            $sanitized[ $field_key ] =
                $this->sanitize_single_value(
                    $value,
                    $type,
                    $field
                );
        }

        return $sanitized;
    }

    /**
     * Sanitize a single value according to its field type.
     *
     * This is the single source of truth for type-based
     * sanitization, shared by top-level fields and by
     * fields nested inside a repeater.
     *
     * FIX: previously repeater items ran through
     * sanitize_text_field() only, regardless of their
     * declared type (checkbox/number/select/url/... were
     * all flattened to strings). Routing everything through
     * this shared method fixes that.
     *
     * @param mixed $value Raw value.
     * @param string $type Field type.
     * @param array $field Field schema definition.
     * @return mixed
     */
    protected function sanitize_single_value(
        $value,
        string $type,
        array $field
    ) {

        /*
         * Checkbox values should always be boolean.
         */
        if ( 'checkbox' === $type ) {

            return ! empty( $value );
        }

        /*
         * Numeric fields, with optional min/max clamping
         * when the schema declares bounds.
         */
        if ( 'number' === $type ) {

            if (
                '' === $value
                || null === $value
            ) {

                return '';
            }

            if ( ! is_numeric( $value ) ) {

                return '';
            }

            $number = ( false !== strpos(
                (string) $value,
                '.'
            ) )
                ? (float) $value
                : (int) $value;

            if (
                isset( $field['min'] )
                && is_numeric( $field['min'] )
                && $number < (float) $field['min']
            ) {

                $number = $field['min'];
            }

            if (
                isset( $field['max'] )
                && is_numeric( $field['max'] )
                && $number > (float) $field['max']
            ) {

                $number = $field['max'];
            }

            return $number;
        }

        /*
         * URL fields.
         */
        if ( 'url' === $type ) {

            return esc_url_raw(
                (string) $value
            );
        }

        /*
         * Color fields.
         */
        if ( 'color' === $type ) {

            $color = sanitize_hex_color(
                (string) $value
            );

            return $color ? $color : '';
        }

        /*
         * Image fields store attachment IDs.
         */
        if ( 'image' === $type ) {

            return absint( $value );
        }

        /*
         * Select values.
         *
         * FIX: multi-select fields send an array (the JS
         * renders <select multiple> and .val() returns an
         * array). The old code cast the value straight to
         * (string), which for an array produces the PHP
         * notice "Array to string conversion" and the
         * literal string "Array". Handle arrays explicitly.
         */
        if ( 'select' === $type ) {

            $options = array();

            if (
                isset( $field['options'] )
                && is_array( $field['options'] )
            ) {

                $options = array_keys(
                    $field['options']
                );
            }

            $is_multiple = ! empty( $field['multiple'] );

            if ( $is_multiple ) {

                $raw_values = is_array( $value )
                    ? $value
                    : ( '' === $value || null === $value
                        ? array()
                        : array( $value ) );

                $clean_values = array();

                foreach ( $raw_values as $raw_value ) {

                    $raw_value = sanitize_text_field(
                        (string) $raw_value
                    );

                    if (
                        ! empty( $options )
                        && ! in_array(
                            $raw_value,
                            $options,
                            true
                        )
                    ) {

                        continue;
                    }

                    $clean_values[] = $raw_value;
                }

                return array_values(
                    array_unique( $clean_values )
                );
            }

            $value = is_array( $value )
                ? ( reset( $value ) ?: '' )
                : $value;

            $value = sanitize_text_field(
                (string) $value
            );

            if (
                ! empty( $options )
                && ! in_array(
                    $value,
                    $options,
                    true
                )
            ) {

                $value = '';
            }

            return $value;
        }

        /*
         * Textarea and normal text fields.
         */
        if ( 'textarea' === $type ) {

            return sanitize_textarea_field(
                (string) $value
            );
        }

        /*
         * Default text sanitization.
         */
        return sanitize_text_field(
            (string) $value
        );
    }

    /**
     * Sanitize repeater values recursively, honoring each
     * sub-field's declared type when a field schema is
     * available.
     *
     * FIX: this previously ignored $field['fields'] entirely
     * and ran every value through sanitize_text_field(),
     * regardless of type. It now mirrors sanitize_fields()
     * for each repeater row.
     *
     * @param array $items Repeater items.
     * @param array $sub_schema Optional field-by-field schema
     *                          for each repeater row. Empty
     *                          for a scalar repeater.
     * @return array
     */
    protected function sanitize_repeater(
        array $items,
        array $sub_schema = array()
    ): array {

        $result = array();

        /*
         * Scalar repeater: no sub-schema, each item is a
         * plain value (string).
         */
        if ( empty( $sub_schema ) ) {

            foreach ( $items as $item ) {

                if ( is_scalar( $item ) ) {

                    $result[] = sanitize_text_field(
                        (string) $item
                    );
                }
            }

            return $result;
        }

        /*
         * Structured repeater: each item is an associative
         * array keyed by sub-field key.
         */
        foreach ( $items as $item ) {

            if ( ! is_array( $item ) ) {
                continue;
            }

            $clean_item = array();

            foreach ( $sub_schema as $sub_key => $sub_field ) {

                $sub_key = sanitize_key(
                    (string) $sub_key
                );

                if ( empty( $sub_key ) ) {
                    continue;
                }

                $sub_field = is_array( $sub_field )
                    ? $sub_field
                    : array();

                $sub_type = isset( $sub_field['type'] )
                    ? sanitize_key(
                        (string) $sub_field['type']
                    )
                    : 'text';

                $sub_value = $item[ $sub_key ] ?? null;

                /*
                 * Support nested repeaters.
                 */
                if ( 'repeater' === $sub_type ) {

                    if ( is_string( $sub_value ) ) {

                        $decoded = json_decode(
                            $sub_value,
                            true
                        );

                        $sub_value = is_array( $decoded )
                            ? $decoded
                            : array();
                    }

                    if ( ! is_array( $sub_value ) ) {
                        $sub_value = array();
                    }

                    $nested_schema = isset( $sub_field['fields'] )
                        && is_array( $sub_field['fields'] )
                            ? $sub_field['fields']
                            : array();

                    $clean_item[ $sub_key ] =
                        $this->sanitize_repeater(
                            $sub_value,
                            $nested_schema
                        );

                    continue;
                }

                $clean_item[ $sub_key ] =
                    $this->sanitize_single_value(
                        $sub_value,
                        $sub_type,
                        $sub_field
                    );
            }

            $result[] = $clean_item;
        }

        return $result;
    }

    /**
     * Validate that required fields are present after
     * sanitization.
     *
     * FIX: required-field checks previously lived only in
     * page-admin.js (validateEditor()). A request sent
     * directly to this endpoint (bypassing the admin UI)
     * had no such guard.
     *
     * @param array $values Sanitized values (content or settings).
     * @param array $schema Field schema.
     * @return string|null Error message, or null if valid.
     */
    protected function validate_required(
        array $values,
        array $schema
    ): ?string {

        foreach ( $schema as $field_key => $field ) {

            $field_key = sanitize_key(
                (string) $field_key
            );

            if ( empty( $field_key ) ) {
                continue;
            }

            $field = is_array( $field )
                ? $field
                : array();

            if ( empty( $field['required'] ) ) {
                continue;
            }

            $type = isset( $field['type'] )
                ? sanitize_key(
                    (string) $field['type']
                )
                : 'text';

            /*
             * Checkboxes are never "empty" in a meaningful
             * sense (false is a valid, complete answer).
             */
            if ( 'checkbox' === $type ) {
                continue;
            }

            $value = $values[ $field_key ] ?? null;

            $is_empty = is_array( $value )
                ? empty( $value )
                : (
                    null === $value
                    || '' === $value
                );

            if ( $is_empty ) {

                $label = isset( $field['label'] )
                    && is_string( $field['label'] )
                        ? $field['label']
                        : $field_key;

                return sprintf(
                    /* translators: %s: field label */
                    __(
                        '%s is required.',
                        'business-builder'
                    ),
                    $label
                );
            }
        }

        return null;
    }
}
