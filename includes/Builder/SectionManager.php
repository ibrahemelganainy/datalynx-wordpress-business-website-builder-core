<?php

namespace BusinessBuilderCore\Builder;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SectionManager {

    /**
     * Page meta key.
     */
    private const META_KEY = '_bb_page_sections';

    /**
     * Get all sections for a page.
     */
    public function get_sections( int $page_id ): array {

        $sections = get_post_meta(
            $page_id,
            self::META_KEY,
            true
        );

        if ( ! is_array( $sections ) ) {
            return array();
        }

        return $this->normalize_sections( $sections );
    }

    /**
     * Save sections for a page.
     */
    public function save_sections(
        int $page_id,
        array $sections
    ): bool {

        if ( $page_id <= 0 ) {
            return false;
        }

        $sections = $this->normalize_sections(
            $sections
        );

        /*
         * update_post_meta() returns false both when the write FAILS and
         * when the new value is identical to the stored one ("no change").
         * Re-saving an unchanged section is a legitimate, successful action,
         * so treat "already identical" as success instead of surfacing a
         * misleading "The section could not be updated." error.
         */
        $existing = get_post_meta(
            $page_id,
            self::META_KEY,
            true
        );

        if ( is_array( $existing ) && $existing === $sections ) {
            return true;
        }

        $updated = update_post_meta(
            $page_id,
            self::META_KEY,
            $sections
        );

        /*
         * update_post_meta() returns the new meta id (truthy) on insert and
         * true on a real change, false only when it genuinely could not write.
         * A false result here means the value was NOT saved.
         */
        if ( false === $updated ) {

            /*
             * Distinguish a genuine failure from a benign no-op: if the
             * stored value now equals what we intended to save, the write is
             * effectively done (another request may have written it first).
             */
            $current = get_post_meta(
                $page_id,
                self::META_KEY,
                true
            );

            return is_array( $current ) && $current === $sections;
        }

        return true;
    }

    /**
     * Add a section.
     */
    public function add_section(
        int $page_id,
        string $type,
        array $settings = array(),
        array $content = array()
    ): ?string {

        $type = sanitize_key( $type );

        if ( empty( $type ) ) {
            return null;
        }

        $sections = $this->get_sections(
            $page_id
        );

        $section_id = wp_generate_uuid4();

        $sections[] = array(
            'id'       => $section_id,
            'type'     => $type,
            'order'    => count( $sections ) + 1,
            'settings' => $settings,
            'content'  => $content,
        );

        if (
            ! $this->save_sections(
                $page_id,
                $sections
            )
        ) {
            return null;
        }

        return $section_id;
    }

    /**
     * Remove a section.
     */
    public function remove_section(
        int $page_id,
        string $section_id
    ): bool {

        $sections = $this->get_sections(
            $page_id
        );

        $found = false;

        foreach ( $sections as $index => $section ) {

            if (
                isset( $section['id'] )
                && $section['id'] === $section_id
            ) {
                unset( $sections[ $index ] );
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return false;
        }

        $sections = array_values(
            $sections
        );

        $sections = $this->reorder(
            $sections
        );

        return $this->save_sections(
            $page_id,
            $sections
        );
    }

    /**
     * Update a section.
     */
    public function update_section(
        int $page_id,
        string $section_id,
        array $data
    ): bool {

        $sections = $this->get_sections(
            $page_id
        );

        foreach ( $sections as $index => $section ) {

            if (
                isset( $section['id'] )
                && $section['id'] === $section_id
            ) {

                if ( isset( $data['type'] ) ) {
                    $data['type'] = sanitize_key(
                        $data['type']
                    );
                }

                $sections[ $index ] = wp_parse_args(
                    $data,
                    $section
                );

                return $this->save_sections(
                    $page_id,
                    $sections
                );
            }
        }

        return false;
    }

    /**
     * Move a section.
     */
    public function move_section(
        int $page_id,
        string $section_id,
        int $new_order
    ): bool {

        $sections = $this->get_sections(
            $page_id
        );

        if ( empty( $sections ) ) {
            return false;
        }

        $target_index = null;

        foreach ( $sections as $index => $section ) {

            if (
                isset( $section['id'] )
                && $section['id'] === $section_id
            ) {
                $target_index = $index;
                break;
            }
        }

        if ( null === $target_index ) {
            return false;
        }

        $section = $sections[ $target_index ];

        unset(
            $sections[ $target_index ]
        );

        $sections = array_values(
            $sections
        );

        $new_order = max(
            1,
            min(
                $new_order,
                count( $sections ) + 1
            )
        );

        array_splice(
            $sections,
            $new_order - 1,
            0,
            array( $section )
        );

        $sections = $this->reorder(
            $sections
        );

        return $this->save_sections(
            $page_id,
            $sections
        );
    }

    /**
     * Duplicate a section with a fresh ID.
     */
    public function duplicate_section(
        int $page_id,
        string $section_id
    ): ?string {

        $sections = $this->get_sections(
            $page_id
        );

        $target = null;

        foreach ( $sections as $section ) {

            if (
                isset( $section['id'] )
                && $section['id'] === $section_id
            ) {
                $target = $section;
                break;
            }
        }

        if ( ! is_array( $target ) ) {
            return null;
        }

        $copy = $target;
        $copy['id'] = wp_generate_uuid4();
        $copy['order'] = count( $sections ) + 1;

        $sections[] = $copy;

        if ( ! $this->save_sections( $page_id, $sections ) ) {
            return null;
        }

        return $copy['id'];
    }

    /**
     * Get one section.
     */
    public function get_section(
        int $page_id,
        string $section_id
    ): ?array {

        $sections = $this->get_sections(
            $page_id
        );

        foreach ( $sections as $section ) {

            if (
                isset( $section['id'] )
                && $section['id'] === $section_id
            ) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Normalize sections.
     */
    private function normalize_sections(
        array $sections
    ): array {

        $normalized = array();

        foreach ( $sections as $section ) {

            if ( ! is_array( $section ) ) {
                continue;
            }

            $id = isset( $section['id'] )
                ? sanitize_text_field(
                    (string) $section['id']
                )
                : wp_generate_uuid4();

            $type = isset( $section['type'] )
                ? sanitize_key(
                    (string) $section['type']
                )
                : '';

            if ( empty( $type ) ) {
                continue;
            }

            $normalized[] = array(
                'id' => $id,

                'type' => $type,

                'order' => isset( $section['order'] )
                    ? absint(
                        $section['order']
                    )
                    : count( $normalized ) + 1,

                'settings' => isset(
                    $section['settings']
                ) && is_array(
                    $section['settings']
                )
                    ? $section['settings']
                    : array(),

                'content' => isset(
                    $section['content']
                ) && is_array(
                    $section['content']
                )
                    ? $section['content']
                    : array(),
            );
        }

        return $this->reorder(
            $normalized
        );
    }

    /**
     * Reorder sections.
     */
    private function reorder(
        array $sections
    ): array {

        foreach ( $sections as $index => &$section ) {

            $section['order'] = $index + 1;
        }

        unset( $section );

        return $sections;
    }

    /**
     * Get meta key.
     */
    public function get_meta_key(): string {

        return self::META_KEY;
    }
}