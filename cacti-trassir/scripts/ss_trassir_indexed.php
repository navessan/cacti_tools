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
	print call_user_func_array("ss_trassir_indexed", $_SERVER["argv"]);
}

#
# main function
#
function ss_trassir_indexed($protocol_bundle="", $object_type="",
	$cacti_request="", $data_request="", $data_request_key="") {

	#
	# 1st function argument contains the protocol-specific bundle
	#
	# use '====' matching for strpos in case colon is 1st character
	#
	if ((trim($protocol_bundle) == "") || (strpos($protocol_bundle, ":") === FALSE)) {

		echo ("FATAL: No parameter API bundle provided\n");
		ss_trassir_indexed_syntax();
		return;
	}

	$protocol_array = explode(":", $protocol_bundle);

	if (count($protocol_array) < 5) {
		echo ("FATAL: Not enough elements in API parameter bundle\n");
		ss_trassir_indexed_syntax();
		return;
	}

	if (count($protocol_array) > 5) {
		echo ("FATAL: Too many elements in API parameter bundle\n");
		ss_trassir_indexed_syntax();
		return;
	}
	
	#
	# 1st bundle element is $api_protocol
	#
	$api_protocol = trim($protocol_array[0]);

	if ($api_protocol == "") {
		echo ("FATAL: Protocol not specified in API parameter bundle\n");
		ss_trassir_indexed_syntax();
		return;
	}
	
	#
	# 2st bundle element is $api_host
	#
	$api_host = trim($protocol_array[1]);

	if ($api_host == "") {
		echo ("FATAL: Hostname not specified in API parameter bundle\n");
		ss_trassir_indexed_syntax();
		return;
	}	

	#
	# 3st bundle element is $api_port
	#
	$api_port = trim($protocol_array[2]);

	if ($api_port == "") {
		echo ("FATAL: port not specified in API parameter bundle\n");
		ss_trassir_indexed_syntax();
		return;
	}	

	#
	# 4nd bundle element is $ipmi_username (NULL username is okay)
	#
	$api_username = trim($protocol_array[3]);

	#
	# 5rd bundle element is $ipmi_password (NULL password is okay)
	#
	$api_password = trim($protocol_array[4]);

	#
	# 2nd function argument is $object_type
	#
	$object_type = strtolower(trim($object_type));

	if (($object_type != "channels") &&
		($object_type != "archive")) {

		echo ("FATAL: $object_type is not a valid object type\n");
		ss_trassir_indexed_syntax();
		return;
	}
	
	#
	# 3rd function argument is $cacti_request
	#
	$cacti_request = strtolower(trim($cacti_request));

	if ($cacti_request == "") {
		echo ("FATAL: No Cacti request provided\n");
		ss_trassir_indexed_syntax();
		return;
	}

	if (($cacti_request != "index") &&
		($cacti_request != "query") &&
		($cacti_request != "get")) {

		echo ("FATAL: \"$cacti_request\" is not a valid Cacti request\n");
		ss_trassir_indexed_syntax();
		return;
	}
	
	#
	# remaining function arguments are $data_request and $data_request_key
	#
	if (($cacti_request == "query") || ($cacti_request == "get")) {
		$data_request = trim($data_request);
		if ($data_request == "") {
			echo ("FATAL: No data requested for Cacti \"$cacti_request\" request\n");
			ss_trassir_indexed_syntax();
			return;
		}

		#
		# get the index variable
		#
		if ($cacti_request == "get") {
			$data_request_key = trim($data_request_key);

			if ($data_request_key == "") {
				echo ("FATAL: No index value provided for \"$data_request\" data request\n");
				ss_trassir_indexed_syntax();
				return;
			}
		}

		#
		# clear out spurious command-line parameters on query requests
		#
		else {
			$data_request_key = "";
		}
	}
	
	$api_url=$api_protocol."://".$api_host.":".$api_port;
	//echo "api_url=$api_url\n";
	$api = client_api_init($api_url);

	$session_key="";
	$session_key=client_trassir_get_sid($api,$api_username,$api_password);

	if( strlen($session_key)==0)
	{
		log_trassir("session_key null length!");
		return;
	}
	//echo "session_key=".$session_key."\n";

	#
	# generate output
	#
			
/*
name
stats/
	kbps_hw_merge
	fps_main
	last_error_hw_merge
	fps_ss
	kbps_ss
	debt_sec_hw_merge
	kbps_main
*/
/*
serial
disk_id
capacity_gb
model
stats/
	archive_size_days
	sync_mode
	in_use_by_db
	disk_write_mbs
	last_error_code
	disk_read_mbs
	current_speed_mbs
	unavailable
	error_counter 
*/
	
	#
	# return output data according to $cacti_request
	#
	switch ($cacti_request) {
		#
		# for "index" requests, dump the device column
		#
		case "index":
			$arg="/settings/".$object_type."/";
			$data=client_trassir_get_data($api, $arg, $session_key); 
			
			if(isset($data["subdirs"])){
				foreach ($data["subdirs"] as $subdir_guid)
					echo $subdir_guid."\n";		
			}
			break;

		#
		# for "query" requests, dump the requested columns
		#
		case "query":
			$dir_path="/settings/".$object_type."/";
			$value_name=$data_request;
			
			$arg=$dir_path;
			$data=client_trassir_get_data($api, $arg, $session_key); 
			
			if(isset($data["subdirs"])){
				foreach ($data["subdirs"] as $subdir_guid){
					if($value_name != 'subdir_guid'){
						$arg=$dir_path.$subdir_guid."/".$value_name;
						$value_data=client_trassir_get_data($api, $arg, $session_key);
						echo $subdir_guid .":". $value_data["value"]."\n";
					}else
						echo $subdir_guid .":". $subdir_guid."\n";
				}			
			}
			break;

		#
		# for "get" requests, dump the requested data for the requested value
		#
		case "get":
			$dir_path="/settings/".$object_type."/";
			$value_name=$data_request;
			$subdir_guid=$data_request_key;
			
			$arg=$dir_path.$subdir_guid."/".$value_name;
			$value_data=client_trassir_get_data($api, $arg, $session_key);
			echo $value_data["value"];
			break;		
	}	
}

/* display the syntax */
function ss_trassir_indexed_syntax() {
	echo ("Usage: ss_trassir.php <protocol>:<host>:<port>:[<api_username>]:[<api_password>] \
		<health|channels|archive> (index|query <fieldname>|get <fieldname> <index>)\n".
	" example usage\n".
	"php ss_trassir.php https:192.168.1.1:8080:admin:password health\n".
	"php ss_trassir.php https:192.168.1.1:8080:admin:password archive index\n".
	"php ss_trassir.php https:192.168.1.1:8080:admin:password channels query name\n".
	"php ss_trassir.php https:192.168.1.1:8080:admin:password channels get name guid\n");
}

?>