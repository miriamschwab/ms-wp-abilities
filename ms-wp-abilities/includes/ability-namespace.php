<?php
/**
 * Canonical ability registrations and backwards-compatible names.
 *
 * @package MS_WP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Share schemas and callbacks between canonical names and compatibility aliases.
 *
 * @param string $name Ability name.
 * @param array  $args Registration arguments.
 * @return array<string, array> Registration arguments keyed by ability name.
 */
function mswpa_ability_registrations( string $name, array $args ): array {
	$canonical = mswpa_canonical_ability_name( $name );
	if ( ! mswpa_is_own_ability( $canonical ) ) {
		return array( $name => $args );
	}
	$legacy = MSWPA_LEGACY_ABILITY_PREFIX . substr( $canonical, strlen( MSWPA_ABILITY_PREFIX ) );
	$alias  = $args;
	$alias['description'] = sprintf(
		/* translators: %s: Canonical ability name. */
		__( 'Compatibility alias for %s. Prefer the canonical name for new calls. ', 'ms-wp-abilities' ),
		$canonical
	) . ( $args['description'] ?? '' );
	return array( $canonical => $args, $legacy => $alias );
}
