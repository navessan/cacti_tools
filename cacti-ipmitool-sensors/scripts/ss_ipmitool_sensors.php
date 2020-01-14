<?php

#
# ss_ipmitool_sensors.php
# version 0.5
# June 26, 2009
#
# Copyright (C) 2006-2009, Eric A. Hall
# http://www.eric-a-hall.com/
#
# This software is licensed under the same terms as Cacti itself
#

#
# load the Cacti configuration settings if they aren't already present
#
$no_http_headers = true;
if (isset($config) == FALSE) {

	if (file_exists(dirname(__FILE__) . "/../include/config.php")) {
		include_once(dirname(__FILE__) . "/../include/config.php");
	}

	if (file_exists(dirname(__FILE__) . "/../include/global.php")) {
		include_once(dirname(__FILE__) . "/../include/global.php");
	}

	if (isset($config) == FALSE) {
		echo ("FATAL: Unable to load Cacti configuration files \n");
		return;
	}
}

#
# call the main function manually if executed outside the Cacti script server
#
if (isset($GLOBALS['called_by_script_server']) == FALSE) {

	array_shift($_SERVER["argv"]);
	print call_user_func_array("ss_ipmitool_sensors", $_SERVER["argv"]);
}

#
# main function
#
function ss_ipmitool_sensors($protocol_bundle="", $sensor_type="",
	$cacti_request="", $data_request="", $data_request_key="") {

	#
	# 1st function argument contains the protocol-specific bundle
	#
	# use '====' matching for strpos in case colon is 1st character
	#
	if ((trim($protocol_bundle) == "") || (strpos($protocol_bundle, ":") === FALSE)) {

		echo ("FATAL: No IPMI parameter bundle provided\n");
		ss_ipmitool_sensors_syntax();
		return;
	}

	$protocol_array = explode(":", $protocol_bundle);

	if (count($protocol_array) < 3) {

		echo ("FATAL: Not enough elements in IPMI parameter bundle\n");
		ss_ipmitool_sensors_syntax();
		return;
	}

	if (count($protocol_array) > 3) {

		echo ("FATAL: Too many elements in IPMI parameter bundle\n");
		ss_ipmitool_sensors_syntax();
		return;
	}

	#
	# 1st bundle element is $ipmi_hostname
	#
	$ipmi_hostname = trim($protocol_array[0]);

	if ($ipmi_hostname == "") {

		echo ("FATAL: Hostname not specified in IPMI parameter bundle\n");
		ss_ipmitool_sensors_syntax();
		return;
	}

	#
	# 2nd bundle element is $ipmi_username (NULL username is okay)
	#
	$ipmi_username = trim($protocol_array[1]);

	#
	# 3rd bundle element is $ipmi_password (NULL password is okay)
	#
	$ipmi_password = trim($protocol_array[2]);

	#
	# 2nd function argument is $sensor_type
	#
	$sensor_type = strtolower(trim($sensor_type));

	if (($sensor_type != "fan") &&
		($sensor_type != "temperature") &&
		($sensor_type != "current") &&		
		($sensor_type != "voltage")) {

		echo ("FATAL: $sensor_type is not a valid sensor type\n");
		ss_ipmitool_sensors_syntax();
		return;
	}

	#
	# 3rd function argument is $cacti_request
	#
	$cacti_request = strtolower(trim($cacti_request));

	if ($cacti_request == "") {

		echo ("FATAL: No Cacti request provided\n");
		ss_ipmitool_sensors_syntax();
		return;
	}

	if (($cacti_request != "index") &&
		($cacti_request != "query") &&
		($cacti_request != "get")) {

		echo ("FATAL: \"$cacti_request\" is not a valid Cacti request\n");
		ss_ipmitool_sensors_syntax();
		return;
	}

	#
	# remaining function arguments are $data_request and $data_request_key
	#
	if (($cacti_request == "query") || ($cacti_request == "get")) {

		$data_request = strtolower(trim($data_request));

		if ($data_request == "") {

			echo ("FATAL: No data requested for Cacti \"$cacti_request\" request\n");
			ss_ipmitool_sensors_syntax();
			return;
		}

		if (($data_request != "sensordevice") &&
			($data_request != "sensorname") &&
			($data_request != "sensorreading")) {

			echo ("FATAL: \"$data_request\" is not a valid data request\n");
			ss_ipmitool_sensors_syntax();
			return;
		}

		#
		# get the index variable
		#
		if ($cacti_request == "get") {

			$data_request_key = strtolower(trim($data_request_key));

			if ($data_request_key == "") {

				echo ("FATAL: No index value provided for \"$data_request\" data request\n");
				ss_ipmitool_sensors_syntax();
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
	#
	# Get data from cache or ipmitool
	# use apt-get install php-memcache memcached
	#
	$MCache_Host = 'localhost';
	$MCache_Port = '11211';
	$cachekey = 'ss_ipmitool_sensors:'.$ipmi_hostname.'-'.$sensor_type;

	$Cache = new Memcache;
	$Cache->connect($MCache_Host, $MCache_Port);	
	
	$sensor_array=$Cache->get($cachekey);
	
	if(!$sensor_array){
		$sensor_array=ss_ipmitool_sensors_ipmi($ipmi_hostname, $ipmi_username, $ipmi_password
		, $sensor_type, $data_request);
	}

	#
	# verify that the sensor_array exists and has data
	#
	if ((isset($sensor_array) == FALSE) ||
		(count($sensor_array) == 0)) {
			
		$message="FATAL: No matching sensors were returned from IPMI\n";
		
		if(isset($GLOBALS['called_by_script_server']))
			cacti_log($message);
		else
			echo $message;
		
		return;
	}
	
	if(isset($Cache))
		$Cache->set($cachekey, $sensor_array, FALSE, 30);

	#
	# generate output
	#
	foreach ($sensor_array as $sensor) {

		#
		# return output data according to $cacti_request
		#
		switch ($cacti_request) {

			#
			# for "index" requests, dump the device column
			#
			case "index":

				echo ($sensor['index'] . "\n");
				break;

			#
			# for "query" requests, dump the requested columns
			#
			case "query":

				switch ($data_request) {

					case "sensordevice":

						echo ($sensor['index'] . ":" . $sensor['index'] . "\n");
						break;

					case "sensorname":

						echo ($sensor['index'] . ":" . $sensor['name'] . "\n");
						break;

					case "sensorreading":

						echo ($sensor['index'] . ":" . $sensor['reading'] . "\n");
						break;
				}

				break;

			#
			# for "get" requests, dump the requested data for the requested sensor
			#
			case "get":

				#
				# skip the current row if it isn't the requested sensor
				#
				if (strtolower($sensor['index']) != $data_request_key) {

					break;
				}

				switch ($data_request) {

					case "sensordevice":

						echo ($sensor['index'] . "\n");
						break;

					case "sensorname":

						echo ($sensor['name'] . "\n");
						break;

					case "sensorreading":

						if (isset($GLOBALS['called_by_script_server']) == TRUE) {

							return($sensor['reading']);
						}

						else {
							echo ($sensor['reading'] . "\n");
						}

						break;
				}

				break;
		}
	}
}

function ss_ipmitool_sensors_ipmi($ipmi_hostname, $ipmi_username, $ipmi_password
			, $sensor_type, $data_request) {
	#
	# build the ipmitool command, starting with the location of the ipmitool executable
	#
	$ipmitool_command = exec('which ipmitool 2>/dev/null');

	if ($ipmitool_command == "") {

		echo ("FATAL: \"ipmitool\" cannot be found in the user path\n");
		return;
	}

	#
	# fill in the blanks for ipmitool
	#
	$ipmitool_command = "$ipmitool_command -I lanplus -L user -H $ipmi_hostname";

	#
	# check for username and use NULL if not provided
	#
	if ($ipmi_username != "") {

		$ipmitool_command = $ipmitool_command . " -U $ipmi_username";
	}

	else {
		$ipmitool_command = $ipmitool_command . " -U \"\"";
	}

	#
	# check for password and use NULL if not provided
	#
	if ($ipmi_password != "") {

		$ipmitool_command = $ipmitool_command . " -P $ipmi_password";
	}

	else {
		$ipmitool_command = $ipmitool_command . " -P \"\"";
	}

	#
	# set the sensor request type
	#
	$ipmitool_command = $ipmitool_command . " sdr type $sensor_type";

	#
	# lastly, redirect STDERR to STDOUT so we can trap error text
	#
	$ipmitool_command = $ipmitool_command . " 2>&1";

	#
	# execute the command and process the results array
	#
	$ipmitool_output = exec($ipmitool_command, $ipmitool_array);

	#
	# verify that the response contains expected data structures
	#
	if ((isset($ipmitool_array) == FALSE) ||
		(count($ipmitool_array) == "0") ||
		(substr_count($ipmitool_array[0], "|") < 4) ||
		(trim($ipmitool_array[0]) == "")) {

		$message= "FATAL: Incomplete results from ipmitool";

		#
		# include any response data from ipmitool if available
		#
		if (trim($ipmitool_array[0] != "")) {

			$message.= " (\"" . substr($ipmitool_array[0], 0, 32) . "...\")";
		}

		elseif (trim($ipmitool_output) != "") {

			$message.= " (\"" . substr($ipmitool_output, 0, 32) . "...\")";
		}

		if(isset($GLOBALS['called_by_script_server']))
			cacti_log($message."\n");
		else
			echo $message."\n";
		
		return;
	}

	#
	# create a sensor array from the wmic output
	#
	$sensor_count = 0;

	foreach ($ipmitool_array as $ipmitool_response) {

		#
		# empty line means no more sensors of the named type were found
		#
		if (trim($ipmitool_response) == "") {

			$sensor_count++;
			break;
		}

		#
		# short IDs don't exist in ipmitool, but hardware sequence is static
		#
		# create a psuedo-device by adding +1 to $sensor_count
		#
		$sensor_array[$sensor_count]['index'] = ($sensor_count + 1);

		#
		# use regex to locate the sensor name and value
		#
		# exit if no match found (not all text values are errors)
		#
		if (preg_match("/^(.+?)\s+\|.+\|.+\|.+\|\s([\-|\.|\d]+)\s/",
			$ipmitool_response, $scratch) == 0) {

			$sensor_array[$sensor_count]['name'] = "";
			$sensor_array[$sensor_count]['reading']="";
			//$sensor_count++;
			//break;		//no ignore text values
		}

		#
		# matches were found so use them
		#
		else {
			#
			# if the name is unknown, use the device index name
			#
			if (trim($scratch[1]) == "") {

				$scratch[1] = $sensor_type . $sensor_array[$sensor_count]['index'];
			}

			#
			# if the name is long and has dashes, trim it down
			#
			while ((strlen($scratch[1]) > 18) && (strrpos($scratch[1], "-") > 12)) {

				$scratch[1] = (substr($scratch[1],0, (strrpos($scratch[1], "-"))));
			}

			#
			# if the name is long and has spaces, trim it down
			#
			while ((strlen($scratch[1]) > 18) && (strrpos($scratch[1], " ") > 12)) {

				$scratch[1] = (substr($scratch[1],0, (strrpos($scratch[1], " "))));
			}

			#
			# if the name is still long, chop it manually
			#
			if (strlen($scratch[1]) > 18) {

				$scratch[1] = (substr($scratch[1],0,17));
			}

			$sensor_array[$sensor_count]['name'] = trim($scratch[1]);
			$sensor_array[$sensor_count]['reading'] = trim($scratch[2]);
		}

		#
		# remove malformed readings from the current row's value field in $sensor_arrayso
		#
		# the readings must be removed instead of zeroed, so that RRD will store a NULL
		#
		if ($data_request == "sensorreading") {

			#
			# remove non-numeric sensor readings
			#
			if (!is_numeric($sensor_array[$sensor_count]['reading'])) {

				$sensor_array[$sensor_count]['reading'] = "";
			}

			#
			# remove impossibly-high temperature and voltage readings
			#
			if ((($sensor_type == "temperature") || ($sensor_type == "voltage")) &&
				($sensor_array[$sensor_count]['reading'] >= "255")) {

				$sensor_array[$sensor_count]['reading'] = "";
			}
		}

		#
		# increment the sensor counter
		#
		$sensor_count++;
	}
	return $sensor_array;
}

#
# display the syntax
#
function ss_ipmitool_sensors_syntax() {

	echo ("Usage: ss_ipmitool_sensors.php <hostname>:[<ipmi_username>]:[<ipmi_password>] \ \n" .
 	"      (FAN|TEMPERATURE|VOLTAGE|CURRENT) (index|query <fieldname>|get <fieldname> <sensor>) \n");
}

?>
