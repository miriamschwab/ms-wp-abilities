<?php
/**
 * Optional `author` input for create-post.
 *
 * A post's author is whichever account created it unless told otherwise, and
 * for this plugin that is the account the MCP client connects as. Sites that
 * give the agent its own login (which is the sensible setup, because revisions
 * and the audit log then show what the agent did) ended up with that login as
 * the public author of everything it drafted, and fixing it afterwards through
 * an update stamps post_modified on every post touched.
 *
 * The rules mirror WP_REST_Posts_Controller::check_assign_author_perm(): naming
 * yourself is always allowed, and naming anyone else needs the post type's
 * edit_others_posts capability. On top of that, the named user must be able to
 * write that post type, so a post cannot be handed to a subscriber.
 *
 * The WordPress lookups are passed in as callables so the decision itself has
 * no WordPress dependencies and can be unit-tested. See tests/PostAuthorTest.php.
 *
 * @package MS_WP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve create-post's `author` input to a user ID.
 *
 * An integer, or a string of digits, is read as a user ID. Any other string is
 * read as a login. Absent, null or empty means the connected user, which is the
 * behavior create-post had before this input existed.
 *
 * @param mixed    $requested       Raw `author` input.
 * @param int      $current_user_id ID of the connected user.
 * @param callable $find_user       Receives an int ID or a string login; returns the
 *                                  user's ID, or 0 if there is no such user.
 * @param callable $can_assign      Receives the resolved user ID of someone other than
 *                                  the connected user; returns whether the post may be
 *                                  assigned to them.
 * @return int|string Resolved user ID, or an error code: 'invalid_author' or
 *                    'cannot_assign_author'.
 */
function mswpa_resolve_post_author( $requested, int $current_user_id, callable $find_user, callable $can_assign ) {
	if ( null === $requested || '' === $requested ) {
		return $current_user_id;
	}

	if ( is_int( $requested ) ) {
		$lookup = $requested;
	} elseif ( is_string( $requested ) && ctype_digit( $requested ) ) {
		$lookup = (int) $requested;
	} elseif ( is_string( $requested ) ) {
		$lookup = trim( $requested );
	} else {
		return 'invalid_author';
	}

	if ( ( is_int( $lookup ) && $lookup <= 0 ) || '' === $lookup ) {
		return 'invalid_author';
	}

	$user_id = (int) $find_user( $lookup );
	if ( $user_id <= 0 ) {
		return 'invalid_author';
	}

	if ( $user_id === $current_user_id ) {
		return $user_id;
	}

	return $can_assign( $user_id ) ? $user_id : 'cannot_assign_author';
}
