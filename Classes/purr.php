<?php
class purr{

	protected $_db;
	protected $_cat1;
	protected $_cat2;

	public function __construct(PDO $db){
		$this->_db = $db;
		if(isset($_SESSION['catid'])) $user = $_SESSION['catid'];
		if(isset($_COOKIE['catid'])) $user = $_COOKIE['catid'];
		$this->_cat1 = $user;
	}

//Set a receiver

	public function setCat($cat){
		$this->_cat2 = $cat;
	}

//Write a purr and store clips

	public function write($text,$clips=array()){
		$clipcount=count($clips);
		$stmt = $this->_db->prepare('INSERT INTO `purr` (`purr_sender`,`purr_receiver`,`purr_content`,`purr_time`,`purr_clip`) SELECT (?,?,?,now(),?) WHERE EXISTS( SELECT * FROM `cat` WHERE `cat_id`=? AND `cat_status`!= ? AND `cat_status`!=? AND `cat_status` != ?)');
		$stmt->execute(array($this->_cat1, $this->_cat2 , $text , $clipcount , $this->_cat2, 'block','uncomfirm','deactivated'));
		if(!$stmt->rowCount()) return FALSE;
		if($clipcount){
			$purrid = $this->_db->lastInsertId();
			$stmt = $this->_db->prepare('INSERT INTO `clip`(`clip_content`,`clip_type`,`clip_privacy`,`clip_link`) SELECT (?,?,?,?) ');
			try{
				$this->_db->beginTransaction();
				foreach($clips as $clip){
					$stmt->execute(array($purrid,$clip['type'],'purr',$clip['link']));
				}
				$this->_db->commit();
			}catch (Exception $e){
		   		$this->_db->rollback();
		    	throw $e;
			}
		}
		return TRUE;
	}

}
?>
