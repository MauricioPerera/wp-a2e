<?php
/**
 * In-memory data store for workflow execution.
 * Each step's result is stored under its ID.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Data_Store {

	private array $data = array();

	public function set( string $key, mixed $value ): void {
		$this->data[ $key ] = $value;
	}

	public function get( string $key ): mixed {
		return $this->data[ $key ] ?? null;
	}

	public function has( string $key ): bool {
		return array_key_exists( $key, $this->data );
	}

	public function all(): array {
		return $this->data;
	}

	public function clear(): void {
		$this->data = array();
	}
}
