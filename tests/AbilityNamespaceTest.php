<?php
/**
 * Namespace migration and compatibility boundaries.
 *
 * @package MS_WP_Abilities
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
class AbilityNamespaceTest extends TestCase {

	protected function setUp(): void {
		require __DIR__ . '/namespace-wordpress-stubs.php';
		require __DIR__ . '/../ms-wp-abilities/ms-wp-abilities.php';
		mswpa_register_abilities();
	}

	public function testEveryCanonicalAbilityHasAnEquivalentCallableLegacyAlias(): void {
		$registered = $GLOBALS['mswpa_namespace_test']['abilities'];
		$this->assertCount( count( mswpa_ability_policy() ) * 2, $registered );
		foreach ( mswpa_ability_policy() as $canonical => $policy ) {
			$legacy = 'miriamschwab/' . substr( $canonical, strlen( MSWPA_ABILITY_PREFIX ) );
			$this->assertArrayHasKey( $canonical, $registered );
			$this->assertArrayHasKey( $legacy, $registered );
			$alias = $registered[ $legacy ];
			$this->assertStringContainsString( 'Compatibility alias for ' . $canonical, $alias['description'] );
			$alias['description'] = $registered[ $canonical ]['description'];
			$this->assertSame( $registered[ $canonical ], $alias );
			$this->assertSame( $policy['cap'], mswpa_required_capability_for( $legacy ) );
			$this->assertSame( $policy['write'], mswpa_is_write_ability( $legacy ) );
		}
	}

	public function testBothNamespacesRetainCapabilityFloorAndRestRestrictions(): void {
		foreach ( array( 'ms-wp-abilities/create-post', 'miriamschwab/create-post' ) as $name ) {
			$this->assertFalse( mswpa_enforce_capability_floor( true, $name, array(), null ) );
			$GLOBALS['mswpa_namespace_test']['caps']['edit_posts'] = true;
			$this->assertTrue( mswpa_enforce_capability_floor( true, $name, array(), null ) );
			$GLOBALS['mswpa_namespace_test']['caps'] = array();
			$this->assertFalse( mswpa_force_no_rest_exposure( array( 'meta' => array( 'public' => true ) ), $name )['meta']['show_in_rest'] );
		}
	}

	public function testUnclassifiedNamesFailClosedInEitherNamespace(): void {
		foreach ( array( 'ms-wp-abilities/unknown', 'miriamschwab/unknown' ) as $name ) {
			$this->assertFalse( mswpa_enforce_capability_floor( true, $name, array(), null ) );
		}
	}

	public function testRestWriteHardBlocksApplyToBothNames(): void {
		foreach ( array( 'ms-wp-abilities/rest-write', 'miriamschwab/rest-write' ) as $name ) {
			$result = mswpa_validate_ability_input( true, array( 'route' => '/wp/v2/users/3', 'method' => 'POST' ), $name );
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'rest_write_blocked', $result->get_error_code() );
			$this->assertTrue( mswpa_validate_ability_input( true, array( 'route' => '/wp/v2/posts/3', 'method' => 'POST' ), $name ) );
		}
	}

	public function testAuditRecordsEitherWriteNameExactlyOnceAndExcludesContent(): void {
		foreach ( array( 'ms-wp-abilities/create-post', 'miriamschwab/create-post' ) as $name ) {
			mswpa_record_ability_invocation( $name, array( 'author' => 3, 'content' => 'private content' ) );
		}
		mswpa_record_ability_invocation( 'miriamschwab/get-posts', array() );
		$log = $GLOBALS['mswpa_namespace_test']['options'][ MSWPA_AUDIT_LOG_OPTION ];
		$this->assertCount( 2, $log );
		$this->assertSame( array( 'miriamschwab/create-post', 'ms-wp-abilities/create-post' ), array_column( $log, 'ability' ) );
		$this->assertStringNotContainsString( 'private content', wp_json_encode_compat( $log ) );
	}

	public function testCatalogRecommendsCanonicalNamesWithoutRepeatingAliases(): void {
		$rows = array();
		foreach ( $GLOBALS['mswpa_namespace_test']['abilities'] as $name => $args ) {
			$rows[] = array( 'name' => $name, 'label' => $args['label'], 'description' => $args['description'] );
		}
		$catalog = mswpa_build_ability_catalog( $rows );
		$this->assertStringContainsString( 'ms-wp-abilities/create-post', $catalog );
		$this->assertStringNotContainsString( 'miriamschwab/', $catalog );
	}

	public function testForeignNamesAndOrphanedLegacyCatalogRowsRemainUnchanged(): void {
		$args = array( 'description' => 'Read orders.' );
		$this->assertSame( array( 'shop/get-orders' => $args ), mswpa_ability_registrations( 'shop/get-orders', $args ) );
		$this->assertFalse( mswpa_is_own_ability( 'ms-wp-abilities-other/get-posts' ) );
		$this->assertFalse( mswpa_is_own_ability( 'miriamschwab-other/get-posts' ) );
		$catalog = mswpa_build_ability_catalog( array( array( 'name' => 'miriamschwab/third-party', 'description' => 'Existing extension.' ) ) );
		$this->assertStringContainsString( 'miriamschwab/third-party', $catalog );
	}
}
