<?php
class catship{
	protected $_cat1;
	protected $_cat2;
	protected $_db;

	public function __construct(POD $db){
		$this->_db=$db;
		if(isset($_SESSION['catid'])) $user = $_SESSION['catid'];
		if(isset($_COOKIE['catid'])) $user = $_COOKIE['catid'];
		$this->_cat1 = $user;
	}

//Set a cat to start a catship

	public function setCat($cat){
		$this->_cat2=$cat;
	}

//Close,Stranger,Block,Unblock,Reply to a cat

	public function update($action){
		try{
			$this->_db->beginTransaction();
			$stmt = $this->_db->prepare('UPDATE `catship` SET `catship_cat1type`= IF(`catship_cat1`=?,?,`catship_cat1type`),`catship_cat2type`= IF(`catship_cat2`=?,?,`catship_cat2type`) WHERE (`catship_cat1`=? AND `catship_cat2`=?) OR (`catship_cat2`=? AND `catship_cat1`=?) ');
			$stmt->execute(array($this->_cat1 ,$action, $this->_cat1 ,$action, $this->_cat1 , $this->_cat2, $this->_cat1 , $this->_cat2));
			if(!$stmt->rowCount()){
				$stmt = $this->_db->prepare('INSERT INTO `catship` (`catship_cat1`,`catship_cat2`,`catship_cat1type`) VALUES (?,?,?)' );
				$stmt->execute(array($this->_cat1, $this->_cat2 , $action));
			}
			$this->_db->commit();
			if(!$stmt->rowCount()) return FALSE;
			return TRUE;
		}catch (Exception $e){
		    $this->_db->rollback();
		    throw $e;
		}
		
	}

//Check catship status

	public function getStatus(){
		$stmt = $this->_db->prepare('
			SELECT CASE
				WHEN a="block" THEN "blockedbyhim"
				WHEN b="block" THEN "blockbyyou"
				WHEN a="close" AND b="close" THEN "kitty"
				WHEN a="close" THEN "fan"
				WHEN b="close" THEN "closed"
				ELSE "other" END AS type FROM
			(SELECT	`catship_cat1type` AS a,`catship_cat2type` AS b FROM `catship` WHERE `catship_cat1` =? AND `catship_cat2`=?
				UNION
			SELECT `catship_cat2type` AS a,	`catship_cat1type` AS b FROM `catship` WHERE `catship_cat2` =? AND `catship_cat1`=?)x
			');
		$stmt->execute(array($this->_cat2,$this->_cat1,$this->_cat2,$this->_cat1));
		$type=$stmt->fetch();
		if(!$type) $type="other";
		if($this->_cat1 == $this->_cat2) $type="me";
		return $type;

	}
}
?>
