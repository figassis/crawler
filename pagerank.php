<?php

// Gets the current URL
function getPageURL() {
	$page_url = 'http';
	
	if($_SERVER['HTTPS'] == 'on')
		$page_url .= 's';
	
	$page_url .= '://';
	
	// Generate the URL
	if((($_SERVER['SERVER_PORT'] != '80') && ($_SERVER['HTTPS'] != 'on')) || (($_SERVER['SERVER_PORT'] != '443') && ($_SERVER['HTTPS'] == 'on')))
		$page_url .= $_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
	else
		$page_url .= $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
	
	return $page_url;
}

// Gets the PageRank for the page
function getPageRank($url = '', $server = 'toolbarqueries.google.com') {
	/* From: http://code.seebz.net/p/google-pagerank/ */
	
	// Get current URL?
	if(!$url)
		$url = getPageURL();
	
	// Process the Google page hash
	$f_str_to_num = create_function('$str, $check, $magic', '
		$int_32_unit = 4294967296;
		$length = strlen($str);
		
		for($i = 0; $i < $length; $i++) {
			$check *= $magic;
			
			if($check >= $int_32_unit) {
				$check = ($check - $int_32_unit * (int) ($check / $int_32_unit));
				$check = ($check < -2147483648) ? ($check + $int_32_unit) : $check;
			}
			
			$check += ord($str{$i});
		}
		
		return $check;
	');
	
	$f_hash_url = create_function('$str', '
		$f_str_to_num = "'.$f_str_to_num.'";
		$check1 = $f_str_to_num($str, 0x1505, 0x21);
		$check2 = $f_str_to_num($str, 0, 0x1003F);
		
		$check1 >>= 2;
		$check1 = (($check1 >> 4) & 0x3FFFFC0 ) | ($check1 & 0x3F);
		$check1 = (($check1 >> 4) & 0x3FFC00 ) | ($check1 & 0x3FF);
		$check1 = (($check1 >> 4) & 0x3C000 ) | ($check1 & 0x3FFF);
		$t1 = (((($check1 & 0x3C0) << 4) | ($check1 & 0x3C)) <<2 ) | ($check2 & 0xF0F );
		$t2 = (((($check1 & 0xFFFFC000) << 4) | ($check1 & 0x3C00)) << 0xA) | ($check2 & 0xF0F0000 );
		
		return ($t1 | $t2);
	');
	
	$f_check_hash = create_function('$hashNum', '
		$check_byte = 0;
		$flag = 0;
		$hash_str = sprintf("%u", $hashNum);
		$length = strlen($hash_str);
		
		for($i = $length - 1; $i >= 0; $i--) {
			$re = $hash_str{$i};
			
			if(1 === ($flag % 2)) {
				$re += $re;
				$re = (int)($re / 10) + ($re % 10);
			}
			
			$check_byte += $re;
			$flag ++;
		}
		
		$check_byte %= 10;
		
		if(0 !== $check_byte) {
			$check_byte = 10 - $check_byte;
			
			if(1 === ($flag % 2) ) {
				if(1 === ($check_byte % 2))
					$check_byte += 9;
				
				$check_byte >>= 1;
			}
		}
		
		return "7".$check_byte.$hash_str;
	');
	
	$checksum = $f_check_hash($f_hash_url($url));
	
	// Request the page PageRank
	$request_url = sprintf(
		'http://%s/tbr?client=navclient-auto&ch=%s&ie=UTF-8&oe=UTF-8&features=Rank&q=info:%s',
		$server,
		$checksum,
		urlencode($url)
	);
	
	if(($c = @file_get_contents($request_url)) === false)
		return false;
	else if(empty($c))
		return -1;
	else
		return intval(substr($c, strrpos($c, ':') + 1));
}

?>