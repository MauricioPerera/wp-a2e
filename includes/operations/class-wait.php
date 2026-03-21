<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Op_Wait {

	private const MAX_SECONDS = 30;

	public static function execute( array $step, A2E_Data_Store $store ): array {
		$seconds = A2E_Path_Resolver::resolve( $step['seconds'] ?? 1, $store );
		$seconds = max( 0, min( (int) $seconds, self::MAX_SECONDS ) );

		if ( $seconds > 0 ) {
			usleep( $seconds * 1_000_000 );
		}

		return array( 'waited' => $seconds );
	}
}
