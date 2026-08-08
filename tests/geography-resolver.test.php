<?php
/**
 * Standalone compiled geography repository and resolver tests.
 */

define( 'THAILAND_PLATFORM_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );

require THAILAND_PLATFORM_DIR . 'src/Geography/Repository.php';
require THAILAND_PLATFORM_DIR . 'src/Geography/Resolver.php';

use Thailand_Platform\Geography\Repository;
use Thailand_Platform\Geography\Resolver;

function geography_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$registry = Repository::all();
geography_test_assert( 'geo:th:country' === $registry['country_id'], 'Country ID mismatch.' );
geography_test_assert( 85 === count( $registry['entities_by_id'] ), 'Compiled entity count mismatch.' );
geography_test_assert( 77 === count( Repository::children( 'geo:th:country' ) ), 'Country province children mismatch.' );
geography_test_assert(
	14 === count( Repository::members( 'nso-seven-region-2025', 'geo:th:region:nso-seven-region-2025:southern' ) ),
	'Southern region membership mismatch.'
);

$canonical = Resolver::resolve( 'geo:th:province:83' );
geography_test_assert( Resolver::STATUS_RESOLVED === $canonical['status'], 'Canonical ID did not resolve.' );
geography_test_assert( 'Phuket' === $canonical['entity']['names']['en'], 'Canonical entity mismatch.' );

$code = Resolver::resolve( '83', array( 'type' => 'province' ) );
geography_test_assert( Resolver::STATUS_RESOLVED === $code['status'], 'Official province code did not resolve.' );
geography_test_assert( 'geo:th:province:83' === $code['entity']['id'], 'Official code resolved to the wrong entity.' );

$slug = Resolver::resolve( 'phuket', array( 'type' => 'province' ) );
geography_test_assert( Resolver::STATUS_RESOLVED === $slug['status'], 'Province slug did not resolve.' );

$english_alias = Resolver::resolve( 'Phuket Island', array( 'locale' => 'en' ) );
geography_test_assert( Resolver::STATUS_RESOLVED === $english_alias['status'], 'English alias did not resolve.' );
geography_test_assert( 'geo:th:province:83' === $english_alias['entity']['id'], 'English alias resolved incorrectly.' );

$hebrew_name = Resolver::resolve( 'פוקט', array( 'locale' => 'he' ) );
geography_test_assert( Resolver::STATUS_RESOLVED === $hebrew_name['status'], 'Hebrew canonical name did not resolve.' );

$thai_name = Resolver::resolve( 'ภูเก็ต', array( 'locale' => 'th' ) );
geography_test_assert( Resolver::STATUS_RESOLVED === $thai_name['status'], 'Thai canonical name did not resolve.' );

$geresh_alias = Resolver::resolve( 'צ׳יאנג מאי', array( 'locale' => 'he' ) );
geography_test_assert( Resolver::STATUS_RESOLVED === $geresh_alias['status'], 'Hebrew punctuation normalization failed.' );
geography_test_assert( 'geo:th:province:50' === $geresh_alias['entity']['id'], 'Hebrew alias resolved incorrectly.' );

$ambiguous = Resolver::resolve( 'Nakhon', array( 'locale' => 'en', 'type' => 'province' ) );
geography_test_assert( Resolver::STATUS_AMBIGUOUS === $ambiguous['status'], 'Ambiguous alias was silently resolved.' );
geography_test_assert(
	array( 'geo:th:province:30', 'geo:th:province:80' ) === $ambiguous['candidates'],
	'Ambiguous alias candidates mismatch.'
);

$retired = Resolver::resolve( 'Sra Kaew', array( 'locale' => 'en', 'type' => 'province' ) );
geography_test_assert( Resolver::STATUS_RETIRED === $retired['status'], 'Retired alias status was lost.' );
geography_test_assert( array( 'geo:th:province:27' ) === $retired['candidates'], 'Retired alias target mismatch.' );

$unknown = Resolver::resolve( 'not-a-thailand-entity' );
geography_test_assert( Resolver::STATUS_NOT_FOUND === $unknown['status'], 'Unknown identity did not fail closed.' );
geography_test_assert( null === $unknown['entity'], 'Unknown identity returned an entity.' );

$phuket_relations = Repository::relations( 'geo:th:province:83' );
geography_test_assert( 2 === count( $phuket_relations ), 'Province relation count mismatch.' );
geography_test_assert(
	array() === Repository::children( 'geo:th:province:83', 'part_of' ),
	'Unknown child relation should be empty.'
);

fwrite( STDOUT, "PASS: geography resolver contract\n" );
