<?php

require 'angkan-config.php';
require 'Person.php';

date_default_timezone_set('America/Los_Angeles');

if ( !isset( $_REQUEST['q'] ) || empty($_REQUEST['q']) ) {
	$searchStr = "";
	$personList = array();
} else {
	$searchStr = $_REQUEST['q'];
	if ( is_array( $searchStr )) { $searchStr = reset( $searchStr ); }
	$personList = Person::searchByName( $searchStr );
}

// we may want to reset the $_REQUEST for security purposes at this point.

$personCount = 0;
if ( is_array( $personList )) {
	$personCount = count( $personList );
}

//include 'angkanPageHeader.php';

#$styleStr = 'body { background: #909090 url("images/search_bg.jpg") repeat-y;}';
$styleStr = 'body { background: #D0D0D0;}';
$styleStr .= "\n" . '#nav a { font-size:14; font-face: helvetica; font-weight: bold; }';

$styleStr .= "\n" . '#searchblock { padding-left: 35px; }';

echo "<html>";
//echo "\n<h1>Lichauco Search Page</h1>\n";
echo "\n<head>";
echo "\n<style>";
echo $styleStr;
echo "\n</style>";
echo "\n<script src='http://cdnjs.cloudflare.com/ajax/libs/jquery/1.2.6/jquery.min.js'></script>";
//echo "\n<script src='jquery.tablesorter.js'</script>";
echo "\n<script src='http://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.17.8/js/jquery.tablesorter.min.js'></script>";
echo "\n<script src='http://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.17.8/js/jquery.tablesorter.widgets.min.js'></script>";

echo "\n" . "<script type='text/javascript'>
    $(document).ready( function(){
            $('#myTable').tablesorter( { sortList : [[1,0], [2,0]], widgets: ['zerbra','columns']} );
    });" .
"\n </script>";;

echo "\n</head>";
echo "\n<body>";
echo "\n<IMG SRC='images/search_hdr.jpg' width='500' border='0'/>";

// grab the rootId from the referrer if there is one..

if ( isset( $_REQUEST['backRootId'] )) {
	$backRootId = $_REQUEST['backRootId'];
} else if ( isset( $_SERVER['HTTP_REFERER'] )) {
	$rootIdPos = strpos( $_SERVER['HTTP_REFERER'], 'rootId=' ) + 7;
	$backRootId = intVal( substr( $_SERVER['HTTP_REFERER'], $rootIdPos ));
} else {
	$backRootId = 0;
}

// retarded nav...
echo "\n<div id='searchblock'>";
echo "\n<br /><h2>Go to: <a href='wp/'>blog</a> &nbsp;&nbsp; <a href='gentree.php?rootId=" . $backRootId . "'>Back to family tree</a></h2>";
//echo "\n<br /><br />";

echo "\n<form action='search.php' METHOD='GET'>\n";
echo "\n   <input type='text' name='q' value='" . $searchStr . "' />";
echo "\n   <input type='submit' name='Search' value='Search' />";
echo "\n   <input type='hidden' name='backRootId' value='" . $backRootId . "' />";
echo "\n</form>\n";

echo "\n<br /><br />Search results for a name like " . $searchStr;
echo "\n<br />Number of results = " . $personCount;
echo "\n<br />";

if ( $personCount > 0 ) {

	echo "\n<table id='myTable' class='tablesorter'>";
	echo "\n<thead><tr><th>id</th><th>name</th><th>Formal Name</th><th>DateOfBirth</th></tr></thead>";
    echo "\n<tbody>";
	foreach ( $personList as $person ) {
		echo "\n <tr>";
        echo "\n   <td>" . $person->id . "</td>";
		echo "\n   <td><a href='gentree.php?rootId=" . $person->id . "'>" . $person->name . "</a></td>";
		$name = $person->realName;
		if ( empty( $name )) $name = $person->nickName;
		$dateOfBirth = 'n/a';
		if ( $person->dateOfBirth ) {
			$year = substr( $person->dateOfBirth, 0, 4 );
			$month = substr( $person->dateOfBirth, 4, 2 );
			$day = substr( $person->dateOfBirth, -2 );
			$dateOfBirth = date( "j F Y", mktime(0, 0, 0, $month, $day, $year));
		}
		echo "<td>$name</td><td>" . $dateOfBirth . "</td>";
		echo "\n </tr>";
	}
    echo "\n</tbody>";
	echo "\n</table>";

}

echo "\n</div>";
$trackingHtml = '';
if ( TRACKINGCODE != 'xxxx' ) {
	$trackingHtml = TRACKINGCODE;
}
echo "\n" . $trackingHtml;
echo "\n</body>";
echo "\n</html>";

