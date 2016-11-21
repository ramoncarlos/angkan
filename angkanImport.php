<?php

require_once( 'angkan-config.php' );
require( 'Person.php' );
require( 'Marriage.php' );
require( 'FamilyTree.php' );


$familyTree = FamilyTree::getInstance();
$familyTree->readFromFile();
//$familyTree->debug( 956 );
$familyTree->save();

print "\nTotal number of persons in the list = " . count(Person::$theList);;
// var_dump( Person::$theList );

print "\n";

