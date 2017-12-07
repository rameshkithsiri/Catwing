<?php

//String checks and edits

class STR{
	public static function check($str,$type){
		if($type=='password'){

//Password strength check

			$match=0;

			if(preg_match('/[[:punct:]]/',$str)){
				$match=$match+1;
			}
			if(strlen($str)>6){
				$match = $match+6;
			}
			if(preg_match('[[A-Za-z]]',$str)){
				$match = $match+1;
			}
			if(preg_match('[[1-9]]',$str)){
				$match = $match+1;
			}
			if($match>7){
				return $str;
			} else {
				return FALSE;
			}
		} else if ($type=='name'){

//Name check
			if(!strlen($str)>0||preg_match('[[1-9]]', $str)||preg_match('/[[:punct:]]/', $str)){
				return FALSE;
			} else{
				return $str;
			}
		} else if($type=='email'){

//Email check

			if (!filter_var($str,FILTER_VALIDATE_EMAIL)){
				return FALSE;
			} else {
				return $str;
			}

		} else if ($type=='date'){

//Check Date is correct output them to same format YY-MM-DD

			function check($date){
				$valid=FALSE;
				$arr = array('231','213','321');
				foreach ($arr as $ar){
					$ar = str_split($ar);
					if(checkdate($date[$ar[0]-1],$date[$ar[1]-1],$date[$ar[2]-1])){
						$validdate= $date[$ar[2]-1].'-'.$date[$ar[0]-1].'-'.$date[$ar[1]-1];
						if(strlen($validdate)>8){
							$validdate=substr($validdate,2,8);
						}
						$valid = $validdate;
						break;
					} else {
						$valid =FALSE;
					}
				}
				return $valid;
			}
			$valid= FALSE;
			if(preg_match('/[[:punct:]]/',$str)){
				$date = preg_split('/[[:punct:]]/',$str);
				$valid = check($date);
			} elseif(strlen($str)==6) {
				$date = str_split($str,2);
				$valid = check($date);
			} else if (strlen($str)==8){
				if(substr($str,0,4)>1940){
					$date=substr($str,2,6);
				} else {
					$date=substr($str,0,6);
				}
				$date = str_split($date,2);
			$valid = check($date);
			}
			return $valid;
		}
	}
}

?>
