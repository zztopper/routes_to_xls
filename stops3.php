<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;


$json = isset($_GET['json'])?true:false;

$filter = isset($_GET['filter'])?$_GET['filter']:'';
$rr=isset($_GET['route'])?$_GET['route']:'';

$request = isset($_GET['trip'])?$_GET['trip']:'';
if ($request=='')
	exit();

$dir = array("Прямой", "Обратный");
$color = array("7FB4C6E7","A9D08E");

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

	$query = "SELECT a.route_short_name, a.route_long_name, b.trip_short_name, b.direction_id, a.route_type, b.trip_id FROM routes as a, trips as b where a.route_id=b.route_id and a.route_id=".$rr." AND b.trip_id=".$mysqli->real_escape_string($request)." LIMIT 1";
	$result=$mysqli->query($query);
	if ($result) {
		while ($row = $result->fetch_array(MYSQLI_NUM)){
			$route_short_name=$row[0];
			$route_long_name=$row[1];
			$trip_name = $row[2];
			$trip_dir = $row[3];
			$trip_id=$row[5];
			$type=$row[4];
		}
		$result->free();
	}
	$var['route_short_name']=$route_short_name;
	$var['route_long_name']=$route_long_name;
	$var['trip_name']=$trip_name;
	$var['trip_dir']=$trip_dir;
	$var['trip_id']=$trip_id;
	$var['type']=$type;

	$query = "SELECT a.stop_id, a.stop_sequence, b.stop_name, b.stop_desc, b.stop_lat, b.stop_lon FROM trip_stops as a, stops as b where a.trip_id=".$mysqli->real_escape_string($trip_id)." AND a.stop_id=b.stop_id ORDER BY a.stop_sequence ASC";
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

if (count($var['items'])>=2) {
	if ( $var['items'][0]['text']==$var['items'][1]['text']) {
		if (($var['items'][0]['desc']=='(к/ст)') || ($var['items'][0]['desc']=='(выс.)')) {
			unset($var['items'][0]);
		}
	}
$cnt = count($var['items']);
	if ( $var['items'][$cnt-2]['text']==$var['items'][$cnt-1]['text']) {
//		if (($var['items'][$cnt-2]['desc']=='(выс.)') && ($var['items'][$cnt-1]['desc']=='(к/ст)')) {
			unset($var['items'][$cnt-2]);
//		}
/*		if (($var['items'][$cnt-2]['desc']=='') && ($var['items'][$cnt-1]['desc']=='(к/ст)')) {
			unset($var['items'][$cnt-2]);
		}*/
	}
}

$mysqli->close();


if (!$json)  //Standard excel output
{

	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$spreadsheet->getProperties()
	->setCreator("Alexander Spassky")
	->setLastModifiedBy("Alexander Spassky")
	->setTitle("Маршрут ".$route_short_name." Мосгортранс")
	->setSubject("$route_short_name. $route_long_name $trip_name")
	->setDescription("")
	->setKeywords("")
	->setCategory("");
	// Create a new worksheet called "My Data"
	$sheet1 = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $var['trip_name']." (".$dir[$var['trip_dir']].")");
	$spreadsheet->addSheet($sheet1, 0);
	$sheetIndex = $spreadsheet->getIndex(
		$spreadsheet->getSheetByName('Worksheet')
	);
	$spreadsheet->removeSheetByIndex($sheetIndex);
	$sheet1->setCellValue('A4', $var['type']);
	$sheet1->setCellValue('B4', $var['route_short_name']);
	$sheet1->setCellValue('C4', $var['trip_name']);
	$sheet1->setCellValue('D4', $var['trip_dir']);

	$cols = array ("Вид ТС", "Маршрут", "Тип маршрута", "Напр.", "Номер остановки", "ID МГТ", "Название", "Орбита", "БК СЦ ТТМ", "Иные БК и СИП", "file0", "file1", "file2", "file3", "file4", "Описание файла 0", "Широта", "Долгота");
	$sheet1->fromArray($cols,NULL,'A1');
	$sheet=$sheet1;
	$i=2;
	$k=1;
	foreach ($var['items'] as $stopid => $stop) {
		$arr = array ($k, $stop['id'], $stop['text']);
		$sheet->fromArray($arr, NULL, 'E'.$i);
		$arr = array ($stop['lat'], $stop['lon']);
		$sheet->fromArray($arr, NULL, 'Q'.$i);
		if ($i==2) {
			$sheet->setCellValue('I'.$i, "=\$E2&\"-\"&\$E2");
		} else {
			$sheet->setCellValue('I'.$i, '=$E'.($i-2).'&"-"&$E'.$i);

			//=$A$4&"_"&$B$4&"_"&$C$4&"_"&$D$4&"_"&I4&"_0"
			$arr = array('=$A$4&"_"&$B$4&"_"&$C$4&"_"&$D$4&"_"&I'.$i.'&"_0"',
			             '=$A$4&"_"&$B$4&"_"&$C$4&"_"&$D$4&"_"&I'.$i.'&"_1"',
			             '=$A$4&"_"&$B$4&"_"&$C$4&"_"&$D$4&"_"&I'.$i.'&"_2"',
			             '=$A$4&"_"&$B$4&"_"&$C$4&"_"&$D$4&"_"&I'.$i.'&"_3"');
			$sheet->fromArray($arr, NULL, 'K'.$i);
		}
		$i++;
		$arr = array ($stop['id'], $stop['text']);
		$sheet->fromArray($arr, NULL, 'F'.$i);
		$arr = array ($stop['lat'], $stop['lon']);
		$sheet->fromArray($arr, NULL, 'Q'.$i);
		$sheet->setCellValue('I'.$i, "=\$E".($i-1));
		$arr = array('=$A$4&"_"&$B$4&"_"&$C$4&"_"&$D$4&"_"&I'.$i.'&"_0"',
		             '=$A$4&"_"&$B$4&"_"&$C$4&"_"&$D$4&"_"&I'.$i.'&"_1"',
		             '=$A$4&"_"&$B$4&"_"&$C$4&"_"&$D$4&"_"&I'.$i.'&"_2"',
		             '=$A$4&"_"&$B$4&"_"&$C$4&"_"&$D$4&"_"&I'.$i.'&"_3"');
		$sheet->fromArray($arr, NULL, 'K'.$i);
		$i++;
		$k++;
	}

	$sheet1->getStyle('A1:X1')->getFont()->setBold('true');

	$sheet1->getStyle('A1:R1')->applyFromArray(
	                                           ['fill' => [
	                                           	'type' => Fill::FILL_SOLID,
	                                           	'color' => ['argb' => $color[$var['trip_dir']]],
	                                           ],
	                                       ]
	                                   );
	for ($i=0;$i<26;$i++) {
		$sheet1->getColumnDimension(chr($i+65))->setAutoSize(true);
	}


header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header('Content-Disposition: attachment; filename="Маршрут_'.$route_short_name.'_'.$var['trip_name'].'('.$dir[$var['trip_dir']].').xlsx"');
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