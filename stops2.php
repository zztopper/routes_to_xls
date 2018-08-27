<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;


$request = isset($_GET['trip'])?$_GET['trip']:'';
if ($request=='')
	exit();
$range = isset($_GET['radius'])?$_GET['radius']:'';
if ($range=='')
	exit();
$json = isset($_GET['json'])?true:false;

$surr = isset($_GET['surround'])?true:false;

$filter = isset($_GET['filter'])?$_GET['filter']:'';
$rr=isset($_GET['route'])?$_GET['route']:'';

$fq = ($filter!=="")?" AND trip_short_name='".$filter."' ":"";


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

$query = "SELECT a.route_short_name, a.route_long_name, b.trip_short_name, b.direction_id FROM routes as a, trips as b where a.route_id=b.route_id and b.trip_id=".$mysqli->real_escape_string($request)." LIMIT 1";
$result=$mysqli->query($query);
if ($result) {
	while ($row = $result->fetch_array(MYSQLI_NUM)){
		$route_short_name=$row[0];
		$route_long_name=$row[1];
		$trip_name = $row[2];
		$trip_dir = $row[3]==0?"Прямой":"Обратный";
	}
	$result->free();
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

$nr=0;

for ($j=0;$j<$i;$j++) {
	$s = $var['items'][$j]['id'];
	$q2 = "select distinct a.route_id, a.direction_id, a.trip_short_name, b.route_short_name, b.route_long_name, b.route_desc, b.status,b.route_view, b.route_type, a.trip_id from trips as a, routes as b where a.trip_id IN (SELECT DISTINCT trip_id FROM `trip_stops` WHERE stop_id=".$s." ".$fq." AND NOT (trip_id <=> ".$mysqli->real_escape_string($request).") order by trip_id ASC) AND a.route_id=b.route_id AND b.route_type='А' ORDER by a.route_id, a.trip_short_name, a.direction_id ASC";
	$result=$mysqli->query($q2);
	$l=0;
	$maxr=0;
	if ($result)
	{
		while ($row = $result->fetch_array(MYSQLI_NUM)) {
			$var['items'][$j]['bus_routes'][$l]['id']=$row[0];
			if ($row[1]==0){
				$var['items'][$j]['bus_routes'][$l]['direction']='Прямой';
			}else{
				$var['items'][$j]['bus_routes'][$l]['direction']='Обратный';
			}
			$var['items'][$j]['bus_routes'][$l]['trip_short_name']=$row[2];
			$var['items'][$j]['bus_routes'][$l]['route_short_name']=$row[3];
			$var['items'][$j]['bus_routes'][$l]['route_long_name']=$row[4];
			$var['items'][$j]['bus_routes'][$l]['route_desc']=$row[5];
			$var['items'][$j]['bus_routes'][$l]['status']=$row[6];
			$var['items'][$j]['bus_routes'][$l]['route_view']=$row[7];
			$var['items'][$j]['bus_routes'][$l]['trip_id']=$row[9];

			$l++;
		}
		$result->free();
		$var['items'][$j]['bus_routes_count']=$l;
		//$var['items'][$j]['bus_query']=$q2;
		$maxr = max($l, $maxr);
		$nr+=$l;
	}

	$q2 = "select distinct a.route_id, a.direction_id, a.trip_short_name, b.route_short_name, b.route_long_name, b.route_desc, b.status,b.route_view, b.route_type, a.trip_id from trips as a, routes as b where a.trip_id IN (SELECT DISTINCT trip_id FROM `trip_stops` WHERE stop_id=".$s." ".$fq." AND NOT (trip_id <=> ".$mysqli->real_escape_string($request).") order by trip_id ASC) AND a.route_id=b.route_id AND b.route_type='Тм' ORDER by a.route_id, a.trip_short_name, a.direction_id ASC";
	$result=$mysqli->query($q2);
	$l=0;
	if ($result)
	{
		while ($row = $result->fetch_array(MYSQLI_NUM)) {
			$var['items'][$j]['tram_routes'][$l]['id']=$row[0];
			if ($row[1]==0){
				$var['items'][$j]['tram_routes'][$l]['direction']='Прямой';
			}else{
				$var['items'][$j]['tram_routes'][$l]['direction']='Обратный';
			}
			$var['items'][$j]['tram_routes'][$l]['trip_short_name']=$row[2];
			$var['items'][$j]['tram_routes'][$l]['route_short_name']=$row[3];
			$var['items'][$j]['tram_routes'][$l]['route_long_name']=$row[4];
			$var['items'][$j]['tram_routes'][$l]['route_desc']=$row[5];
			$var['items'][$j]['tram_routes'][$l]['status']=$row[6];
			$var['items'][$j]['tram_routes'][$l]['route_view']=$row[7];
			$var['items'][$j]['tram_routes'][$l]['trip_id']=$row[9];

			$l++;
		}
		$result->free();
		$var['items'][$j]['tram_routes_count']=$l;
		//$var['items'][$j]['tram_query']=$q2;
		$maxr = max($l, $maxr);
		$nr+=$l;
	}

	$q2 = "select distinct a.route_id, a.direction_id, a.trip_short_name, b.route_short_name, b.route_long_name, b.route_desc, b.status,b.route_view, b.route_type, a.trip_id from trips as a, routes as b where a.trip_id IN (SELECT DISTINCT trip_id FROM `trip_stops` WHERE stop_id=".$s." ".$fq." AND NOT (trip_id <=> ".$mysqli->real_escape_string($request).") order by trip_id ASC) AND a.route_id=b.route_id AND b.route_type='Тб' ORDER by a.route_id, a.trip_short_name, a.direction_id ASC";
	$result=$mysqli->query($q2);
	$l=0;
	if ($result)
	{
		while ($row = $result->fetch_array(MYSQLI_NUM)) {
			$var['items'][$j]['trolley_routes'][$l]['id']=$row[0];
			if ($row[1]==0){
				$var['items'][$j]['trolley_routes'][$l]['direction']='Прямой';
			}else{
				$var['items'][$j]['trolley_routes'][$l]['direction']='Обратный';
			}
			$var['items'][$j]['trolley_routes'][$l]['trip_short_name']=$row[2];
			$var['items'][$j]['trolley_routes'][$l]['route_short_name']=$row[3];
			$var['items'][$j]['trolley_routes'][$l]['route_long_name']=$row[4];
			$var['items'][$j]['trolley_routes'][$l]['route_desc']=$row[5];
			$var['items'][$j]['trolley_routes'][$l]['status']=$row[6];
			$var['items'][$j]['trolley_routes'][$l]['route_view']=$row[7];
			$var['items'][$j]['trolley_routes'][$l]['trip_id']=$row[9];
			$l++;
		}
		$result->free();
		$var['items'][$j]['trolley_routes_count']=$l;
		//$var['items'][$j]['trolley_query']=$q2;
		$maxr = max($l, $maxr);
		$nr+=$l;
	}
	$var['items'][$j]['max_routes_count']=$maxr;

	$nr+=$l;
}
$var['routes_count']=$nr;

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
		$q2 = "select distinct a.route_id, a.direction_id, a.trip_short_name, b.route_short_name, b.route_long_name, b.route_desc, b.status,b.route_view, b.route_type, a.trip_id from trips as a, routes as b where a.trip_id IN (SELECT DISTINCT trip_id FROM `trip_stops` WHERE stop_id=".$s." ".$fq." AND NOT (trip_id <=> ".$mysqli->real_escape_string($request).") order by trip_id ASC) AND a.route_id=b.route_id AND b.route_type='А' ORDER by a.route_id, a.trip_short_name, a.direction_id ASC";
		$result=$mysqli->query($q2);
		$l=0;
		if ($result)
		{
			while ($row = $result->fetch_array(MYSQLI_NUM)) {
				$var['items'][$j]['neighbours'][$k]['bus_routes'][$l]['id']=$row[0];
				if ($row[1]==0){
					$var['items'][$j]['neighbours'][$k]['bus_routes'][$l]['direction']='Прямой';
				}else{
					$var['items'][$j]['neighbours'][$k]['bus_routes'][$l]['direction']='Обратный';
				}
				$var['items'][$j]['neighbours'][$k]['bus_routes'][$l]['trip_short_name']=$row[2];
				$var['items'][$j]['neighbours'][$k]['bus_routes'][$l]['route_short_name']=$row[3];
				$var['items'][$j]['neighbours'][$k]['bus_routes'][$l]['route_long_name']=$row[4];
				$var['items'][$j]['neighbours'][$k]['bus_routes'][$l]['route_desc']=$row[5];
				$var['items'][$j]['neighbours'][$k]['bus_routes'][$l]['status']=$row[6];
				$var['items'][$j]['neighbours'][$k]['bus_routes'][$l]['route_view']=$row[7];
				$var['items'][$j]['neighbours'][$k]['bus_routes'][$l]['trip_id']=$row[9];

				$l++;
			}
			$result->free();
		}
		$var['items'][$j]['neighbours'][$k]['bus_routes_count']=$l;
		$nr+=$l;
		$q2 = "select distinct a.route_id, a.direction_id, a.trip_short_name, b.route_short_name, b.route_long_name, b.route_desc, b.status,b.route_view, b.route_type, a.trip_id from trips as a, routes as b where a.trip_id IN (SELECT DISTINCT trip_id FROM `trip_stops` WHERE stop_id=".$s." ".$fq." AND NOT (trip_id <=> ".$mysqli->real_escape_string($request).") order by trip_id ASC) AND a.route_id=b.route_id AND b.route_type='Тм' ORDER by a.route_id, a.trip_short_name, a.direction_id ASC";
		$result=$mysqli->query($q2);
		$l=0;
		if ($result)
		{
			while ($row = $result->fetch_array(MYSQLI_NUM)) {
				$var['items'][$j]['neighbours'][$k]['tram_routes'][$l]['id']=$row[0];
				if ($row[1]==0){
					$var['items'][$j]['neighbours'][$k]['tram_routes'][$l]['direction']='Прямой';
				}else{
					$var['items'][$j]['neighbours'][$k]['tram_routes'][$l]['direction']='Обратный';
				}
				$var['items'][$j]['neighbours'][$k]['tram_routes'][$l]['trip_short_name']=$row[2];
				$var['items'][$j]['neighbours'][$k]['tram_routes'][$l]['route_short_name']=$row[3];
				$var['items'][$j]['neighbours'][$k]['tram_routes'][$l]['route_long_name']=$row[4];
				$var['items'][$j]['neighbours'][$k]['tram_routes'][$l]['route_desc']=$row[5];
				$var['items'][$j]['neighbours'][$k]['tram_routes'][$l]['status']=$row[6];
				$var['items'][$j]['neighbours'][$k]['tram_routes'][$l]['route_view']=$row[7];
				$var['items'][$j]['neighbours'][$k]['tram_routes'][$l]['trip_id']=$row[9];

				$l++;
			}
			$result->free();
		}
		$var['items'][$j]['neighbours'][$k]['tram_routes_count']=$l;
		$nr+=$l;
		$q2 = "select distinct a.route_id, a.direction_id, a.trip_short_name, b.route_short_name, b.route_long_name, b.route_desc, b.status,b.route_view, b.route_type, a.trip_id from trips as a, routes as b where a.trip_id IN (SELECT DISTINCT trip_id FROM `trip_stops` WHERE stop_id=".$s." ".$fq." AND NOT (trip_id <=> ".$mysqli->real_escape_string($request).") order by trip_id ASC) AND a.route_id=b.route_id AND b.route_type='Тб' ORDER by a.route_id, a.trip_short_name, a.direction_id ASC";
		$result=$mysqli->query($q2);
		$l=0;
		if ($result)
		{
			while ($row = $result->fetch_array(MYSQLI_NUM)) {
				$var['items'][$j]['neighbours'][$k]['trolley_routes'][$l]['id']=$row[0];
				if ($row[1]==0){
					$var['items'][$j]['neighbours'][$k]['trolley_routes'][$l]['direction']='Прямой';
				}else{
					$var['items'][$j]['neighbours'][$k]['trolley_routes'][$l]['direction']='Обратный';
				}
				$var['items'][$j]['neighbours'][$k]['trolley_routes'][$l]['trip_short_name']=$row[2];
				$var['items'][$j]['neighbours'][$k]['trolley_routes'][$l]['route_short_name']=$row[3];
				$var['items'][$j]['neighbours'][$k]['trolley_routes'][$l]['route_long_name']=$row[4];
				$var['items'][$j]['neighbours'][$k]['trolley_routes'][$l]['route_desc']=$row[5];
				$var['items'][$j]['neighbours'][$k]['trolley_routes'][$l]['status']=$row[6];
				$var['items'][$j]['neighbours'][$k]['trolley_routes'][$l]['route_view']=$row[7];
				$var['items'][$j]['neighbours'][$k]['trolley_routes'][$l]['trip_id']=$row[9];

				$l++;
			}
			$result->free();
		}
		$var['items'][$j]['neighbours'][$k]['trolley_routes_count']=$l;
		$nr+=$l;
	}
	$var['items'][$j]['neighbour_routes']=$nr;
	$tr+=$nr;
}
$var['neighbour_routes_count']=$tr;
$var['neighbout_count']=$i;

$mysqli->close();


if (!$json)  //Standard excel output
{

	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	$spreadsheet->getProperties()
	->setCreator("Alexander Spassky")
	->setLastModifiedBy("Alexander Spassky")
	->setTitle("Маршрут ".$route_short_name." Мосгортранс")
	->setSubject("$route_short_name. $route_long_name $trip_name ($trip_dir)")
	->setDescription("")
	->setKeywords("")
	->setCategory("");
	$sheet->mergeCells('A1:C1');
	$sheet->mergeCells('D1:F1');
	$sheet->mergeCells('G1:L1');
	$sheet->mergeCells('M1:R1');
	$sheet->mergeCells('S1:X1');
	$sheet->setCellValue('A1', "$route_short_name. $route_long_name");
	$sheet->setCellValue('D1', "$trip_name ($trip_dir)");
	$sheet->setCellValue('G1', "Автобус");
	$sheet->setCellValue('M1', "Троллейбус");
	$sheet->setCellValue('S1', "Трамвай");
	$sheet->getStyle('A1:X1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

	$cols = array("№ п\\п","ID остановки", "Название остановки", "Тип остановки", "Расст. (прям)", "Расст. (OSRM)", "ID маршрута", "№ маршрута", "Название маршрута", "ID рейса", "Вид рейса", "Направление", "ID маршрута", "№ маршрута", "Название маршрута", "ID рейса", "Вид рейса", "Направление", "ID маршрута", "№ маршрута", "Название маршрута", "ID рейса", "Вид рейса", "Направление");
	$sheet->fromArray($cols,NULL,'A2');
	$i=3;
	$l=0;
	foreach ($var['items'] as $stopid => $stop) {
		$maxline=0;
		$arr = array($stop['sequence'], $stop['id'], $stop['text'], $stop['desc']=="NULL"?"":$stop['desc']);
		$sheet->fromArray($arr, NULL, 'A'.$i);
		$sheet->getStyle('A'.$i.':F'.$i)->applyFromArray(
			['fill' => [
				'type' => Fill::FILL_SOLID,
				'color' => ['argb' => '7F92CDDC'],
			],
		]
	);
		$j=0;
		if ($stop['bus_routes_count']!=0) {
			foreach ($stop['bus_routes'] as $routeid => $route) {
				$arr = array($route['id'], $route['route_short_name'], $route['route_long_name'], $route['trip_id'], $route['trip_short_name'], $route['direction']);
				$sheet->fromArray($arr, NULL, 'G'.($i+$j));
				$j++;
			}
		}
		$maxline = max($j,$maxline);
		$j=0;
		if ($stop['trolley_routes_count']!=0) {
			foreach ($stop['trolley_routes'] as $routeid => $route) {
				$arr = array($route['id'], $route['route_short_name'], $route['route_long_name'], $route['trip_id'], $route['trip_short_name'], $route['direction']);
				$sheet->fromArray($arr, NULL, 'M'.($i+$j));
				$j++;
			}
		}
		$maxline = max($j,$maxline);
		$j=0;
		if ($stop['tram_routes_count']!=0) {
			foreach ($stop['tram_routes'] as $routeid => $route) {
				$arr = array($route['id'], $route['route_short_name'], $route['route_long_name'], $route['trip_id'], $route['trip_short_name'], $route['direction']);
				$sheet->fromArray($arr, NULL, 'S'.($i+$j));
				$j++;
			}
		}
		$maxline = max($j,$maxline);
		$sheet->getStyle('A'.$i.':X'.$i)->applyFromArray(
			[  'borders' => [
				'top' => ['style' => Border::BORDER_THIN],
			],
		]
	);
		$i+=$maxline;
		$maxline=0;
		$j=0;
		$k=1;
		if ($stop['neighbours_count']!=0) {
			foreach ($stop['neighbours'] as $n_id=>$neigh) {
				if ($surr) {
					$arr = array($stop['sequence']."_".($k), $neigh['id'], $neigh['text'], $neigh['desc']=="NULL"?"":$neigh['desc'],$neigh['distance'],$neigh['osrmdistance']);
					$sheet->fromArray($arr, NULL, 'A'.($i));
					$sheet->getStyle('A'.($i).':X'.($i))->applyFromArray(
						[  'borders' => [
							'top' => ['style' => Border::BORDER_THIN],
						],
					]
				);
					$sheet->getStyle('B'.($i).':F'.($i))->applyFromArray(
						['fill' => [
							'type' => Fill::FILL_SOLID,
							'color' => ['argb' => '7FDAEEF3'],
						],
					]
				);
				}else{
					if ($k==1) {
						$sheet->getStyle('G'.($i).':X'.($i))->applyFromArray(
							[  'borders' => [
								'top' => ['style' => Border::BORDER_THIN],
							],
						]);
					}
				}
				if ($neigh['bus_routes_count']!=0) {
					foreach ($neigh['bus_routes'] as $routeid => $route) {
						$arr = array($route['id'], $route['route_short_name'], $route['route_long_name'], $route['trip_id'], $route['trip_short_name'], $route['direction']);
						$sheet->fromArray($arr, NULL, 'G'.($i+$j));
						$j++;
					}
				}
				$maxline = max($j,$maxline);
				$j=0;
				if ($neigh['trolley_routes_count']!=0) {
					foreach ($neigh['trolley_routes'] as $routeid => $route) {
						$arr = array($route['id'], $route['route_short_name'], $route['route_long_name'], $route['trip_id'], $route['trip_short_name'], $route['direction']);
						$sheet->fromArray($arr, NULL, 'M'.($i+$j));
						$j++;
					}
				}
				$maxline = max($j,$maxline);
				$j=0;
				if ($neigh['tram_routes_count']!=0) {
					foreach ($neigh['tram_routes'] as $routeid => $route) {
						$arr = array($route['id'], $route['route_short_name'], $route['route_long_name'], $route['trip_id'], $route['trip_short_name'], $route['direction']);
						$sheet->fromArray($arr, NULL, 'S'.($i+$j));
						$j++;
					}
				}
				$maxline = max(1,max($j,$maxline));
				$i+=$maxline;
				$k++;
			}
		}
	}
	$l=$i-1;
	for ($i=0;$i<26;$i++) {
		$sheet->getColumnDimension(chr($i+65))->setAutoSize(true);
	}

	$sheet->getStyle('A2:X2')->getFont()->setBold('true');
	$sheet->getStyle('G1:L'.$l)->applyFromArray(
		['fill' => [
			'type' => Fill::FILL_SOLID,
			'color' => ['argb' => '7FDCE6F1'],
		],
	]
);
	$sheet->getStyle('M1:R'.$l)->applyFromArray(
		['fill' => [
			'type' => Fill::FILL_SOLID,
			'color' => ['argb' => '7FEBF1DE'],
		],
	]
);
	$sheet->getStyle('S1:X'.$l)->applyFromArray(
		['fill' => [
			'type' => Fill::FILL_SOLID,
			'color' => ['argb' => '7FF2DCDB'],
		],
	]
);
	$sheet->getStyle('A1:X'.$l)->applyFromArray(
		[  'borders' => [
			'vertical' => ['style' => Border::BORDER_THIN],
		],
	]
);


	$sheet->freezePane('D3');


	header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
	header('Content-Disposition: attachment; filename="route_'.$route_short_name.'.xlsx"');
	header('Cache-Control: max-age=0');
	header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
	header ('Cache-Control: cache, must-revalidate');
	header ('Pragma: public');
	ob_clean();



	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
	$writer->save("php://output");
	$spreadsheet->disconnectWorksheets();
	unset($spreadsheet);
}
else
{
	//JSON DEBUG OUTPUT;
	header ("Content-Type: application/json");
	echo json_encode($var);
}




?>