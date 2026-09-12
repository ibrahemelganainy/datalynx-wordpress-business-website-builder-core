<?php

namespace BusinessBuilderCore\Packs\LawFirm\Starter;

use BusinessBuilderCore\Builder\PageManager;
use BusinessBuilderCore\Packs\LawFirm\Sections\LawFirmQueries;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LawFirm starter website configuration (spec Â§20 / Â§21).
 *
 * Creates a ready-made set of builder pages for a new Law Firm site
 * using the EXISTING Page/Builder architecture â€” nothing is
 * hard-coded into a theme. Each page is a normal WordPress page with:
 *   - the builder enabled
 *   - a chosen visual template
 *   - an ordered list of registered section types
 *
 * Safety (spec Â§35):
 *   - Runs ONLY when explicitly invoked (opt-in admin action).
 *   - Idempotent: skips any page whose slug already exists; will not
 *     overwrite or delete existing content.
 *   - Never touches entities (Lawyers, Services, ...) â€” only pages.
 */
class StarterSite {

    /**
     * PageManager (existing Core service).
     */
    protected PageManager $page_manager;

    /**
     * Constructor.
     *
     * @param PageManager $page_manager Existing Core PageManager.
     */
    public function __construct( PageManager $page_manager ) {

        $this->page_manager = $page_manager;
    }

    /**
     * The starter site blueprint.
     *
     * Each entry: slug, title, template, and the ordered section
     * types to place on the page. Section types reference the
     * registered slugs (core + pack) so they stay in sync with the
     * SectionRegistry rather than duplicating markup.
     *
     * @return array
     */
    public function blueprint(): array {

        return array(

            'home' => array(
                'title'    => __( 'Home', 'business-builder' ),
                'template' => 'modern',
                'sections' => array(
                    'header',
                    'slider',
                    'about',
                    'practice_areas',
                    'legal_services',
                    'lawyers',
                    'testimonials',
                    'faq',
                    'contact',
                    'footer',
                ),
            ),

            'about' => array(
                'title'    => __( 'About', 'business-builder' ),
                'template' => 'modern',
                'sections' => array(
                    'header',
                    'about',
                    'lawyers',
                    'cta',
                    'footer',
                ),
            ),

            'practice-areas' => array(
                'title'    => __( 'Practice Areas', 'business-builder' ),
                'template' => 'default',
                'sections' => array(
                    'header',
                    'practice_areas',
                    'cta',
                    'footer',
                ),
            ),

            'services' => array(
                'title'    => __( 'Services', 'business-builder' ),
                'template' => 'default',
                'sections' => array(
                    'header',
                    'legal_services',
                    'cta',
                    'footer',
                ),
            ),

            'lawyers' => array(
                'title'    => __( 'Lawyers', 'business-builder' ),
                'template' => 'default',
                'sections' => array(
                    'header',
                    'lawyers',
                    'footer',
                ),
            ),

            'contact' => array(
                'title'    => __( 'Contact', 'business-builder' ),
                'template' => 'default',
                'sections' => array(
                    'header',
                    'contact',
                    'consultation',
                    'footer',
                ),
            ),
        );
    }

    /**
     * Build the starter pages.
     *
     * @return array Result summary: created[], skipped[], sections_added.
     */
    public function build(): array {

        $created = array();
        $skipped = array();
        $sections_added = 0;

        foreach ( $this->blueprint() as $slug => $config ) {

            $existing = get_page_by_path( $slug );

            if ( $existing instanceof \WP_Post ) {
                $skipped[] = $slug;
                continue;
            }

            $page_id = wp_insert_post(
                array(
                    'post_type'   => 'page',
                    'post_status' => 'publish',
                    'post_title'  => $config['title'],
                    'post_name'   => $slug,
                ),
                true
            );

            if ( is_wp_error( $page_id ) || ! $page_id ) {
                $skipped[] = $slug;
                continue;
            }

            $this->page_manager->enable_builder( $page_id );
            $this->page_manager->set_page_template( $page_id, $config['template'] );

            foreach ( $config['sections'] as $section_type ) {

                $section_id = $this->page_manager->add_section(
                    $page_id,
                    $section_type
                );

                if ( null !== $section_id ) {
                    $sections_added++;
                }
            }

            $created[] = $slug;
        }

        return array(
            'created'        => $created,
            'skipped'        => $skipped,
            'sections_added' => $sections_added,
        );
    }

    /**
     * Whether the starter site has already been built.
     *
     * Used to avoid duplicate work / repeated admin notices.
     *
     * @return bool
     */
    public function is_built(): bool {

        return (bool) get_option( 'bb_lawfirm_starter_built', false );
    }

    /**
     * Mark the starter site as built.
     */
    public function mark_built(): void {

        update_option( 'bb_lawfirm_starter_built', 1 );
    }
}
