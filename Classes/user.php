<?php

class catServices {

	protected $_userId;
	protected $_db;
	protected $_userKey;
	protected $_passWord;
	protected $_userSecure;

	public function __construct(PDO $db){
		$this->_db = $db;
	}

//Logging User

	public function login($keyword,$password,$remember=FALSE){
		session_start();
		$this->_userKey=$keyword;
		$this->_passWord=$password;
		$verify = $this->authenticate();
		if($verify){
			if($remember){
				setcookie("catsecure", $this->_userSecure, time()+604800, "/", "",  0);
				setcookie("catid", $this->_userId, time()+604800, "/", "",  0);
			}else {
				$_SESSION['catid']=$this->_userId;
				$_SESSION['catsecure']=$this->_userSecure;
			}
			return $this->_userId;
		} else {
			return FALSE;
		}
	}

//Authenticating user with password

	protected function authenticate(){
		$user = $this->checkUserExists($this->_userKey);
		if($user['cat_status']=="deactivated"){
			$stmt = $this->_db->prepare('UPDATE `cat` SET `cat_satus`= ? WHERE `cat_id`=?');
			$stmt->execute(array('default',$user['cat_id']));
		}
		if($user&&password_verify($this->_passWord,password_hash($user['cat_password'],PASSWORD_DEFAULT))&&$user['cat_status']!="uncomfirm"&&$user['cat_status']!="block"){
			$this->_userId=$user['cat_id'];
			$this->_userSecure = $user['cat_secure'];
			return $this->_userId;
		} else {
			return FALSE;
		}
	}

//Signup a user

	public function signup($firstname,$lastname,$password1,$password2,$email,$sex,$dob,$captcha){
		$exists = $this->checkUserExists($email);
		$firstname = STR::check($firstname,'name');
		$lastname = STR::check($lastname,'name');
		$password1 = STR::check($password1,'password');
		$comparepasswords = password_verify($password1,password_hash($password2,PASSWORD_DEFAULT));
		$email = STR::check($email,'email');
		$dob = STR::check($dob,'date');
		$errors = array();

//Creating list of errors

		if($exists) array_push($errors,'exists');
		if(!$firstname) array_push($errors,'firstname');
		if (!$lastname) array_push($errors,'lastname');
		if(!$password1) array_push($errors,'password');
		if(!$comparepasswords) array_push($errors,'comparepasswords');
		if(!$email) array_push($errors,'email');
		if(!$dob) array_push($errors,'dob');
		if(!$captcha) array_push($errors,'captcha');

//Returning errors or signup status

		if(count($errors)>0){
			return $errors;
		} else {
			$email = smtpmailer($email, 'comfirm@catwing.com', 'Catwing', 'Comfirm your catwing account', $body);
			if(!$email) {
				array_push($errors,'email');
				return $errors;
			}
			$live = $this->getUserCountry();
			$secure =$this->generateToken();
			$stmt = $this->_db->prepare('INSERT INTO `cat` (`cat_firstname`,`cat_lastname`,`cat_password`,`cat_email`,`cat_sex`,`cat_birthday`,`cat_live`,`cat_secure`)
				VALUES (?,?,?,?,?,?,?,?)
				');
			$stmt->execute(array($firstname,$lastname,$password1,$email,$sex,$dob,$live,$secure));
			return TRUE;

		}
	}


//Check user exists

	protected function checkUserExists($keyword){
		$stmt = $this->_db->prepare('SELECT * FROM `cat` WHERE `cat_id`= :keyword1 OR `cat_link`= :keyword2 OR `cat_email`= :keyword3 OR `cat_secure`= :keyword4');
		$stmt-> execute(array('keyword1'=>$keyword,'keyword2'=>$keyword,'keyword3'=>$keyword,'keyword4'=>$keyword));
		$user = $stmt->fetch();
		return $user;
	}

//Get the real ip of user. (Adapted from https://stackoverflow.com/questions/3003145/how-to-get-the-client-ip-address-in-php)

	protected function getUserIP(){
		$client = @$_SERVER['HTTP_CLIENT_IP'];
		$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
		$remote = $_SERVER['REMOTE_ADDR'];
		if(filter_var($client,FILTER_VALIDATE_IP)){
			$ip=$client;
		} else if (filter_var($forward,FILTER_VALIDATE_IP))
		{
			$ip = $forward;
		} else {
			$ip=$remote;
		}
		return $ip;
	}

//Get the user's country. (Adapted from https://stackoverflow.com/questions/37111788/how-to-get-user-country-name-from-user-ip-address-in-php)

	protected function getUserCountry(){
		$ip = $this->getUserIP();
		$json       = file_get_contents("http://ipinfo.io/".$ip);
    	$details    = json_decode($json);
    	$country = $details->country;
    	return $country;
	}

//Make secure unique token for users

	protected function generateToken (){

		try {
			$check =NULL;
		    $this->_db->beginTransaction();
		    $stmt = $this->_db->prepare('SELECT * FROM `cat` WHERE `cat_secure`=?');
		    do{
				$random = bin2hex(random_bytes(64));
				$stmt->execute(array($random));
				$check = $stmt->fetch();
			} while($check);    
		    $this->_db->commit();
		}catch (Exception $e){
		    $this->_db->rollback();
		    throw $e;
		}
		return $random;
	}

//Comfirm user

	public function comfirm($secure){
		$stmt = $this->_db->prepare('UPDATE `cat` SET `cat_status`="default" WHERE `cat_status`!="blocked" ');
		$stmt->execute();
		if(!$stmt->rowCount()){
			return FALSE;
		}
		return TRUE;
	}

//Block a user

	public function block($userid){
		$stmt = $this->_db->prepare('UPDATE `cat` SET `cat_status`= ? WHERE `cat_id`=?');
		$stmt->execute(array("block",$userid));
		if(!$stmt->rowCount()){
			return FALSE;
		}
		return TRUE;
	}

//Check a user is logged or not

	public function checkLogged(){
		session_start();
		if(isset($_SESSION['catid'])&&isset($_SESSION['catsecure'])){
			$this->_userId = $_SESSION['catid'];
			$this->_userSecure = $_SESSION['catsecure'];
			$stmt = $this->_db->prepare('SELECT * FROM `cat` WHERE `cat_secure`=? AND `cat_id`=?');
			$stmt->execute(array($this->_userSecure , $this->_userId));
			if($stmt->rowCount()==1){
				return $this->_userId;
			} else{
				$this->logout();
				return FALSE;
			}
		} else if(isset($_COOKIE['catid'])&&isset($_COOKIE['catsecure'])){
			$this->_userId = $_COOKIE['catid'];
			$this->_userSecure = $_COOKIE['catsecure'];
			$stmt = $this->_db->prepare('SELECT * FROM `cat` WHERE `cat_secure`=? AND `cat_id`=?');
			$stmt->execute(array($this->_userSecure , $this->_userId));
			if($stmt->rowCount()==1){
				return $this->_userId;
			} else{
				$this->logout();
				return FALSE;
			}
		} else {
			return FALSE;
		}
	}

//User log out

	public function logout(){
		session_start();
		if (isset($_SESSION['catid'])){
			unset($_SESSION['catid']);
			unset($_SESSION['catsecure']);
		}

//Unset cookies (adapted from https://stackoverflow.com/questions/2310558/how-to-delete-all-cookies-of-my-website-in-php)

		if (isset($_SERVER['HTTP_COOKIE'])) {
		    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
		    foreach($cookies as $cookie) {
		        $parts = explode('=', $cookie);
		        $name = trim($parts[0]);
		        setcookie($name, '', time()-1000);
		        setcookie($name, '', time()-1000, '/');
		    }
		}

	}

//User log out from all devices

	public function logoutAll(){
		$user = FALSE;
		session_start();
		if(isset($_SESSION['catid'])) $user = $_SESSION['catid'];
		if(isset($_COOKIE['catid'])) $user = $_COOKIE['catid'];
		if($user){
			$secure = $this->generateToken();
			$stmt = $this->_db->prepare('UPDATE `cat` SET `cat_secure`=? WHERE `cat_id` = ?');
			$stmt ->execute(array($secure,$user));
			if($stmt->rowCount()==1){
				return TRUE;
			} else{
				return FALSE;
			}
		} else {
			return FALSE;
		}
	}

//Deactivate a user

	public function deactivate(){
		if(isset($_SESSION['catid'])) $user = $_SESSION['catid'];
		if(isset($_COOKIE['catid'])) $user = $_COOKIE['catid'];
		$stmt = $this->_db->prepare('UPDATE `cat` SET `cat_status`= ? WHERE `cat_id`=?');
		$stmt->execute(array("deactivated",$user));
		if(!$stmt->rowCount()){
			return FALSE;
		}
		return TRUE;
	}

}


?>
