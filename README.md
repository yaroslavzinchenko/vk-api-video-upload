# vk-api-video-upload
Application that interacts with VK API, uploads all videos from certain directory and sorts them by albums according to the directory they are located in.

To make this application work, you need to put file named **config.php** into the root directory.

**config.php** file example:
```
<?php
define('VK_API_VERSION', 5.122);
define('REDIRECT_URI', 'https://oauth.vk.com/blank.html');
# BASE_DIR defines the directory from where you want to upload your videos.
define('BASE_DIR', 'C:/Users/User/Desktop/GitHub/vk-api-video-upload');
define('OWNER_ID', 111111111);
define('APPLICATION_ID', 2222222);
define('CLIENT_SECRET', 'eee3xxaa4mmpplle1eee');

# Подключаемся к MySQL.
$mysqli = new mysqli('localhost', 'root', 'root', 'vk_api_video_upload');
if ($mysqli->connect_errno)
{
	echo "Не удалось подключиться к MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}
else
{
	echo '<br> Подключение к MySQL установлено. <br>';
}
?>
```

The uploaded videos will be located in albums according to the directory they are located in.

For example, if the **BASE_URL** defined as **'C:\MyFolder'**, and there is a video that has a path **'C:\MyFolder/Subfolder1/Subfolder2/MyVideo'**,
the album name will be **'Subfolder1/Subfolder2'** and the video name will be **'MyVideo'**.
