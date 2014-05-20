<?php
include("functions.php");

$count = 0;
$max_count = 10;
$master = "107.170.38.21";
$ipnode = ipnode();
$connection = db_connect($master, $ipnode);
$fqueues = array(); //Priority Queues (Front F queues - 10)
$bqueues = array(); //Host Queues (Back B queues - 10)
$F = 11; //ranks from 0 to 10
$maxB = 10; //maxmium unique hosts per node
$nodes = 5; //maximum number of crawler nodes allowed to join the job
$node = false; //will automatically register on database and be replaced by the ID number of the node
$hostmap = array();
$heap = new MinHeap();
$loop = 2; //never mid this
//var_dump($connection);die();

init_bqueues($bqueues, $maxB);

init_fqueues($fqueues, $F);

//Only mess with the database if you're master
if($ipnode == $master){
	clear_tbl($connection, "nodes");
}

//If we have no node ID assigned, register with database
if(!$node){
	$node = register_node($connection, $nodes);
}

echo "Node #$node ==> IP: $ipnode\n";

//clear_tbl($connection, "frontier");

//die();

while(mysqli_ping($connection) and $loop > 0 and !endjob($connection)){
//echo "1 ";
//If there is a file caled seed.txt in same directory, import urls and rename file to seed.old
seed($connection) or die("There are no urls to crawl!\n");

//die();

if(depleted($fqueues)){//depleted queues
	echo "F queue DEPLETED\n";
	$seed = get_frontier($connection, $nodes, $node);
	//print_r($seed);die();

	//prioritize urls by rank and return number of unique hosts.
	$B = prioritize($fqueues, $seed);
	echo "$B Unique Hosts\n";
	//print_r($B);die();

}

//check if heap is empty
if($heap->isEmpty()){
	//get top host from highest non empty f queue
	//find host in hostmap
	//if found, add to respective B queue, else, add new entry and create new B queue (make sure hostmap size does not exceed B)
	//add host to B queue
	//add host to hostmap
	//add entry to heap
	//repeat until found B hosts or F queues are empty

	while(count($hostmap) <= $maxB){
		$mapsize = count($hostmap);
		echo "Hostmap Size: $mapsize\n";

		$urlobj = fdequeue($fqueues);
		

		if($urlobj === false){break;} //Front queues are empty

		$host = $urlobj['address'];
		
		$bqueue = 0;

		if(empty($hostmap)){
			echo "Hostmap is empty. Adding URL object to back queue 0\n";
			$bqueue = 0;
			$hostmap[$host] = $bqueue;

		}else{
			if(array_key_exists($host, $hostmap)){
				$bqueue = $hostmap[$host];
			}else{
				$bqueue = count($hostmap);
				$hostmap[$host] = $bqueue;
			}
		}

		//Add entry to back queue
		echo "Added URL to B queue\n";
		benqueue($bqueues, $bqueue, $urlobj);
		//print_r($bqueues); echo "\n"; die();
		

		//Add entry to heap
		$heapentry = array('bqueue' => $bqueue,'time' => $urlobj['ctime2']);
		$heap->insert($heapentry);
		
	}

}else{
	//fetch host from heap
	$next = $heap->extract();
	
	//time we need to wait before contacting server again
	$ttf = $next['time'] - time();
	

	if($ttf > 0){

		//sleep for amount of time necessary
		sleep($ttf);
	}


//get B queue number from hostmap
$bqueueid = $next['bqueue'];

//get url brom B queue
$url = bdequeue($bqueues, $bqueueid);
echo "Pulled {$url['url']} from B queue\n";

if($url === false){
	foreach ($hostmap as $host => $queue) {
		if($queue == $bqueueid){
			unset($hostmap[$host]);
			break;
		}
	}
}else{

//Fetch url from server
$url['images'] = 0;
$url['links'] = array();

//Update url timestamp on database
$url['ctime2'] = round($url['ctime2'] + ($url['ctime2'] - $url['ctime'])*10);
$url['ctime'] = time();


// get DOM from URL or file
$url['size'] = memory_get_usage();
$html = file_get_html($url['url']);
$url['size'] = bytesToSize(memory_get_usage() - $url['size']);

// find all link
foreach($html->find('a') as $e){
    $link = $e->href;
    if(!validate_url($link)){continue;};
    $url['links'][] = mysqli_real_escape_string($connection, $link);
}
// find all image
foreach($html->find('img') as $e){
    //echo $e->src . '<br>';
    $url['images'] +=1;
    }	
// extract text from HTML
$url['content'] = md5($html->plaintext);
$html->clear();
unset($html);
//print_r($url); echo "\n"; die();
store_url($url, $connection);

}

}

//echo "---------------------------Front Queues---------------------------\n";
//print_r($fqueues); echo "\n\n";

//echo "---------------------------Back Queues---------------------------\n";
//print_r($bqueues); echo "\n\n";
//$loop -=1;
}

if($ipnode == $master){ terminate($connection);}
?>