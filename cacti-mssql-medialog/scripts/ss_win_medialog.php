<?php
/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
   die("<br><strong>This script is only meant to run at the command line.</strong>");
}

/* display No errors */
error_reporting(0);

if (!isset($called_by_script_server)) {
        array_shift($_SERVER["argv"]);

        print call_user_func_array("ss_win_medialog", $_SERVER["argv"])."\n";
}

function ss_win_medialog ($hostname, $cmd, $username = NULL, $password = NULL, $db = NULL) {
        list($host, $port) = explode(':', $hostname);
        $port = ($port == '' ? '1433' : $port);
        $username = ($username == NULL ? 'medistats' : $username);
        $password = ($password == NULL ? 'SQL_PASSWORD_' : $password);
        $db = ($db == NULL ? 'medialog7' : $db);

        $ret = '';

        $MCache_Host = 'localhost';
        $MCache_Port = '11211';
        $cachekey = 'ss_win_medialog:'.$host.'-'.$port.'-'.$db.'-'.$cmd;
        $MemCache = new Memcache;
        $MemCache->connect($MCache_Host, $MCache_Port);
        if (! $vals = $MemCache->get($cachekey)){

                if (! $link = mssql_connect($host.':'.$port, $username, $password) )
		{
			print 'connection failed to '.$host.':'.$port;
                        return;
		}
		switch ($cmd){
		  case "bill":
		    $sql = "use $db
		    SELECT top 1
		    (COUNT(DISTINCT FM_BILL.PATIENTS_ID )) CNT_PAT,(COUNT(FM_BILL.FM_BILL_ID )) CNT_BILL
		    FROM FM_BILL
		    where FM_BILL.BILL_DATE=DATEADD(day, DATEDIFF(day, 0, getdate()), 0)
		    GROUP BY FM_BILL.BILL_DATE
		    ";
		    break;
		    case "billdet":
		    $sql = "use $db
SELECT top 1
SUM(FM_BILLDET.CNT) CNT
,SUM(case when FM_CLINK.FM_CONTR_ID in (9082) then FM_BILLDET.CNT else 0  end) CNT_GASO
,SUM(case when FM_CLINK.FM_CONTR_ID in (554) then FM_BILLDET.CNT else 0  end) CNT_OMS,
 SUM(case when FM_BILLDET_PAY.FM_ORG_ID is not null and FM_CLINK.FM_CONTR_ID not in (554, 9082) then FM_BILLDET.CNT  else 0  end) CNT_DMS
,SUM(case when FM_BILLDET_PAY.FM_ORG_ID is null then FM_BILLDET.CNT  else 0  end) CNT_PU
,COUNT(distinct FM_BILLDET.PATIENTS_ID) PAT_CNT
,COUNT(distinct case when FM_CLINK.FM_CONTR_ID in (9082) then FM_BILLDET.PATIENTS_ID else null  end) PAT_GASO
,COUNT(distinct case when FM_CLINK.FM_CONTR_ID in (554) then FM_BILLDET.PATIENTS_ID else null  end) PAT_OMS
,COUNT(distinct case when FM_BILLDET_PAY.FM_ORG_ID is not null and FM_CLINK.FM_CONTR_ID not in (554, 9082) then FM_BILLDET.PATIENTS_ID else null  end) PAT_DMS
,COUNT(distinct case when FM_BILLDET_PAY.FM_ORG_ID is null then FM_BILLDET.PATIENTS_ID else null  end) PAT_PU
,SUM(FM_BILLDET.PRICE_TO_PAY) PRICE_TO_PAY
,SUM(case when FM_CLINK.FM_CONTR_ID in (9082) then FM_BILLDET.PRICE_TO_PAY else 0  end) PAY_GASO
,SUM(case when FM_CLINK.FM_CONTR_ID in (554) then FM_BILLDET.PRICE_TO_PAY else 0  end) PAY_OMS,
 SUM(case when FM_BILLDET_PAY.FM_ORG_ID is not null and FM_CLINK.FM_CONTR_ID not in (554, 9082) then FM_BILLDET_PAY.PRICE else 0  end) PAY_DMS
,SUM(case when FM_BILLDET_PAY.FM_ORG_ID is null then FM_BILLDET_PAY.PRICE else 0  end) PAY_PU
FROM
 FM_BILLDET FM_BILLDET 
 LEFT OUTER JOIN FM_BILL FM_BILL ON FM_BILL.FM_BILL_ID = FM_BILLDET.FM_BILL_ID 
 LEFT OUTER JOIN FM_CLINK FM_CLINK ON FM_CLINK.FM_CLINK_ID = FM_BILLDET.FM_CLINK_ID
 JOIN FM_BILLDET_PAY FM_BILLDET_PAY ON FM_BILLDET.FM_BILLDET_ID = FM_BILLDET_PAY.FM_BILLDET_ID 
WHERE
 FM_BILL.BILL_DATE=DATEADD(day, DATEDIFF(day, 0, getdate()), 0)
		    ";
		    break;

		    case "planning":
		    $sql = "use $db
		    select top 1 
		    (COUNT(DISTINCT planning.PATIENTS_ID )) CNT_PAT,(COUNT(planning.planning_id )) CNT_PLAN
		    from planning
		    where planning.create_DATE_time>DATEADD(day, DATEDIFF(day, 0, getdate()), 0)
		    and planning.create_DATE_time<DATEADD(day, DATEDIFF(day, 0, getdate()), +1)
		    ";
		    break;
		}
		
//                print "sql=".$sql."\n";

                $res = mssql_query($sql, $link);

		//get columns names and put zero values
                $names=array();
                for ($i = 0; $i < mssql_num_fields($res); $i++)
                {
            	    $names[$i] = strtolower(mssql_field_name($res, $i));
            	    $vals[$names[$i]] = 0;
            	}
            	
                while ($row = mssql_fetch_row($res)){
		  for ($i = 0; $i < mssql_num_fields($res); $i++)
		    $vals[$names[$i]] = (empty($row[$i]) ? '0' : $row[$i]);
                 }

                $MemCache->set($cachekey, $vals, FALSE, 15);
        }

//	print_r($vals);
//	print "\n";

	$names = array_keys($vals);
	for ($i = 0; $i < count($names = array_keys($vals)); $i++) 
	  $ret .= $names[$i].':'.$vals[$names[$i]].' ';

#       $ret .= ':'.$vals[''].' ';
//	print "ret=".$ret."\n";
        return trim($ret);
}
?>
