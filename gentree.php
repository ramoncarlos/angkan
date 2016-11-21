<?php

require_once'angkan-config.php';
require_once 'Person.php';
require_once 'Marriage.php';
require_once 'FamilyTree.php';

try {
//require_once '../../lib/fbsdk/src/facebook.php';
} catch (Exception $e) {
   error_log(">>>>RAMON error requiring facebook");
}

if (!session_id()) {
   session_start();
}

$userId = null;
$fb_app_id = YOUR_APP_ID;
$fb_app_secret = YOUR_APP_SECRET;
try {
//  $facebook = new Facebook(array(
//    'appId'  => YOUR_APP_ID,
//    'secret' => $fb_app_secret,
//  ));
//  $accessToken = $facebook->getAccessToken();

//   if ($accessToken) {
//       $userId = $facebook->getUser();
//   }
//print "userId=" . $userId;
} catch (Exception $e) {
   print "<br>Caught Exception: " . $e->getMessage();
}

$userProfile = array();
$user_profile_html = '';
if ($userId) {
/*
   try {
      $userProfile = $facebook->api('/me','GET');
//      $user_profile_html = print_r($userProfile,1);
   } catch (FacebookApiException $e) {
      error_log(">>>>RAMON - facebook Exception: " . $e->getType() . " msg:" . $e->getMessage());
   } catch (Exception $e) {
      error_log(">>>>RAMON - other Exception: " . $e->getType() . " msg:" . $e->getMessage());
   }
*/
}

// Check the cookie for this user
//print "<br />DEBUG: at 1";
print "";

if (isset($_COOKIE['cu'])) {
//   $userId = User::getUserIdFromGuid($_COOKIE['cu']);
} else {
   // create a new user for this person. Might be dupe user if he uses many browsers
//   $userGuid = User::CreateGuid();
//   setcookie('cu',$useerGuid, time()+(3600*24*700), "/", "lichaucoclan.com", false, true);
//   $userId = User::getUserIdFromGuid($userGuid);
}
if (isset($_SESSION['userId']) && ($_SESSION['userId'] != $userId)) {
   // oooo switched user?
}
//$_SESSION['userId'] = $userId;
$this_page_url = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
//echo "<br>$this_page_url";

//echo "<pre>REQUEST";
//var_dump( $_REQUEST );
//echo "\nSESSION:";
//var_dump( $_SESSION );
//echo "\n</pre>";

if ( !isset( $_REQUEST['rootId'] )) {
	$rootId = 1;	// defaulting to top
	if ( isset( $argc ) && $argc > 1 ) {
		$rootId = $argv[1];
	}
} else {
	$rootId = $_REQUEST['rootId'];
}
if ( !is_numeric( $rootId ) || ( $rootId == 0 )) {
//	echo "<br />Missing rootId param. TODO: Ramon make error pages friendlier. defaulting to top. rootId=1";
	$rootId = 1;
}

insert_visit();

// read any options that are turned on...
foreach ( $_REQUEST as $optName => $optValue ) {
	if ( substr( $optName, 0, 4 ) == 'opt_' ) {
		$realOptName = substr( $optName, 4 );
		$_SESSION[ $realOptName ] = $optValue;
	}
}

$opt_ShowFormalNames = (isset($_SESSION['ShowFormalNames']) && ($_SESSION['ShowFormalNames'] == '0')) ? 0 : 1;
$opt_AllowSameSexMarriages = (isset($_SESSION['AllowSameSexMarriages']) && ($_SESSION['AllowSameSexMarriages'] == '1')) ? 1 : 0;
$opt_Debug = (isset($_SESSION['debug']) && ($_SESSION['debug'] == 1)) ? 1 : 0;
$collapseToSpouseId = (isset($_GET['collapseTo']) && ($_GET['collapseTo'] > '1')) ? $_GET['collapseTo'] : 0;

$suppressionList = array();

$familyTree = FamilyTree::getInstance();
$options = array(
        'showFormalNames' => $opt_ShowFormalNames,
        'allowSameSexMarriages' => $opt_AllowSameSexMarriages,
        'collapseTo' => $collapseToSpouseId,
        'debug' => $opt_Debug,
);
FamilyTree::$originalRootId = $rootId;   // TODO: make this into a setter function later
$mainPerson = Person::readById($rootId);
$mainTitle = "The " . $mainPerson->lastName . " Family Tree";
$tree = $familyTree->genTree( $rootId, 3, $options );
if ( !$tree ) {
	echo "\n<br> ERROR: $rootId generated a NULL tree";
}
//var_dump( $tree );
// $newTree = $familyTree->enforceSuppression( $tree, $suppressionList );
$tree['isCollapsed'] = $collapseToSpouseId;
$htmlMeat = $familyTree->tree2Html( $tree );

if ( is_array( $tree['marriages'] ) && count( $tree['marriages'] ) > 0 ) {
	$level1ChildCount = count( $tree['marriages'][0]['children'] );
} else {
	$level1ChildCount = 1;
}
if ( $level1ChildCount < 3 ) $level1ChildCount = 3;

// override the text font size for l1 children if there are too many children
$overrideL1Font = '';
if ( $level1ChildCount > 8 ) {
	$overrideL1Font = "#famtreeMain li a {
		font-size: 12px;
		font-weight: normal;
	}";
}
$level1SpouseCount = count( $tree['marriages'] );
if ( $level1SpouseCount > 1 ) { 
	if ( $level1SpouseCount < 3 ) $level1SpouseCount = 3;
	$calculatedWidth = 100 / $level1SpouseCount; 
} else {
	$calculatedWidth = 100 / $level1ChildCount;
}

$lichauco_groups = array(
'214260075315752' => 'Filipino Clans',
'102092156527183' => 'Lichauco Clan',
'86973513566' => 'The Lichauco Family',
'286135028137433' => 'LICHAUCO'
);

$is_lichauco = 0;

$fb_login_menu_item = "<li><div class='fb-login-button' scope='email,user_groups'>Login with Facebook</div></li>";
$welcome_msg = '';
$announcements = '';
//print "<br />DEBUG: at 2";
print "  ";
if ($userId) {
   $userInfo = $facebook->api('/' + $userId);
   $fb_login_menu_item = "";
   $welcome_msg = "Welcome back, " .$userInfo['name'];
   $groups = $facebook->api('/me/groups','GET');
//   print "<pre>" . print_r($groups, 1) . "</pre>";
   foreach ($groups['data'] as $group) {
      // fields are: version, name, id, unread, bookmark_order
      if (isset($lichauco_groups[$group['id']])) {
//print "<br />verified Lichauco CLan relative - group=" . $group['name'];
         $is_lichauco = 1;
         break;
      }
   }
}
//print "<br />DEBUG: at 3";
print "  ";

$subTitle = "Preliminary Tree &mdash; Version 1.02";
$helpText = "Click on a box to zoom in to that family. Click the top most box to zoom out and move up the tree.";
$menu = "<div id='utilityNav' name='utilityNav' width='100%'>" .
	"<li><a href='javascript:openHelp()'>Help</a></li>" .
	"<li><a href='search.php'>Search</a></li>" .
	"<li><a href='wp'>Blog</a></li>" .
	"<li><a href='javascript:openOptions()'>Options</a></li>" .
	"<li><a href='javascript:corrections()'>Feedback</a></li>" .
    $fb_login_menu_item;
	"</div>";

$fb_like_html = "<iframe src='http://www.facebook.com/plugins/like.php?href=$this_page_url'
        scrolling='no' frameborder='0'
        style='border:none; width:350px; height:60px'></iframe>";

// if ShowFormalNames option is ON, then show only the option to turn it off.
if (isset($_SESSION['ShowFormalNames']) && ($_SESSION['ShowFormalNames'] == '1')) {
	// change the option name to ShowNickNames
	$FNLabel = "Show Only Nick Names";
	$FNOptValue = 0;
} else {
	$FNLabel = "Show Only Formal Names";
	$FNOptValue = 1;
}
$optionsText = "
	<form method='POST' action=''>
		<br /><input type='checkbox' name='opt_ShowFormalNames' value='" . $FNOptValue . "' " .
		"/>&nbsp;" . $FNLabel . 
		"<br /> <input type='checkbox' name='opt_OverrideSuppression' value='1' />&nbsp;Ignore Suppression / may contain invalid data
		<br /><br /><input type='submit' name='submit'>
	</form>
";
$correctionsText = "
	<form method='POST' action='corrections.php'>
		Your Email: &nbsp;<input type='text' size='40' name='requestedby'/><br />
		<input type='hidden' name='currentRoot' value='" . $rootId . "'>
		Please enter your feedback:<br />
		<textarea name='correction' rows='7' cols='60' >
		</textarea>
		<input type='submit' name='submit' />
	</form>
";

$htmlBegin = <<<HTMLBEGIN
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> 
 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"> 
<head> 
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/> 
	<title>$mainTitle</title> 
	<link rel="stylesheet" type="text/css" media="screen, print" href="./famtree.css?2" /> 
    <style>
       .hover : border 1px dotted red;
    </style>
	<script src="js/jquery.js"></script>
	<script>
		function closeHelp () {
			var helpBox = document.getElementById( 'famtreeHelp' );
			helpBox.style.display = 'none';
		}
		function openHelp () {
			var div = document.getElementById( 'famtreeOptions' );
			div.style.display = 'none';
			div = document.getElementById( 'famtreeCorrections' );
			div.style.display = 'none';
			var helpBox = document.getElementById( 'famtreeHelp' );
			helpBox.style.display = 'block';
		}
		function openOptions () {
			var div = document.getElementById( 'famtreeHelp' );
			div.style.display = 'none';
			div = document.getElementById( 'famtreeCorrections' );
			div.style.display = 'none';
			div = document.getElementById( 'famtreeOptions' );
			div.style.display = 'block';
		}
		function corrections () {
			var div = document.getElementById( 'famtreeHelp' );
			div.style.display = 'none';
			div = document.getElementById( 'famtreeOptions' );
			div.style.display = 'none';
			div = document.getElementById( 'famtreeCorrections' );
			div.style.display = 'block';
		}
        $('#famtreeMain').hover(  function () {
               $(this).addClass("hover");
            },
            function () {
               $(this).removeClass("hover");
            }
        );
	</script>
	<style>
		/* override the base li style with the actual number of columns */
#famtreeMain li {
	width:$calculatedWidth%;
}
		$overrideL1Font
	</style>
	<!--[if lte IE 7]> <link rel="stylesheet" type="text/css" media="screen,print" href="famtree-ie.css" /> <![endif]--> 
	
</head> 
 
<body> 
     <!--  $userId 
        $user_profile_html
     -->
      <script>
        window.fbAsyncInit = function() {
          FB.init({
            appId      : '$fb_app_id',
            status     : true, 
            cookie     : true,
            xfbml      : true,
            oauth      : true,
          });

          FB.Event.subscribe('auth.login', function(response) {
            window.location.reload();
          });
        };
        (function(d){
           var js, id = 'facebook-jssdk'; if (d.getElementById(id)) {return;}
           js = d.createElement('script'); js.id = id; js.async = true;
           js.src = "//connect.facebook.net/en_US/all.js";
           d.getElementsByTagName('head')[0].appendChild(js);
         }(document));
      </script>
<div id="fb-root"></div>

$welcome_msg
<table width='100%'>
<tr><td align='left' width='50%' nowrap='nowrap' valign='top'>$menu</td>
<td align='right' valign='top'>$fb_like_html</td>
</tr>
</table>

<br clear="all"/>
<div id="famtreeHelp" class="famtreeHelp" style="display:none">
<a href="javascript:closeHelp();">close</a><br /><br />
$helpText
</div>

<div id="famtreeOptions" class="famtreeOptions" style="display:none">
$optionsText
</div>

<div id="famtreeCorrections" class="famtreeCorrections" style="display:none">
$correctionsText
</div>

<div class="famtree"> 
		
	<center>
	<h1>$mainTitle</h1> 
	<h2>$subTitle</h2> 
	<br />
	<!-- TODO: put nav here -->
	<br />
<!--
<br /> level1ChildCount=$level1ChildCount
<br /> level1SpouseCount=$level1SpouseCount
<br /> calculatedWidth = $calculatedWidth
<br /><br /><br />
-->

HTMLBEGIN;

$htmlEnd = <<<HTMLEND

</body>
</html>
HTMLEND;

$trackingHtml = '';
if ( TRACKINGCODE != 'xxxx' ) {
	$trackingHtml = TRACKINGCODE;
}

$completeHtml = $htmlBegin . $htmlMeat . "</div>\n". $trackingHtml . $htmlEnd;
print $completeHtml;

//print "\nTotal number of persons in the list = " . count(Person::$theList);;
// var_dump( Person::$theList );

print "\n";

function insert_visit() {
   global $rootId;
   global $userId;  // facebook userid
   global $userProfile;  // facebook user profile

   $safeRootId = intval($rootId);
   $fbuserid = intval($userId);
   $fbusername = $fbuseremail = '';
   if (isset($userProfile['name'])) {
      $fbusername = $userProfile['name'];
   }
   if (isset($userProfile['email'])) {
      $fbuseremail = $userProfile['email'];
   }
   if ($_SERVER['REMOTE_ADDR'] == '24.4.196.27') { return; }
   $ua = $_SERVER['HTTP_USER_AGENT'];
   if (is_bot($ua)) { return; }
   $sql = 'insert into visit values (null,"' . $_SERVER['REMOTE_ADDR'] . '",now(),' . $safeRootId . ',"' . $fbuserid . '","' . $fbusername . '","' . $fbuseremail . '")';
//print "sql=$sql";
   Person::connectToDB();
   Person::queryDB($sql);
}

//returns 1 if the user agent is a bot
function is_bot($user_agent) {
  //if no user agent is supplied then assume it's a bot
  if($user_agent == "")
    return 1;

  //array of bot strings to check for
  $bot_strings = Array(  "google",     "bot",
            "yahoo",     "spider",
            "archiver",   "curl",
            "python",     "nambu",
            "twitt",     "perl",
            "sphere",     "PEAR",
            "java",     "wordpress",
            "radian",     "crawl",
            "yandex",     "eventbox",
            "monitor",   "mechanize",
            "facebookexternal"
          );
  foreach($bot_strings as $bot) {
    if(strpos($user_agent,$bot) !== false) { return 1; }
  }
  
  return 0;
}
