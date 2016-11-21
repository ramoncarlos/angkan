<?php

	require_once( 'angkan-config.php' );

	$db = mysql_connect( DBHOST, DBUSERNAME, DBPASSWORD );
	if ( !$db ) {
		die ('<br>Unable to connect to the DB' );
	}
	$rc = mysql_selectdb( DBNAME );

	$q = 'SELECT * from corrections where requesteddate > date_sub(now(), interval 7 day)';
	$res = mysql_query( $q );
	if ( !$res ) {
		$rc = mysql_close( $db );
		die ('<br> mysql_query barfed with errors. query = ' . $q . ' error=' . mysql_error() );
	}
	
	echo "\n<br />Corrections<br />";
	echo "\n<table>";
	while ( $row = mysql_fetch_assoc( $res )) {
		echo "\n<tr>";
		echo "\n  <td valign='top'>" . $row['id'] . '</td>';
		echo "\n  <td valign='top'>" . $row['requestedby'] . '</td>';
		echo "\n  <td valign='top'>" . ' root=' . $row['currentRoot'] . '</td>';
		echo "\n  <td valign='top'>" . ' ' . $row['requesteddate'] . '</td>';
		echo "\n  <td valign='top'><pre>" . $row['correctionText'] . '</pre></td>';
		echo "\n</tr>";
	}
	echo "\n</table>";

    $q = 'select * from visit where visit_date > date_sub(now(), interval 48 hour)';
	$res = mysql_query( $q );
	if ( !$res ) {
		$rc = mysql_close( $db );
		die ('<br> mysql_query barfed with errors. query = ' . $q . ' error=' . mysql_error() );
	}
	
	echo "\n<br />Visits<br />";
	echo "\n<table>";
	while ( $row = mysql_fetch_assoc( $res )) {
		echo "\n<tr>";
		echo "\n  <td valign='top'>" . $row['visitId'] . '</td>';
		echo "\n  <td valign='top'>" . $row['ip'] . '</td>';
		echo "\n  <td valign='top'>" . ' root=' . $row['rootId'] . '</td>';
		echo "\n  <td valign='top'>" . ' ' . $row['visit_date'] . '</td>';
		echo "\n  <td valign='top'>" . ' ' . $row['fbuserid'] . '</td>';
		echo "\n</tr>";
	}
	echo "\n</table>";

   $sql = 'select * from visit where visitId > 60';

	$rc = mysql_close( $db );


