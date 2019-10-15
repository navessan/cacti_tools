<?php

/*
select and install appropriate drivers for php-pdo-firebird
	apt install php-interbase
*/

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . '/../include/cli_check.php');
	array_shift($_SERVER['argv']);
    print call_user_func_array("ss_firebird", $_SERVER["argv"])."\n";
}

function ss_firebird($hostname, $port, $database, $username = NULL, $password = NULL) {
	if(trim($hostname)=="" || trim($database)=="") {
		echo ("FATAL: No input parameters provided\n");
		ss_firebird_syntax();
		return;
	}

	//list($host, $port) = explode(':', $hostname);
	$trimchr = '\'":'; // default characters to remove
	
	$host=$hostname;
	$port = trim($port,$trimchr);
	$port = ($port == '' ? '3050' : $port);
	$username = ($username == NULL ? 'cactistats' : $username);
	$password = ($password == NULL ? 'CHANGEME' : $password);
	
	$database_type="firebird";
	$database_charset="UTF8";

	$ret = '';
	
	$dsn = "$database_type:dbname=$host/$port:$database;charset=$database_charset";
	//echo "DSN=".$dsn."\n";
		
	$opt = array(
		PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		//PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM
	);
		
	try {
		$conn = new PDO($dsn, $username, $password, $opt);
	}
	catch(PDOException $e) {
		echo "FATAL:". $e->getMessage()."\n";
	}

	$sql = 'SELECT
       db.mon$pages "pages",
       (db.mon$pages * db.mon$page_size )  as "db_size",
       r.mon$record_seq_reads as "seq_reads",
       r.mon$record_idx_reads as "idx_reads",
       r.mon$record_inserts as "record_inserts",
       r.mon$record_updates as "record_updates",
       r.mon$record_deletes as "record_deletes",
       r.mon$record_backouts as "record_backouts",
       r.mon$record_purges as "record_purges",
       r.mon$record_expunges as "record_expunges",
       io.mon$page_reads as "page_reads",
       io.mon$page_writes as "page_writes",
       io.mon$page_fetches as "page_fetches",
       io.mon$page_marks as "page_marks",
       (select count(*) from mon$attachments) as "connections"
	FROM mon$database db
	left join mon$record_stats r on (db.mon$stat_id = r.mon$stat_id)
	left join mon$io_stats io on (db.mon$stat_id = io.mon$stat_id)
	';

	$stmt = $conn->query($sql);
	$rows = $stmt->fetchAll();
		
	//print_r($rows);
	
	foreach ($rows as $row){		
		foreach($row as $key => $value)
			$ret.="$key:$value ";
	}
	
	return trim($ret);
}

function ss_firebird_syntax(){
	echo ("Usage: ss_firebird.php <host> <port> <database> [<username>] [<password>]\n".
	" example usage\n".
	"php ss_firebird.php 192.168.1.1 3050 database cactistats password\n");
}

?>
