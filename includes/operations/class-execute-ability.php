<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Op_ExecuteAbility {

	public static function execute( array $step, A2E_Data_Store $store ): mixed {
		$ability_name = $step['ability'] ?? '';
		if ( '' === $ability_name ) {
			return new WP_Error( 'missing_ability', 'ExecuteAbility requires an "ability" field.' );
		}

		$ability = wp_get_ability( $ability_name );
		if ( ! $ability ) {
			return new WP_Error( 'ability_not_found', "Ability '{$ability_name}' not found." );
		}

		$input = isset( $step['input'] ) ? A2E_Path_Resolver::resolve( $step['input'], $store ) : null;

		return $ability->execute( $input );
	}
}
