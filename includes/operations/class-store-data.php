<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Op_StoreData {

	public static function execute( array $step, A2E_Data_Store $store ): mixed {
		$key   = $step['key'] ?? $step['id'] ?? '';
		$value = isset( $step['value'] ) ? A2E_Path_Resolver::resolve( $step['value'], $store ) : null;

		if ( '' === $key ) {
			return new WP_Error( 'missing_key', 'StoreData requires a "key" field.' );
		}

		$store->set( $key, $value );

		return array( 'stored' => $key, 'value' => $value );
	}
}
