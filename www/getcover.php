<?php
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
	
	if (isset($_GET['url']) && $_GET['url'] != '') {
		if (is_file('./anime_cover/' . $_GET['url'] . '.webp')) {
			$name = './anime_cover/' . $_GET['url'] . '.webp';
			$fp = fopen($name, 'rb');
			// send the right headers
			header("Content-Type: image/webp");
			header("Content-Length: " . filesize($name));
			// dump the picture and stop the script
			fpassthru($fp);
		} else if (is_file('./anime_cover/' . $_GET['url'] . '.jpeg')) {
			$name = './anime_cover/' . $_GET['url'] . '.jpeg';
			$fp = fopen($name, 'rb');
			// send the right headers
			header("Content-Type: image/jpeg");
			header("Content-Length: " . filesize($name));
			// dump the picture and stop the script
			fpassthru($fp);
		} else if (is_file('./anime_cover/' . $_GET['url'] . '.png')) {
			$name = './anime_cover/' . $_GET['url'] . '.png';
			$fp = fopen($name, 'rb');
			// send the right headers
			header("Content-Type: image/png");
			header("Content-Length: " . filesize($name));
			// dump the picture and stop the script
			fpassthru($fp);
		} else {
			// we have to download it.
			header("Content-Type: image/webp");
			$urlName = $_GET['url'];
			$urlId = $_GET['id'];
			
			//$url = "https://www.anime-loads.org/%2f/files/image/w200-" . $urlName . "-cover.jpg";
			$url = "https://cdn.anisearch.de/images/anime/cover/" . substr($urlId, 0, 2) . "/" . $urlId . "_300.webp";
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			
			
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1)AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.1 Safari/537.11');
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); 
			curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
			//curl_setopt($ch, CURLOPT_REFERER, 'https://www.anime-loads.org/');
			$res = curl_exec($ch);
			$rescode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
			curl_close($ch) ;
			
			$file = './anime_cover/' . $urlName . '.webp'; // File name to save the image
			$fp = fopen($file, 'w'); // Open a file handle in write mode

			if ($fp === false) {
				// Handle the error if unable to open the file
				echo 'Error opening file';
			} else {
				fwrite($fp, $res); // Write the webP data to the file
				fclose($fp); // Close the file handle
				//echo 'File saved as ' . $file;
			}
			
			
			//we have to delete non 200px width images, they are wrong
			if (is_file('./anime_cover/' . $urlName . '.webp')) {
				list($width, $height) = getimagesize('./anime_cover/' . $urlName . '.webp');
				
				if ($width != 300) {
					unlink('./anime_cover/' . $urlName . '.webp');
				} else {
					echo $res;
				}
			}
			
			/*
			if (!is_file('./anime_cover/' . $urlName . '.jpg')) {
				//we try png
				$url = "https://www.anime-loads.org/%2f/files/image/w200-" . $urlName . "-cover.png";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				
				$fp = fopen('./anime_cover/' . $urlName . '.png', 'wb');
				curl_setopt($ch, CURLOPT_FILE, $fp);
				
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1)AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.1 Safari/537.11');
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); 
				curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
				curl_setopt($ch, CURLOPT_REFERER, 'https://www.anime-loads.org/');
				$res = curl_exec($ch);
				$rescode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
				curl_close($ch) ;
				fclose($fp);
				
				//we have to delete non 200px width images, they are wrong
				if (is_file('./anime_cover/' . $urlName . '.png')) {
					list($width, $height) = getimagesize('./anime_cover/' . $urlName . '.png');
					
					if ($width != 200) {
						unlink('./anime_cover/' . $urlName . '.png');
					} else {
						echo $res;
					}
				}
				
				
			}
			
			if (!is_file('./anime_cover/' . $urlName . '.png')) {
				//we try jpeg
				$url = "https://www.anime-loads.org/%2f/files/image/w200-" . $urlName . "-cover.jpeg";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				
				$fp = fopen('./anime_cover/' . $urlName . '.jpeg', 'wb');
				curl_setopt($ch, CURLOPT_FILE, $fp);
				
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1)AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.1 Safari/537.11');
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); 
				curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
				curl_setopt($ch, CURLOPT_REFERER, 'https://www.anime-loads.org/');
				$res = curl_exec($ch);
				$rescode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
				curl_close($ch) ;
				fclose($fp);
				
				
				
				//we have to delete non 200px width images, they are wrong
				if (is_file('./anime_cover/' . $urlName . '.jpeg')) {
					list($width, $height) = getimagesize('./anime_cover/' . $urlName . '.jpeg');
					
					if ($width != 200) {
						unlink('./anime_cover/' . $urlName . '.jpeg');
					} else {
						echo $res;
					}
				}
				
				
			}*/
			
			
			
			
			
		}
		
	    
    }
?>
