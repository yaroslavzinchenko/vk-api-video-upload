<?php

require_once('config.php');

?>

<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<title>vk-api-video-upload</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
</head>
<body>
	<div class="container">
		<br>
		<a href="https://oauth.vk.com/authorize?client_id=<?php echo APPLICATION_ID; ?>&display=page&redirect_uri=https://oauth.vk.com/blank.html&scope=video&response_type=code&v=5.122" target="_blank
">Получить код</a>
		<br>
		<br>
			<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
				<div class="form-group">
    				<label for="code">Код</label>
    				<input type="text" class="form-control" id="code" name="code">
  				</div>
				<button type="submit" class="btn btn-primary">Отправить</button>
			</form>




<?php

if (isset($_POST['code']))
{
	$code = htmlspecialchars($_POST['code']);
	echo "<br> Код получен <br>";
}
else
{
	echo "<br> Код не определён <br> ";
	exit();
}



/*
URL для получения access_code.
code берётся из URL, который отдаёт ВК после отправки запроса предыдущим URL.
*/

# Получаем access_token для отправки запросов.

$urlGetAccessToken = 'https://oauth.vk.com/access_token?client_id=' . APPLICATION_ID . '&client_secret=' . CLIENT_SECRET . '&redirect_uri=https://oauth.vk.com/blank.html&code=' . $code;
$resultGetAccessToken = file_get_contents($urlGetAccessToken);
$resultGetAccessToken = json_decode($resultGetAccessToken, true);
$access_token = $resultGetAccessToken['access_token'];
echo "<br> access_token: " . $access_token . "<br>";
echo "expires_in: " . $resultGetAccessToken['expires_in'] . "<br>";
echo "user_id: " . $resultGetAccessToken['user_id'] . "<br>";



$rootDir = BASE_DIR;
$uploaded_videos = getUploadedVideos($mysqli);
scanDirectory($rootDir, $mysqli, $uploaded_videos, $access_token);




# Функции.

function scanDirectory($dirName, $mysqli, $uploaded_videos, $access_token)
{
	echo '<br><i><b>' . $dirName . '</b></i><br>';

	$dir = scandir($dirName);
	foreach($dir as $elem)
	{
		if (
		    $elem == '.'
            or $elem == '..'
            or $elem == 'index.php'
            or $elem == 'config.php'
            or $elem == '.git'
            or $elem == '.gitignore'
            or $elem == 'README.md'
            or $elem == 'desktop.ini'
            or $elem == 'Thumbs.db'
        )
		{
			continue;
		}

		# Если не директория, загружаем видео и обновляем информацию в БД.
		if (!is_dir($dirName . '/' . $elem))
		{
			echo $dirName . '/' . $elem . " is not a dir. <br>";

			$videoAlreadyExists = false;
			foreach($uploaded_videos as $uploaded_video)
			{
				// $albumName = str_replace(BASE_DIR, basename(BASE_DIR), $dirName);
				$albumName = str_replace(BASE_DIR, "", $dirName);
				$albumName = ltrim($albumName, "/");

				if ($elem == $uploaded_video['video_name'] and $albumName == $uploaded_video['album'])
				{
					$videoAlreadyExists = true;
					echo "Видео " . $dirName . '/' . $elem . " уже есть <br>";
					break;
				}
			}

			if (!$videoAlreadyExists)
			{
				$albums = getAlbums($access_token);

				$albumExists = false;

				foreach($albums as $album)
				{
					// $albumName = str_replace(BASE_DIR, basename(BASE_DIR), $dirName);
					$albumName = str_replace(BASE_DIR, "", $dirName);
					$albumName = ltrim($albumName, "/");

					if ($album['title'] == $albumName)
					{
						$albumId = $album['id'];
						$albumExists = true;
						break;
					}
				}

				if ($albumExists)
				{
					echo 'Альбом уже существует. <br>';
					$uploadResult = uploadVideoToVk($access_token, $dirName . '/' . $elem, $albumId);
					if ($uploadResult)
					{
						insertVideoInfo($elem, $uploadResult, $albumName, $mysqli);
					}
					else
					{
						echo "uploadResult != 'success' <br>";
					}
				}
				else
				{
					// $albumName = str_replace(BASE_DIR, basename(BASE_DIR), $dirName);
					$albumName = str_replace(BASE_DIR, "", $dirName);
					$albumName = ltrim($albumName, "/");
					
					echo 'Альбома ' . $albumName . ' ещё нет. <br>';
					$albumId = addAlbum($albumName, $access_token);
					$uploadResult = uploadVideoToVk($access_token, $dirName . '/' . $elem, $albumId);
					if ($uploadResult)
					{
						insertVideoInfo($elem, $uploadResult, $albumName, $mysqli);
					}
					else
					{
						echo "uploadResult != 'success'";
					}
				}
			}
		}
		# Если директория, вызываем функцию повторно.
		else
		{
			echo $dirName . '/' . $elem . '<b> is dir </b><br>';
			$uploaded_videos = getUploadedVideos($mysqli);
			scanDirectory($dirName . '/' . $elem, $mysqli, $uploaded_videos, $access_token);
		}	
	}
}

# Получаем все загруженные видеозаписи.
function getUploadedVideos($mysqli)
{
	$uploaded_videos = array();
	if ($result = mysqli_query($mysqli, "SELECT * FROM uploaded_videos"))
	{
		while ($row = mysqli_fetch_assoc($result)) 
		{
        	$uploaded_videos[] = array('video_name' => $row['video_name'], 'album' => $row['album']);
    	}
	}
	return $uploaded_videos;
}

function addAlbum($title, $access_token)
{
	$ch = curl_init();
	$parameters = http_build_query([
    	'title'        => $title,
    	'privacy'      => 'only_me',
    	'access_token' => $access_token,
    	'v'            => VK_API_VERSION,
	]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/video.addAlbum?' . $parameters);
	$curl_result = json_decode(curl_exec($ch), TRUE);

	if (isset($curl_result['error']))
	{
		var_dump($curl_result);
    	exit('Строка ' . __LINE__ . ': Ошибка при создании альбома: ' . $curl_result['error'] . '.');
	}

	echo 'Альбом ' . $curl_result['response']['album_id'] . ' создан. <br>';
	return $curl_result['response']['album_id'];
}

function getAlbums($access_token)
{
	$ch = curl_init();
	$parameters = http_build_query([
    	'owner_id'     => OWNER_ID,
    	'count'        => 100,
    	'need_system'  => 0,
    	'access_token' => $access_token,
    	'v'            => VK_API_VERSION,
	]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/video.getAlbums?' . $parameters);
	$curl_result = json_decode(curl_exec($ch), TRUE);

	if (isset($curl_result['error']))
	{
    	exit('Строка ' . __LINE__ . ': Ошибка при получении альбома: ' . $curl_result['error'] . '.');
	}

	$albums = array();
	foreach($curl_result['response']['items'] as $album)
	{
		$albums[] = array('title' => $album['title'], 'id' => $album['id']);
	}
	return $albums;
}

function uploadVideoToVk($access_token, $videoName, $albumId)
{
	$videoNameEncoded = bin2hex(random_bytes(30));

	# Получение адреса.
	echo "uploadVideoToVk: Получение адреса. <br>";
	$ch = curl_init();
	$parameters = http_build_query([
    	'name'         => $videoNameEncoded,
    	'wallpost'     => 0,
    	'album_id' 	   => $albumId,
    	'privacy_view' => 'only_me',
    	'repeat'       => 0,
    	'compression'  => 0,
    	'access_token' => $access_token,
    	'v'            => VK_API_VERSION,
	]);
	curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/video.save?' . $parameters);
	curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

	$curl_result = json_decode(curl_exec($ch), TRUE);

	if (isset($curl_result['error']))
	{
		var_dump($curl_result);
    	exit('Строка ' . __LINE__ . ': Ошибка при получении адреса для загрузки видео: ' . $curl_result['error'] . '.');
	}
	echo "Ссылка для загрузки видео получена. <br>";
	sleep(1);


	# Передача файла.
	$ch = curl_init();
	$parameters = ['video_file' => new CURLFile($videoName)];
	curl_setopt($ch, CURLOPT_URL, $curl_result['response']['upload_url']);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_SAFE_UPLOAD, TRUE);
	$curl_result = json_decode(curl_exec($ch), TRUE);
	curl_close($ch);
	if (isset($curl_result['error']))
	{
		var_dump($curl_result);
    	exit('<br> Строка ' . __LINE__ . ': Ошибка при загрузке видео на серверы ВК: ' . $curl_result['error'] . '.');
	}
	else
	{
		echo 'Видеозапись успешно загружена. <br>';
		sleep(1);
		return $videoNameEncoded;
	}
}

function insertVideoInfo($videoName, $videoNameEncoded, $album, $mysqli)
{
	$sql = "
		INSERT INTO uploaded_videos (video_name, video_name_encoded, album)
		VALUES ('$videoName', '$videoNameEncoded', '$album')
	";
	if (!mysqli_query($mysqli, $sql))
	{
		echo "Не удалось импортировать $videoName, $album " . mysqli_errno($mysqli) . ' ' . mysqli_error($mysqli) . '<br>';
	}
	else 
	{
		echo "Imported to MySQL: $videoName, $album <br><br>";
	}
}
	
?>

	</div>
</body>
</html>