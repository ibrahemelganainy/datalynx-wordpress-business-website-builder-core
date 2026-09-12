<?php

namespace BusinessBuilderCore\Admin;

use BusinessBuilderCore\Builder\PageManager;
use BusinessBuilderCore\Builder\SectionRegistry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PageAdmin {

    private const META_BOX_ID = 'bb_page_builder';

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
     * Register admin functionality.
     */
    public function register(): void {

        add_action(
            'add_meta_boxes_page',
            array(
                $this,
                'register_meta_box',
            )
        );

        add_action(
            'save_post_page',
            array(
                $this,
                'save',
            ),
            10,
            2
        );

        add_action(
            'admin_enqueue_scripts',
            array(
                $this,
                'enqueue_assets',
            )
        );
    }

    /**
     * Register Builder meta box on WordPress Pages.
     */
    public function register_meta_box(
        \WP_Post $post
    ): void {

        add_meta_box(
            self::META_BOX_ID,
            __(
                'Business Builder',
                'business-builder'
            ),
            array(
                $this,
                'render_meta_box',
            ),
            'page',
            'normal',
            'high'
        );
    }

    /**
     * Render Builder interface.
     */
    public function render_meta_box(
        \WP_Post $post
    ): void {

        wp_nonce_field(
            'bb_save_page_builder',
            'bb_page_builder_nonce'
        );

        $builder_enabled =
            $this->page_manager->is_builder_page(
                $post->ID
            );

        $template =
            $this->page_manager->get_page_template(
                $post->ID
            );

        $sections =
            $this->page_manager->get_sections(
                $post->ID
            );

        $section_types =
            $this->section_registry->get_enabled();

        ?>

        <div class="bb-page-builder">

            <div class="bb-builder-header">

                <div>
                    <h3>
                        <?php
                        echo esc_html(
                            __(
                                'Page Builder',
                                'business-builder'
                            )
                        );
                        ?>
                    </h3>

                    <p class="description">
                        <?php
                        echo esc_html(
                            __(
                                'Build this page using Business Builder sections.',
                                'business-builder'
                            )
                        );
                        ?>
                    </p>
                </div>

                <div class="bb-builder-actions">
                    <button
                        type="button"
                        class="button bb-live-preview-button"
                        data-preview-url="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"
                    >
                        <?php
                        echo esc_html(
                            __(
                                'Live Preview',
                                'business-builder'
                            )
                        );
                        ?>
                    </button>

                    <label>

                        <input
                            type="checkbox"
                            name="bb_builder_enabled"
                            value="1"
                            <?php checked( $builder_enabled, true ); ?>
                        />

                        <?php
                        echo esc_html(
                            __(
                                'Enable Builder',
                                'business-builder'
                            )
                        );
                        ?>

                    </label>
                </div>

            </div>

            <hr>

            <div class="bb-builder-template">

                <label
                    for="bb_page_template"
                >
                    <strong>
                        <?php
                        echo esc_html(
                            __(
                                'Template',
                                'business-builder'
                            )
                        );
                        ?>
                    </strong>
                </label>

                <select
                    name="bb_page_template"
                    id="bb_page_template"
                >

                    <option
                        value=""
                        <?php selected( $template, '' ); ?>
                    >
                        <?php
                        echo esc_html(
                            __(
                                'Default',
                                'business-builder'
                            )
                        );
                        ?>
                    </option>

                    <option
                        value="modern"
                        <?php selected( $template, 'modern' ); ?>
                    >
                        <?php
                        echo esc_html(
                            __(
                                'Modern',
                                'business-builder'
                            )
                        );
                        ?>
                    </option>

                    <option
                        value="luxury"
                        <?php selected( $template, 'luxury' ); ?>
                    >
                        <?php
                        echo esc_html(
                            __(
                                'Luxury',
                                'business-builder'
                            )
                        );
                        ?>
                    </option>

                </select>

            </div>

            <hr>

            <div class="bb-builder-sections">

                <div class="bb-builder-sections-header">

                    <div>
                        <h4>
                            <?php
                            echo esc_html(
                                __(
                                    'Sections',
                                    'business-builder'
                                )
                            );
                            ?>
                        </h4>

                        <span>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %d: number of sections */
                                    _n(
                                        '%d section',
                                        '%d sections',
                                        count( $sections ),
                                        'business-builder'
                                    ),
                                    count( $sections )
                                )
                            );
                            ?>
                        </span>
                    </div>

                </div>

                <?php if ( empty( $sections ) ) : ?>

                    <div class="bb-builder-empty">

                        <p>
                            <?php
                            echo esc_html(
                                __(
                                    'No sections have been added to this page yet.',
                                    'business-builder'
                                )
                            );
                            ?>
                        </p>

                    </div>

                <?php else : ?>

                    <div class="bb-section-list">

                        <?php foreach ( $sections as $section ) : ?>

                            <?php

                            $section_id =
                                isset( $section['id'] )
                                    ? $section['id']
                                    : '';

                            $section_type =
                                isset( $section['type'] )
                                    ? $section['type']
                                    : '';

                            $config =
                                $this->section_registry->get(
                                    $section_type
                                );

                            if ( null === $config ) {
                                continue;
                            }

                            $label =
                                ! empty( $config['label'] )
                                    ? $config['label']
                                    : $section_type;

                            ?>

                            <div
                                class="bb-section-item"
                                data-section-id="<?php echo esc_attr( $section_id ); ?>"
                                draggable="true"
                            >

                                <div class="bb-section-item-handle">

                                    <span
                                        class="dashicons dashicons-menu"
                                    ></span>

                                </div>

                                <div class="bb-section-item-info">

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $label
                                        );
                                        ?>
                                    </strong>

                                    <span>
                                        <?php
                                        echo esc_html(
                                            $section_type
                                        );
                                        ?>
                                    </span>

                                </div>

                                <div class="bb-section-item-order">

                                    <?php
                                    echo esc_html(
                                        $section['order']
                                    );
                                    ?>

                                </div>

                                <div class="bb-section-item-actions">

                                    <button
                                        type="button"
                                        class="button bb-edit-section"
                                        data-section-id="<?php echo esc_attr( $section_id ); ?>"
                                        data-section-type="<?php echo esc_attr( $section_type ); ?>"
                                    >
                                        <?php
                                        echo esc_html(
                                            __(
                                                'Edit',
                                                'business-builder'
                                            )
                                        );
                                        ?>
                                    </button>

                                    <button
                                        type="button"
                                        class="button bb-duplicate-section"
                                        data-section-id="<?php echo esc_attr( $section_id ); ?>"
                                    >
                                        <?php
                                        echo esc_html(
                                            __(
                                                'Duplicate',
                                                'business-builder'
                                            )
                                        );
                                        ?>
                                    </button>

                                    <button
                                        type="button"
                                        class="button-link-delete bb-delete-section"
                                        data-section-id="<?php echo esc_attr( $section_id ); ?>"
                                    >
                                        <?php
                                        echo esc_html(
                                            __(
                                                'Delete',
                                                'business-builder'
                                            )
                                        );
                                        ?>
                                    </button>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

                <div
                    id="bb-section-editor"
                    class="bb-section-editor"
                    style="display:none;"
                >

                    <div class="bb-section-editor-overlay"></div>

                    <div class="bb-section-editor-modal">

                        <div class="bb-section-editor-header">

                            <div>
                                <h2 id="bb-section-editor-title">
                                    <?php
                                    echo esc_html(
                                        __(
                                            'Edit Section',
                                            'business-builder'
                                        )
                                    );
                                    ?>
                                </h2>
                            </div>

                            <button
                                type="button"
                                class="button-link bb-close-section-editor"
                            >
                                ×
                            </button>

                        </div>

                        <div class="bb-section-editor-content">

                            <div
                                id="bb-section-editor-body"
                                class="bb-section-editor-body"
                            >

                                <p class="description">
                                    <?php
                                    echo esc_html(
                                        __(
                                            'Select a section to edit its content.',
                                            'business-builder'
                                        )
                                    );
                                    ?>
                                </p>

                            </div>

                            <div
                                id="bb-section-editor-preview"
                                class="bb-section-editor-preview"
                            >
                                <div class="bb-section-editor-preview-header">
                                    <h3>
                                        <?php
                                        echo esc_html(
                                            __(
                                                'Live Preview',
                                                'business-builder'
                                            )
                                        );
                                        ?>
                                    </h3>
                                </div>
                                <div class="bb-section-editor-preview-body">
                                    <p class="description">
                                        <?php
                                        echo esc_html(
                                            __(
                                                'Preview updates as you edit fields.',
                                                'business-builder'
                                            )
                                        );
                                        ?>
                                    </p>
                                </div>
                            </div>

                        </div>

                        <div class="bb-section-editor-footer">

                            <button
                                type="button"
                                class="button bb-close-section-editor"
                            >
                                <?php
                                echo esc_html(
                                    __(
                                        'Cancel',
                                        'business-builder'
                                    )
                                );
                                ?>
                            </button>

                            <button
                                type="button"
                                class="button button-primary"
                                id="bb-save-section"
                            >
                                <?php
                                echo esc_html(
                                    __(
                                        'Save Section',
                                        'business-builder'
                                    )
                                );
                                ?>
                            </button>

                        </div>

                    </div>

                </div>

            </div>

            <div
                id="bb-live-preview-modal"
                class="bb-live-preview-modal"
                style="display:none;"
            >
                <div class="bb-live-preview-overlay"></div>
                <div class="bb-live-preview-panel">
                    <div class="bb-live-preview-header">
                        <h3>
                            <?php
                            echo esc_html(
                                __(
                                    'Preview',
                                    'business-builder'
                                )
                            );
                            ?>
                        </h3>
                        <button
                            type="button"
                            class="button-link bb-close-live-preview"
                        >
                            ×
                        </button>
                    </div>
                    <iframe
                        id="bb-live-preview-iframe"
                        title="Business Builder Live Preview"
                        src=""
                    ></iframe>
                </div>
            </div>

            <hr>

            <div class="bb-builder-add-section">

                <h4>
                    <?php
                    echo esc_html(
                        __(
                            'Add Section',
                            'business-builder'
                        )
                    );
                    ?>
                </h4>

                <div class="bb-builder-section-types">

                    <?php foreach ( $section_types as $slug => $config ) : ?>

                        <button
                            type="button"
                            class="button bb-add-section"
                            data-section-type="<?php echo esc_attr( $slug ); ?>"
                        >

                            <?php if ( ! empty( $config['icon'] ) ) : ?>

                                <span
                                    class="dashicons <?php echo esc_attr( $config['icon'] ); ?>"
                                ></span>

                            <?php endif; ?>

                            <?php
                            echo esc_html(
                                $config['label']
                            );
                            ?>

                        </button>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

        <?php
    }

    /**
     * Save Builder page settings.
     */
    public function save(
        int $post_id,
        \WP_Post $post
    ): void {

        if (
            ! isset(
                $_POST['bb_page_builder_nonce']
            )
        ) {
            return;
        }

        if (
            ! wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['bb_page_builder_nonce']
                    )
                ),
                'bb_save_page_builder'
            )
        ) {
            return;
        }

        if (
            defined( 'DOING_AUTOSAVE' )
            && DOING_AUTOSAVE
        ) {
            return;
        }

        if (
            wp_is_post_revision(
                $post_id
            )
        ) {
            return;
        }

        if (
            'page' !== $post->post_type
        ) {
            return;
        }

        if (
            ! current_user_can(
                'edit_post',
                $post_id
            )
        ) {
            return;
        }

        $builder_enabled =
            isset(
                $_POST['bb_builder_enabled']
            );

        if ( $builder_enabled ) {

            $this->page_manager->enable_builder(
                $post_id
            );

        } else {

            $this->page_manager->disable_builder(
                $post_id
            );
        }

        if (
            isset(
                $_POST['bb_page_template']
            )
        ) {

            $template =
                sanitize_key(
                    wp_unslash(
                        $_POST['bb_page_template']
                    )
                );

            $this->page_manager->set_page_template(
                $post_id,
                $template
            );
        }
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets(
        string $hook
    ): void {

        if (
            'post.php' !== $hook
            && 'post-new.php' !== $hook
        ) {
            return;
        }

        $screen = get_current_screen();

        if (
            ! $screen
            || 'page' !== $screen->post_type
        ) {
            return;
        }

        // ---------- أضف هذا السطر هنا ----------
        wp_enqueue_media();
        // ----------------------------------------

        wp_enqueue_style(
            'bb-page-admin',
            BB_CORE_URL . 'assets/css/page-admin.css',
            array(),
            BB_CORE_VERSION
        );

        wp_enqueue_script(
            'bb-page-admin',
            BB_CORE_URL . 'assets/js/page-admin.js',
            array( 'jquery' ),
            BB_CORE_VERSION,
            true
        );

        wp_localize_script(
            'bb-page-admin',
            'BBPageAdmin',
            array(
                'ajaxUrl' => admin_url(
                    'admin-ajax.php'
                ),
                'nonce' => wp_create_nonce(
                    'bb_page_builder_ajax'
                ),
            )
        );
    }

    /**
     * Get PageManager.
     */
    public function get_page_manager(): PageManager {

        return $this->page_manager;
    }

    /**
     * Get SectionRegistry.
     */
    public function get_section_registry(): SectionRegistry {

        return $this->section_registry;
    }
}