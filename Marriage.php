<?php

require_once( 'angkan-config.php' );

class Marriage {
	public $id;
	public static $theList = array();
	public static $theCount = 0;
	public $personId1;
	public $personId2;
	public $suppress;
	public $date;
	public function __construct( $personId1, $personId2 ) {
		$this->id = ++self::$theCount;			// late binding precludes us from superclassing this
		$this->personId1 = $personId1;
		$this->personId2 = $personId2;
		$this->suppress = 0;
		self::$theList[ $this->id ] = $this;
	}
	public function create ( $personId1, $personId2 ) {
		$newMarriage = new Marriage( $personId1, $personId2 );
		return $newMarriage;
	}
	public static function getById( $id ) {
		return self::$theList[ $id ];
	}
	public static function getExactMarriage ( $id1, $id2 ) {
		// TODO: improve this. do a sequential search through the list...
		foreach ( self::$theList as $marriage ) {
			if ( $marriage->personId1 == $id1 ) {
				if ( $marriage->personId2 == $id2 ) return $marriage;
			} if ( $marriage->personId2 == $id1 ) {
				if ( $marriage->personId1 == $id2 ) return $marriage;
			}
		}
		return null;
	}
}
