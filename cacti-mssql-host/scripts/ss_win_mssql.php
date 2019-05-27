<?php

/*
select and install appropriate drivers
dblib:
	apt install php7.0-sybase
or
sqlsrv:
	apt-get install mssql-tools unixodbc-dev
	pecl install sqlsrv pdo_sqlsrv
	
for faster polling use memcache 
	apt install memcached php-memcache	
*/

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . '/../include/cli_check.php');
	array_shift($_SERVER['argv']);
    print call_user_func_array("ss_win_mssql", $_SERVER["argv"])."\n";
}

function ss_win_mssql($hostname, $port, $cmd, $username = NULL, $password = NULL) {
	if(trim($hostname)=="" || trim($cmd)=="") {
		echo ("FATAL: No input parameters provided\n");
		ss_win_mssql_syntax();
		return;
	}

	//list($host, $port) = explode(':', $hostname);
	$host=$hostname;
	$port = ($port == '' ? '1433' : $port);
	$username = ($username == NULL ? 'cactistats' : $username);
	$password = ($password == NULL ? 'CHANGEME' : $password);
	
	$database_type="dblib";
	$database_charset="UTF-8";
	$database_default="master";

	$ret = '';

	$MCache_Host = 'localhost';
	$MCache_Port = '11211';
	$cachekey = 'ss_win_mssql:'.$host.'-'.$port;
	$vals=null;
	
	// Check that the class exists before trying to use it
	if (class_exists('Memcache')) {
		$MemCache = new Memcache;
		$MemCache->connect($MCache_Host, $MCache_Port);
		$vals = $MemCache->get($cachekey);
	}
	
	if (! $vals ){
		
		$dsn = "$database_type:host=$host:$port;dbname=$database_default;charset=$database_charset";
		//echo $dsn."\n";
		
		$opt = array(
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			//PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM
		);
			
		try {
			$conn = new PDO($dsn, $username, $password, $opt);
		}
		catch(PDOException $e) {
			echo "FATAL:". $e->getMessage()."\n";
			//log_mssql("FATAL:". $e->getMessage()."add: $dsn, $username, $password");
		}

		$sql = "SELECT SERVERPROPERTY('productversion')";
		$stmt = $conn->query($sql);
		$rows = $stmt->fetchAll();

		list($server_version) =$rows;
				
		$perf_counter_table = (substr($server_version[0], 0, 1) == "8" ? 'sysperfinfo' : 'sys.dm_os_performance_counters');

		$sql = "SELECT [counter_name], [cntr_value] FROM ".$perf_counter_table." ".
				"WHERE ([instance_name] = '' OR [instance_name] = '_Total') AND (".
				"([object_name] LIKE ('%Plan Cache%') AND [counter_name] IN ".
				  "('Cache Hit Ratio', 'Cache Hit Ratio Base')) OR ".
				"([object_name] LIKE ('%Buffer Manager%') AND [counter_name] IN ".
				  "('Buffer Cache Hit Ratio', 'Buffer Cache Hit Ratio Base', 'Page reads/sec', 'Page writes/sec')) OR ".
				"([object_name] LIKE ('%General Statistics%') AND [counter_name] IN ".
				  "('Active Temp Tables', 'User Connections')) OR ".
				"([object_name] LIKE ('%Databases%') AND [counter_name] IN ".
				  "('Transactions/sec', 'Log Cache Hit Ratio', 'Log Cache Hit Ratio Base', 'Log Flushes/sec', ".
					"'Log Bytes Flushed/sec', 'Backup/Restore Throughput/sec')) OR ".
				"([object_name] LIKE ('%Access Methods%') AND [counter_name] IN ".
				  "('Full Scans/sec', 'Range Scans/sec', 'Probe Scans/sec', 'Index Searches/sec', 'Page Splits/sec')) OR ".
				"([object_name] LIKE ('%Memory Manager%') AND [counter_name] IN ".
				  "('Target Server Memory (KB)', 'Target Server Memory(KB)', 'Total Server Memory (KB)')) OR".
				"([object_name] LIKE ('%SQL Statistics%') AND [counter_name] IN ".
				  "('SQL Compilations/sec', 'SQL Re-Compilations/sec'))".
				")";

		$stmt = $conn->query($sql);
		$rows = $stmt->fetchAll();
			
		//print_r($rows);
		
		$search = array(' ', '/sec', '(KB)', '/', '-');

		//while ($row = mssql_fetch_row($res)){
		foreach ($rows as $row){		
			$vals[strtolower(str_replace($search, '', $row[0]))] = (empty($row[1]) ? '0' : $row[1]);
			//echo trim($row[0]).': '.$row[1]."\n";
		}

		$vals['buffercachehitratio'] = $vals['buffercachehitratio'] / $vals['buffercachehitratiobase'] * 100;
		$vals['logcachehitratio'] = $vals['logcachehitratio'] / $vals['logcachehitratiobase'] * 100;
		$vals['proccachehitratio'] = $vals['cachehitratio'] / $vals['cachehitratiobase'] * 100;
		$vals['memoryhitratio'] = $vals['totalservermemory'] / $vals['targetservermemory'] * 100;

		unset($vals['buffercachehitratiobase'], $vals['logcachehitratiobase'], $vals['cachehitratiobase'], $vals['cachehitratio']);

		if(isset($MemCache))
			$MemCache->set($cachekey, $vals, FALSE, 15);
	}

	switch ($cmd){
	case "bckrsttroughput":
			$ret .= 'bckrsttroughput:'.$vals['backuprestorethroughput'].' ';
	break;
	case "buffercache":
			$ret .= 'buffercachehitratio:'.$vals['buffercachehitratio'].' ';
	break;
	case "compilations":
			$ret .= 'compliations:'.$vals['sqlcompilations'].' ';
			$ret .= 'recompliations:'.$vals['sqlrecompilations'].' ';
	break;
	case "connections":
			$ret .= 'userconnections:'.$vals['userconnections'].' ';
	break;
	case "logcache":
			$ret .= 'logcachehitratio:'.$vals['logcachehitratio'].' ';
	break;
	case "logflushes":
			$ret .= 'logflushes:'.$vals['logflushes'].' ';
	break;
	case "logflushtraffic":
			$ret .= 'bytesflushed:'.$vals['logbytesflushed'].' ';
	break;
	case "memory":
			$ret .= 'memoryhitratio:'.$vals['memoryhitratio'].' ';
			$ret .= 'totalservermemory:'.$vals['totalservermemory'].' ';
			$ret .= 'targetservermemory:'.$vals['targetservermemory'].' ';
	break;
	case "pageio":
			$ret .= 'pagereads:'.$vals['pagereads'].' ';
			$ret .= 'pagewrites:'.$vals['pagewrites'].' ';
	break;
	case "pagesplits":
			$ret .= 'pagesplits:'.$vals['pagesplits'].' ';
	break;
	case "proccache":
			$ret .= 'proccachehitratio:'.$vals['proccachehitratio'].' ';
	break;
	case "scans":
			$ret .= 'fullscans:'.$vals['fullscans'].' ';
			$ret .= 'rangescans:'.$vals['rangescans'].' ';
			$ret .= 'probescans:'.$vals['probescans'].' ';
			$ret .= 'indexsearches:'.$vals['indexsearches'].' ';
	break;
	case "temptables":
			$ret .= 'activetemptables:'.$vals['activetemptables'].' ';
	break;
	case "transactions":
			$ret .= 'transactions:'.$vals['transactions'].' ';
	break;
#       case "":
#               $ret .= ':'.$vals[''].' ';
#       break;
	}
	return trim($ret);
}

function ss_win_mssql_syntax(){
	echo ("Usage: ss_win_mssql.php <host> <port> <cmd> [<username>] [<password>]\n".
	" example usage\n".
	"php ss_win_mssql.php 192.168.1.1 1433 connections cactistats password\n");
}
function log_mssql($message){
	//echo $message;
	$filename="/tmp/cacti_mssql.log";
	file_put_contents($filename,date('Y-m-d H:i:s ').$message."\n",FILE_APPEND);
}

?>
