<?php

$request = isset($_GET['q'])?$_GET['q']:'';
if ($request=='')
	exit();
$range = isset($_GET['r'])?$_GET['r']:'';
if ($range=='')
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


function osrmdistance(
	$res, $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo)
{
	curl_setopt($res, CURLOPT_URL, 'http://osrm.app.local/route/v1/feet/'.$longitudeFrom.','.$latitudeFrom.';'.$longitudeTo.','.$latitudeTo.'?overview=false');
	curl_setopt($res, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($res, CURLOPT_RETURNTRANSFER, 1);
	$resp = curl_exec($res);

	if(!$resp) {
		die('Error: "' . curl_error($res) . '" - Code: ' . curl_errno($res));
	} else {
		$osrm=json_decode($resp, true);
		$routedist = $osrm['routes'][0]['distance'];
        //              var_dump($osrm);
	}
	return $routedist;
}

$query = "SELECT a.stop_id, a.stop_sequence, b.stop_name, b.stop_desc, b.stop_lat, b.stop_lon FROM trip_stops as a, stops as b where a.trip_id=".$mysqli->real_escape_string($request)." AND a.stop_id=b.stop_id ORDER BY a.stop_sequence ASC";
$result=$mysqli->query($query);
$i = 0;
if ($result) {
	while ($row = $result->fetch_array(MYSQLI_NUM)){
		$var['items'][$i]['id']=$row[0];
		$var['items'][$i]['text']=$row[2];
		$var['items'][$i]['sequence']=$row[1];
		$var['items'][$i]['desc']=$row[3];
		$var['items'][$i]['lat']=$row[4];
		$var['items'][$i]['lon']=$row[5];
		$i++;
	}
	$result->free();
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

for ($j=0;$j<$i;$j++) {
	$q2 = "SELECT a.stop2_id, a.distance, b.stop_name, b.stop_desc, b.stop_lat, b.stop_lon FROM stops_distance as a, stops as b where a.stop2_id=b.stop_id AND a.distance<".$mysqli->real_escape_string($range)." AND a.stop1_id=".$var['items'][$j]['id']." ORDER BY a.distance ASC";
	$result=$mysqli->query($q2);
	$k=0;
	if ($result)
	{
		while ($row = $result->fetch_array(MYSQLI_NUM)) {
			$var['items'][$j]['neighbours'][$k]['id']=$row[0];
			$var['items'][$j]['neighbours'][$k]['distance']=$row[1];
			$var['items'][$j]['neighbours'][$k]['text']=$row[2];
			$var['items'][$j]['neighbours'][$k]['desc']=$row[3];
			$var['items'][$j]['neighbours'][$k]['lat']=$row[4];
			$var['items'][$j]['neighbours'][$k]['lon']=$row[5];
			$dist = osrmdistance($ch,$var['items'][$j]['lat'], $var['items'][$j]['lon'],$row[4], $row[5]);
			$var['items'][$j]['neighbours'][$k]['osrmdistance']=$dist;
			$k++;
		}
		$result->free();
	}
	$var['items'][$j]['neighbours_count']=$k;
}




curl_close($ch);

$tr=0;
for ($j=0;$j<$i;$j++){
	$n = $var['items'][$j]['neighbours_count'];
	$nr = 0;
	for ($k=0;$k<$n;$k++) {
		$s = $var['items'][$j]['neighbours'][$k]['id'];
		$q2 = "select distinct a.route_id, a.direction_id, a.trip_short_name, b.route_short_name, b.route_long_name, b.route_desc, b.status,b.route_view, b.route_type, a.trip_id from trips as a, routes as b where a.trip_id IN (SELECT DISTINCT trip_id FROM `trip_stops` WHERE stop_id=".$s." order by trip_id ASC) AND a.route_id=b.route_id ORDER by a.route_id, a.trip_short_name, a.direction_id ASC";
		$result=$mysqli->query($q2);
		$l=0;
		if ($result)
		{
			while ($row = $result->fetch_array(MYSQLI_NUM)) {
				$var['items'][$j]['neighbours'][$k]['routes'][$l]['id']=$row[0];
				if ($row[1]==0){
					$var['items'][$j]['neighbours'][$k]['routes'][$l]['direction']='Прямой';
				}else{
					$var['items'][$j]['neighbours'][$k]['routes'][$l]['direction']='Обратный'; 
				}
				$var['items'][$j]['neighbours'][$k]['routes'][$l]['trip_short_name']=$row[2];
				$var['items'][$j]['neighbours'][$k]['routes'][$l]['route_short_name']=$row[3];
				$var['items'][$j]['neighbours'][$k]['routes'][$l]['route_long_name']=$row[4];
				$var['items'][$j]['neighbours'][$k]['routes'][$l]['route_desc']=$row[5];
				$var['items'][$j]['neighbours'][$k]['routes'][$l]['status']=$row[6];
				$var['items'][$j]['neighbours'][$k]['routes'][$l]['route_view']=$row[7];
				switch ($row[8]) {
					case "А": $type="Автобус";
					break;
					case "Тм": $type="Трамвай";
					break;
					case "Тб": $type="Троллейбус";
					break;
				}
				$var['items'][$j]['neighbours'][$k]['routes'][$l]['route_type']=$type;
				$var['items'][$j]['neighbours'][$k]['routes'][$l]['trip_id']=$row[9];

				$l++;
			}
			$result->free();
		}
		$var['items'][$j]['neighbours'][$k]['routes_count']=$l;
		$nr+=$l;
	}
	$var['items'][$j]['neighbour_routes']=$nr;
	$tr+=$nr;
}
$var['incomplete_results']=false;
$var['total_routes']=$tr;
$var['total_count']=$i;
$var['request']=$request;
$var['query']=$query;
header('Content-Type: application/json');
echo json_encode($var);

$mysqli->close();
?>