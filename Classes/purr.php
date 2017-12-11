<?php
class purr{

	protected $_db;
	protected $_cat1;
	protected $_cat2;

	public function __construct(PDO $db){
		$this->_db = $db;
		session_start();
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
		$stmt = $this->_db->prepare('INSERT INTO `purr` (`purr_sender`,`purr_receiver`,`purr_content`,`purr_time`,`purr_clip`) SELECT ?,?,?,now(),? FROM dual WHERE EXISTS( SELECT * FROM `cat` WHERE `cat_id`=? AND `cat_status`!= ? AND `cat_status`!=? AND `cat_status` != ?)');
		$stmt->execute(array($this->_cat1, $this->_cat2 , $text , $clipcount , $this->_cat2, 'block','uncomfirm','deactivated'));
		if(!$stmt->rowCount()) return FALSE;
		if($clipcount){
			$purrid = $this->_db->lastInsertId();
			$stmt = $this->_db->prepare('INSERT INTO `clip`(`clip_content`,`clip_type`,`clip_privacy`,`clip_link`) VALUES (?,?,?,?) ');
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

// Load new purrs and private purr list

	public function update($private=FALSE,$page=1){
		if($private){
			$middle = '';
			$last = ' LIMIT 5 OFFSET '.(($page-1)*5);
		} else {
			$middle=' AND `purr_seen` IS NULL ';
			$last ='';
		}
		$stmt = $this->_db->prepare('SELECT `purr_id` ,`purr_content`,`purr_time` ,`purr_clip` FROM `purr` WHERE (`purr_sender`=? AND `purr_receiver`=?'.$middle.') OR (`purr_sender`=? AND `purr_receiver`=? '.$middle.') '.$last);
		$stmt->execute(array($this->_cat1,$this->_cat2,$this->_cat2,$this->_cat1));
		$purrs = $stmt->fetchAll();
		$stmt = $this->_db->prepare('UPDATE `purr` SET `purr_seen`=IF(`purr_seen` IS NULL,now(),`purr_seen`) WHERE (`purr_sender`=? AND `purr_receiver`=?'.$middle.') OR (`purr_sender`=? AND `purr_receiver`=? '.$middle.')');
		$stmt->execute(array($this->_cat1,$this->_cat2,$this->_cat2,$this->_cat1));
		if($purrs){
			try{
				$stmt = $this->_db->prepare('CALL getPurrClip(?)');
				$i=0;
				$this->_db->beginTransaction();
				foreach($purrs as $purr){
					if($purr['purr_clip']){
						$stmt->execute(array($purr['purr_id']));
						$clips=$stmt->fetch();
						$purrs[$i]['purr_clip']=$clips;
					}
					$i=$i+1;
				}
				$this->_db->commit();
			} catch (Exception $e){
			    $this->_db->rollback();
			    throw $e;
			}
			return $purrs;
		}
		return FALSE;

	}

// Get a list of purrs

	public function getList($page=1){
		$stmt = $this->_db->prepare('CALL getPurrList(?,?)');
		$stmt->execute(array($this->_cat1,$page));
		if(!$stmt->rowCount()) return FALSE;
		$purrs = $stmt->fetchAll();
		return $purrs;

	}

//Delete purrs

	public function delete($purrs=array()){
		$i=0;
		if(count($purrs)){
			try{
				$stmt = $this->_db->prepare('DELETE FROM `purr` WHERE `purr_id`= ? AND (`purr_sender`= ? OR `purr_receiver`= ? )');
				$this->_db->beginTransaction();
				foreach($purrs as $purr){
					$stmt->execute(array($purr,$this->_cat1,$this->_cat1));
					if($stmt->rowCount()) $i++;
				}
				$this->_db->commit();
			} catch(Exception $e){
				$this->_db->rollback();
				throw $e;
			}
		}
		if(count($purrs)==$i) return TRUE;
		return FALSE;
	}

}
?>
