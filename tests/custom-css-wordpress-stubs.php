<?php
/**
 * WordPress API spies, loaded only in CustomCssTest's isolated PHP processes.
 *
 * @package MS_WP_Abilities
 */

$GLOBALS['mswpa_css_test'] = array(
	'abilities'   => array(),
	'active'      => 'child-theme',
	'caps'        => array( 'edit_css' => true ),
	'cap_checks'  => array(),
	'css'         => array( 'child-theme' => 'body { color: green; }', 'parent-theme' => 'body { color: blue; }' ),
	'writes'      => array(),
	'write_error' => null,
);

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function add_action( ...$args ) {}
function add_filter( ...$args ) {}

function wp_register_ability( $name, $args ) {
	$GLOBALS['mswpa_css_test']['abilities'][ $name ] = $args;
}

function current_user_can( $capability ) {
	$GLOBALS['mswpa_css_test']['cap_checks'][] = $capability;
	return ! empty( $GLOBALS['mswpa_css_test']['caps'][ $capability ] );
}

function get_stylesheet() {
	return $GLOBALS['mswpa_css_test']['active'];
}

function wp_get_custom_css( $stylesheet ) {
	return $GLOBALS['mswpa_css_test']['css'][ $stylesheet ] ?? '';
}

function wp_update_custom_css_post( $css, $args ) {
	$GLOBALS['mswpa_css_test']['writes'][] = array( $css, $args );
	if ( $GLOBALS['mswpa_css_test']['write_error'] ) {
		return $GLOBALS['mswpa_css_test']['write_error'];
	}
	$GLOBALS['mswpa_css_test']['css'][ $args['stylesheet'] ] = $css;
	return (object) array( 'ID' => 839 );
}

class WP_Error {
	private string $code;

	public function __construct( $code, $message = '' ) {
		$this->code = $code;
	}

	public function get_error_code() {
		return $this->code;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}
