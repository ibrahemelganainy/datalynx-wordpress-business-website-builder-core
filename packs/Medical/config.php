<?php
/**
 * Medical Pack metadata.
 *
 * Metadata only. The pack is registered with the Core through the
 * `bb_register_packs` action (see MedicalPack::register_pack()) — never by
 * editing Core. See docs/phase-19-medical-pack-audit.md §3.
 *
 * @package BusinessBuilderCore\Packs\Medical
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'name'        => 'Medical Pack',
	'slug'        => 'medical',
	'business'    => 'medical',
	'description' => 'Website pack for doctors, clinics and medical practices.',
	'version'     => '1.0.0',
);