<?php

include './env.php';
require_once( __DIR__ . '/vendor/autoload.php' );

//set_time_limit(0);
setlocale(LC_ALL, 'fr_FR.UTF-8');
ini_set ("user_agent", "ZiziBot http://fr.wikipedia.org/wiki/user:ZiziBot");
error_reporting(E_ALL ^ E_NOTICE);
ini_set('xdebug.var_display_max_depth', '10');
ini_set('xdebug.var_display_max_children', '256');
ini_set('xdebug.var_display_max_data', '1024');

// Log in to a wiki
$api = new \Mediawiki\Api\MediawikiApi( getenv('API_URL') );
try{
    $api->login( new \Mediawiki\Api\ApiUser( getenv('API_USERNAME'), getenv('API_PASSWORD') ) );
}catch (\Throwable $e) {
    die('Exception '.$e);
}
$services = new \Mediawiki\Api\MediawikiFactory( $api );

$page = $services->newPageGetter()->getFromTitle( 'Board_de_carving' );
var_dump($page->getRevisions()->getLatest()->getContent()->getData());

//// Edit a page
//$content = new \Mediawiki\DataModel\Content( 'New Text' );
//$revision = new \Mediawiki\DataModel\Revision( $content, $page->getPageIdentifier() );
//$services->newRevisionSaver()->save( $revision );
//
//// Move a page
//$services->newPageMover()->move(
//$services->newPageGetter()->getFromTitle( 'FooBar' ),
//new Title( 'FooBar' )
//);
//
//// Delete a page
//$services->newPageDeleter()->delete(
//$services->newPageGetter()->getFromTitle( 'DeleteMe!' ),
//array( 'reason' => 'Reason for Deletion' )
//);
//
//// Create a new page
//$newContent = new \Mediawiki\DataModel\Content( 'Hello World' );
//$title = new \Mediawiki\DataModel\Title( 'New Page' );
//$identifier = new \Mediawiki\DataModel\PageIdentifier( $title );
//$revision = new \Mediawiki\DataModel\Revision( $newContent, $identifier );
//$services->newRevisionSaver()->save( $revision );

// List all pages in a category
$pages = $services->newPageListGetter()->getPageListFromCategoryName( 'Category:Type de skateboards' )->toArray();
foreach($pages as $page) {
    var_dump($page->getPageIdentifier()->getTitle()->getText());
}

