<?php
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
		echo convertBash(file_get_contents('/config/manualOutput.log'));
	} else if ($_GET['file'] == 10) {
		echo convertBash(file_get_contents('/config/docker_live_output.log'));
	} else if ($_GET['file'] == 2) {
		
		
		
		$json=file_get_contents("/config/ani.json");
		$data =  json_decode($json);

		 if (count($data->anime)) {
			// Open the table
		
			echo '<div style="display: block; width: 80%; margin: 0 auto;">';
			$reverseIndex = count($data->anime) - 1;
			// Cycle through the array
			foreach (array_reverse($data->anime) as $anime) {
		
				$urlName = substr($anime->url, strrpos($anime->url, '/') + 1);
				
				$coverToDisplay = '';
				if (!file_exists('./anime_cover/'.$urlName.'.png')) {
					$url = 'https://www.anisearch.de/anime/index?text=' . $urlName . '&char=all&q=true&sort=date&order=asc&view=2';
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
				}
			
			
			
				// Output a row
				// cover url: https://www.anime-loads.org/files/image/w200-saikin-yatotta-maid-ga-ayashii-cover.jpg
				$urlName = substr($anime->url, strrpos($anime->url, '/') + 1);
				//echo '<td style="width: 70%;" ><div class="imageCoverDiv" imageCoverURL="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.jpg"></div><div class="imageCoverDiv" imageCoverURL="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.jpeg"></div><div class="imageCoverDiv" imageCoverURL="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.png"></div></td>';
				//echo '<td style="width: 70%;" ><img src="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.jpg" /><img src="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.jpeg" /><img src="https://www.anime-loads.org/files/image/w200-' . $urlName . '-cover.png" /></td>';
				
			
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
				echo '	  <img data="' . $anime->url . '" style="width: 124px; height: 175px; cursor: pointer;" src="./anime_cover/'.$urlName.'.png" class="animeCover img-fluid rounded-start" alt="' . $anime->name . '">';
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

				
		
	} else if ($_GET['file'] == 3) {
		echo file_get_contents('/config/downloading_and_monitoring.txt');
	} else if ($_GET['file'] == 4) {
		echo file_get_contents('/config/no_releases_found_log.txt');
	} else if ($_GET['file'] == 5) {
		$output = shell_exec("find /downloads -path '*german*tv*' -printf '%s -- %p\n' -o -path '*german*movie*' -printf '%s -- %p\n' -o -path '*japanese*tv*' -printf '%s -- %p\n' -o -path '*japanese*movie*' -printf '%s -- %p\n' | grep -v -E 'completed|series_complete|intermediate|movies_complete|tmp'");// | grep -oP '[^/]*$'
		$output = preg_replace('/[^\d\s-].*\//', '', $output);
		$output = preg_replace('/[^\d\s-].*$/', '', $output);
		$output = preg_replace('/32 -- /', '<br />', $output);
		$output = nl2br($output);
		echo $output;
	} else if ($_GET['file'] == 7) {
		$output = shell_exec("find /volumeUSB10/usbshare -path '*german*tv*' -printf '%s -- %p\n' -o -path '*german*movie*' -printf '%s -- %p\n' -o -path '*japanese*tv*' -printf '%s -- %p\n' -o -path '*japanese*movie*' -printf '%s -- %p\n' | grep -v -E 'completed|series_complete|intermediate|movies_complete|tmp'");// | grep -oP '[^/]*$'
		$output = preg_replace('/[^\d\s-].*\//', '', $output);
		$output = preg_replace('/[^\d\s-].*$/', '', $output);
		$output = preg_replace('/32 -- /', '<br />', $output);
		$output = nl2br($output);
		echo $output;
	} else if ($_GET['file'] == 6) {
		//generate cover
		//first check if cover does not exist
		if (!file_exists('./anime_cover/w200-' . $_GET['urlName'] . '-cover.jpg')) {
			$output = shell_exec('cp -u /config/animeloadsGetCover.py . &&  python animeloadsGetCover.py „https://www.anime-loads.org/media/black-rock-shooter-dawn-fall"');
		}
	}
	
?>
