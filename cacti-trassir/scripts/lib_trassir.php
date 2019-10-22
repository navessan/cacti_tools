<?php

/*
 functions for trassir CCTV server 
*/

include_once(dirname(__FILE__)."/restclient.php");

#
# Get API sid from cache or login
# use apt-get install php-memcache memcached
#
function client_trassir_get_sid($api,$api_username,$api_password)
{
	// Check that the class exists before trying to use it
	if (class_exists('Memcache')) {
		$MCache_Host = 'localhost';
		$MCache_Port = '11211';
		$cachekey = 'ss_trassir:'.$api_username;
		
		$MemCache = new Memcache;
		$MemCache->connect($MCache_Host, $MCache_Port);
		$session_key = $MemCache->get($cachekey);
	}
	
	if(! $session_key){
		$session_key=client_trassir_login($api,$api_username,$api_password);
		if(isset($MemCache) && strlen($session_key)>0)
			$MemCache->set($cachekey, $session_key, FALSE, 30);
	}
	
	return $session_key;
}
		
function client_api_init($api_url)
{
	$api = new RestClient([
		'base_url' => $api_url
		,'headers' =>['Accept'=>"application/json"]
		,'curl_options'=>array(CURLOPT_FOLLOWLOCATION=>true
								,CURLOPT_SSL_VERIFYPEER=>false
								,CURLOPT_SSL_VERIFYHOST=>false)
		//,'format' => "json"
	]);
	//allows you to receive decoded JSON data as an array.
	$api->register_decoder('json', function($data){
		$comment_position = strripos($data, '/*');
		if($comment_position)
			$data = substr($data, 0, $comment_position);	//removing comment tail from data
		return json_decode($data, TRUE);
	});
	
	return $api;
}

function client_trassir_login($api,$api_username,$api_password)
{
	if($api==null)
		return false;
	
	/*
		https://192.168.1.200:8080/login?username=Admin&password=987654321
	*/
	
	$result = $api->get("/login", ['username' => $api_username,'password'=>$api_password]);
	if($result->info->http_code != 200)
	{
		log_trassir("Authorization Error:" );
		log_trassir(var_export($result, true));
	}
	//var_dump($result);

	$res=$result->decode_response();
		
	if(!$res['success'])
	{
		log_trassir("Authorization failed:");
		log_trassir('error_code=;'.$res['error_code']);
		return null;
	}
	$session_key=$res['sid'];
	
	return $session_key;
}

function client_trassir_health($api,$session_key)
{
	$arg="/health/";
	//echo $arg.":\n";
	$data=client_trassir_get_data($api, $arg, $session_key);
	//print_r($data);
	return $data;
}

function client_trassir_get_data($api, $path, $session_key, $dump_result=0)
{
	$result = $api->get($path, ['sid' => $session_key]);
	if($result->info->http_code != 200)
	{
		var_dump($result);
		log_trassir( "API Call Error on path:".$path."\n");
		return null;
	}
	if ($dump_result)
		print_r($result);
		
	$res=$result->decode_response();
	return $res;
}

function log_trassir($message)
{
	//echo $message;
	$filename="/tmp/cacti_trassir_sdk.log";
	file_put_contents($filename,date('Y-m-d H:i:s ').$message."\n",FILE_APPEND);
}

?>