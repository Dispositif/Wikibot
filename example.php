<?php

// Load all the stuff
require_once( __DIR__ . '/vendor/autoload.php' );

// Log in to a wiki
$api = new \Mediawiki\Api\MediawikiApi( 'http://localhost/w/api.php' );
$api->login( new \Mediawiki\Api\ApiUser( 'username', 'password' ) );
$services = new \Mediawiki\Api\MediawikiFactory( $api );

// Get a page
$page = $services->newPageGetter()->getFromTitle( 'Foo' );

// Edit a page
$content = new \Mediawiki\DataModel\Content( 'New Text' );
$revision = new \Mediawiki\DataModel\Revision( $content, $page->getPageIdentifier() );
$services->newRevisionSaver()->save( $revision );

// Move a page
$services->newPageMover()->move(
$services->newPageGetter()->getFromTitle( 'FooBar' ),
new Title( 'FooBar' )
);

// Delete a page
$services->newPageDeleter()->delete(
$services->newPageGetter()->getFromTitle( 'DeleteMe!' ),
array( 'reason' => 'Reason for Deletion' )
);

// Create a new page
$newContent = new \Mediawiki\DataModel\Content( 'Hello World' );
$title = new \Mediawiki\DataModel\Title( 'New Page' );
$identifier = new \Mediawiki\DataModel\PageIdentifier( $title );
$revision = new \Mediawiki\DataModel\Revision( $newContent, $identifier );
$services->newRevisionSaver()->save( $revision );

// List all pages in a category
$pages = $services->newPageListGetter()->getPageListFromCategoryName( 'Category:Cat name' );
