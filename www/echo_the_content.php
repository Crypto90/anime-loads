<?php
$configFile = '/config/config.json';
if (!file_exists($configFile)) {
    header('Location: setup.php');
    exit;
}
$config = json_decode(file_get_contents($configFile), true);
$base_dir = $config['base_dir'];

error_reporting(E_ERROR | E_PARSE);
header('Access-Control-Allow-Origin: *');
header("Content-type: text/plain");

	//
	// Converts Bashoutput to colored HTML
	//
	function convertBash($code) {
		$dictionary = array(
			'[30m' => '<br><span style="color:black">',
			'[31m' => '<br><span style="color:red">', 
			'[32m' => '<br><span style="color:green">',   
			'[33m' => '<br><span style="color:orange">',
			'[34m' => '<br><span style="color:blue">',
			'[35m' => '<br><span style="color:purple">',
			'[36m' => '<br><span style="color:cyan">',
			'[37m' => '<br><span style="color:white">',
			'[m'   => '</span><br>',
			'[0m'   => '</span><br>'
		);
		$htmlString = str_replace(array_keys($dictionary), $dictionary, $code);
		//$htmlString = str_replace('\n', '<br>', $htmlString);
		return $htmlString;
	}



	if ($_GET['file'] == 1) {
		echo convertBash(file_get_contents($base_dir . '/manualOutput.log'));
	} else if ($_GET['file'] == 10) {
		echo convertBash(file_get_contents($base_dir . '/docker_live_output.log'));
	} else if ($_GET['file'] == 2) {
		
		
		
		$json=file_get_contents($base_dir . "/ani.json");
		$data =  json_decode($json);

		 if (count($data->anime)) {
			// Open the table
		
			echo '<div style="display: block; width: 80%; margin: 0 auto;">';
			$reverseIndex = count($data->anime) - 1;
			// Cycle through the array
			foreach (array_reverse($data->anime) as $anime) {
		
				$urlName = substr($anime->url, strrpos($anime->url, '/') + 1);
				
				$coverToDisplay = '';
				/*if (!file_exists('./anime_cover/'.$urlName.'.png')) {
					$url = 'https://www.anisearch.de/anime/index/page-1?char=all&text=' . $urlName . '&smode=1&sort=date&order=asc&view=2&kev=7478ce6e';
					//$url = 'https://www.anisearch.de/anime/index?text=' . $urlName . '&char=all&q=true&sort=date&order=asc&view=2';
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
				}*/
			
			
			
				// Output a row
				// cover url: https://www.anime-loads.org/files/image/w200-saikin-yatotta-maid-ga-ayashii-cover.jpg
				$urlName = substr($anime->url, strrpos($anime->url, '/') + 1);
				//echo '<td style="width: 70%;" ><div class="imageCoverDiv" imageCoverURL="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.jpg"></div><div class="imageCoverDiv" imageCoverURL="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.jpeg"></div><div class="imageCoverDiv" imageCoverURL="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.png"></div></td>';
				//echo '<td style="width: 70%;" ><img src="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.jpg" /><img src="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.jpeg" /><img src="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.png" /></td>';
				
			
				$flag = 'germany';
				if (strpos($anime->customPackage, 'japanese') !== false) {
					$flag = 'japan';
				}
			
				$maxEpisodesSaved = $anime->maxEpisodes;
				if ($maxEpisodesSaved == 1337) {
					$maxEpisodesSaved = 1;
				}
				$anisearchUrl = $anime->anisearchUrl;
				$number = preg_match('/\d+$/', $anisearchUrl, $matches);
				$anisearchId = $matches[0];
			
			
				$completedGreenBGStyle = '';
			
				if ((strpos($anime->customPackage, 'movie') !== false && $anime->episodes == 1 && count($anime->missing) == 0) || ($anime->episodes == $maxEpisodesSaved && count($anime->missing) == 0)) {
					$completedGreenBGStyle = 'background-color: rgb(15, 70, 25) !important;';
				}
				
				$releaseUID = "";
				if ($anime->releaseUID != "") {
					$releaseUID = "..." . substr($anime->releaseUID, -20);
				}
				
				
				if (strpos($anime->status, 'Release fehlerhaft') !== false && $releaseUID != "") {
					$completedGreenBGStyle = 'background-color: rgb(110, 0, 0) !important;';
				}
			
				echo '<div class="card bg-dark text-white mb-3" style="max-width: 373px; float: left; margin: 10px 10px 0 0 !important; height: 210px; width: 374px;' . $completedGreenBGStyle . '">';
				echo '  <div class="row g-0">';
				echo '	<div class="col-md-4">';
				//echo '	  <img data="' . $anime->url . '" style="width: 124px; height: 175px; cursor: pointer;" src="./anime_cover/'.$urlName.'.png" class="animeCover img-fluid rounded-start" alt="' . $anime->name . '">';
				echo '	  <img data="' . $anime->url . '" style="width: 124px; height: 175px; cursor: pointer;" src="getcover.php?url=' . urlencode($anime->coverUrl) . '&id=' . $anisearchId . '&urlName=' . urlencode($urlName) . '" class="animeCover img-fluid rounded-start" alt="' . $anime->name . '">';
				//echo '	  <img data="' . $anime->url . '" style="width: 124px; height: 175px; cursor: pointer;" src="' . $anime->coverUrl . '" class="animeCover img-fluid rounded-start" alt="' . $anime->name . '">';
				echo '	</div>';
				echo '	<div class="col-md-8">';
				echo '	  <div class="card-body" style="text-align: left; height: 210px; overflow-y: scroll; scrollbar-width: thin; scrollbar-color: #363e44 #202529;">';
				echo '		<h5 class="card-title" data="' . $anime->url . '" style="color: lightgrey; cursor: pointer;">' . $anime->name . ' <span style="color: green; font-size: 10px;">(' . $anime->year . ')</span> <br><span style="color: lightblue; font-size: 10px;">ReleaseID: ' . $anime->releaseID . '</span><br><span style="color: lightblue; font-size: 10px;">AniSearchID: ' . $anisearchId . '</span><br><span style="color: lightblue; font-size: 10px;">ReleaseUID: ' . $releaseUID . '</span></h5>';
				echo '	    <p class="card-text" style="color: orange; font-size: 12px;"><i class="bi bi-box-seam"></i> ' . $anime->customPackage . '</p>';
				echo '	    <p class="card-text" style="font-size: 12px;"><i class="bi bi-calendar-range"></i> Status: ' . $anime->status . '</p>';
				echo '	    <p class="card-text" style="color: green; font-size: 12px;"><i class="bi bi-file-earmark-check"></i> ' . ($anime->episodes . ' episodes / ' . $maxEpisodesSaved . ' total') . '</p>';
				echo '	    <p class="card-text" style="color: red; font-size: 12px;"><i class="bi bi-file-earmark-excel"></i> ' . (count($anime->missing) > 0 ? 'Episode # ' . implode(', ', $anime->missing) . ' missing' : '-') . '</p>';
				echo '	    <button data="?unmonitor=' . urlencode($anime->customPackage) . '" class="unmonitorBtn btn btn-danger btn-sm" style="position: absolute; left: 0; top: 179px; width: 124px; height: 26px; font-size: 10px; padding-top: 5px;">Nicht mehr beobachten</button>';
				echo '	    <img src="' . $flag . '.png" style="position: absolute; bottom:-5px; right: 15px; width: 30px; opacity: 0.5;" />';
				echo '	  </div>';
				echo '	</div>';
				echo '  </div>';
				echo '</div>';
				$reverseIndex--;
			}
			echo '</div>';
			
			
			
		
		}

				
		
		
		
		//$data = explode("\n",explode('"settings":', file_get_contents( "/volumeUSB10/usbshare/docker//anime-loads/ani.json" ))[0]);
		//foreach(array_reverse($data) as $value) { 
		//	echo $value."\n";
		//}
		
		//echo file_get_contents($base_dir . '/ani.json');
	} else if ($_GET['file'] == 3) {
		echo file_get_contents($base_dir . '/downloading_and_monitoring.txt');
	} else if ($_GET['file'] == 4) {
		echo file_get_contents($base_dir . '/no_releases_found_log.txt');
	} else if ($_GET['file'] == 77) {
		echo file_get_contents('queue.txt');
	} else if ($_GET['file'] == 78) {
		//echo file_get_contents('requestlog.txt');
		$logFilePath = 'requestlog.txt'; // Replace this with the path to your log file

		$lines = [];
		$linesToRead = 10;

		// Read the file line by line
		$file = new SplFileObject($logFilePath);
		$file->seek(PHP_INT_MAX); // Move the pointer to the end of the file
		
		$aniJsonContent = file_get_contents($base_dir . '/ani.json');
		
		// Start from the end of the file and store lines in an array
		while ($file->key() > 0 && count($lines) < $linesToRead) {
			$file->seek($file->key() - 1);
			array_unshift($lines, $file->current());
		}
		
		
		echo "<span style='color: red;'>RED</span> Currently not monitored | <span style='color: green;'>GREEN</span> Currently monitored\n";
		// Output the last 10 lines in reverse order (if needed)
		$count = 1;
		for ($i = $linesToRead - 1; $i >= 0; $i--) {
			if ($lines[$i] == '') {
				continue;
			}
			
			$animeURLName = end(explode("/", explode(";", $lines[$i])[0]));
			if (strpos($aniJsonContent, $animeURLName) !== false) { 
				echo "<span style='color: green;'>$count. " . $lines[$i] . "</span>\n";
			} else {
				echo "<span style='color: red;'>$count. " . $lines[$i] . "</span>\n";
			}
			
			//echo "$count. " . $lines[$i] . "\n";
			$count++;
		}
		
		
	} else if ($_GET['file'] == 5) {
        $extDir = $config['jd_extraction_dir'] ?? '';
        if (empty($extDir) || !is_dir($extDir)) {
            echo "";
        } else {
            $cmd = sprintf("find %s -path '*german*tv*' -printf '%%s -- %%p\n' -o -path '*german*movie*' -printf '%%s -- %%p\n' -o -path '*japanese*tv*' -printf '%%s -- %%p\n' -o -path '*japanese*movie*' -printf '%%s -- %%p\n' 2>/dev/null | grep -v -E 'completed|series_complete|intermediate|movies_complete|tmp'", escapeshellarg($extDir));
            $output = shell_exec($cmd);
            $output = preg_replace('/[^\d\s-].*\//', '', $output);
            $output = preg_replace('/[^\d\s-].*$/', '', $output);
            $output = preg_replace('/32 -- /', '<br />', $output);
            $output = nl2br(trim($output));
            echo $output;
        }
	} else if ($_GET['file'] == 7) {
        $downDir = $config['jd_download_dir'] ?? '';
        if (empty($downDir) || !is_dir($downDir)) {
            echo "";
        } else {
            $cmd = sprintf("find %s -path '*german*tv*' -printf '%%s -- %%p\n' -o -path '*german*movie*' -printf '%%s -- %%p\n' -o -path '*japanese*tv*' -printf '%%s -- %%p\n' -o -path '*japanese*movie*' -printf '%%s -- %%p\n' 2>/dev/null | grep -v -E 'completed|series_complete|intermediate|movies_complete|tmp'", escapeshellarg($downDir));
            $output = shell_exec($cmd);
            $output = preg_replace('/[^\d\s-].*\//', '', $output);
            $output = preg_replace('/[^\d\s-].*$/', '', $output);
            $output = preg_replace('/32 -- /', '<br />', $output);
            $output = nl2br(trim($output));
            echo $output;
        }
	} else if ($_GET['file'] == 6) {
		//generate cover
		//first check if cover does not exist
		//if (!file_exists('./anime_cover/w200-' . $_GET['urlName'] . '-cover.jpg')) {
			//$output = shell_exec('cp -u /config/animeloadsGetCover.py . &&  python animeloadsGetCover.py „https://www.anime-loads.org/media/black-rock-shooter-dawn-fall"');
		//}
	}
	
?>