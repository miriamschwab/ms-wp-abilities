<?php
/**
 * WordPress spies loaded only in the namespace tests' isolated PHP processes.
 *
 * @package MS_WP_Abilities
 */

$GLOBALS['mswpa_namespace_test'] = array( 'abilities' => array(), 'caps' => array(), 'options' => array() );

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function add_action( ...$args ) {}
function add_filter( ...$args ) {}

function wp_register_ability( $name, $args ) {
	$GLOBALS['mswpa_namespace_test']['abilities'][ $name ] = $args;
}

function current_user_can( $cap ) {
	return ! empty( $GLOBALS['mswpa_namespace_test']['caps'][ $cap ] );
}

function sanitize_text_field( $text ) {
	return trim( strip_tags( $text ) );
}

class WP_Error {
	private string $code;

	public function __construct( $code, $message = '', $data = null ) {
		$this->code = $code;
	}

	public function get_error_code() {
		return $this->code;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function get_option( $name, $default = false ) {
	return $GLOBALS['mswpa_namespace_test']['options'][ $name ] ?? $default;
}

function update_option( $name, $value ) {
	$GLOBALS['mswpa_namespace_test']['options'][ $name ] = $value;
}

function get_current_user_id() {
	return 3;
}
