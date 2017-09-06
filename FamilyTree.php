<?php

class FamilyTree {

	// a stack of current person nodes...
	// public $currentPerson;
	public static $personStack = array();
	public $stackIndex = 0;
	public $nPersons = 0;
	public $nUnions = 0;
	public static $instance = null;
	public static $gentreeUrl = '"gentree.php?rootId=';
	public static $gentreeUrlXXX = "gentree.php?rootId=xxx";
	public static $agentreeUrlXXX = '<a href="gentree.php?rootId=xxx" title="Born: yyy">';
    public static $collapseToSpouseId = 0;   // if present, other spouses are not considered in the generated tree
                                             // and the tree is generated as if the root had only one spouse
    public static $originalRootId = 0;         // set by the caller so we don't collapse descendants' marraiges
    public static $options;
    public static $displayedMarriageId = 0;
    public static $displayedSpouseId = '';

    public function __construct() {
       if (defined('GENTREEPREFIX')) {
          self::$gentreeUrl = '"' . GENTREEPREFIX . '?rootId=';
          self::$gentreeUrlXXX = '"' . GENTREEPREFIX . '?rootId=xxx';
      	  self::$agentreeUrlXXX = '<a href="' . GENTREEPREFIX . '?rootId=xxx" title="Born: yyy">';
       }
    
    }

	/**
	 * singleton
	 **/
	public static function getInstance () {
		if ( !self::$instance ) self::$instance = new FamilyTree();
		return self::$instance;
	}

	/**
	 *
	 **/
	public function readFromFile () {
		$marriageStack = array();
		$file = fopen( 'FAMTREE.TXT', 'r' );
		while ( $line = fgets( $file )) {
//				print $line . "\n";
			if ( strstr( $line, ':' )) {
				// it's an attrib - parse it. add it.
				list( $attrName, $attrValue ) = $currentPerson->addAttribute( $line );
				// SOME attributes pertain to the marriage, not the individual. detect this - apply as needed.
				if ( $attrName == 'TREEHINT' ) {
					if ( $attrValue == 'SUPPRESS_INDIVIDUAL' ) {
						$currentPerson->suppress = 1;
					} else if ( $attrValue == 'SUPPRESS_UNION' ) {
						$currentMarriage->suppress = 1;
					} else {
						echo "\n***ERROR: Unknown TREEHINT: " . $attrValue;
					}
				}
				continue;
			}
			if ( strstr( $line, '>' )) {

				// print $line; // it's a person with lichauco blood
				$gen = strpos( $line, '>' );
				$ordinal = intval(ltrim(substr( $line, 0, $gen )));
				$pLine = substr( $line, $gen + 1 );
				$currentPerson = Person::create( $pLine );
				$currentPerson->isSpouseRecord = 0;
				$currentPerson->ordinal = $ordinal;
				$gen2 = (( $gen - 2 ) / 6 ) + 1;
//				print $gen . " " . $gen2 . " " . $currentPerson->id . " " . $currentPerson->name;
				// now set the union from which this guy came...
				if ( $gen2 > 1 ) {			// we're >1st gen. let's set the parent union
					$currentPerson->cameFromUnion = $marriageStack[ $gen2 - 1 ];
				}
				$this->personStack[ $gen2 ] = $currentPerson;

			} else {

				// it's a person who married into the lichauco clan
				// create the person. then create the union....	
				$gen = strpos( $line, '^' );
				$ordinal = ord( substr( $line, $gen-1, 1 ) ) - ord( 'a' ) + 1;
				$pLine = substr( $line, $gen + 1 );
				$currentPerson = $person2 = Person::Create( $pLine );
				$currentPerson->isSpouseRecord = 1;
				$currentPerson->ordinal = $ordinal;
				$gen2 = (( $gen - 5 ) / 6) + 1;
//				print $gen .  " " . $gen2 . " " . $person2->id . " " . $person2->name;

				$person1 = $this->personStack[ $gen2 ];
				// check if person2 is a dupe
				if ( $person2->id != Person::$theCount ) {
					// do any special processing for dupe here...
					// TODO: find the marriage featuring person2->id and person1->id
					$currentMarriage = Marriage::getExactMarriage( $person1->id, $person2->id );
					$person1->unions[] = $currentMarriage;
				} else {
//print "\nAdding Marrage between " . $person1->name . " and " . $person2->name . " gen2 = "  . $gen2;
					$currentMarriage = Marriage::create( $person1->id, $person2->id );
					$marriageStack[ $gen2 ] = $currentMarriage;
				}

				// add the union to the lichauco blood person...
				$person1->unions[] = $currentMarriage;

			}
		}
		fclose( $file );
	}

	/**
	 *
	 **/
	public function debug( $personId ) {
		$person = Person::getById( $personId );
		print "\nName: (" . $person->id . "): " . $person->name;
		if ( count( $person->unions )) {
			$spouseId = $person->unions[0]->personId2;
			$spouse = Person::getById( $person->unions[0]->personId2 );
			print "\nSpouse: ($spouseId): " . $spouse->name;
		}
		// print parents
		$parent1 = Person::getById( $person->cameFromUnion->personId1 );
		$parent2 = Person::getById( $person->cameFromUnion->personId2 );
		print "\n" . $person->name . " was a child of " . $parent1->name . " and " . $parent2->name;
	}

	public function save () {
		$db = mysqli_connect( DBHOST, DBUSERNAME, DBPASSWORD );
		if ( !$db ) {
			print "\nUnable to save, cannot connect to the DB\n";
		}
		$rc = mysqli_selectdb( DBNAME );
		for ( $i = 1; $i < Person::$theCount; $i++ ) {
			$person = Person::getById( $i );
			$cameFromUnionId = 0;
			if ( $person->cameFromUnion ) 
				$cameFromUnionId = $person->cameFromUnion->id;
			$sql = "INSERT INTO person (personId,name,realName,gender,dateOfBirth,dateOfDeath,cameFromUnionId,ordinal,suppress) VALUES (";
			$sql .= $person->id . ", '" . $person->name . "', '" . $person->realName . "', '" . $person->gender . "', '" . 
					$person->dateOfBirth . "', '" . $person->dateOfDeath . "', " . $cameFromUnionId . ", " . $person->ordinal . 
					", " . $person->suppress . " ) ";
			$sql .= " ON DUPLICATE KEY UPDATE name='" . $person->name . "', cameFromUnionId= " .  $cameFromUnionId;
			$rc = mysqli_query( $sql );
			if ( !$rc ) print "\n>>> error = " . mysqli_error() . " sql= " . $sql;

			// save attributes
			foreach ( $person->attribs as $attrName => $attrValue ) {
				if ( is_array( $attrValue )) { 
					$tmp = join( ",", $attrValue ); 
					$attrValue = $tmp;
				}
				$sql = "INSERT INTO personattr (personId, attrName, attrValue ) VALUES (";
				$sql .= $person->id . ", '" . $attrName . "', '" . addslashes( $attrValue ) . "' );";
				$rc = mysqli_query( $sql );
				if ( !$rc ) print "\n>>> error = " . mysqli_error() . " sql= " . $sql;
			}

		}

		// save marriages
		for ( $i = 1; $i < Marriage::$theCount; $i++ ) {
			$marriage = Marriage::getById($i);
			$sql = "INSERT INTO marriage (marriageId, personId1, personId2, suppress) VALUES ( ";
			$sql .= $marriage->id . ', ' . $marriage->personId1 . ', ' . $marriage->personId2 . 
				', ' . $marriage->suppress . ') ';
			$sql .= " ON DUPLICATE KEY UPDATE personId1 = " . $marriage->personId1 . ", personId2 = " .
						$marriage->personId2 . ", suppress=" . $marriage->suppress;
			$rc = mysqli_query( $sql );
			if ( !$rc ) print "\n>>> error = " . mysqli_error() . " sql=" . $sql;
		}

		mysqli_close();
		
	}

	/**
	 * returned structure is:
	 * array( "id"=>id, "name"=>name, "camefrom"=>cameFromUnionId, "dob"=>dateOfBirth, 
	 *     "marriages"=> array (
	 *        array( "id"=>id, "spouseId"=>personId, "spouseName"=>name, "suppressUnion"=>1/0, "suppressSpouse"=>1/0, "camefrom" =>cameFromUnionId
	 *               "children" => array( 
	 *                                array( "id"=>id, "name"=>name, "dob"=>dateOfBirth ), 
	 *                                array( "id"=>id, "name"=>name, "dob"=>dateOfBirth ) ),
	 *        array( ... next marriage )... 
	 *     ) 
	 * )
     * 2011/12/08 - changed to handle array of options.
	 **/
	public function genTree ( $rootId, $depth, $options ) {

		if ( $depth == 0 ) {
			return false;
		}

        // save options
        self::$options = $options;

        $forceFormalNames = 0;
        if (isset($options['showFormalNames']) && $options['showFormalNames'] == 1) {
            $forceFormalNames = 1;
        }
        if (isset($options['collapseTo']) && $options['collapseTo'] > 0) {
            self::$collapseToSpouseId = $options['collapseTo'];
        }
		// get the first person...
		$root = Person::readById( $rootId, $forceFormalNames );

		$cameFromUnionId = $root->cameFromUnionId;
		$dateOfBirth = $root->dateOfBirth;

		// who's this guy/gal married to? - we can't use the unions[] field since we didn't persist it.
		// NOTE: as of 01/12/10 marriage suppression happens in findMarriages...
		$rootMarriages = Person::findMarriages( $rootId, 0 );

		// let's get the spouseId
		if ( !$rootMarriages ) {
			// print "\n... c'mon, " . $root->name . " was never married...";
			$ret = array( "id"=>$rootId, "name"=>$root->name , "camefrom"=>$cameFromUnionId, "dob"=>$dateOfBirth, "marriages"=>array() );
			return $ret;
		}

		$savedKids = array();
		$marriages = array();
		foreach ( $rootMarriages as $rootMarriage ) {

//			$rootMarriage = reset( $rootMarriages );	// this is only for the first rootMarriage...
		
			$suppressUnion = $suppressSpouse = 0;
			if ( $rootMarriage->suppress ) {
				$suppressUnion = 1; 
				continue;
			}

			$spouseId = ($rootMarriage->personId1 == $rootId ) ? $rootMarriage->personId2 : $rootMarriage->personId1;

            // check if we have collapseTo set. if so, ignore all other marriages...
            // NOTE: if collapseTo is set to the wrong Id, then it will appear that the person had no marriages
            if (isset(self::$collapseToSpouseId) && self::$collapseToSpouseId > 0 
                   && $spouseId != self::$collapseToSpouseId 
                   && $rootId == self::$originalRootId) {
                // ignore this spouse since we are told to collapse...
error_log(">>>>RAMON ignoring spouse = " . $spouseId . " with rootId=" . $rootId);
                continue;
            }
			$spouse = Person::readById( $spouseId, $forceFormalNames );
			if ( $spouse->suppress ) { $suppressSpouse = 1; }
			if ( $cameFromUnionId == 0 ) {
				// let's fake the cameFromUnionId with the unionId of the spouse so we can browse up the tree
				$cameFromUnionId = $spouse->cameFromUnionId;
			}

			$spouseName = ( $spouse ) ? $spouse->name : "spouse Not found";
            $spouseCameFromUnionId = ( $spouse ) ? $spouse->cameFromUnionId : 0;
			$rootName = ( $root ) ? $root->name : "root Not found";

//			print "\n... ok we got the root and spouse. " . $rootName . " m. " . $spouseName;

			$children = Person::findChildren( $rootMarriage->id );	// array of rows! children are raw rows...

			$childrenArray = array();

			// call recursion here on each of the children... but remember the returned structure must be ok already
			foreach( $children as $child ) {
// ramon-hack 20170206 - don't show suppressed children...
if ($child['suppress'] == 1) { continue; }
				$childNode = $this->genTree( $child['personId'], $depth - 1, $options );
				if ( $childNode ) {
					$childrenArray[] = $childNode;
				} else {
					// TODO: we might want to put the spouse, but not the children.
					$childrenArray[] = array( "id"=>$child["personId"], "name"=>$child["name"], "dob"=>$child['dateOfBirth'] );
				}
			}

			// handle suppression during building...
			if ( $suppressSpouse ) {
				// don't add the marriage node... keep hold of the children
				$savedKids[] = $childrenArray;
				continue;
			}

			// TODO: if the root flag is set showStepKids then consider the marriage(s) of the spouse and add the children ONLY
			// to the chindrenArray.

			$marriageNode = array( "id"=>$rootMarriage->id, "spouseId"=>$spouseId, "spouseName"=>$spouseName,
                "suppressUnion"=>$suppressUnion, "suppressSpouse"=>$suppressSpouse, "children"=>$childrenArray,
                "cameFromUnionId"=>$spouseCameFromUnionId );
			$marriages[] = $marriageNode;

		}

		// if we have savedKids - they can't be orphaned. we need to add them to a good marriage. or create a dummy union where we can add them
		if ( count( $savedKids ) > 0 ) {
			// find a marriage which can take the kids
			if ( count( $marriages ) > 0 ) {
				$mergedKids = $marriages[0]['children'];
				foreach ( $savedKids as $x => $kid ) {
					foreach ( $kid as $innerKid ) {
						$mergedKids[] = $innerKid;
					}
				}
				$marriages[0]['children'] = $mergedKids;
				$savedKids = array();
			} else {
				// we need a dummy record.
				$mergedKids = array();
				foreach ( $savedKids as $x => $kid ) {
					foreach ( $kid as $innerKid ) {
						$mergedKids[] = $innerKid;
					}
				}
				$marriageNode = array( "id"=>-1, "spouseId"=>-1, "spouseName"=> "- -", "suppressUnion"=>0, "suppressSpouse"=>0,
					"children"=>$mergedKids );
				$marriages[] = $marriageNode;
			}
		}

		// assemble the returned package...
		$ret = array( "id"=>$rootId, "name"=>$root->name , "camefrom"=>$cameFromUnionId, "dob"=>$root->dateOfBirth, "marriages"=>$marriages );
		return $ret;

	}

	public function tree2Html ( $tree ) {
		// TODO: handle count of only unsuppressed marriages!
		if ( count( $tree['marriages'] ) > 1 ) {	
			return $this->tree2Html_MultiSpouse( $tree );
		}
		$li1 = '<li>' . self::$agentreeUrlXXX;
		$li2 = '</li>';
		$a2 = '</a>';
		$html = "\n" . '<ul id="famtreeMain">' . "\n";

		// determine the parentId.. we know the union Id as $tree['camefrom']
		list( $parentId, $otherParentId ) = Person::getIdsOfParents( $tree['camefrom'] );
		if ( $parentId == 0 ) {
			$parentId = $otherParentId;
		}
		if ( !is_array( $tree['marriages'] )) {
			// another trivial case. this guy is a leaf.
			$html .= "\n" . '<li id="home">' . str_replace('xxx',$parentId,self::$agentreeUrlXXX) . $tree['name'] . '</a></li></ul>';
			return $html;
		}
        // create the main node on this style of tree.
		$marriage = reset( $tree['marriages'] );
		$dateOfBirth = Person::convertPersonDate( $tree['dob'] );

        // let's find the parentId of the spouse. it's not in our struct
        $parent2Id = 0;
        if ($marriage['cameFromUnionId'] != 0) {
            list($parent2Id, $dummy) = Person::getIdsOfParents($marriage['cameFromUnionId']);
        }

        // set what happens when the user clicks the root. in the normal uncollapsed case, we go to the parent
        // however, in the collapsed case, we want to see all this guys' marriages.
        $rootClickThruId = $parentId;
        if ($tree['isCollapsed']) {
            $rootClickThruId = $tree['id'];  // if this was probably collapsed - and if so - return
        }
        $style = 2;
        self::$displayedMarriageId = $marriage['id'];
        self::$displayedSpouseId = $marriage['spouseId'];
        $html .= self::createRootNode($style, $tree['name'], $marriage['spouseName'], $rootClickThruId, $parent2Id, $dateOfBirth);
        $html .= "\n</ul>\n</center>\n\n";  // this sucks because I'm closing a center that I did not start. bleah. did you go to cornell or what?
        
        // create the children nodes.
        $html .= '<ul id="famtreeMain">' . "\n\n";
		// does this tree have a spouse? bleah - trivial if not but let's check against it.
		if ( count( $tree['marriages'] ) == 0 ) {   // I don't think this ever executes. this is handled above.
			$html .= '</a></li></ul>' . "\n";
			return $html;
		}

		// if we got to here, it means we don't care about multiple level 0 marriages
		$marriage = reset( $tree['marriages'] );
		$gen = 1;
        // iterate through the children of this marriage.
		foreach ( $marriage['children'] as $child ) {
			$dateOfBirth = Person::convertPersonDate( $child['dob'] );
			$html .= "\n" . str_repeat('   ',$gen) . '<li>' . 
				str_replace('xxx',$child['id'],str_replace('yyy',$dateOfBirth,self::$agentreeUrlXXX)) . $child['name'];
			// does the level1 child have a spouse? if so...
			if ( isset( $child['marriages'] ) && count( $child['marriages'] )) {
				// TODO: fix multiple level 1 marriages.
//					$l1Marriage = reset( $child['marriages']);
				// we've already opened up the first <li> for the first marriage
				$childMarriageCount = 0;
				foreach ( $child['marriages'] as $l1Marriage ) {
					$childMarriageCount++;
					if ( $childMarriageCount > 1 ) {
						// emit the lines for this marriage..... this will come after the tree2L2Html lines
						$dateOfBirth = Person::convertPersonDate( $child['dob'] );
						$html .= "\n" . str_repeat('   ',$gen) . "&nbsp;<br /><br /><br />" . 
							str_replace('xxx',$child['id'],str_replace('yyy',$dateOfBirth,self::$agentreeUrlXXX));
					} else {
						$html .= '<br />';		// this means it's the first l1 marriage. we need the br/
					}
					if ( substr( $l1Marriage['spouseName'],0,1) != '-' ) {
						$html .= "m. " . $l1Marriage['spouseName'];
					}
					$html .= '</a>';
					$html .= "\n" . $this->tree2L2Html( $l1Marriage['children'], $gen );
				}
			} else {
				$html .= "\n" . str_repeat('   ',$gen) . '</a>';
			}
			$html .= "\n   </li>";
		}
		$html .= "\n</ul>";

		return $html;
	}

	public function tree2Html_MultiSpouse ( $tree ) {
		$li1 = '<li><a href="gentree.php?rootId=xxx">';
		$li2 = '</li>';
		$a2 = '</a>';
		$html = "\n" . '<ul id="famtreeMain">' . "\n";

		// determine the parentId.. we know the union Id as $tree['camefrom']
		list( $parentId, $otherParentId ) = Person::getIdsOfParents( $tree['camefrom'] );

		$html .= '<li id="home"><a href=' . self::$gentreeUrl . $parentId . '">' . $tree['name'] . '</a></li>';
        $html .= "\n</ul>\n</center>\n";    // stupid closing a center that I did not start.
        $html .= "\n<ul id='famtreeMain'>\n\n";
		// does this tree have a spouse? bleah - trivial if not but let's check against it.
		if ( count( $tree['marriages'] ) == 0 ) {
			$html .= '</a></li></ul>' . "\n";
			return $html;
		}

		$gen = 1;
		foreach ( $tree['marriages'] as $marriage ) {
            $collapseToSpouseUrl = self::$gentreeUrl . $tree['id'] . '&collapseTo=' . $marriage['spouseId'];
//			$html .= str_repeat('   ',$gen) . '<li><a href=' . self::$gentreeUrl . $tree['id'] . '">u. ' . $marriage['spouseName'] . '</a>' . "\n";
			$html .= str_repeat('   ',$gen) . '<li><a href=' . $collapseToSpouseUrl . '">u. ' . $marriage['spouseName'] . '</a>' . "\n";
			$html .= "\n" . $this->xtree2L2Html( $marriage['children'], $gen );
			$html .= "\n" . '</li>';
		}

		return $html;

	}

	// TODO: consolidate tree2L2Html and tree2L3Html
	public function xtree2L2Html( $childrenList, $gen ) {
		$li1 = '<li><a href="gentree.php?rootId=xxx" title="Born: yyy">';
		$html = '';
		// now do the L2 children.
		if ( count( $childrenList ) > 0 ) {
			$gen++;
			$html .= str_repeat('   ',$gen) . '<ul>';
			foreach ( $childrenList as $child ) {
				$dateOfBirth = Person::convertPersonDate( $child['dob'] );
				$html .= "\n" . str_repeat('   ',$gen) . str_replace('xxx',$child['id'],str_replace('yyy',$dateOfBirth,$li1)) . $child['name'];
				if ( isset( $child['marriages'] ) && count( $child['marriages'] )) {
					// TODO: handle multiple marriages..
					$l2Marriage = reset( $child['marriages']);
					if ( substr( $l2Marriage['spouseName'],0,1) != '-' ) {
						$html .= "<br />m. " . $l2Marriage['spouseName'];
					}
					$html .= '</a>';
	
					// now do the l3 children.. (last level) - this has no <ul>s in between. just one ahref after another
					$html .= "\n" . $this->tree2L3Html( $l2Marriage['children'], $gen );
				} else {
					$html .= '</a>';
				}
				$html .= "\n" . str_repeat('   ',$gen) . '</li>';
			}
			$html .= "\n" . str_repeat('   ',$gen) . '</ul>';
			$gen--;
		}		// l1child has a union that created children
		return $html;
	}


	// TODO: consolidate tree2L2Html and tree2L3Html
	public function tree2L2Html( $childrenList, $gen ) {
		$li1 = '<li><a href="gentree.php?rootId=xxx" title="Born: yyy">';
		$html = '';
		// now do the L2 children.
		if ( count( $childrenList ) > 0 ) {
			$gen++;
			$html .= str_repeat('   ',$gen) . '<ul>';
			foreach ( $childrenList as $child ) {
				$dateOfBirth = Person::convertPersonDate( $child['dob'] );
				$html .= "\n" . str_repeat('   ',$gen) . str_replace('xxx',$child['id'],str_replace('yyy',$dateOfBirth,$li1)) . $child['name'];
				if ( isset( $child['marriages'] ) && count( $child['marriages'] )) {
					// TODO: handle multiple marriages..
					$l2Marriage = reset( $child['marriages']);
					if ( substr( $l2Marriage['spouseName'],0,1) != '-' ) {
						$html .= "<br />m. " . $l2Marriage['spouseName'];
					}
					$html .= '</a>';
	
					// TODO: move this into it's own function.
					// now do the l3 children.. (last level) - this has no <ul>s in between. just one ahref after another
					$html .= "\n" . $this->tree2L3Html( $l2Marriage['children'], $gen );
				} else {
					$html .= '</a>';
				}
				$html .= "\n" . str_repeat('   ',$gen) . '</li>';
			}
			$html .= "\n" . str_repeat('   ',$gen) . '</ul>';
			$gen--;
		}		// l1child has a union that created children
		return $html;
	}

	/**
	 * using the childrenList, generate the HTML for the level 3 children 
	 **/
	public function tree2L3Html( $childrenList, $gen ) {
		$li1 = '<a href="gentree.php?rootId=xxx" title="Born: yyy">';
		$html = '';
		// now fo the L3 children
		if ( count($childrenList) > 0 ) {
			$gen++;
			$html .= "\n" . str_repeat('   ',$gen) . '<ul>';
			$html .= "\n" . str_repeat('   ',$gen) . '<li>';
			foreach ( $childrenList as $child ) {
				$dateOfBirth = Person::convertPersonDate( $child['dob'] );
				$html .= "\n" . str_repeat('   ',$gen) . str_replace('xxx',$child['id'],str_replace('yyy',$dateOfBirth,$li1)) . $child['name'];
				// TODO: handle marriages of l3 children
				$html .= '</a>';
			}
			$html .= "\n" . str_repeat('   ',$gen) . "</li>\n" . str_repeat('   ',$gen) . "</ul>";
			$gen--;
		}	// l2child has a union with children
		return $html;
	}

	/**
	 * 
	 **/
	public function enforceSuppression ( $tree, $suppressionList ) {
		// ignore suppressionList for now, let's just re-arrange the tree to remove suspended individuals or nodes
		// Handle suppressed marriages  - this becomes more urgent once we remove the database suppression code
			// TODO: marriage suppression - pretty easy stuff...
		// Handle the individual suppression - harder since we have to move kids around
		// determine if there's exactly one unsuppressed spouse. if there is such, move all the children under him/her
		$numberOfOKSpouses = 0;
		$marriages = $tree['marriages'];
		foreach ( $marriages as $marriage ) {
			if ( $marriage['suppressSpouse'] == 0 ) { 
				$numberOfOKSpouses++;
				$firstOKMarriage = $marriage['id'];
			}
		}
		if ( $numberOfOKSpouses == 1 ) {
			// TODO: make first level single spouse work...
		}
		foreach ( $marriages as $marriage ) {
			// make 2nd level work as well...- eh... recursively...
			$childCount = $marriage['children'];
			for ( $i = 0; $i < $childCount; $i++ ) {
				$tempChild = $marriage['children'][$i];
				$cleanTree = $this->enforceSuspension( $tempChild, $suppressionList );
				$marriage['children'][$i] = $cleanTree;
			}
		}
		return $tree;
	}

    /**
     *
     **/
    public static function createRootNode($style, $name, $spouseName, $parent1Id, $parent2Id, $dateOfBirth) {
        $html = '';
        if ($style == 1) {
           $html = self::createRootNodeStyle1($name, $spouseName, $parent1Id, $dateOfBirth);
        } else {
           $html = self::createRootNodeStyle2($name, $spouseName, $parent1Id, $parent2Id, $dateOfBirth);
        }
        return $html;
    }

    /**
     *
     **/
    public static function createRootNodeStyle1($name, $spouseName, $parent1Id, $dateOfBirth) {
		$html = "\n" . '<li id="home">' . str_replace('xxx',$parent1Id,str_replace('yyy',$dateOfBirth,self::$agentreeUrlXXX)) . $name;
		// before displaying the spouseName make sure it's not one of those dummy spouses
		if ( substr($spouseName,0,1) != '-' ) {
			$html .= '<BR />m. ' . $spouseName;
		}
		$html .= '</a></li>';
        return $html;
    }

    /**
     *
     **/
    public static function createRootNodeStyle2($name, $spouseName, $parent1Id, $parent2Id, $dateOfBirth) {
        $marriageChar = 'm.';
        if (self::$options['debug']) {
//            $name .= '(' . $tree['id'] . ')';
            if (self::$displayedSpouseId) {
               $spouseName .= '(' . self::$displayedSpouseId . ')';
               $marriageChar = 'm.(' . self::$displayedMarriageId . ')';
            }
        }
        $linkToPerson1Parent = '';
        $linkToPerson1ParentSuffix = '';
        if ($parent1Id != 0) {
           $linkToPerson1Parent = str_replace('xxx',$parent1Id,str_replace('yyy',$dateOfBirth,self::$agentreeUrlXXX));
           $linkToPerson1ParentSuffix = '</a>';
        }
        $linkToPerson2Parent = '';
        $linkToPerson2ParentSuffix = '';
        if ($parent2Id != 0) {
           $linkToPerson2Parent = str_replace('xxx',$parent2Id,str_replace('yyy','n/a',self::$agentreeUrlXXX));
           $linkToPerson2ParentSuffix = '</a>';
        }
        $person1Html = $linkToPerson1Parent . $name . $linkToPerson1ParentSuffix;
        $person2Html = '';
		// before displaying the spouseName make sure it's not one of those dummy spouses
		if ( substr($spouseName,0,1) != '-' ) {
			$person2Html = $linkToPerson2Parent . $spouseName . $linkToPerson2ParentSuffix;
		}
        if ($person2Html != '') {  // () because of the debug flag...
           $html = "\n" . '<div id="test01">' .
                   "<table border=0 width='100%'><tr><td width='45%' align='center'>" . $person1Html . "</td><td width='10%' align='center'>$marriageChar</td>" .
                   "<td width='45%' align='center'>" . $person2Html . "</td></tr></table></div>";
        } else {
           $html = "\n" . '<div id="test01">' . $person1Html . "</div>";
        }
        return $html;
    }

}
