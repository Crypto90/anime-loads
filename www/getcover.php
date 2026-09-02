<?php
$configFile = '/config/config.json';
if (!file_exists($configFile)) {
    header('Location: setup.php');
    exit;
}
$config = json_decode(file_get_contents($configFile), true);
$base_dir = $config['base_dir'];

	ini_set('display_errors', 0);
	ini_set('display_startup_errors', 0);
	error_reporting(0);
	
	$rawName = $_GET['urlName'] ?? ($_GET['url'] ?? '');
	$urlName = basename(trim($rawName));

	if ($urlName != '') {
		if (is_file('./anime_cover/' . $urlName . '.webp')) {
			$name = './anime_cover/' . $urlName . '.webp';
			$fp = fopen($name, 'rb');
			header("Content-Type: image/webp");
			header("Content-Length: " . filesize($name));
			fpassthru($fp);
			exit;
		} else if (is_file('./anime_cover/' . $urlName . '.jpeg')) {
			$name = './anime_cover/' . $urlName . '.jpeg';
			$fp = fopen($name, 'rb');
			header("Content-Type: image/jpeg");
			header("Content-Length: " . filesize($name));
			fpassthru($fp);
			exit;
		} else if (is_file('./anime_cover/' . $urlName . '.png')) {
			$name = './anime_cover/' . $urlName . '.png';
			$fp = fopen($name, 'rb');
			header("Content-Type: image/png");
			header("Content-Length: " . filesize($name));
			fpassthru($fp);
			exit;
		} else {
			$urlId = preg_replace('/[^0-9]/', '', $_GET['id'] ?? '');
			if (empty($urlId)) {
				http_response_code(404);
				exit;
			}
			$url = "https://cdn.anisearch.de/images/anime/cover/" . substr($urlId, 0, 2) . "/" . $urlId . "_600.webp";
			
			//var_dump($url);
			//die();
			
			
			// Use pathinfo to extract information about the URL
			$pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));

			// Get the file extension
			$extension = isset($pathInfo['extension']) ? $pathInfo['extension'] : '';
			
			//var_dump($url);die();
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			
			
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36');
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); 
			curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
			//curl_setopt($ch, CURLOPT_REFERER, 'https://www.anime-loads.org/');
			$res = curl_exec($ch);
			$rescode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
			curl_close($ch) ;
			
			if ($rescode == 200) {
				$file = './anime_cover/' . $urlName . '.' . $extension; // File name to save the image
				//$file = './anime_cover/' . $urlName . '.png'; // File name to save the image
				$fp = fopen($file, 'w'); // Open a file handle in write mode

				if ($fp === false) {
					// Handle the error if unable to open the file
					echo 'Error opening file';
				} else {
					fwrite($fp, $res); // Write the webP data to the file
					fclose($fp); // Close the file handle
					//echo 'File saved as ' . $file;
					
					header("Content-Type: image/" . $extension);
					echo $res;
					
				}
			} else {
				echo $url;
				echo "<br>";
				echo "Error response code while downloading cover image: " . $rescode;
			}
			
			
			
			
			
			
			
			
			/*
			
			// Use pathinfo to extract information about the URL
			$pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));

			// Get the file extension
			$extension = isset($pathInfo['extension']) ? $pathInfo['extension'] : '';
			
			
			$flarerSolverrUrl = 'http://192.168.178.26:8191/v1'; // FlareSolverr Docker container URL
			$imageUrl = $url; // URL of the image
			$outputFile = './anime_cover/' . $urlName . '.' . $extension; // Path to save the downloaded image

			// Create the POST body for FlareSolverr request
			$postBody = json_encode([
				"cmd" => "request.get",
				"url" => $imageUrl,
				"maxTimeout" => 60000
			]);

			$ch = curl_init($flarerSolverrUrl);

			// Set headers and other cURL options
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);

			// Execute the request
			$response = curl_exec($ch);

			// Check for errors
			if (curl_errno($ch)) {
				echo 'cURL error: ' . curl_error($ch);
			} else {
				// Decode the JSON response
				$responseData = json_decode($response, true);

				// Check if the response contains the image data
				if (isset($responseData['status']) && $responseData['status'] === 'ok' && isset($responseData['solution']['response'])) {
					// Get the body of the response which should contain the image data
					$imageData = base64_decode($responseData['solution']['response']);

					// Save the image data to a file
					if (file_put_contents($outputFile, $imageData)) {
						//echo 'Image downloaded successfully and saved to ' . $outputFile;
						
						header("Content-Type: image/" . $extension);
						echo $imageData;
						
					} else {
						echo 'Error saving image to file.';
					}
				} else {
					echo 'Failed to download image. Response: ' . print_r($responseData, true);
				}
			}

			// Close the cURL session
			curl_close($ch);
			*/
			
			
			
			
			
			/*
			$urlBase = "https://www.anime-loads.org/files/image/w200-" . $urlName . "-cover";
			$extensions = ['png', 'jpg', 'jpeg', 'webp'];
			$success = false;
			$urlsTried = "";
			foreach ($extensions as $ext) {
				$url = $urlBase . '.' . $ext;
				
				$urlsTried = $urlsTried . $url ."<br>";
				
				// Initialize cURL
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1)AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.1 Safari/537.11');
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); 
				curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout in seconds

				// Execute the request
				$res = curl_exec($ch);
				$rescode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
				curl_close($ch);
				
				
				
				list($width, $height) = getimagesize($res);
				
				if ($width != 200) {
					continue;
				}

				// Check if the request was successful
				if ($res !== false) { //$rescode == 200 && 
					$file = './anime_cover/' . $urlName . '.' . $ext; // File name to save the image
					$fp = fopen($file, 'w'); // Open a file handle in write mode

					if ($fp === false) {
						echo 'Error opening file: ' . $file;
					} else {
						fwrite($fp, $res); // Write the image data to the file
						fclose($fp); // Close the file handle
						//echo 'File saved as ' . $file;
						$success = true;
						
						header("Content-Type: image/" . $ext);
						echo $res;
						
						break; // Stop the loop since one file was successfully downloaded
					}
				}
			}

			if (!$success) {
				echo 'Failed to download image with any of the extensions.<br>';
				echo $urlsTried;
			}
			*/
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			//we have to delete non 200px width images, they are wrong
			/*if (is_file('./anime_cover/' . $urlName . '.png')) {
				list($width, $height) = getimagesize('./anime_cover/' . $urlName . '.png');
				
				if ($width != 300) {
					unlink('./anime_cover/' . $urlName . '.png');
				} else {
					header("Content-Type: image/webp");
					echo $res;
				}
			}*/
			
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