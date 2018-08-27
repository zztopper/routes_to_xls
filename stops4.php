<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;


$json = isset($_GET['json'])?true:false;

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

for ($dir=0;$dir<2;$dir++) {
	$query = "SELECT a.route_short_name, a.route_long_name, b.trip_short_name, b.direction_id, a.route_type, b.trip_id FROM routes as a, trips as b where a.route_id=b.route_id and a.route_id=".$rr." AND b.trip_short_name=\"00\" AND b.direction_id=".$dir." LIMIT 1";
	$result=$mysqli->query($query);
	if ($result) {
		while ($row = $result->fetch_array(MYSQLI_NUM)){
			$route_short_name=$row[0];
			$route_long_name=$row[1];
			$trip_name = $row[2];
			$trip_dir = $dir;
			$trip_id=$row[5];
			$type=$row[4];
		}
		$result->free();
	}
	$var[$dir]['route_short_name']=$route_short_name;
	$var[$dir]['route_long_name']=$route_long_name;
	$var[$dir]['trip_name']=$trip_name;
	$var[$dir]['trip_dir']=$trip_dir;
	$var[$dir]['type']=$type;

	$query = "SELECT a.stop_id, a.stop_sequence, b.stop_name, b.stop_desc, b.stop_lat, b.stop_lon FROM trip_stops as a, stops as b where a.trip_id=".$mysqli->real_escape_string($trip_id)." AND a.stop_id=b.stop_id ORDER BY a.stop_sequence ASC";
	$result=$mysqli->query($query);
	$i = 0;
	if ($result) {
		while ($row = $result->fetch_array(MYSQLI_NUM)){
			$var[$dir]['items'][$i]['id']=$row[0];
			$var[$dir]['items'][$i]['text']=$row[2];
			$var[$dir]['items'][$i]['sequence']=$row[1];
			$var[$dir]['items'][$i]['desc']=$row[3];
			$var[$dir]['items'][$i]['lat']=$row[4];
			$var[$dir]['items'][$i]['lon']=$row[5];

			$i++;
		}
		$result->free();
	}
}
$nr=0;


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
	$sheet1 = new \PhpOffice\PhpSpreadsheet\Worksheet($spreadsheet, 'Туда');
	$spreadsheet->addSheet($sheet1, 0);
	$sheet2 = new \PhpOffice\PhpSpreadsheet\Worksheet($spreadsheet, 'Обратно');
	$spreadsheet->addSheet($sheet2, 1);
	$sheetIndex = $spreadsheet->getIndex(
		$spreadsheet->getSheetByName('Worksheet')
	);
	$spreadsheet->removeSheetByIndex($sheetIndex);
	$sheet1->setCellValue('A4', $var[0]['type']);
	$sheet2->setCellValue('A4', $var[1]['type']);
	$sheet1->setCellValue('B4', $var[0]['route_short_name']);
	$sheet2->setCellValue('B4', $var[1]['route_short_name']);
	$sheet1->setCellValue('C4', $var[0]['trip_name']);
	$sheet2->setCellValue('C4', $var[1]['trip_name']);
	$sheet1->setCellValue('D4', $var[0]['trip_dir']);
	$sheet2->setCellValue('D4', $var[1]['trip_dir']);

	$cols = array ("Вид ТС", "Маршрут", "Тип маршрута", "Напр.", "Номер остановки", "ID МГТ", "Название", "Орбита", "БК СЦ ТТМ", "Иные БК и СИП", "file0", "file1", "file2", "file3", "file4", "Описание файла 0", "Широта", "Долгота");
	$sheet1->fromArray($cols,NULL,'A1');
	$sheet2->fromArray($cols,NULL,'A1');
	$sheet = $sheet1;
	$i=2;
	foreach ($var[0]['items'] as $stopid => $stop) {
		$arr = array ($stop['sequence'], $stop['id'], $stop['text']);
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
	}
	$sheet = $sheet2;
	$i=2;
	foreach ($var[1]['items'] as $stopid => $stop) {
		$arr = array ($stop['sequence'], $stop['id'], $stop['text']);
		$sheet->fromArray($arr, NULL, 'E'.$i);
		$arr = array ($stop['lat'], $stop['lon']);
		$sheet->fromArray($arr, NULL, 'Q'.$i);
		if ($i==2) {
			$sheet->setCellValue('I'.$i, "=\$E2&\"-\"&\$E2");
		} else {
			$sheet->setCellValue('I'.$i, '=$E'.($i-2).'&"-"&$E'.$i);
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
	}
	$sheet1->getStyle('A1:X1')->getFont()->setBold('true');
	$sheet2->getStyle('A1:X1')->getFont()->setBold('true');

	$sheet1->getStyle('A1:R1')->applyFromArray( //прямой
	                                           ['fill' => [
	                                           	'type' => Fill::FILL_SOLID,
	                                           	'color' => ['argb' => '7FB4C6E7'],
	                                           ],
	                                       ]
	                                   );
	$sheet2->getStyle('A1:R1')->applyFromArray( //прямой
	                                           ['fill' => [
	                                           	'type' => Fill::FILL_SOLID,
	                                           	'color' => ['argb' => 'A9D08E'],
	                                           ],
	                                       ]
	                                   );
	for ($i=0;$i<26;$i++) {
		$sheet1->getColumnDimension(chr($i+65))->setAutoSize(true);
		$sheet2->getColumnDimension(chr($i+65))->setAutoSize(true);
	}


/*	$l=$i-1;



	$sheet->getStyle('M1:R'.$l)->applyFromArray(
		['fill' => [
			'type' => Fill::FILL_SOLID,
			'color' => ['argb' => '7FA9D08E'], //обратный
		],
	]
);
*/



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