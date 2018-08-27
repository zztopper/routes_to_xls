<?php
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




$mysqli->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
	<title>Информация о маршрутах и остановках общественного транспорта МосГорТранс</title>

	<!-- Bootstrap -->
	<link href="/routes/css/bootstrap.min.css" rel="stylesheet">
	<!-- Bootstrap theme -->
	<link href="/routes/css/bootstrap-theme.min.css" rel="stylesheet">
	<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
	<link href="/routes/css/ie10-viewport-bug-workaround.css" rel="stylesheet">

	<!-- Custom styles for this template -->
	<link href="/routes/css/theme.css" rel="stylesheet">

	<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
	<!-- Include all compiled plugins (below), or include individual files as needed -->
	<script src="/routes/js/bootstrap.min.js"></script>

	<link href="/routes/css/select2.min.css" rel="stylesheet" />
	<script src="/routes/js/select2.min.js"></script>

	<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
	<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->
</head>
<body>

	<nav class="navbar navbar-inverse navbar-fixed-top">
		<div class="container">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="#">МосГорТранс</a>
			</div>
			<div id="navbar" class="navbar-collapse collapse">
				<ul class="nav navbar-nav">
					<li class="active"><a href="#">Главная</a></li>
					<li><a href="/routes/index2.php">Выгрузка в Excel</a></li>
					<li><a href="/routes/excel2.php">Выгрузка в Excel (вар.2)</a></li>
					<li><a href="/routes/geometry.php">Выгрузка JSON</a></li>
				</ul>
			</div><!--/.nav-collapse -->
		</div>
	</nav>


	<div class="container theme-showcase" role="main">


		<div class="page-header">
			<h1>Маршруты наземного транспорта</h1>
		</div>
		<div class="row">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title">Маршруты</h3>
				</div>
				<div class="panel-body">
					<select id="routes" class="js-route-ajax" style="width: 50%">

					</select>
					<select id="trips" class="js-trips-ajax" style="width: 20%">

					</select>&nbsp;<button type="button" class="btn btn-primary" onclick="loadStops()">Обновить</button>
					<br><br>
					<label id="radlbl">Радиус поиска остановок: 250 метров</label><input id="range" type="range" min="0" max="2000" id="radius" oninput="updateLabel(this.value)" value="250" step="50">

					<script type="text/javascript">

						function updateLabel(slideAmount) {
							document.getElementById("radlbl").innerHTML="Радиус поиска остановокa: "+slideAmount+" метров";
						}

						function formatRoutes (routes) {
							if (routes.loading) return routes.text;

							var markup = "<div class='select2-result-route clearfix'>" +
							"<div class='select2-result-route__id'>" +
							"<div class='select2-result-route__title'>" + routes.short_name +" ("+routes.type+"): "+routes.text+"</div>";

							markup += "</div></div>";

							return markup;
						}

						function formatRouteSelection (routes) {
							if (routes.id==='')
							{
								return "Выберите маршрут";
							}
							return routes.short_name+" ("+routes.type+"): "+routes.text;
						}
						function formatTrips (trips) {
							if (trips.loading) return trips.text;

							var markup = "<div class='select2-result-trip clearfix'>" +
							"<div class='select2-result-trip__id'>" +
							"<div class='select2-result-trip__name'>"+trips.text+" ("+trips.dir+")</div>";

							markup += "</div></div>";

							return markup;
						}

						function formatTripSelection (trips) {
							if (trips.id==='')
							{
								return "Выберите рейс маршрута";
							}
							return trips.text+" ("+trips.dir+")";
						}

						function loadStops () {
							$('#loading-indicator').show();
							$.ajax({
								url: "/routes/stops.php",
								dataType: "json",
								data: 	{
									q: $("#trips").val(),
									r: $("#range").val()
								},
								cache: true,
								success: function (data) {
									var table = $("#stops tbody");
									table.empty();
									var em=[];
									$.each(data.items, function(idx,elem){
										em[idx]=0;
										$.each(elem.neighbours, function(idx2,elem2){
											if (elem2.routes_count==0)
												em[idx]++;
										});
									});
									$.each(data.items, function(idx, elem){
										if (elem.id!=null) {
											var rows = elem.neighbour_routes+em[idx];
											var stop = "";
											if (rows==0) {
												stop = "<tr><td>"+elem.sequence+"</td><td>["+elem.id+"]. "+elem.text;
											} else {
												stop = "<tr><td rowspan="+(rows)+">"+elem.sequence+"</td><td rowspan="+(elem.neighbour_routes+em[idx])+">["+elem.id+"]. "+elem.text;
											}
											if (elem.desc!='NULL') {
												stop+=elem.desc;
											}
											$.each(elem.neighbours, function (idx2, elem2){
												var cnt = elem2.routes_count;
												if (cnt!=0) {
													rows=cnt;
													cols=cnt;
												} else {
													cnt=1;
													}
												if (idx2!=0) {
													stop+="<tr><td rowspan="+(cnt)+">";
												} else {
													stop+="<td rowspan="+(cnt)+">";
												}
												stop+="["+elem2.id+"]. "+elem2.text;
												if (elem2.desc!='NULL') {
													stop+=elem2.desc;
												}
												center_lat = Math.abs(elem.lat - elem2.lat)+Math.min(elem.lat,elem2.lat);
												center_lon = Math.abs(elem.lon - elem2.lon)+Math.min(elem.lon, elem2.lon);
												stop+=" - "+elem2.distance+"м. ("+elem2.osrmdistance+"м.)<a target=\"_blank\" href=\"http://osrm.app.moscow/osrm/?z=10&center="+center_lat+"%2C"+center_lon+"&loc="+elem.lat+"%2C"+elem.lon+"&loc="+elem2.lat+"%2C"+elem2.lon+"\"><img src=\"route.svg\"></a></td>";
												if (elem2.routes_count==0)
												{
													stop+="<td></td></tr>";
												}
												$.each(elem2.routes, function (idx3, elem3){
													if (idx3!=0) {
														stop+="<tr><td><i>"+elem3.route_type+"</i> "+elem3.route_short_name+"<br>"+elem3.route_long_name;
														stop+="<br>Маршрут ["+elem3.id+"] Рейс ["+elem3.trip_id+"] "+elem3.trip_short_name+" ("+elem3.direction+")</td></tr>";
													}
													else
													{
														stop+="<td><i>"+elem3.route_type+"</i> "+elem3.route_short_name+"<br>"+elem3.route_long_name;
														stop+="<br>Маршрут ["+elem3.id+"] Рейс ["+elem3.trip_id+"] "+elem3.trip_short_name+" ("+elem3.direction+")</td></tr>";
													}
												});
											});
											table.append(stop);
										}
									});
									$('#loading-indicator').hide();
								}
							});
						}


						$("#routes").select2({
							ajax: {
								url: "/routes/routes.php",
								dataType: 'json',
								delay: 250,
								data: function (params) {
									return {
        									q: params.term // search term
        									//page: params.page
        								};
        							},
        							processResults: function (data, params) {
        								return {
								      	results: data.items//,
								      };
								  },
								  cache: true,
								},
							escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
							minimumInputLength: 0,
							templateResult: formatRoutes,
							templateSelection: formatRouteSelection,
							language: "ru",
							placeholder: "Выберите маршрут",
							allowClear: true
						});
						$('#routes').on("select2:select", function (e) {
						  //$("#trips").select2().prop("disabled", false);
						  $("#trips").select2("open");
						  //$("#trips").val(null).trigger("change");
						});
						$('#routes').on("select2:unselect", function (e) {
							$("#stops tbody").empty();
						});
						$("#trips").select2({
							ajax: {
								url: "/routes/trips.php",
								dataType: 'json',
								data: function (params) {
									//console.log(params);
									return {
        									q: $("#routes").val()//params.term
        								};
        							},
        							processResults: function (data, params) {
        								console.log(params);
        								//console.log(data);
        								return {
        									results: data.items
        								};
        							},
        							cache: true,
        						},
							escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
							minimumResultsForSearch: Infinity,
							templateResult: formatTrips,
							templateSelection: formatTripSelection,
							language: "ru",
							placeholder: "Выберите рейс маршрута"
						});
						$('#trips').on("select2:select", function (e) {
							loadStops();
						});
					</script>
				</div>
			</div>
		</div>
		<div align="center" class="row" id="loading-indicator" style="display:none; padding:10px">
			<img src="/routes/ajax-loader.gif"/><br>
			<div class="row"></div>
		</div>
		<div class="row">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title">Остановки</h3>
				</div>
				<div class="panel-body">
					<div style="width: 100%">
						<table id="stops" class="table table-bordered table-hover">
							<thead>
								<tr>
									<th style="width: 2%">№ п\п</th>
									<th style="width: 25%">Остановка<br>[ID]. Название (тип)</th>
									<th style="width: 35%">Соседние остановки<br>[ID]. Название (тип) - расст. (OSRM)</th>
									<th style="width: 35%">Соседние маршруты</th>
								</tr>
							</thead>
							<tbody>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script>window.jQuery || document.write('<script src="/routes/js/jquery.min.js"><\/script>')</script>
	<script src="/routes/js/bootstrap.min.js"></script>
	<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
	<script src="/routes/js/ie10-viewport-bug-workaround.js"></script>
</body>
</html>