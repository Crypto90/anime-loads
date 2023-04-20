<?php
//error_reporting(E_ERROR | E_PARSE);
set_time_limit(0);
header('Access-Control-Allow-Origin: *');
session_start();



$web_user = "admin";
$web_password = "password";



if ($_GET['action'] == "logout") {
	session_destroy();
	header("Refresh:0; url=index.php");
	die();
}





if (isset($_GET['unmonitor']) && $_GET['unmonitor'] != '') {

    	
    	//get all your data on file
	$data = file_get_contents('/config/ani.json');

	// decode json to associative array
	$json_arr = json_decode($data, true);




	//delete the data
	foreach($json_arr['anime'] as $k=>$arr) {
		if($arr["customPackage"] == $_GET['unmonitor']) {
			$animeToDeleteURL = $json_arr['anime'][$k]['url'];
			$urlNameToDelete = substr($animeToDeleteURL, strrpos($animeToDeleteURL, '/') + 1);
			//delete cover image
			unlink('./anime_cover/'.$urlNameToDelete.'.png');


			//delete max episodes count file
			unlink('./anime_cover/'.$urlNameToDelete.'.txt');

			//remove entry from json object
			unset($json_arr['anime'][$k]);
		}
	}   

	// rebase array
	$json_arr['anime'] = array_values($json_arr['anime']);

	// encode array to json and save to file
	file_put_contents('/config/ani.json', json_encode($json_arr));
    	die();
	//header("Refresh:0; url=" . basename(__FILE__));
		
}
    
if (isset($_GET['downloader']) && $_GET['downloader'] == '1') {
	//stop downloader container
	shell_exec("docker stop pfuenzle-anime-loads1");
	//start downloader container
	shell_exec("docker start pfuenzle-anime-loads1");
	header("Refresh:0; url=index.php");
	  die();
}

if (isset($_GET['killrequest']) && $_GET['killrequest'] == '1') {
	//kill download_anime.py script.
	shell_exec("pkill -9 -f 'download_anime.py'");
	header("Refresh:0; url=index.php");
	die();
}

?>



<html>
<head>
	<title>Anime-Loads Downloader</title>
	<!-- CSS only -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
	<!-- JavaScript Bundle with Popper -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
	<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
	
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
	
	
	<link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
	<link rel="manifest" href="/site.webmanifest">
	<link rel="mask-icon" href="/images/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="msapplication-TileColor" content="#da532c">
	<meta name="theme-color" content="#ffffff">


	<style>
		* {
			color: #dddddd;
		}
		body {
			background-color: #000000;
			text-align: center;
			font-family: Arial, sans-serif;
		}
		input {
			color: #000000;	
			padding: 10px;
    		width: 100%;
    		margin-bottom: 20px;
		}
		a {
    		width: 248px;
    		display: inline-block;
    		text-decoration: none;
    		color: orange;
		}
		hr {
    		width: 300px;
    		height: 1px;
	        background-color: #ccc;
	        border: none;
		}
		
		.form-select {
    		margin-bottom: 20px;
		}
		
		pre, #manualOutput, #manualOutput2 {
    		text-align: left;
    		border: 2px solid grey;
    		width: 80%;
    		margin: 0 auto;
    		max-height: 400px;
    		overflow: auto;
    		padding: 5px;
		}
		
		#manualOutput2 {
			max-height: 200px !important;
		}
		
		#manualOutput, #manualOutput2 {
			display: inline-block;
			width: 80%;
		}
		
		#processRunning {
			width: 800px;
			margin: 0 auto;
			margin-bottom: 30px;
		}
		.form-control:valid, .form-select:valid {
		  background-color:  #181A1B!important;
		  border-color: #333333;
		  color: #dddddd;
		}
		
		.card-title:hover {
			color: lightblue !important;
		}
		
		.img-fluid {
			opacity: 0.8;
		}
		
		.img-fluid:hover {
			opacity: 1.0;
		}
		
	</style>
	<script>
		
		
		function endsWith(str, suffix) {
		    return str.indexOf(suffix, str.length - suffix.length) !== -1;
		}
		
		
		/**
		 * Format bytes as human-readable text.
		 * 
		 * @param bytes Number of bytes.
		 * @param si True to use metric (SI) units, aka powers of 1000. False to use 
		 *           binary (IEC), aka powers of 1024.
		 * @param dp Number of decimal places to display.
		 * 
		 * @return Formatted string.
		 */
		function humanFileSize(bytes, si=false, dp=1) {
		  const thresh = si ? 1000 : 1024;
		
		  if (Math.abs(bytes) < thresh) {
		    return bytes + ' B';
		  }
		
		  const units = si 
		    ? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'] 
		    : ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
		  let u = -1;
		  const r = 10**dp;
		
		  do {
		    bytes /= thresh;
		    ++u;
		  } while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);
		
		
		  return bytes.toFixed(dp) + ' ' + units[u];
		}
		
		function popupwindow(url, title, w, h) {
		  var left = (screen.width/2)-(w/2);
		  var top = (screen.height/2)-(h/2);
		  $( "body" ).append( "<div id='dark-overlay' style='position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: #000000; opacity: 0.8;'></div>" );
		  var windoww = window.open(url, title, 'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width='+w+', height='+h+', top='+top+', left='+left);
		  
		  
		  window.addEventListener('focus', (e) => {
			 if (!windoww.closed) {
			 	windoww.close();
			 }
			 $('#dark-overlay').remove();
		 })
		  return windoww;
		} 

		var lastDataPrinted = '';
		var lastDataPrinted2 = '';
		$("document").ready(function(){
			
			$(document).on('click', '.unmonitorBtn', function() {
				$.get($(this).attr("data"));
				$(this).closest( ".card" ).remove();
			});
			
			
			$(document).on('click', '.card-title, .animeCover', function() {
				var win = popupwindow($(this).attr("data") + '#description', $(this).text(), 770, 430);
			});
			
			
			
			
		    setInterval(function(){
		    	$.get('/echo_the_content.php?file=1', function(data) {
					if (lastDataPrinted != data) {
						lastDataPrinted = data;
						
						var data = data.replace('[0m', '').replace('[33m', '');
						$("#manualOutput").html(data);
		        		$('#manualOutput').scrollTop($('#manualOutput')[0].scrollHeight);
		        		$('#manualOutput')
						  .animate({borderColor:'green'}, 400, 'linear')
						  .delay(200)
						  .animate({borderColor:'#808080'}, 400, 'linear');
		        		
		        		if (data.indexOf('Exit') != -1) {
		        			//refresh the other log files too
		        			setTimeout(
							  function() 
							  {
							    $.get('/echo_the_content.php?file=2', function(data2) {
									$("#anijson").html(data2);
									$('#anijson')
									  .animate({borderColor:'green'}, 400, 'linear')
									  .delay(200)
									  .animate({borderColor:'#808080'}, 400, 'linear');
			        			});
			        			$.get('/echo_the_content.php?file=3', function(data2) {
									$("#log1").html(data2);
						        	$('#log1').scrollTop($('#log1')[0].scrollHeight);
						        	$('#log1')
									  .animate({borderColor:'green'}, 400, 'linear')
									  .delay(200)
									  .animate({borderColor:'#808080'}, 400, 'linear');
			        			});
			        			$.get('/echo_the_content.php?file=4', function(data2) {
									$("#log2").html(data2);
						        	$('#log2').scrollTop($('#log2')[0].scrollHeight);
						        	$('#log2')
									  .animate({borderColor:'green'}, 400, 'linear')
									  .delay(200)
									  .animate({borderColor:'#808080'}, 400, 'linear');
			        			});
							  }, 8000);
		        			
		        		}
		        		
		        		if (data.indexOf('Anime wurde hinzugef') != -1) {
		        			$("#manualOutput").html('<b style="color: green;">Anime wurde hinzugef&uuml;gt.</b>');
		        		}
		        		
		        		
				   }
				});
		    },500);
		    
		    
		    
		    setInterval(function(){
		    	$.get('/echo_the_content.php?file=10', function(data) {
					if (lastDataPrinted2 != data) {
						lastDataPrinted2 = data;
						data = data.replace(' Ist JDownloader gestartet?', '');
						$("#manualOutput2").html(data);
						
						$('#manualOutput2').scrollTop($('#manualOutput2')[0].scrollHeight);
						$('#manualOutput2')
						  .animate({borderColor:'green'}, 400, 'linear')
						  .delay(200)
						  .animate({borderColor:'#808080'}, 400, 'linear');
		        		
				   	}
				});
		    },500);
		    
		    
		    
		    $.get('/echo_the_content.php?file=7', function(data) {
			    var lines = data.split('<br />\n');
			    for(var line = 0; line < lines.length; line++){
			      if (endsWith(lines[line], " tv") || endsWith(lines[line], " movie")) {
			      	var modifiedLineToReplace = lines[line].split(' -- ')[1]; // remove the size for the folder
			    	data = data.replace(lines[line], '<span style="color: orange; font-weight: bold;">' + modifiedLineToReplace + '</span>');
			      } else {
			      	//its a file, we edit the size to get it readable
			      	var fileSize = lines[line].split(' -- ')[0];
			      	if (fileSize.indexOf('<br />') != -1) {
			      		fileSize = lines[line].split('<br />')[0];
			      	}
			      	var modifiedLineToReplace = lines[line].replace(fileSize, humanFileSize(fileSize)); // remove the size for the folder
			      	modifiedLineToReplace = modifiedLineToReplace.replace('<br />', ' -- ');
			      	if (modifiedLineToReplace == ' B') {
			      		continue;
			      	}
			      	var colorToSet = 'green';
			      	if (modifiedLineToReplace.indexOf('.rar') != -1 || modifiedLineToReplace.indexOf('.part') != -1) {
			      		colorToSet = 'yellow';
			      	}
			    	data = data.replace(lines[line], '<span style="color: ' + colorToSet + ';">' + modifiedLineToReplace + '</span>');
			      }
			    }
				$("#downloaded-files-data").html(data);
			});
			$.get('/echo_the_content.php?file=5', function(data) {
			    var lines = data.split('<br />\n');
			    for(var line = 0; line < lines.length; line++){
			      if (endsWith(lines[line], " tv") || endsWith(lines[line], " movie")) {
			      	var modifiedLineToReplace = lines[line].split(' -- ')[1]; // remove the size for the folder
			    	data = data.replace(lines[line], '<span style="color: orange; font-weight: bold;">' + modifiedLineToReplace + '</span>');
			      } else {
			      	//its a file, we edit the size to get it readable
			      	var fileSize = lines[line].split(' -- ')[0];
			      	if (fileSize.indexOf('<br />') != -1) {
			      		fileSize = lines[line].split('<br />')[0];
			      	}
			      	var modifiedLineToReplace = lines[line].replace(fileSize, humanFileSize(fileSize)); // remove the size for the folder
			      	modifiedLineToReplace = modifiedLineToReplace.replace('<br />', ' -- ');
			      	if (modifiedLineToReplace == ' B') {
			      		continue;
			      	}
			      	var colorToSet = 'green';
			      	if (modifiedLineToReplace.indexOf('.rar') != -1 || modifiedLineToReplace.indexOf('.part') != -1) {
			      		colorToSet = 'yellow';
			      	}
			    	data = data.replace(lines[line], '<span style="color: ' + colorToSet + ';">' + modifiedLineToReplace + '</span>');
			      }
			    }
				$("#downloaded-files-data2").html(data);
			});
		    setInterval(function(){
		    	$.get('/echo_the_content.php?file=7', function(data) {
					var lines = data.split('<br />\n');
					for(var line = 0; line < lines.length; line++){
					  if (endsWith(lines[line], " tv") || endsWith(lines[line], " movie")) {
						var modifiedLineToReplace = lines[line].split(' -- ')[1]; // remove the size for the folder
						data = data.replace(lines[line], '<span style="color: orange; font-weight: bold;">' + modifiedLineToReplace + '</span>');
					  } else {
						//its a file, we edit the size to get it readable
						var fileSize = lines[line].split(' -- ')[0];
						if (fileSize.indexOf('<br />') != -1) {
							fileSize = lines[line].split('<br />')[0];
						}
						var modifiedLineToReplace = lines[line].replace(fileSize, humanFileSize(fileSize)); // remove the size for the folder
						modifiedLineToReplace = modifiedLineToReplace.replace('<br />', ' -- ');
						if (modifiedLineToReplace == ' B') {
							continue;
						}
						var colorToSet = 'green';
						if (modifiedLineToReplace.indexOf('.rar') != -1 || modifiedLineToReplace.indexOf('.part') != -1) {
							colorToSet = 'yellow';
						}
						data = data.replace(lines[line], '<span style="color: ' + colorToSet + ';">' + modifiedLineToReplace + '</span>');
					  }
					}
					$("#downloaded-files-data").html(data);
				});
				$.get('/echo_the_content.php?file=5', function(data) {
					var lines = data.split('<br />\n');
					for(var line = 0; line < lines.length; line++){
					  if (endsWith(lines[line], " tv") || endsWith(lines[line], " movie")) {
						var modifiedLineToReplace = lines[line].split(' -- ')[1]; // remove the size for the folder
						data = data.replace(lines[line], '<span style="color: orange; font-weight: bold;">' + modifiedLineToReplace + '</span>');
					  } else {
						//its a file, we edit the size to get it readable
						var fileSize = lines[line].split(' -- ')[0];
						if (fileSize.indexOf('<br />') != -1) {
							fileSize = lines[line].split('<br />')[0];
						}
						var modifiedLineToReplace = lines[line].replace(fileSize, humanFileSize(fileSize)); // remove the size for the folder
						modifiedLineToReplace = modifiedLineToReplace.replace('<br />', ' -- ');
						if (modifiedLineToReplace == ' B') {
							continue;
						}
						var colorToSet = 'green';
						if (modifiedLineToReplace.indexOf('.rar') != -1 || modifiedLineToReplace.indexOf('.part') != -1) {
							colorToSet = 'yellow';
						}
						data = data.replace(lines[line], '<span style="color: ' + colorToSet + ';">' + modifiedLineToReplace + '</span>');
					  }
					}
					$("#downloaded-files-data2").html(data);
				});
		    }, 3000);
		    
		    
		    
		    $('#manualOutput').scrollTop($('#manualOutput')[0].scrollHeight);
		    $('#log1').scrollTop($('#log1')[0].scrollHeight);
		    $('#log2').scrollTop($('#log2')[0].scrollHeight);
		    
		    
		    
		});
	</script>
</head>
<body>
<?php


function base64_to_jpeg($base64_string, $output_file) {
    // open the output file for writing
    $ifp = fopen( $output_file, 'wb' ); 

    // split the string on commas
    // $data[ 0 ] == "data:image/png;base64"
    // $data[ 1 ] == <actual base64 string>
    $data = explode( "'", $base64_string );

    // we could add validation here with ensuring count( $data ) > 1
    fwrite( $ifp, base64_decode( $data[ 1 ] ) );

    // clean up the file resource
    fclose( $ifp ); 

    return $output_file; 
}



$user = $_POST['user'];
$pass = $_POST['pass'];

$userGET = $_GET['user'];
$passGET = $_GET['pass'];

if(($user == $web_user && $pass == $web_password) || ($userGET == $web_user && $passGET == $web_password) || ($_SESSION['user'] == $web_user && $_SESSION['pass'] == $web_password))
{
    $_SESSION['user'] = $web_user;
    $_SESSION['pass'] = $web_password;
    
    if (!file_exists('/config/manualOutput.log')) {
	    touch('/config/manualOutput.log');
	}
	
	if (!file_exists('/config/docker_live_output.log')) {
		touch('/config/docker_live_output.log');
	}
    
    ?>
    
    
    <br>
    
    <a style="display: inline-block;" class="btn btn-danger" href="index.php?action=logout">Logout</a>
    <br>
    <div style="width: 1024px; margin: 30px auto; position: relative; display: inline-block;">
    	
    	<div style="width: 460px; border: 1px solid grey; padding: 20px; display: block; float: left;">
			<form method="POST" action="index.php">
				<div class="mb-3" style="text-align: left;">
					<label for="animeTitel" class="form-label"><span style='color: orange;'>Titel</span> oder <span style='color: orange;'>URL</span> von Film oder Serie auf <span style='color: orange;'>Anime-Loads.org</span> (falls Hentai, die exakte URL angeben!):</label>
					<input type="text" class="form-control" name="animeTitel" id="animeTitel" placeholder="Super cooler kawaii Anime <3" value="<?php echo $_POST['animeTitel'] ?>">
			
					<label for="languageselect" class="form-label">Sprache:</label>
					<select class="form-select" id="languageselect" name="languageselect" aria-label="Sprache">
						<option value="german" <?php echo (!isset($_POST['languageselect']) || $_POST['languageselect'] == 'german' ? 'selected' : '') ?>>Deutsch</option>
						<option value="japanese" <?php echo ($_POST['languageselect'] == 'japanese' ? 'selected' : '') ?>>Japanisch</option>
					</select>
			
					<label for="resolutionselect" class="form-label">Aufl&ouml;sung:</label>
					<select class="form-select" id="resolutionselect" name="resolutionselect" aria-label="Auflˆsung">
						<option value="1080p" <?php echo (!isset($_POST['resolutionselect']) || $_POST['resolutionselect'] == '1080p' ? 'selected' : '') ?>>1080p</option>
						<option value="720p" <?php echo ($_POST['resolutionselect'] == '720p' ? 'selected' : '') ?>>720p</option>
					</select> 
			
			
					<label for="forceAnimeResult" class="form-label">(optional) Erzwinge Anime Ergebnis Nummer:</label>
					<input type="text" class="form-control" name="forceAnimeResult" id="forceAnimeResult" placeholder="1" value="<?php echo $_POST['forceAnimeResult'] ?>"
			
					<label for="forceAnimeRelease" class="form-label">(optional) Erzwinge Release Nummer:</label>
					<input type="text" class="form-control" name="forceAnimeRelease" id="forceAnimeRelease" placeholder="1" value="<?php echo $_POST['forceAnimeRelease'] ?>">
			
					<div class="form-check" style="margin-bottom: 20px;">
					  <input class="form-check-input" type="checkbox" value="" id="ISHENTAI" name="ISHENTAI" <?php echo (isset($_POST['ISHENTAI']) ? 'checked' : '') ?>>
					  <label class="form-check-label" for="ISHENTAI" style="margin-left: 5px;">
						Is Hentai?
					  </label>
					</div>
					
					<div class="form-check" style="margin-bottom: 20px;">
					  <input class="form-check-input" type="checkbox" value="" id="DRYRUN" name="DRYRUN" <?php echo (isset($_POST['DRYRUN']) ? 'checked' : '') ?>>
					  <label class="form-check-label" for="DRYRUN" style="margin-left: 5px;">
						DRY RUN (Prozessausgabe aber kein Download Start)
					  </label>
					</div>
			
			
			
		  
					<button type="submit" value="Submit" class="btn btn-success">Anfragen</button>
					
					<!-- Button trigger modal -->
					<a href="https://www.anime-loads.org/" class="btn btn-primary" target="_blank">
					  Zeige Anime-Loads.org
					</a>
					<br>
					<br>
					<a href="https://www.anime-loads.org/all?sort=episodes&order=desc" class="btn btn-primary" target="_blank">
					  Zeige neue Episoden
					</a>
					<a href="https://www.anime-loads.org/anime-movies" class="btn btn-primary" target="_blank">
					  Zeige neue Filme
					</a>
					
					
					
					<br>
					<br>
					<a href='?downloader=1' class='btn btn-danger btn-sm' style='float: left; margin-right: 10px; width: auto;'>Background Prozess neu starten</a>
					<br><br>
					<a href='?killrequest=1' class='btn btn-danger btn-sm' style='float: left; margin-right: 10px; width: auto;'>Laufende Anfrage abbrechen (nur wenn etwas haengt)</a>
					
				</div>
			</form>
			
			
			
			
		</div>
		<div id="downloaded-files" style="width: 558px; max-height: 606px; overflow: auto; border: 1px solid grey; padding: 20px; display: block; float: right; text-align: left;">
			<p style="color: grey; font-size: 14px;">Downloads werden automatisch verschoben, wenn die neuste Datei &auml;lter als 10 Minuten ist und keine .rar Datei mehr existiert.<br><br>Hinzugef&uuml;gte Downloads starten innerhalb von 10 Minuten!</p>
			<p class="form-label" style="width: 100%; display: block;">Laufende Downloads (SSD Cache):</p>
			<div id="downloaded-files-data" style="display: block; font-size: 12px;"></div>
			<p class="form-label" style="width: 100%; display: block;">Fertig entpackte Dateien im Downloads Ordner:</p>
			<div id="downloaded-files-data2" style="display: block; font-size: 12px;"></div>
		</div>
	</div>
    
    
    <?php
    $running = false;
    $pids=trim(shell_exec("ps ux | grep 'download_anime.py' | grep -v grep"));
	if($pids == '') {
		$running = false;
	} else {
		$running = true;
		echo '<div id="processRunning" class="alert alert-danger" role="alert">A process is running! Next request is possible as soon as the current process ends.</div>';
	}
    
    
    
    echo'<h5>Anfrage Prozess Output:</h5>';
    echo '<div id="manualOutput">';
    if ($running == false && isset($_POST['animeTitel']) && $_POST['animeTitel'] != '')
    {
    	$animeTitel = $_POST['animeTitel'];
    	
    	if (strpos($animeTitel, 'http') === false) {
    		$animeTitel = preg_replace('/[^A-Za-z0-9]/', ' ', $animeTitel);
    	}
    	
    	
    	$animeTitel = trim($animeTitel);
    	
    	if(isset($_POST['ISHENTAI'])) {
  			$animeTitel = 'HENTAI_' . $animeTitel;
    	}
    	
    	$languageselect = $_POST['languageselect'];
    	$resolutionselect = $_POST['resolutionselect'];
    	
    	$forceAnimeResult = ' 0';
    	if (isset($_POST['forceAnimeResult']) && $_POST['forceAnimeResult'] != '') {
    		$forceAnimeResult = ' ' . $_POST['forceAnimeResult'];
    	}
    	
    	$forceAnimeRelease = ' 0';
    	if (isset($_POST['forceAnimeRelease']) && $_POST['forceAnimeRelease'] != '') {
    		$forceAnimeRelease = ' ' . $_POST['forceAnimeRelease'];
    	}
    	
    	
    	$DRYRUN = ' 0';
    	if(isset($_POST['DRYRUN']))
  			$DRYRUN = ' 1';
    	
    	file_put_contents('/config/manualOutput.log', '');
		
    	$result = liveExecuteCommand('cd /usr/src/app/; python3 -u download_anime.py "' . $animeTitel . '" ' . $languageselect . ' ' . $resolutionselect . $forceAnimeResult . $forceAnimeRelease . $DRYRUN . ' > /config/manualOutput.log 2>&1 &');
		
		
		if (strpos($animeTitel, 'http') !== false) {
    		$animeTitel = explode("/media/", $animeTitel)[1];
    	}
		
		if($result['exit_status'] === 0){
		   // do something if command execution succeeds
		   if(isset($_POST['DRYRUN'])) {
		       file_put_contents("/config/manualOutput.log", "DRY RUN (KEIN DOWNLOAD) Prozess gestartet [" . $animeTitel . "]... Es dauert etwa 60 Sekunden bis es weiter geht...");
		   } else {
		       file_put_contents("/config/manualOutput.log", "Prozess gestartet [" . $animeTitel . "]... Es dauert etwa 60 Sekunden bis es weiter geht...");
		   }
		   
		} else {
		    // do something on failure
		    file_put_contents("/config/manualOutput.log", "Prozess konnte nicht gestartet werden!");
		}
    }
    
    echo '</div>';
    
    
    
    
    
    echo'<br><br><h5>Background Prozess Log:</h5>';
    echo '<pre id="manualOutput2"></pre>';
    $pids2=trim(shell_exec("ps ux | grep 'docker logs' | grep 'pfuenzle-anime-loads1' | grep -v grep"));
    if($pids2 == '') {
	//log redirect process is not running, so start it once.
	//docker logs --follow pfuenzle-anime-loads1
	//docker logs -f --tail 10 pfuenzle-anime-loads1
	file_put_contents('/config/docker_live_output.log', '');
	$result = liveExecuteCommand('timeout 300 /usr/local/bin/docker logs --tail 100 --until=300s -f pfuenzle-anime-loads1 > /config/docker_live_output.log 2>&1 &');
    }
	
	
    
    echo '<br><br>';
    echo'<h5>Gefundene, hinzugef&uuml;gte und beobachtete Titel (neuste oben) (ani.json):</h5>';
    
    
    echo '<div id="anijson">';
    $json=file_get_contents("/config/ani.json");
    $data =  json_decode($json);

    if (count($data->anime)) {
        // Open the table
        
	echo '<div style="display: block; width: 80%; margin: 0 auto;">';
	$reverseIndex = count($data->anime) - 1;
        // Cycle through the array
        $first = true;
        foreach (array_reverse($data->anime) as $anime) {
        
			$urlName = substr($anime->url, strrpos($anime->url, '/') + 1);
			
			$coverToDisplay = '';
			if (!file_exists('./anime_cover/'.$urlName.'.png') || !file_exists('./anime_cover/'.$urlName.'.txt')) {
				
				$url = 'https://www.anisearch.de/anime/index/page-1?char=all&text=' . $urlName . '&smode=1&sort=title&order=asc&view=2';
				$options = array(
						CURLOPT_RETURNTRANSFER => 1, 
						CURLOPT_USERAGENT      => "Mozilla/5.0",  
						CURLOPT_FOLLOWLOCATION => true,   
						CURLOPT_CONNECTTIMEOUT => 5,
						CURLOPT_TIMEOUT => 10,
						CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0',
						CURLOPT_REFERER => 'https://www.anisearch.de/',
						//CURLOPT_PROXY => '213.136.89.121:80',
				);
			
				$ch      = curl_init( $url );
				curl_setopt_array( $ch, $options );
				$htmlContent = curl_exec( $ch );
				curl_close( $ch );
				
								
				$doc = new DOMDocument();
				libxml_use_internal_errors(true);
				$doc->loadHTML($htmlContent);
				libxml_clear_errors();
			
				$detailsRedirectCoverURL = '';
				$gotElement = $doc->getElementById("details-cover");
				if ($gotElement != NULL) {
					$detailsRedirectCoverURL = $doc->getElementById("details-cover")->getAttribute('src');
				}
				
				$resultsCoverURL = '';
			
				
				$xpath = new DomXPath($doc);

				$images = [];
				foreach ($xpath->query("//th[contains(@class, 'showpop')]") as $img) {
					if ($img->hasAttribute('data-tooltip')) {
						//echo '<pre>';
						//var_dump($img->getAttribute('data-tooltip'));
						//echo '</pre>';
						//preg_match('/src=\"\((.*)\"\)/', $img->getAttribute('data-tooltip'), $match);
						preg_match('/< *img[^>]*src *= *["\']?([^"\']*)/i', $img->getAttribute('data-tooltip'), $match);

						if (isset($match[1])) $images[] = trim($match[1], '\'" ');
					}
				}

			
				if ($images[0] != NULL) {
					$resultsCoverURL = $images[0];
				}
			
				
				if ($detailsRedirectCoverURL != '') {
					$coverToDisplay = $detailsRedirectCoverURL;
				} else if ($resultsCoverURL != '') {
					$coverToDisplay = $resultsCoverURL;
				}
			
			
				//save image url to folder for local display
				if ($coverToDisplay != '') {
					//file_put_contents(('./anime_cover/'.$urlName.'.png'), file_get_contents($coverToDisplay));
					// file handler
					$file = fopen('./anime_cover/'.$urlName.'.png', 'w');
					// cURL
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $coverToDisplay);
					//curl_setopt($ch, CURLOPT_PROXY, '213.136.89.121:80');
					// set cURL options
					curl_setopt($ch, CURLOPT_FAILONERROR, true);
					curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0');
					// set file handler option
					curl_setopt($ch, CURLOPT_FILE, $file);
					// execute cURL
					curl_exec($ch);
					// close cURL
					curl_close($ch);
					// close file
					fclose($file);
				}
				
				
				
				
				
				$maxEpisodes = '';
				if (!file_exists('./anime_cover/'.$urlName.'.txt')) {
					
					
					$episodeCounts = [];
					foreach ($xpath->query("//span[contains(@class, 'showpop')]") as $span) {
						if ($span->hasAttribute('data-max') && is_numeric($span->getAttribute('data-max'))) {
							$episodeCounts[] = $span->getAttribute('data-max');
						}
					}
					
					if ($episodeCounts[0] != NULL) {
						$maxEpisodes = $episodeCounts[0];
						//write it to txt file
						$myfile = fopen("./anime_cover/".$urlName.".txt", "w") or die("Unable to open file for writing: ./anime_cover/".$urlName.".txt");
						fwrite($myfile, $maxEpisodes);
						fclose($myfile);
					}
					
					
					
				}
				
				
				
				
			}
			
			
						
			
			
			
			
			
			
			$flag = 'germany';
			if (strpos($anime->customPackage, 'japanese') !== false) {
				$flag = 'japan';
			}
			
			$maxEpisodesSaved = file_get_contents('./anime_cover/'.$urlName.'.txt');
			
			
			$completedGreenBGStyle = '';
			
			if ((strpos($anime->customPackage, 'movie') !== false && $anime->episodes == 1 && count($anime->missing) == 0) || ($anime->episodes == $maxEpisodesSaved && count($anime->missing) == 0)) {
				$completedGreenBGStyle = 'background-color: rgb(15, 70, 25) !important;';
			}
			
			echo '<div class="card bg-dark text-white mb-3" style="max-width: 373px; float: left; margin: 10px 10px 0 0 !important; height: 210px; width: 374px;' . $completedGreenBGStyle . '">';
			echo '  <div class="row g-0">';
			echo '	<div class="col-md-4">';
			echo '	  <img data="' . $anime->url . '" style="width: 124px; height: 175px; cursor: pointer;" src="/config/anime_cover/'.$urlName.'.png" class="animeCover img-fluid rounded-start" alt="' . $anime->name . '">';
			echo '	</div>';
			echo '	<div class="col-md-8">';
			echo '	  <div class="card-body" style="text-align: left; height: 210px; overflow-y: scroll;">';
			echo '		<h5 class="card-title" data="' . $anime->url . '" style="color: lightgrey; cursor: pointer;">' . $anime->name . '<br><span style="color: lightblue; font-size: 10px;">(ReleaseID: ' . $anime->releaseID . ')</span></h5>';
			echo '	    <p class="card-text" style="color: orange; font-size: 12px;"><i class="bi bi-box-seam"></i> ' . $anime->customPackage . '</p>';
			echo '	    <p class="card-text" style="color: green; font-size: 12px;"><i class="bi bi-file-earmark-check"></i> ' . ($anime->episodes > 0 ? $anime->episodes . ' episodes / ' . $maxEpisodesSaved . ' total' : 'Waiting for processing...') . '</p>';
			echo '	    <p class="card-text" style="color: red; font-size: 12px;"><i class="bi bi-file-earmark-excel"></i> ' . (count($anime->missing) > 0 ? implode(', ', $anime->missing) . ' missing' : '-') . '</p>';
			echo '	    <button data="?unmonitor=' . urlencode($anime->customPackage) . '" class="unmonitorBtn btn btn-danger btn-sm" style="position: absolute; left: 0; top: 179px; width: 124px; height: 26px; font-size: 10px; padding-top: 5px;">Nicht mehr beobachten</button>';
			echo '	    <img src="/images/' . $flag . '.png" style="position: absolute; bottom: 0; right: 0; width: 30px; opacity: 0.5;" />';
			echo '	  </div>';
			echo '	</div>';
			echo '  </div>';
			echo '</div>';
			
			
			
			
			$reverseIndex--;
        }
		echo '</div>';
        
    }
    echo '</div>';
    
    
    echo '<br style="clear: both;"><br>';
    echo '<br><br>';
    echo '<br><br>';
    
    echo '<h3>Logs f&uuml;r automatische Requests verarbeitung und anisearch.de "popular top 20" Parsing:</h3>';
    echo'<h6 style="color: red;">Nicht f&uuml;r das manuelle suchen und hinzuf&uuml;gen von dieser Seite!</h6>';
    echo '<br><br>';
    echo'<h5>downloading_and_monitoring.txt:</h5>';
    echo '<pre id="log1">';
    echo file_get_contents( "/config/downloading_and_monitoring.txt" ); // get the contents, and echo it out.
	echo '</pre>';
    echo '<br><br>';
    echo'<h5>no_releases_found_log.txt:</h5>';
    echo '<pre id="log2">';
    echo file_get_contents( "/config/no_releases_found_log.txt" ); // get the contents, and echo it out.
	echo '</pre>';
	echo '<br><br>';
	echo '<br><br>';
	echo '<br><br>';
    
    
}
else
{
    ?>
	
            <form method="POST" action="index.php" style="width: 500px; border: 1px solid #333333; padding: 20px; margin: 100px auto;">
	            Username:<br><input type="text" name="user" class="form-control"></input><br/>
	            Passwort:<br><input type="password" name="pass" class="form-control"></input><br/>
	            <input type="submit" name="submit" value="Login" class="btn btn-success" style="margin-top: 5px;"></input>
            </form>
    <?
    
}











/**
 * Execute the given command by displaying console output live to the user.
 *  @param  string  cmd          :  command to be executed
 *  @return array   exit_status  :  exit status of the executed command
 *                  output       :  console output of the executed command
 */
function liveExecuteCommand($cmd, $echoLive = false)
{

    while (@ ob_end_flush()); // end all output buffers if any

    $proc = popen("$cmd 2>&1 ; echo Exit status : $?", 'r');

    $live_output     = "";
    $complete_output = "";

    while (!feof($proc))
    {
        $live_output     = fread($proc, 4096);
        $complete_output = $complete_output . $live_output;
        if ($echoLive) {
        	echo "$live_output";
        }
        @ flush();
    }

    pclose($proc);

    // get exit status
    preg_match('/[0-9]+$/', $complete_output, $matches);

    // return exit status and intended output
    return array (
                    'exit_status'  => intval($matches[0]),
                    'output'       => str_replace("Exit status : " . $matches[0], '', $complete_output)
                 );
}





?>




	</body>
</html>
