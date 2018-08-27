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
					<li><a href="/routes/index.php">Главная</a></li>
					<li><a href="/routes/index2.php">Выгрузка в Excel</a></li>
					<li class="active"><a href="#">Выгрузка в Excel (вар.2)</a></li>
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
			<div class="panel panel-primary" >
				<div class="panel-heading">
					<h3 class="panel-title">Маршруты</h3>
				</div>
				<div class="panel-body" align="center">
					<form action="/routes/stops5.php" method="GET">
					<select id="routes" name="route" class="js-route-ajax" style="width: 50%">

					</select>
					<select id="trips" name="trip" class="js-trips-ajax" style="width: 20%">

					</select>&nbsp;<input type="submit" class="btn btn-primary" value="Сформировать"></input>
					</form>
						<script type="text/javascript">

							function updateLabel(slideAmount) {
								document.getElementById("radlbl").innerHTML="Радиус поиска остановок: "+slideAmount+" метров";
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
							function formatTripTypes (trips) {
								if (trips.loading) return trips.text;

								var markup = "<div class='select2-result-triptype clearfix'>" +
								"<div class='select2-result-triptype__id'>" +
								"<div class='select2-result-triptype__name'>"+trips.text+"</div>";

								markup += "</div></div>";

								return markup;
							}

							function loadStops () {
								$('#loading-indicator').show();
								$.ajax({
									url: "/routes/stops2.php",
									dataType: "json",
									data: 	{
										q: $("#trips").val(),
										r: $("#range").val(),
										f: $("#trip_filter").val()
									},
									cache: true,
									success: function (data) {

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
        								//console.log(params);
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
							$("#trip_filter").select2({
								ajax: {
									url: "/routes/triptypes.php",
									dataType: 'json',
									data: '',
									processResults: function (data, params) {
       								return {
        									results: data.items
        								};
        							},
        							cache: true,
        						},
							escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
							minimumResultsForSearch: Infinity,
							templateResult: formatTripTypes,
							language: "ru",
							placeholder: "Вид рейса"
						});
					</script>
				</div>
			</div>
		</div>
		<div align="center" class="row" id="loading-indicator" style="display:none; padding:10px">
			<img src="/routes/ajax-loader.gif"/><br>
			<div class="row"></div>
		</div>
	</div>
	<script>window.jQuery || document.write('<script src="/routes/js/jquery.min.js"><\/script>')</script>
	<script src="/routes/js/bootstrap.min.js"></script>
	<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
	<script src="/routes/js/ie10-viewport-bug-workaround.js"></script>
</body>
</html>