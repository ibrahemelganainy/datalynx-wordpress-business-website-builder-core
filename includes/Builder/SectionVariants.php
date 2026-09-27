<?php

namespace BusinessBuilderCore\Builder;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Section Variant infrastructure (generic, domain-agnostic).
 *
 * A Section Variant is a PRESENTATION strategy (layout/composition) for an
 * existing section, using the SAME data and the SAME Phase-10 components.
 * It is NOT a new section type, a new query, or new business logic.
 *
 * This class owns the *infrastructure* only:
 *   - a whitelist registry of variants per section type,
 *   - a safe resolver (whitelist-based, traversal-proof, default fallback),
 *   - a safe template locator.
 *
 * The domain (e.g. the LawFirm pack) registers its own variants and ships its
 * own variant templates. Core never learns about LawFirm concepts.
 */
class SectionVariants {

    /**
     * The variant every section falls back to.
     */
    public const DEFAULT_VARIANT = 'default';

    /**
     * Registered variant templates.
     *
     * Shape: [ section_type => [ variant_slug => absolute_template_path ] ].
     *
     * @var array<string, array<string, string>>
     */
    protected array $variants = array();

    /**
     * Register a variant template for a section type.
     *
     * @param string $section_type Section slug (e.g. 'lawyers').
     * @param string $variant      Variant slug (e.g. 'list').
     * @param string $template     Absolute path to the variant template.
     * @return bool
     */
    public function register( string $section_type, string $variant, string $template ): bool {

        $section_type = sanitize_key( $section_type );
        $variant      = sanitize_key( $variant );

        if ( '' === $section_type || '' === $variant ) {
            return false;
        }

        if ( ! is_readable( $template ) ) {
            return false;
        }

        $this->variants[ $section_type ][ $variant ] = $template;

        return true;
    }

    /**
     * Register several variants for one section type at once.
     *
     * @param string                $section_type Section slug.
     * @param array<string, string> $variants     variant slug => absolute template path.
     */
    public function register_many( string $section_type, array $variants ): void {

        foreach ( $variants as $variant => $template ) {
            $this->register( $section_type, (string) $variant, (string) $template );
        }
    }

    /**
     * The variant slugs registered for a section type (always includes default).
     *
     * @param string $section_type Section slug.
     * @return string[]
     */
    public function available( string $section_type ): array {

        $section_type = sanitize_key( $section_type );

        $slugs = isset( $this->variants[ $section_type ] )
            ? array_keys( $this->variants[ $section_type ] )
            : array();

        if ( ! in_array( self::DEFAULT_VARIANT, $slugs, true ) ) {
            $slugs[] = self::DEFAULT_VARIANT;
        }

        return array_values( array_unique( $slugs ) );
    }

    /**
     * Whether a section type has any non-default variant.
     *
     * @param string $section_type Section slug.
     * @return bool
     */
    public function has_variants( string $section_type ): bool {

        return count( $this->available( $section_type ) ) > 1;
    }

    /**
     * Resolve a requested variant to a REGISTERED variant slug.
     *
     * Whitelist-based: an unknown/malformed/absent request resolves to
     * `default`. The requested value is never used as a path.
     *
     * @param string      $section_type Section slug.
     * @param string|null $requested    Requested variant slug (may be null/'').
     * @return string A registered variant slug.
     */
    public function resolve( string $section_type, ?string $requested ): string {

        $section_type = sanitize_key( $section_type );

        $requested = is_string( $requested ) ? sanitize_key( $requested ) : '';

        $registered = isset( $this->variants[ $section_type ] )
            ? array_keys( $this->variants[ $section_type ] )
            : array();

        if ( '' !== $requested && in_array( $requested, $registered, true ) ) {
            return $requested;
        }

        return self::DEFAULT_VARIANT;
    }

    /**
     * Resolve the absolute template path for a (section type, variant) pair.
     *
     * Only a REGISTERED template is ever returned, so no user-supplied string
     * can influence the filesystem path.
     *
     * @param string      $section_type Section slug.
     * @param string|null $requested    Requested variant slug.
     * @return string Absolute path, or '' when none is registered.
     */
    public function template( string $section_type, ?string $requested ): string {

        $section_type = sanitize_key( $section_type );

        if ( '' === $section_type ) {
            return '';
        }

        $variant = $this->resolve( $section_type, $requested );

        // Prefer an exact registered match for the resolved slug.
        if ( isset( $this->variants[ $section_type ][ $variant ] ) ) {
            return $this->variants[ $section_type ][ $variant ];
        }

        // Resolved to 'default' but only a fallback exists → use any registered
        // template as the default (first registered wins), else ''.
        if ( self::DEFAULT_VARIANT === $variant && ! empty( $this->variants[ $section_type ] ) ) {
            $templates = array_values( $this->variants[ $section_type ] );

            return (string) $templates[0];
        }

        return '';
    }

    /**
     * All registered variants (for debugging / tooling).
     *
     * @return array<string, array<string, string>>
     */
    public function all(): array {

        return $this->variants;
    }
}