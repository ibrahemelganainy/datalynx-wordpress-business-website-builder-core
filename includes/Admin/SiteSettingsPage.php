<?php

namespace BusinessBuilderCore\Admin;

use BusinessBuilderCore\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SiteSettingsPage {

    protected Plugin $plugin;

    public function __construct( Plugin $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Register admin hooks.
     */
    public function register(): void {

        add_action(
            'admin_menu',
            array( $this, 'register_menu' )
        );

        add_action(
            'admin_post_bb_save_site_settings',
            array( $this, 'save_settings' )
        );
    }

    /**
     * Register admin menu.
     */
    public function register_menu(): void {

        add_menu_page(
            'Business Builder',
            'Business Builder',
            'manage_options',
            'business-builder',
            array( $this, 'render_page' ),
            'dashicons-admin-customizer',
            30
        );

        add_submenu_page(
            'business-builder',
            'Site Settings',
            'Site Settings',
            'manage_options',
            'business-builder-settings',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render settings page.
     */
    public function render_page(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'You do not have permission to access this page.', 'business-builder' )
            );
        }

        $settings      = $this->plugin->get_site_settings()->get_all();
        $business_type = $this->plugin->get_business_type();

        $current_type = $business_type->get_current();
        $types        = $business_type->get_all();

        ?>
        <div class="wrap">

            <h1>Business Builder — Site Settings</h1>

            <?php if ( isset( $_GET['updated'] ) ) : ?>

                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php esc_html_e( 'Settings saved successfully.', 'business-builder' ); ?>
                    </p>
                </div>

            <?php endif; ?>

            <form
                method="post"
                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
            >

                <input
                    type="hidden"
                    name="action"
                    value="bb_save_site_settings"
                >

                <?php wp_nonce_field( 'bb_save_site_settings' ); ?>

                <h2>Business Information</h2>

                <table class="form-table">

                    <tr>
                        <th scope="row">
                            <label for="bb_business_type">
                                Business Type
                            </label>
                        </th>

                        <td>
                            <select
                                name="business_type"
                                id="bb_business_type"
                            >

                                <option value="">
                                    Select Business Type
                                </option>

                                <?php foreach ( $types as $slug => $type ) : ?>

                                    <option
                                        value="<?php echo esc_attr( $slug ); ?>"
                                        <?php selected( $current_type, $slug ); ?>
                                    >
                                        <?php echo esc_html( $type['label'] ); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <p class="description">
                                Select the type of business this website represents.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bb_business_name">
                                Business Name
                            </label>
                        </th>

                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                name="business_name"
                                id="bb_business_name"
                                value="<?php echo esc_attr( $settings['business_name'] ); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bb_tagline">
                                Tagline
                            </label>
                        </th>

                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                name="tagline"
                                id="bb_tagline"
                                value="<?php echo esc_attr( $settings['tagline'] ); ?>"
                            >
                        </td>
                    </tr>

                </table>

                <hr>

                <h2>Contact Information</h2>

                <table class="form-table">

                    <tr>
                        <th scope="row">
                            <label for="bb_phone">
                                Phone
                            </label>
                        </th>

                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                name="phone"
                                id="bb_phone"
                                value="<?php echo esc_attr( $settings['phone'] ); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bb_email">
                                Email
                            </label>
                        </th>

                        <td>
                            <input
                                type="email"
                                class="regular-text"
                                name="email"
                                id="bb_email"
                                value="<?php echo esc_attr( $settings['email'] ); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bb_address">
                                Address
                            </label>
                        </th>

                        <td>
                            <textarea
                                name="address"
                                id="bb_address"
                                rows="4"
                                class="large-text"
                            ><?php echo esc_textarea( $settings['address'] ); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bb_whatsapp">
                                WhatsApp
                            </label>
                        </th>

                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                name="whatsapp"
                                id="bb_whatsapp"
                                value="<?php echo esc_attr( $settings['whatsapp'] ); ?>"
                            >

                            <p class="description">
                                Use the international format, for example:
                                +201001234567
                            </p>
                        </td>
                    </tr>

                </table>

                <hr>

                <h2>Brand Colors</h2>

                <table class="form-table">

                    <tr>
                        <th scope="row">
                            Primary Color
                        </th>

                        <td>
                            <input
                                type="color"
                                name="primary_color"
                                value="<?php echo esc_attr( $settings['primary_color'] ); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            Secondary Color
                        </th>

                        <td>
                            <input
                                type="color"
                                name="secondary_color"
                                value="<?php echo esc_attr( $settings['secondary_color'] ); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            Accent Color
                        </th>

                        <td>
                            <input
                                type="color"
                                name="accent_color"
                                value="<?php echo esc_attr( $settings['accent_color'] ); ?>"
                            >
                        </td>
                    </tr>

                </table>

                <hr>

                <h2>Social Media</h2>

                <table class="form-table">

                    <?php
                    $social_fields = array(
                        'facebook'  => 'Facebook',
                        'instagram' => 'Instagram',
                        'youtube'   => 'YouTube',
                        'linkedin'  => 'LinkedIn',
                        'twitter'   => 'Twitter / X',
                    );
                    ?>

                    <?php foreach ( $social_fields as $field => $label ) : ?>

                        <tr>
                            <th scope="row">
                                <label for="bb_<?php echo esc_attr( $field ); ?>">
                                    <?php echo esc_html( $label ); ?>
                                </label>
                            </th>

                            <td>
                                <input
                                    type="url"
                                    class="large-text"
                                    name="<?php echo esc_attr( $field ); ?>"
                                    id="bb_<?php echo esc_attr( $field ); ?>"
                                    value="<?php echo esc_attr( $settings[ $field ] ); ?>"
                                >
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </table>

                <hr>

                <h2>Visibility</h2>

                <table class="form-table">

                    <tr>
                        <th scope="row">
                            Contact Elements
                        </th>

                        <td>

                            <label>
                                <input
                                    type="checkbox"
                                    name="show_phone"
                                    value="1"
                                    <?php checked( $settings['show_phone'], true ); ?>
                                >
                                Show Phone
                            </label>

                            <br>

                            <label>
                                <input
                                    type="checkbox"
                                    name="show_email"
                                    value="1"
                                    <?php checked( $settings['show_email'], true ); ?>
                                >
                                Show Email
                            </label>

                            <br>

                            <label>
                                <input
                                    type="checkbox"
                                    name="show_address"
                                    value="1"
                                    <?php checked( $settings['show_address'], true ); ?>
                                >
                                Show Address
                            </label>

                            <br>

                            <label>
                                <input
                                    type="checkbox"
                                    name="show_whatsapp"
                                    value="1"
                                    <?php checked( $settings['show_whatsapp'], true ); ?>
                                >
                                Show WhatsApp
                            </label>

                        </td>
                    </tr>

                </table>

                <?php submit_button( 'Save Settings' ); ?>

            </form>

        </div>
        <?php
    }

    /**
     * Save settings.
     */
    public function save_settings(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'You do not have permission to perform this action.', 'business-builder' )
            );
        }

        check_admin_referer(
            'bb_save_site_settings'
        );

        $settings = array(
            'business_name'   => isset( $_POST['business_name'] )
                ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) )
                : '',

            'tagline'         => isset( $_POST['tagline'] )
                ? sanitize_text_field( wp_unslash( $_POST['tagline'] ) )
                : '',

            'phone'           => isset( $_POST['phone'] )
                ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )
                : '',

            'email'           => isset( $_POST['email'] )
                ? sanitize_email( wp_unslash( $_POST['email'] ) )
                : '',

            'address'         => isset( $_POST['address'] )
                ? sanitize_text_field( wp_unslash( $_POST['address'] ) )
                : '',

            'whatsapp'        => isset( $_POST['whatsapp'] )
                ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) )
                : '',

            'primary_color'   => isset( $_POST['primary_color'] )
                ? sanitize_hex_color( wp_unslash( $_POST['primary_color'] ) )
                : '',

            'secondary_color' => isset( $_POST['secondary_color'] )
                ? sanitize_hex_color( wp_unslash( $_POST['secondary_color'] ) )
                : '',

            'accent_color'    => isset( $_POST['accent_color'] )
                ? sanitize_hex_color( wp_unslash( $_POST['accent_color'] ) )
                : '',

            'facebook'        => isset( $_POST['facebook'] )
                ? esc_url_raw( wp_unslash( $_POST['facebook'] ) )
                : '',

            'instagram'       => isset( $_POST['instagram'] )
                ? esc_url_raw( wp_unslash( $_POST['instagram'] ) )
                : '',

            'youtube'         => isset( $_POST['youtube'] )
                ? esc_url_raw( wp_unslash( $_POST['youtube'] ) )
                : '',

            'linkedin'        => isset( $_POST['linkedin'] )
                ? esc_url_raw( wp_unslash( $_POST['linkedin'] ) )
                : '',

            'twitter'         => isset( $_POST['twitter'] )
                ? esc_url_raw( wp_unslash( $_POST['twitter'] ) )
                : '',

            'show_phone'      => isset( $_POST['show_phone'] ),

            'show_email'      => isset( $_POST['show_email'] ),

            'show_address'    => isset( $_POST['show_address'] ),

            'show_whatsapp'   => isset( $_POST['show_whatsapp'] ),
        );

        $this->plugin
            ->get_site_settings()
            ->update( $settings );

        /*
         * Business Type is stored separately because
         * it controls which Business Pack is loaded.
         */
        if ( isset( $_POST['business_type'] ) ) {

            $this->plugin
                ->get_business_type()
                ->set_current(
                    sanitize_key(
                        wp_unslash( $_POST['business_type'] )
                    )
                );
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => 'business-builder-settings',
                    'updated' => '1',
                ),
                admin_url( 'admin.php' )
            )
        );

        exit;
    }
}