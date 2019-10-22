<?php
/*
 cacti script for trassir CCTV server 
*/

include_once(dirname(__FILE__)."/lib_trassir.php");

error_reporting(1);

#
# call the main function manually if executed outside the Cacti script server
#
if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . '/../include/cli_check.php');
	array_shift($_SERVER["argv"]);
	print call_user_func_array("ss_trassir_status", $_SERVER["argv"])."\n";
}

#
# main function
#
function ss_trassir_status($protocol="", $host="", $port="", $api_username="", $api_password="", $object_type="")
{
	if(trim($host)=="" || trim($object_type)=="")
	{
		echo ("FATAL: No trassir parameters provided\n");
		ss_trassir_syntax();
		return;
	}
	
	if (($object_type != "health") &&
		($object_type != "channels") &&
		($object_type != "archive")) {

		echo ("FATAL: \"$object_type\" is not a valid request\n");
		ss_trassir_syntax();
		return;
	}
		
	$trimchr = '\'":'; // default characters to remove
	
	$host = trim($host, $trimchr);
	$api_port = trim($port, $trimchr);
	$api_username = trim($api_username, $trimchr);
	$api_password = trim($api_password, $trimchr);
		
    $protocol = ($protocol == '' ? 'https' : $protocol);
	$api_port = ($port == '' ? '8079' : $api_port);
	
	$api_url=$protocol."://".$host.":".$api_port;
	$api = client_api_init($api_url);
	
	$session_key="";
	$session_key=client_trassir_get_sid($api,$api_username,$api_password);

	if( strlen($session_key)==0)
	{
		log_trassir("session_key null length!");
		//return;
	}
	//echo "session_key=".$session_key."\n";

	$channels_vals=array('stats/kbps_hw_merge',
		'stats/fps_main',
		'stats/last_error_hw_merge',
		'stats/fps_ss',
		'stats/kbps_ss',
		'stats/debt_sec_hw_merge',
		'stats/kbps_main');

	$archive_vals=array('capacity_gb',
		'stats/archive_size_days',
		'stats/sync_mode',
		'stats/in_use_by_db',
		'stats/disk_write_mbs',
		'stats/last_error_code',
		'stats/disk_read_mbs',
		'stats/current_speed_mbs',
		'stats/unavailable',
		'stats/error_counter');

	if($object_type=="health"){
		$data=client_trassir_health($api,$session_key);
	}
	else{
	
		if($object_type=="channels")
			$vals=$channels_vals;
		
		if($object_type=="archive")
			$vals=$archive_vals;
		
		$dir_path="/settings/".$object_type."/";
					
		$arg=$dir_path;
		$dirs=client_trassir_get_data($api, $arg, $session_key); 
		
		foreach($vals as $value_name)
			$data[str_replace("/","_",$value_name)]=0;
		
		if(isset($dirs["subdirs"])){
			foreach ($dirs["subdirs"] as $subdir_guid){
				foreach($vals as $value_name){
					$arg=$dir_path.$subdir_guid."/".$value_name;
					$value_data=client_trassir_get_data($api, $arg, $session_key);
					$data[str_replace("/","_",$value_name)] += floatval($value_data["value"]);
					//echo $subdir_guid .":\t". $value_name .":".$value_data["value"]."\n";
				}
			}			
		}
	}		
	//print_r($data);
	
	$result="";
	
	foreach($data as $key => $value)
	    $result.="$key:$value ";	
	
	//$result="disks:1 database:1 channels_total:48 channels_online:48 uptime:396403 cpu_load:10.15 network:1 automation:1 disks_stat_main_days:68.99 disks_stat_priv_days:0.00 disks_stat_subs_days:69.05 ";
	//log_trassir($result);
	return trim($result);	
}

/* display the syntax */
function ss_trassir_syntax() {
	echo ("Usage: ss_trassir.php <protocol> <host> <port> [<api_username>] [<api_password>] <health>\n".
	" example usage\n".
	"php ss_trassir.php https 192.168.1.1 8080 admin password health \n".
	"php ss_trassir.php https 192.168.1.1 8080 \"\" password channels\n".
	"php ss_trassir.php https 192.168.1.1 8080 admin password archive\n");
}

?>