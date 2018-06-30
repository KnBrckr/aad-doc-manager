<?php

/**
 * Class TestGuid
 *
 * @package PumaStudios-DocManager
 */
use PumaStudios\DocManager\Guid;

/**
 * Test Guid class used for Document taxonomy
 *
 * @group document
 */
class TestGuid extends WP_UnitTestCase {

	/**
	 * Test generation of GUID
	 *
	 * @return string generated GUID
	 */
	function test_generate_guid() {
		$guid = Guid::generate_guid();

		$UUIDv4 = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';
		$this->assertEquals( 1, preg_match( $UUIDv4, $guid ) );

		return $guid;
	}

	/**
	 * test guid checking - negative result
	 */
	function test_is_guidv4_negative() {
		$this->assertFalse( Guid::is_guidv4( 'not-a-guid' ) );
	}

	/**
	 * test guid checking - positive result
	 *
	 * @depends test_generate_guid
	 * @var string $guid GUID V4 string
	 */
	function test_is_guidv4_positive( $guid ) {
		$this->assertTrue( Guid::is_guidv4( $guid ) );
		$this->assertTrue( Guid::is_guidv4( '378ff88b-7eef-4156-836c-85e1eefc1e65' ) );
	}

}
