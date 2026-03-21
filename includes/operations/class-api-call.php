<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Op_ApiCall {

	public static function execute( array $step, A2E_Data_Store $store ): mixed {
		$url    = A2E_Path_Resolver::resolve( $step['url'] ?? '', $store );
		$method = strtoupper( $step['method'] ?? 'GET' );

		if ( empty( $url ) || ! is_string( $url ) ) {
			return new WP_Error( 'missing_url', 'ApiCall requires a "url" field.' );
		}

		$headers = array();
		if ( ! empty( $step['headers'] ) && is_array( $step['headers'] ) ) {
			foreach ( $step['headers'] as $k => $v ) {
				$headers[ $k ] = is_string( $v ) ? A2E_Path_Resolver::resolve( $v, $store ) : $v;
			}
		}

		$body = null;
		if ( isset( $step['body'] ) ) {
			$body = A2E_Path_Resolver::resolve( $step['body'], $store );
			if ( is_array( $body ) ) {
				$body = wp_json_encode( $body );
				$headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
			}
		}

		$response = wp_remote_request( $url, array(
			'method'  => $method,
			'headers' => $headers,
			'body'    => $body,
			'timeout' => (int) ( $step['timeout'] ?? 15 ),
		));

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'api_error', "HTTP {$code}", array( 'status' => $code, 'body' => $data ?? $raw ) );
		}

		return $data ?? $raw;
	}
}
