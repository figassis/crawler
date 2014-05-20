<?php 
include("pagerank.php");
include("Heap.php");
include('simple_html_dom.php');

function db_connect($master, $ipnode){
$username='crawler';
$password='7B7nkyaNzPatEP';
$database='crawler';
$hostname = ($ipnode == $master)?'localhost':$master;

$connection_link = mysqli_connect($hostname,$username, $password, $database) OR DIE (mysql_error() . '\n');

if (mysqli_connect_error()) {
	die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
}

return $connection_link;
}

function ipnode(){
	$command="/sbin/ifconfig eth0 | grep 'inet addr:' | cut -d: -f2 | awk '{ print $1}'";
	$ipnode = exec ($command);
	return $ipnode;
}

function gethost($hostname){
	$dns = array('8.8.8.8', '209.244.0.3', '209.244.0.4', '8.8.4.4', '8.26.56.26',
		'8.20.247.20', '208.67.222.222', '208.67.220.220', '156.154.70.1', '156.154.71.1',
		'199.85.126.10', '199.85.127.10', '81.218.119.11', '209.88.198.133', '195.46.39.39',
		'195.46.39.40', '216.87.84.211', '23.90.4.6', '199.5.157.131', '208.71.35.137', '208.76.50.50',
		'208.76.51.51', '216.146.35.35', '216.146.36.36', '89.233.43.71', '89.104.194.142', '74.82.42.42', '109.69.8.51');
	//$server = $dns[rand(0,count($dns)-1)];
	$server = $dns[0];
	$command = "host $hostname $server | grep 'has address' | cut -d' '  -f4 | awk '{ print $1}' | head -n1";
	$ip = trim(exec ($command));
	return $ip;
}



function store_url(&$url, $connection){
	$links = count($url['links']);
	$query = "UPDATE frontier set ctime = '{$url['ctime']}',
		ctime2 = '{$url['ctime2']}', links = $links, images = {$url['images']},
			size = '{$url['size']}', content = '{$url['content']}' where idfrontier = '{$url['idfrontier']}'";
	//echo $query . '\n';//die();
  	$result = mysqli_query($connection,$query) or die('Error: ' . mysqli_error($connection) . '\n');

  	
  	foreach($url['links'] as $link){
  	//call pagerank api
  	//$rank = get_rank($connection,$url);

  	//use previous page's rank
  	//$rank = $url['rank'];

  	//use a random rank
  	$rank = rand(0,10);
  	$hash = md5($link);
  	$host = parse_url($link, PHP_URL_HOST);
  	$ipaddr = gethostbyname ($host);
  	$time = time();
  	$time2 = time() + 10;
  	$query = "INSERT IGNORE INTO frontier (idfrontier, url, rank, ctime, ctime2, address) VALUES ('$hash','$link','$rank','$time', '$time2', '$ipaddr')";
  	$result = mysqli_query($connection,$query) or die('Error: ' . mysqli_error($connection) . '\n');
  	}

  	return true;

}

function register_node($connection, $max){
	
	$query = "select * from nodes order by idnodes desc";
  	$result = mysqli_query($connection,$query) or die(mysqli_error() . '\n');
	
	if($row = mysqli_fetch_array($result,MYSQLI_ASSOC)) {
		if($row['idnodes'] >= $max){ die("We've reached the maximum number of nodes. Be nice to the internet :-) \n");}
	}

	$address = ipnode();
	$query = "INSERT INTO nodes (address) VALUES ('$address')";
  	$result = mysqli_query($connection,$query) or die('Error: ' . mysqli_error($connection) . '\n');
  	$id = mysqli_insert_id ($connection);
  	return $id;
}

function get_rank($connection, $url){
	$hash = md5($url);
	$query = "select * from frontier where idfrontier = '$hash'";
  	$result = mysqli_query($connection,$query) or die(mysqli_error() . '\n');
	
	if($row = mysqli_fetch_array($result,MYSQLI_ASSOC)) {
		//found rank in database, return result
  		$rank = $row['rank'];
	}else{
		//rank not in database, get from internet
		//$rank = getPageRank($url);
		//$rank = ($rank < 0)?0:$rank;
		$rank = rand(0,10);
	}
	return $rank;
}

function get_frontier($connection, $nodes, $node){
	$query = "select * from frontier ORDER BY rank desc";
  	$result = mysqli_query($connection,$query) or die(mysqli_error() . "\n");
	
	if(mysqli_num_rows($result) == 0){return false;}

	$urls = array();
	while($row = mysqli_fetch_array($result,MYSQLI_ASSOC)) {
		$pos = md5($row['address']) % $nodes; 
		
		//echo $hash . "\n";
		
		if ($pos == $node){
			$urls[] = $row;
		}
	}
	return $urls;
}

function endjob($connection){
	$query = "select * from master where control_name = 'end_crawl'";
  	$result = mysqli_query($connection,$query) or die(mysqli_error() . "\n");
	
	if(mysqli_num_rows($result) != 1){die("There is not crawl control!\n");}

	if($row = mysqli_fetch_array($result,MYSQLI_ASSOC)) {
		$end = $row['control_value']; 
		if ($end == 'yes'){
			return true;
			//die("Crawl Job terminated by control signal\n");
		}
	}
	return false;
}

function terminate($connection){
	$query = "update master set control_value = 'yes' where control_name = 'end_crawl'";
  	$result = mysqli_query($connection,$query) or die(mysqli_error() . "\n");
	return true;
}


function prioritize(&$fqueues, &$seed){

$unique = array();

foreach($seed as $url){
	$rank = $url['rank'];
	
	//Divide hostnames between crawling nodes
		$fqueues[$rank]->enqueue($url);
		$unique[$url['idfrontier']] = 1;
}
$unique_urls = count($unique);
return $unique_urls;
}

function depleted(&$queues){

if(count($queues) == 0){return true;}

foreach($queues as $q){
	if($q->isEmpty() === false){return false;}
}
return true;
}

function fdequeue(&$queues){
	$rank = count($queues) -1;

	while($rank > 0 ){
		if($queues[$rank]->isEmpty()){
			$rank -=1;
			continue;
		}else{
			$url = $queues[$rank]->dequeue();
			return $url;
		}
	}
	return false;
}

function bdequeue(&$queues, $id){
		
		if($id < count($queues) and ($queues[$id]->isEmpty() === false)){
			$url = $queues[$id]->dequeue();
			return $url;
		}
		
		return false;
}

function benqueue(&$queues, $id, $url){
	
		if($id < count($queues)){
			$queues[$id]->enqueue($url);
			return true;
		}
		return false;
}


function init_bqueues(&$bqueues, $max){
	for($i = 0; $i < $max; $i++){
		$bqueues[$i] = new SplQueue();
	}
	return true;
}


function init_fqueues(&$fqueues, $F){
	for($i = 0; $i < $F; $i++){$fqueues[] = new SplQueue();}
	return true;
}


function tbl_empty($connection, $table){
	$query = "SELECT * FROM $table LIMIT 1";
  	$result = mysqli_query($connection,$query) or die(mysqli_error() . '\n');
	
	if(mysqli_num_rows($result) > 0) {
		return false;
	}
	return true;
}

function complete_url($url){
	if ( $parts = parse_url($url) ) {
   		if ( !isset($parts["scheme"]) ){
       		$url = "http://$url";
       		return $url;
   		}
	}
}

//to be implemented in the future
function url_filter($url){
	return true;
}

function validate_url($url){
	if (preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $url)) {
		//Also add filters here!!!!
		//Urlfilter
		return url_filter($url);
	}
	else {
		return false;
	}
}

function clear_tbl($connection, $table){
		mysqli_query($connection,"TRUNCATE TABLE $table") or die(mysqli_error() . '\n');
}

function bytesToSize($bytes, $precision = 2)
{  
    $kilobyte = 1024;
    $megabyte = $kilobyte * 1024;
    $gigabyte = $megabyte * 1024;
    $terabyte = $gigabyte * 1024;
   
    if (($bytes >= 0) && ($bytes < $kilobyte)) {
        return $bytes . ' B';
 
    } elseif (($bytes >= $kilobyte) && ($bytes < $megabyte)) {
        return round($bytes / $kilobyte, $precision) . ' KB';
 
    } elseif (($bytes >= $megabyte) && ($bytes < $gigabyte)) {
        return round($bytes / $megabyte, $precision) . ' MB';
 
    } elseif (($bytes >= $gigabyte) && ($bytes < $terabyte)) {
        return round($bytes / $gigabyte, $precision) . ' GB';
 
    } elseif ($bytes >= $terabyte) {
        return round($bytes / $terabyte, $precision) . ' TB';
    } else {
        return $bytes . ' B';
    }
}

function seed($connection){

	if(tbl_empty($connection,'frontier')){
		echo "Reading seed urls from seed.txt into frontier table\n";
		$fh = fopen('seed.txt','r')  or die('Error: ' . "Please provide seed.txt file and ensure it's readable by the crawler\n");
		while ($url = fgets($fh)) {
			
			$url = complete_url(trim(strtolower($url)));
			
			//echo "URL: $url\n";

			if(!validate_url($url)){
			echo "Invalid URL: $url\n";
			continue;
  			}else{
  				echo "Valid URL: $url => ";
  				$rank = get_rank($connection,$url);
  				$hash = md5($url);
  				$host = parse_url($url, PHP_URL_HOST);
  				//$ipaddr = gethostbyname ($host . '.');
  				$ipaddr = gethost($host . '.');
  				if(empty($ipaddr)){continue;}
  				echo "$ipaddr\n";
  				$time = time();
  				$time2 = time() + 10;
  				$query = "INSERT IGNORE INTO frontier (idfrontier, url, rank, ctime, ctime2, address) VALUES ('$hash','$url','$rank','$time', '$time2', '$ipaddr')";
  				$result = mysqli_query($connection,$query) or die('Error: ' . mysqli_error($connection) . '\n');
  			}
		}
		fclose($fh);
		rename('seed.txt','seed.old');
	}
	if(tbl_empty($connection,'frontier')){ return false;}
	return true;
}




?>