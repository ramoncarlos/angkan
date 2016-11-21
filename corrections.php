<?php

	require 'angkan-config.php';

	echo "\n<html>";
	$currentRoot = 0;
	$requestedBy = '';
	$correctionText = '';
	if ( isset( $_REQUEST['requestedby'] ) && isset( $_REQUEST['correction']) &&
		isset( $_REQUEST['currentRoot'] )) {
		$currentRoot = $_REQUEST['currentRoot'];
		$requestedBy = $_REQUEST['requestedby'];
		$correctionText = $_REQUEST['correction'];

		echo "\n<br />rootId to redirect to = " . $_REQUEST['currentRoot'];
		echo "\n<br />email = " . $_REQUEST[ 'requestedby' ];
		echo "\n<br />correctionsText = <pre>" . $_REQUEST['correction'] . "</pre>";

		echo "\n<br /><br />";
		// save the data if it's valid, otherwise.... 
		$currentRoot = $_REQUEST['currentRoot'];	
		$sql = "INSERT into corrections (requestedby, requesteddate, currentRoot, correctionText ) " .
			" VALUES ( '" . addslashes( $requestedBy ) . "', now(), " . addslashes($currentRoot) .
			", '" . addslashes($correctionText) . "' );";

//		echo "\n<pre>";
//		echo "sql = " . $sql;
//		echo "\n</pre>";

		$db = mysql_connect( DBHOST, DBUSERNAME, DBPASSWORD );
		$rc = mysql_selectdb( DBNAME );
//echo "\n<br />rc at mysql_seelctdb = " . $rc;
		$res = mysql_query($sql);
		$rc = mysql_close( $db );

	} else {
		echo "<br />Could not validate the request, please fill in the email and correctionText";
	}
	$newUrl = 'gentree.php?rootId=' . $currentRoot;

	echo "\n<br /><a href='" . $newUrl . "'>back to family tree</a>";

