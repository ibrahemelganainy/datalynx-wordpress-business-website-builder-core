<?php

namespace BusinessBuilderCore\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Multilingual {

    /**
     * Get multilingual post meta.
     *
     * Example:
     *
     * bb_get_multilingual_meta(
     *     $post_id,
     *     'name',
     *     'ar'
     * );
     */
    public static function get_post_meta(
        int $post_id,
        string $field,
        string $language = 'ar',
        mixed $default = ''
    ): mixed {

        $language = sanitize_key( $language );

        $field = sanitize_key( $field );

        $meta_key = '_bb_' . $field . '_' . $language;

        $value = get_post_meta(
            $post_id,
            $meta_key,
            true
        );

        if ( '' === $value || null === $value ) {
            return $default;
        }

        return $value;
    }

    /**
     * Update multilingual post meta.
     */
    public static function update_post_meta(
        int $post_id,
        string $field,
        string $language,
        mixed $value
    ): bool {

        $language = sanitize_key( $language );

        $field = sanitize_key( $field );

        $meta_key = '_bb_' . $field . '_' . $language;

        return (bool) update_post_meta(
            $post_id,
            $meta_key,
            $value
        );
    }

    /**
     * Get all language values for a post field.
     */
    public static function get_post_meta_all(
        int $post_id,
        string $field
    ): array {

        $languages = array(
            'ar',
            'en',
        );

        $values = array();

        foreach ( $languages as $language ) {

            $values[ $language ] = self::get_post_meta(
                $post_id,
                $field,
                $language
            );
        }

        return $values;
    }

    /**
     * Get multilingual term meta.
     */
    public static function get_term_meta(
        int $term_id,
        string $field,
        string $language = 'ar',
        mixed $default = ''
    ): mixed {

        $language = sanitize_key( $language );

        $field = sanitize_key( $field );

        $meta_key = '_bb_' . $field . '_' . $language;

        $value = get_term_meta(
            $term_id,
            $meta_key,
            true
        );

        if ( '' === $value || null === $value ) {
            return $default;
        }

        return $value;
    }

    /**
     * Update multilingual term meta.
     */
    public static function update_term_meta(
        int $term_id,
        string $field,
        string $language,
        mixed $value
    ): bool {

        $language = sanitize_key( $language );

        $field = sanitize_key( $field );

        $meta_key = '_bb_' . $field . '_' . $language;

        return (bool) update_term_meta(
            $term_id,
            $meta_key,
            $value
        );
    }

    /**
     * Get all language values for a term field.
     */
    public static function get_term_meta_all(
        int $term_id,
        string $field
    ): array {

        $languages = array(
            'ar',
            'en',
        );

        $values = array();

        foreach ( $languages as $language ) {

            $values[ $language ] = self::get_term_meta(
                $term_id,
                $field,
                $language
            );
        }

        return $values;
    }
}