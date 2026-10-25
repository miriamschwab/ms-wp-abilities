<?php
/**
 * Exercise the registered Additional CSS callbacks with isolated WordPress spies.
 *
 * @package MS_WP_Abilities
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
class CustomCssTest extends TestCase {

	protected function setUp(): void {
		require __DIR__ . '/custom-css-wordpress-stubs.php';
		require __DIR__ . '/../ms-wp-abilities/ms-wp-abilities.php';
		mswpa_register_abilities();
	}

	private function ability( string $name ): array {
		return $GLOBALS['mswpa_css_test']['abilities'][ 'miriamschwab/' . $name ];
	}

	private function update( array $input ) {
		return ( $this->ability( 'update-custom-css' )['execute_callback'] )( $input );
	}

	public function testReadsActiveThemeAndExistingCss(): void {
		$result = ( $this->ability( 'get-custom-css' )['execute_callback'] )();
		$this->assertSame( array( 'stylesheet' => 'child-theme', 'css' => 'body { color: green; }' ), $result );
		$this->assertSame( array(), $GLOBALS['mswpa_css_test']['writes'] );
	}

	public function testReadsEmptyCssWhenThemeHasNoCustomCss(): void {
		$GLOBALS['mswpa_css_test']['active'] = 'new-theme';
		$result = ( $this->ability( 'get-custom-css' )['execute_callback'] )();
		$this->assertSame( array( 'stylesheet' => 'new-theme', 'css' => '' ), $result );
	}

	public function testBothAbilitiesCheckEditCssWithoutRequiringEditPosts(): void {
		foreach ( array( 'get-custom-css', 'update-custom-css' ) as $name ) {
			$this->assertTrue( ( $this->ability( $name )['permission_callback'] )() );
		}
		$this->assertSame( array( 'edit_css', 'edit_css' ), $GLOBALS['mswpa_css_test']['cap_checks'] );
	}

	public function testOrdinaryPostPermissionsDoNotGrantCssAccess(): void {
		$GLOBALS['mswpa_css_test']['caps'] = array( 'edit_posts' => true, 'edit_post' => true );
		foreach ( array( 'get-custom-css', 'update-custom-css' ) as $name ) {
			$this->assertFalse( ( $this->ability( $name )['permission_callback'] )() );
		}
		$this->assertSame( array(), $GLOBALS['mswpa_css_test']['writes'] );
	}

	public function testUpdatePreservesCssAndOtherThemes(): void {
		$css = "/* Keep escapes and custom properties. */\nbody::before { content: '\\2713'; --label: \"a&b\"; }";
		$result = $this->update( array( 'stylesheet' => 'child-theme', 'css' => $css ) );
		$this->assertSame( array( 'ID' => 839, 'stylesheet' => 'child-theme', 'css' => $css ), $result );
		$this->assertSame( array( array( $css, array( 'stylesheet' => 'child-theme' ) ) ), $GLOBALS['mswpa_css_test']['writes'] );
		$this->assertSame( 'body { color: blue; }', $GLOBALS['mswpa_css_test']['css']['parent-theme'] );
	}

	public function testEmptyCssClearsExistingRules(): void {
		$result = $this->update( array( 'stylesheet' => 'child-theme', 'css' => '' ) );
		$this->assertSame( '', $result['css'] );
		$this->assertCount( 1, $GLOBALS['mswpa_css_test']['writes'] );
	}

	public function testThemeSwitchBetweenReadAndWriteIsRejected(): void {
		$GLOBALS['mswpa_css_test']['active'] = 'parent-theme';
		$result = $this->update( array( 'stylesheet' => 'child-theme', 'css' => 'body {}' ) );
		$this->assertSame( 'stylesheet_mismatch', $result->get_error_code() );
		$this->assertSame( array(), $GLOBALS['mswpa_css_test']['writes'] );
	}

	public function testCoreWriteErrorsAreReturned(): void {
		$error = new WP_Error( 'db_update_error', 'Could not save CSS.' );
		$GLOBALS['mswpa_css_test']['write_error'] = $error;
		$this->assertSame( $error, $this->update( array( 'stylesheet' => 'child-theme', 'css' => 'body {}' ) ) );
	}

	#[DataProvider( 'invalidInput' )]
	public function testInvalidInputDoesNotWrite( array $input ): void {
		$this->assertSame( 'invalid_custom_css_input', $this->update( $input )->get_error_code() );
		$this->assertSame( array(), $GLOBALS['mswpa_css_test']['writes'] );
	}

	public static function invalidInput(): array {
		return array(
			'missing CSS'        => array( array( 'stylesheet' => 'child-theme' ) ),
			'missing theme'      => array( array( 'css' => 'body {}' ) ),
			'null CSS'           => array( array( 'stylesheet' => 'child-theme', 'css' => null ) ),
			'non-string CSS'     => array( array( 'stylesheet' => 'child-theme', 'css' => array() ) ),
			'non-string theme'   => array( array( 'stylesheet' => 1, 'css' => '' ) ),
		);
	}

	#[DataProvider( 'markup' )]
	public function testMarkupIsRejectedWithoutWriting( string $css ): void {
		$this->assertSame( 'illegal_markup', $this->update( array( 'stylesheet' => 'child-theme', 'css' => $css ) )->get_error_code() );
		$this->assertSame( array(), $GLOBALS['mswpa_css_test']['writes'] );
	}

	public static function markup(): array {
		return array_map(
			static fn( $css ) => array( $css ),
			array( '<style>body {}</style>', '</STYLE><script>alert(1)</script>', 'body {}</sty', 'body {}<', 'body {}</' )
		);
	}

	public function testAuditRecordsThemeButNeverCssContent(): void {
		$summary = mswpa_summarize_ability_input( array( 'stylesheet' => 'child-theme', 'css' => 'private-selector {}' ) );
		$this->assertSame( array( 'stylesheet' => 'child-theme' ), $summary['values'] );
		$this->assertStringNotContainsString( 'private-selector', wp_json_encode_compat( $summary ) );
	}
}
