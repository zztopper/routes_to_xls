<?php
//mb_internal_encoding("UTF-8");

$json = isset($_GET['json'])?true:false;
$dir = array("", "o");

$filter = isset($_GET['filter'])?$_GET['filter']:'';
$rr=isset($_GET['route'])?$_GET['route']:'';

$request = isset($_GET['trip'])?$_GET['trip']:'';
if ($request=='')
	exit();

$mysqli = new mysqli('localhost', 'root', 'pEr#Ad*1', 'routing');

if ($mysqli->connect_error) {
	die('Ошибка подключения (' . $mysqli->connect_errno . ') '
	    . $mysqli->connect_error);
}
/* изменение набора символов на utf8 */
if (!$mysqli->set_charset("utf8")) {
	die("Ошибка при загрузке набора символов utf8: ". $mysqli->error."\n");
}
$mysqli->query("SET GLOBAL max_allowed_packet=524288000");

$query = "SELECT a.route_short_name, a.route_long_name, b.trip_short_name, b.direction_id, a.route_type, b.trip_id, UNIX_TIMESTAMP(a.route_date_start) as startdate, UNIX_TIMESTAMP(a.route_date_end) as enddate  FROM routes as a, trips as b where a.route_id=b.route_id and a.route_id=".$rr." AND b.trip_id=".$mysqli->real_escape_string($request)." LIMIT 1";
$result=$mysqli->query($query);
if ($result) {
	while ($row = $result->fetch_array(MYSQLI_NUM)){
		$route_short_name =  $row[0];
		$route_long_name=$row[1];
		$trip_name = $row[2];
		$trip_dir = $row[3];
		$trip_id=$row[5];
		$type= $row[4];
		$startdate = $row[6];
		$enddate = $row[7];
		$route_long_name = str_replace("\"","''",$route_long_name);
		$var['route_short_name']=$route_short_name;
		$var['route_long_name']=$route_long_name;
		$var['trip_name']=$trip_name;
		$var['trip_dir']=$trip_dir;
		$var['trip_id']=$trip_id;
		$var['type']=$type;
		$var['startdate']=$startdate;
		$var['enddate']=$enddate;
	}
	$result->free();
}


$query = "SELECT a.stop_id, a.stop_sequence, b.stop_name, b.stop_desc, b.stop_lat, b.stop_lon FROM trip_stops as a, stops as b where a.trip_id=".$mysqli->real_escape_string($trip_id)." AND a.stop_id=b.stop_id ORDER BY a.stop_sequence ASC";
$result=$mysqli->query($query);
$i = 0;
if ($result) {
	while ($row = $result->fetch_array(MYSQLI_NUM)){
		$var['items'][$i]['id']=$row[0];
		$stop_name = str_replace("\"","''",$row[2]);
		$var['items'][$i]['text']=$stop_name;
		$var['items'][$i]['sequence']=$row[1];
		if ($row[3]=="NULL") {
			$var['items'][$i]['desc']=NULL;
		} else {
		$var['items'][$i]['desc']=str_replace("\"","''",$row[3]);
	}
		$var['items'][$i]['lat']=(float)$row[4];
		$var['items'][$i]['lon']=(float)$row[5];
		$i++;
	}
	$result->free();
}

$query = "select trip_id, arc_sequence, arc_geometry FROM trip_shapes WHERE trip_id=".$mysqli->real_escape_string($trip_id)." ORDER BY arc_sequence ASC;";
$result=$mysqli->query($query);
$i = 0;
$geometry = array();
if ($result) {
	while ($row = $result->fetch_array(MYSQLI_NUM)){
		$str = substr_replace($row[2], "", -1, 1);
		$geom = explode(";",$str);
		foreach ($geom as $k=>$v) {
			$point = explode(",",$v);
			if (isset($point[0])&&isset($point[1])) {
			$geometry[]=(float)$point[0];
			$geometry[]=(float)$point[1];
			$i++;
			}
		}
	}
	$result->free();
}

$nr=0;
$out['type']='route';
$out['version']=100;
$out['valid']=true;
$out['id_route']=$var['trip_id'];
$out['id']=$var['route_short_name'];
$out['region']='Москва';
$out['transport']=$var['type'];
$out['route']=stripcslashes($var['route_short_name']);
$out['name']=$var['route_short_name'];
$out['direction']=stripcslashes($var['route_long_name']);
$out['route_dir']=(int)$var['trip_dir'];
$out['created_by']='Весовщук Д.В';
$out['sign_by']="";
$out['created_ts']=time();
$out['updated_ts']=$out['created_ts'];
$out['published_ts']=$out['created_ts'];
$out['start_ts']=(int)$var['startdate'];
$out['finish_ts']=(int)$var['enddate'];
$out['stops']=array();

foreach ( $var['items'] as $k=>$v) {
	$out['stops'][] = array("short_name" => stripcslashes($v['text']),
	                                "long_name" => stripcslashes($v['text']." ".$v['desc']),
	                                "description" => stripcslashes($v['desc']),
	                                "geometry" => array($v['lon'],$v['lat']),
									"audio_info" => array(stripcslashes("''".$v['text']."''")),
	                                "audio" => array("curr" => array (),
	                            					"next" => array()));
}
$mysqli->close();

$out['geopoints'] = $i;
$out['geometry'] = $geometry;


	//JSON DEBUG OUTPUT;
header ("Content-Type: application/json; encoding=windows-1251");
$fn=$route_short_name.$dir[$var["trip_dir"]];
header('Content-Disposition: attachment; filename="'.$fn.'.json"');
header('Cache-Control: max-age=0');
header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header ('Cache-Control: cache, must-revalidate');
header ('Pragma: public');
ob_clean();
$json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$data = iconv("UTF-8", "Windows-1251", $json);
echo $data;


?>