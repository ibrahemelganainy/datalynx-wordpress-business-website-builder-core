<?php

namespace BusinessBuilderCore\Builder;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PageManager {

    private const BUILDER_ENABLED_META = '_bb_builder_enabled';

    private const PAGE_TEMPLATE_META = '_bb_page_template';

    protected SectionManager $section_manager;

    public function __construct(
        SectionManager $section_manager
    ) {

        $this->section_manager = $section_manager;
    }

    /**
     * Check whether the given page is managed by the Builder.
     */
    public function is_builder_page(
        int $page_id
    ): bool {

        if ( $page_id <= 0 ) {
            return false;
        }

        return '1' === get_post_meta(
            $page_id,
            self::BUILDER_ENABLED_META,
            true
        );
    }

    /**
     * Enable Builder for a page.
     */
    public function enable_builder(
        int $page_id
    ): bool {

        if ( ! $this->is_valid_page( $page_id ) ) {
            return false;
        }

        return update_post_meta(
            $page_id,
            self::BUILDER_ENABLED_META,
            '1'
        );
    }

    /**
     * Disable Builder for a page.
     */
    public function disable_builder(
        int $page_id
    ): bool {

        if ( $page_id <= 0 ) {
            return false;
        }

        return update_post_meta(
            $page_id,
            self::BUILDER_ENABLED_META,
            '0'
        );
    }

    /**
     * Get the page template identifier.
     */
    public function get_page_template(
        int $page_id
    ): string {

        if ( $page_id <= 0 ) {
            return '';
        }

        $template = get_post_meta(
            $page_id,
            self::PAGE_TEMPLATE_META,
            true
        );

        if ( ! is_string( $template ) ) {
            return '';
        }

        return sanitize_key( $template );
    }

    /**
     * Set the page template identifier.
     */
    public function set_page_template(
        int $page_id,
        string $template
    ): bool {

        if ( ! $this->is_valid_page( $page_id ) ) {
            return false;
        }

        $template = sanitize_key(
            $template
        );

        if ( empty( $template ) ) {

            delete_post_meta(
                $page_id,
                self::PAGE_TEMPLATE_META
            );

            return true;
        }

        return update_post_meta(
            $page_id,
            self::PAGE_TEMPLATE_META,
            $template
        );
    }

    /**
     * Get all sections assigned to a page.
     */
    public function get_sections(
        int $page_id
    ): array {

        if ( $page_id <= 0 ) {
            return array();
        }

        return $this->section_manager->get_sections(
            $page_id
        );
    }

    /**
     * Save all sections assigned to a page.
     */
    public function save_sections(
        int $page_id,
        array $sections
    ): bool {

        if ( ! $this->is_valid_page( $page_id ) ) {
            return false;
        }

        return $this->section_manager->save_sections(
            $page_id,
            $sections
        );
    }

    /**
     * Add a section to a page.
     */
    public function add_section(
        int $page_id,
        string $type,
        array $settings = array(),
        array $content = array()
    ): ?string {

        if ( ! $this->is_valid_page( $page_id ) ) {
            return null;
        }

        return $this->section_manager->add_section(
            $page_id,
            $type,
            $settings,
            $content
        );
    }

    /**
     * Remove a section from a page.
     */
    public function remove_section(
        int $page_id,
        string $section_id
    ): bool {

        if ( ! $this->is_valid_page( $page_id ) ) {
            return false;
        }

        return $this->section_manager->remove_section(
            $page_id,
            $section_id
        );
    }

    /**
     * Update a section assigned to a page.
     */
    public function update_section(
        int $page_id,
        string $section_id,
        array $data
    ): bool {

        if ( ! $this->is_valid_page( $page_id ) ) {
            return false;
        }

        return $this->section_manager->update_section(
            $page_id,
            $section_id,
            $data
        );
    }

    /**
     * Move a section to another position.
     */
    public function move_section(
        int $page_id,
        string $section_id,
        int $new_order
    ): bool {

        if ( ! $this->is_valid_page( $page_id ) ) {
            return false;
        }

        return $this->section_manager->move_section(
            $page_id,
            $section_id,
            $new_order
        );
    }

    /**
     * Duplicate a section and return the new section ID.
     */
    public function duplicate_section(
        int $page_id,
        string $section_id
    ): ?string {

        if ( ! $this->is_valid_page( $page_id ) ) {
            return null;
        }

        return $this->section_manager->duplicate_section(
            $page_id,
            $section_id
        );
    }

    /**
     * Get a single section.
     */
    public function get_section(
        int $page_id,
        string $section_id
    ): ?array {

        if ( $page_id <= 0 ) {
            return null;
        }

        return $this->section_manager->get_section(
            $page_id,
            $section_id
        );
    }

    /**
     * Get the number of sections assigned to a page.
     */
    public function count_sections(
        int $page_id
    ): int {

        return count(
            $this->get_sections(
                $page_id
            )
        );
    }

    /**
     * Check whether a page contains sections.
     */
    public function has_sections(
        int $page_id
    ): bool {

        return ! empty(
            $this->get_sections(
                $page_id
            )
        );
    }

    /**
     * Get the SectionManager.
     */
    public function get_section_manager(): SectionManager {

        return $this->section_manager;
    }

    /**
     * Validate that the post exists and is a WordPress Page.
     */
    private function is_valid_page(
        int $page_id
    ): bool {

        if ( $page_id <= 0 ) {
            return false;
        }

        $post = get_post(
            $page_id
        );

        if ( ! $post instanceof \WP_Post ) {
            return false;
        }

        return 'page' === $post->post_type;
    }

    /**
     * Get Builder enabled meta key.
     */
    public function get_builder_enabled_meta_key(): string {

        return self::BUILDER_ENABLED_META;
    }

    /**
     * Get page template meta key.
     */
    public function get_page_template_meta_key(): string {

        return self::PAGE_TEMPLATE_META;
    }
}