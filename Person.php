<?php

class Person {
	public $id;
	public static $theList = array();
	public static $theCount = 0;
	public static $dupeList = array();
	public static $dbcols = array(
		'id', 'name', 'nickName', 'realName', 'lastName', 'gender', 'dateOfBirth', 'dateOfDeath', 'cameFromUnionId',
		'ordinal', 'suppress', 'displayFlag' );
	public static $db;
	public $name;
	public $realName;
	public $nickName;
	public $lastName;
	public $gender;
	public $dateOfBirth;
	public $dateOfDeath;
	public $cameFromUnion;
	public $ordinal;
	public $suppress;
	public $unions;
	public $contactInfo;
	public $attribs;

	/**
	 * contruct a Person. increment Ids as needed
	 **/
	public function __construct ( $name ) {
		$this->id = ++self::$theCount;
		$this->name = self::normalizeName( $name );
		$this->gender = 'M';
		$this->suppress = 0;
		$this->unions = array();
		$this->cameFromUnion = 0;
		$this->attribs = array();
		self::$theList[ $this->id ] = $this;
	}

	public static function normalizeName ( $name ) {
		$name = str_replace( chr(209), "N", $name );
		$name = ucwords( strtolower($name));
		// handle DE LEON, DE LA CRUZ, DEL ROSARIO, DE LA FUENTE, DE ASIS
		if ( strstr( $name, " De " )) { $name = str_replace( " De ", " de ", $name ); }
		if ( strstr( $name, " Del " )) { $name = str_replace( " Del ", " del ", $name ); }
		if ( strstr( $name, " La " )) { $name = str_replace( " La ", " la ", $name ); }
		if ( strstr( $name, " Ii " )) { $name = str_replace( " Ii ", " II ", $name ); }
		if ( strstr( $name, " Iii " )) { $name = str_replace( " Iii ", " III ", $name ); }
		if ( strstr( $name, " Iv " )) { $name = str_replace( " Iv ", " IV ", $name ); }
		$name = trim($name);
		return $name;
	}

	/**
	 * create a person, return the Person object
	 * the person may be created out of an existing Person
	 * if the person has a (DUP009) (for instance) on their
	 * name, then the (DUPxxx) is stripped out. and we return
	 * the Person object if one already exists.
 	 **/
	public function create ( $name ) {
		// TODO: add logic to detect dupes.? or should that be done post process?
		// for the moment - let's just construct one.
		// let's see if the name contains a DUP indicator..
		if ( $dupeStr = strstr( $name, '(DUP' )) {
			// lets see if this DUP number is already in the list.
			$dupeNumber = substr( $dupeStr, 1, 6 );		// yeah it's hardcoded. dupeNumber should be like DUP012
			if ( isset( self::$dupeList[$dupeNumber] )) {
				// TODO: set the cameFromUnion once we know it.
				$dupePerson = self::$dupeList[$dupeNumber];	//this is pulled by reference
				return $dupePerson;
			} else {
				// remove the dupe indicator from the name and add this person to the dupeList
				$name = substr( $name, 0, -10 );
				$newGuy = new Person( $name );
				self::$dupeList[ $dupeNumber ] = $newGuy;
			}
		} else
			$newGuy = new Person( $name );
		return $newGuy;
	}

	/**
	 * creates a person record from a row in the DB. this is a factory method.
	 **/
	public static function createFromRow ( $row ) {
		$newPerson = new Person( $row['name'] );
		$newPerson->id = $row['personId'];
		$newPerson->realName = $row['realName'];
		$newPerson->lastName = $row['lastName'];
		$newPerson->nickName = $row['nickName'];
		$newPerson->dateOfBirth = $row['dateOfBirth'];
		$newPerson->dateOfDeath = $row['dateOfDeath'];
		$newPerson->cameFromUnion = $row['cameFromUnionId'];
		return $newPerson;
	}

	/**
	 * adds the given string in the form of attributeName: attributeValue to this person
	 * not all names are unique and so the data might be additive.
	 **/
	public function addAttribute ( $attributeStr ) {
		// so some attribute values might be an array?
		$attributeStr = ltrim( trim( $attributeStr ));
		$colonPos = strpos( $attributeStr, ':' );
		$attributeName = trim(substr( $attributeStr, 0, $colonPos ));
		$attributeValue = trim(substr( $attributeStr, $colonPos + 1 ));
		if ( $attributeName == 'E-MAIL' ) $attributeName = 'EMAIL';
		// specialcase certain attribs so they don't get into the attrib field
		if ( $attributeName == 'F' ) {
			// this is just a gender indicator. set the gender.
			$this->gender = 'F';
			return array( "GENDER", "F" );
		}
		if ( $attributeName == 'BORN' ) {
			// this is just a date of birth
			$temp = self::convertToProperDate( $attributeValue );
			if ( $temp ) {
				$this->dateOfBirth = $temp;
				return array( "BORN", $temp );
			}
		}
		if ( $attributeName == 'DIED' ) {
			$temp = self::convertToProperDate( $attributeValue );
			if ( $temp ) {
				$this->dateOfDeath = $temp;
				return array( "DIED", $temp );
			}
		}
		if ( $attributeName == 'REAL NAME' ) {
			$this->realName = self::normalizeName($attributeValue);
			return array( "REAL NAME", $this->realName );
		}
		if ( isset( $this->attribs[ $attributeName ] )) {
			if ( is_array( $this->attribs[ $attributeName ] )) {
				// append the new value to the array
				$this->attribs[ $attributeName ][] = $attributeValue;
			} else {	// convert scalar attribute to array
				$tmp = $this->attribs[ $attributeName ];
				$this->attribs[ $attributeName ] = array( $tmp, $attributeValue );
			}
		} else {	// assume it's scalar
			$this->attribs[ $attributeName ] = $attributeValue;
		}
		return array( $attributeName, $attributeValue );
	}

	/**
	 * the date str will be of the form: dd MMM YYYY - in some instances, YYYY will be omitted. in some cases dd will be omitted.
	 * in the latter case. The return of this is a string which can be interpretted as a number: YYYYMMDD and can be used for comparison
     * ?? are ignored.
	 * in the event the format is bad, then return false so this value is treated as a text attrib
	 **/
	public static function convertToProperDate ( $dateStr ) {
        $dateStr = strtoupper( $dateStr );
		$months = array( "JAN"=>"01", "FEB"=>"02", "MAR"=>"03", "APR"=>"04", "MAY"=>"05", "JUN"=>"06", 
				"JUL"=>"07", "AUG"=>"08", "SEP"=>"09", "OCT"=>"10", "NOV"=>"11", "DEC"=>"12" );
		// get the day part
		$dateStr = trim( $dateStr );
		if ( strlen( $dateStr ) != 11 ) return false;
		if ( substr( $dateStr, 2, 1 ) != ' ' ) return false;
		if ( substr( $dateStr, 6, 1 ) != ' ' ) return false;
		// now we know it's xx xxx xxxx
		$dayPart = substr( $dateStr, 0, 2 );
		if ( !is_numeric( $dayPart )) return false;
		$monthStr = substr( $dateStr, 3, 3 );
		$monthPart = $months[$monthStr];
		if ( !is_numeric( $monthPart )) return false;
		$yearPart = substr( $dateStr, 7 );
		if ( !is_numeric( $yearPart )) return false;
		$ret = $yearPart . $monthPart . $dayPart;
		return $ret;
	}

	/**
	 * 
	 **/
	public static function getById ( $id ) {	// Lack of Late Staic Binding prevents us from superclassing
		return self::$theList[ $id ];
	}

	/**
	 *
 	 **/
	public static function readById ( $id, $useFormalNames = 0 ) {
		$newGuy = false;
		self::connectToDB();
		$sql = 'SELECT * FROM person where personid = ' . $id;
		$res = self::queryDB( $sql );
		if ( $res ) {
			while ( $row = mysqli_fetch_assoc( $res )) {
				$newGuy = new Person( "dummy" );
				$newGuy->id = $id;
	  			$newGuy->name = $row['name'];
				if ( $useFormalNames ) {
					$name = $row['realName'];
					if ( trim($row['realName']) == '' ) $name=$row['nickName'];
                    // last ditch effort to set the name so it is not just blank
					if ( trim($name) == '' ) $name=$row['name'];
  					$newGuy->name = $name . ' ' . $row["lastName"];
				}
				$newGuy->cameFromUnionId = $row['cameFromUnionId'];
				$newGuy->gender = $row['gender'];
				$newGuy->dateOfBirth = $row['dateOfBirth'];
				$newGuy->dateOfDeath = $row['dateOfDeath'];
				$newGuy->nickName = $row['nickName'];
				$newGuy->realName = $row['realName'];
				$newGuy->lastName = $row['lastName'];
				$newGuy->ordinal = $row['ordinal'];
				$newGuy->suppress = $row['suppress'];
				$newGuy->displayFlag = $row['displayFlag'];
				break; 			// better NOT have more than ONE!
			}
		}
		mysqli_close( self::$db );
		return $newGuy;;
	}

	/**
	 *
 	 **/
	public static function findMarriages ( $id, $stopOnFirst = 0 ) {
		// the $id is the personId
		$marriages = array();
		self::connectToDB();
		$sql = 'SELECT * FROM marriage WHERE ( personId1 = ' . $id . ' OR personId2 = ' . $id . ' ) ';
		if ( 1 ) {	// later make this switchable so we can see suppressed marriages for people who choose to do so
			$sql .= ' AND ( suppress != 1 OR suppress IS NULL ) ';
		}
        $sql .= ' ORDER BY ORDINAL';
		$res = self::queryDB( $sql );
		if ( $res ) {
			while ( $row = mysqli_fetch_assoc( $res )) {
				$newMarriage = new Marriage( 0, 0);
  				$newMarriage->id = $row["marriageId"];
  				$newMarriage->personId1 = $row["personId1"];
  				$newMarriage->personId2 = $row["personId2"];
  				$newMarriage->suppress = $row["suppress"];
  				$newMarriage->status = $row["status"];  // map the status.. m., d., a., u.
  				$newMarriage->ordinal = $row["ordinal"];
				$marriages[] = $newMarriage;
				if ( $stopOnFirst ) break;
			}
		}
		mysqli_close( self::$db );
		if ( count($marriages) == 0 ) $marriages = false;
		return $marriages;
	}

	/**
	 *
 	 **/
	public static function searchByName ( $searchName ) {
		$results = array();
		self::connectToDB();
		$sql = "SELECT * FROM person WHERE name like '%$searchName%' or realName like '%$searchName%'";
		$res = self::queryDB( $sql );
		if ( $res ) {
			while ( $row = mysqli_fetch_assoc( $res )) {
				$newPerson = Person::createFromRow( $row );
				$results[] = $newPerson;
			}
		}
		mysqli_close( self::$db );
		if ( count($results) == 0 ) $results = false;
		return $results;
	}

	/**
	 *
	 **/
	public static function getIdsOfParents ( $marriageId ) {
		// the $id is the marriageId
		if ( $marriageId == 0 ) return array( 0, 0 );	// no marriageId...
		$parentId1 = $parentId2 = 0;
		self::connectToDB();
		$sql = 'SELECT personId1, personId2 FROM marriage WHERE marriageId = ' . $marriageId;
		$res = self::queryDB( $sql );
		if ( $res ) {
			while ( $row = mysqli_fetch_assoc( $res )) {
				$parentId1 = $row['personId1'];
				$parentId2 = $row['personId2'];
				break;
			}
		}
		return array( $parentId1, $parentId2 );
		mysqli_close( self::$db );
	}

	/**
 	 * finds children given the marriage or union ID.
	 **/
	public static function findChildren ( $id ) {
		// the $id is the marriage id
		$children = array();
		self::connectToDB();
		$sql = 'SELECT * FROM person where cameFromUnionId = ' . $id . ' ORDER BY ordinal';
		$res = self::queryDB( $sql );
		if ( $res ) {
			while ( $row = mysqli_fetch_assoc( $res )) {
				$children[] = $row;
			}
			return $children;
		}
		mysqli_close( self::$db );
		return null;
	}

	/**
	 * _save
	 **/
	public static function _save ( $personInfo ) {
	}

	/**
	 * save
	 **/
	public function save () {
		self::connectToDB();
		// check if this person has a real id
		$q = 'UPDATE person SET ';
		foreach ( self::$dbcols as $col ) {
			if ( $col == 'id' ) {
//				$q .= ' personId = ' . $this->$col . ', ';
			} else {
	 			$q .= ' ' . $col . ' = "' . $this->$col . '", ';
			}
		}
		$q = substr( $q, 0, strlen( $q ) - 2  );
		$q .= ' WHERE personId = ' . $this->id . ';';
		$res = self::queryDB( $q );
		mysqli_close( self::$db );
	}

	/**
	 *
	 **/
	public static function connectToDB () {
		// don't tell me this method needs to know the DB credentials?! sheesh.
		self::$db = mysqli_connect( DBHOST, DBUSERNAME, DBPASSWORD );
		if ( !self::$db ) {
			print "\nUnable to open DB for reading\n";
		}
		$rc = mysqli_select_db( self::$db, DBNAME );
		return self::$db;
	}
	public static function queryDB ( $sql ) {
		$res = mysqli_query( self::$db, $sql );
		if ( !$res ) {
			print "\nmysql error = " . mysqli_error() . " sql=" . $sql;
		}
		return $res;
	}
	public static function convertPersonDate ( $dateToConvert ) {
		if ( empty( $dateToConvert )) {
			return '';
		}
		$dateStr = substr( $dateToConvert, 4, 2 ) . '/' . substr( $dateToConvert, 6, 2 ) . '/' . substr( $dateToConvert, 0, 4 );
		return $dateStr;
	}

	public function getBirthday () {
		if ( !empty($this->dateOfBirth)) {
			return self::convertPersonDate( $this->dateOfBirth );
		}
		return '';
	}

}
