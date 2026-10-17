<?php
/**
 * Tests for create-post's optional `author` input.
 *
 * @package MS_WP_Abilities
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers mswpa_resolve_post_author().
 */
class PostAuthorTest extends TestCase {

	/** The connected user, i.e. the agent's own login. */
	private const AGENT = 5;

	/** The person the site's content should be credited to. */
	private const OWNER = 3;

	/** A user who exists but cannot write posts. */
	private const SUBSCRIBER = 7;

	/**
	 * Logins this fake site knows.
	 *
	 * @var array<string, int>
	 */
	private const LOGINS = array(
		'cloudagent'   => self::AGENT,
		'miriamschwab' => self::OWNER,
		'subscriber'   => self::SUBSCRIBER,
	);

	/**
	 * Every user ID passed to find_user, so tests can assert how input was read.
	 *
	 * @var array<int, int|string>
	 */
	private array $lookups = array();

	/**
	 * A user lookup over the fake site's users.
	 *
	 * @return callable
	 */
	private function find(): callable {
		return function ( $lookup ): int {
			$this->lookups[] = $lookup;
			if ( is_int( $lookup ) ) {
				return in_array( $lookup, self::LOGINS, true ) ? $lookup : 0;
			}
			return self::LOGINS[ $lookup ] ?? 0;
		};
	}

	/**
	 * Resolve with a connected user who may assign posts to anyone who can write them.
	 *
	 * @param mixed $requested Raw `author` input.
	 * @return int|string
	 */
	private function resolve( $requested ) {
		return mswpa_resolve_post_author(
			$requested,
			self::AGENT,
			$this->find(),
			fn( int $user_id ): bool => self::SUBSCRIBER !== $user_id
		);
	}

	/**
	 * Leaving author out keeps the behavior create-post had before the input existed.
	 *
	 * @param mixed $requested Absent-equivalent input.
	 * @return void
	 */
	#[DataProvider( 'absentInputs' )]
	public function testAbsentAuthorIsTheConnectedUser( $requested ): void {
		$this->assertSame( self::AGENT, $this->resolve( $requested ) );
		$this->assertSame( array(), $this->lookups, 'No lookup should run when no author was named.' );
	}

	/**
	 * Inputs that mean "no author named".
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function absentInputs(): array {
		return array(
			'null'         => array( null ),
			'empty string' => array( '' ),
		);
	}

	/**
	 * An ID, a digit string and a login all resolve to the same person.
	 *
	 * @param int|string $requested Ways of naming the owner.
	 * @return void
	 */
	#[DataProvider( 'ownerSpellings' )]
	public function testOwnerResolvesByIdOrLogin( $requested ): void {
		$this->assertSame( self::OWNER, $this->resolve( $requested ) );
	}

	/**
	 * Ways of naming the owner.
	 *
	 * @return array<string, array{int|string}>
	 */
	public static function ownerSpellings(): array {
		return array(
			'integer ID'     => array( self::OWNER ),
			'digit string'   => array( '3' ),
			'login'          => array( 'miriamschwab' ),
			'padded login'   => array( '  miriamschwab ' ),
		);
	}

	/**
	 * A digit string is looked up as an ID, not as a login.
	 *
	 * @return void
	 */
	public function testDigitStringIsReadAsAnId(): void {
		$this->resolve( '3' );
		$this->assertSame( array( self::OWNER ), $this->lookups );
	}

	/**
	 * Naming yourself never needs the assign permission.
	 *
	 * @return void
	 */
	public function testNamingYourselfSkipsTheAssignCheck(): void {
		$result = mswpa_resolve_post_author(
			'cloudagent',
			self::AGENT,
			$this->find(),
			function (): bool {
				$this->fail( 'can_assign must not run when the author is the connected user.' );
			}
		);
		$this->assertSame( self::AGENT, $result );
	}

	/**
	 * Without permission to assign, naming someone else is refused, not ignored.
	 *
	 * @return void
	 */
	public function testAssigningOthersWithoutPermissionIsRefused(): void {
		$result = mswpa_resolve_post_author( self::OWNER, self::AGENT, $this->find(), fn(): bool => false );
		$this->assertSame( 'cannot_assign_author', $result );
	}

	/**
	 * A user who cannot write posts is refused as an author.
	 *
	 * @return void
	 */
	public function testUserWhoCannotWriteIsRefused(): void {
		$this->assertSame( 'cannot_assign_author', $this->resolve( 'subscriber' ) );
	}

	/**
	 * Unknown users and unusable input never fall back to the connected user.
	 *
	 * @param mixed $requested Input that names no real user.
	 * @return void
	 */
	#[DataProvider( 'invalidInputs' )]
	public function testInvalidAuthorIsAnError( $requested ): void {
		$this->assertSame( 'invalid_author', $this->resolve( $requested ) );
	}

	/**
	 * Input that names no real user.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function invalidInputs(): array {
		return array(
			'unknown ID'      => array( 999 ),
			'unknown login'   => array( 'nobody' ),
			'zero'            => array( 0 ),
			'negative'        => array( -3 ),
			'whitespace only' => array( '   ' ),
			'float'           => array( 3.0 ),
			'array'           => array( array( 3 ) ),
			'boolean'         => array( true ),
		);
	}
}
