<?php

include("anticaptcha.php");
include("imagetotext.php");

/*
		Настройки
	*/
$login = "********"; //Логин (email)
$password = ""********";"; //Пароль
$resume_id = "********"; //Идентификатор обновляемого резюме


$pause = false; //Пауза

$log_limit = 100; //Число записей в лог-файле
$hash = "*************"; //Тест-комбинация просмотра статистики.

header('Content-type: text/html; charset=cp1251');

$logfile_content = @file_get_contents('job-mo.log');

$logfile_content = @unserialize($logfile_content);

if ($logfile_content === false || gettype($logfile_content) != 'array') $logfile_content = array();

if (count($logfile_content) > $log_limit) array_splice($logfile_content, $log_limit);

// Вывод статистики
if (isset($_GET["log"])) {
    if ($_GET["log"] != $hash) die('Ошибка доступа к статистике');

    if (count($logfile_content) == 0) die('Нет событий');

    $text = '';
    foreach ($logfile_content as $log) {
        if (gettype($log) == 'array' && isset($log['time']) && isset($log["name"])) {
            $text .= $log['time'] . ' ' . $log["name"] . '<br />';
        }
    }

    if ($text == '') die('Нет событий');

    die($text);
}

if ($pause) //Если пауза
{
    array_unshift($logfile_content, array('time' => date("Y-m-d H:i:s"), 'name' => 'Выполнение отменено - пауза'));
    LogWrite();
    die('Пауза');
}


$curl = curl_init("https://www.job-mo.ru/access.php");


//curl_setopt($curl, CURLOPT_COOKIESESSION, TRUE);
curl_setopt($curl, CURLOPT_COOKIEFILE, __DIR__ . "/cookie.txt");
curl_setopt($curl, CURLOPT_COOKIEJAR, __DIR__ . "/cookie.txt");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

//----------------------------------------------
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
//----------------------------------------------

curl_setopt($curl, CURLOPT_REFERER, "http://www.job-mo.ru/access.php");
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36');


$url_files = "https://www.job-mo.ru/script/antispam/index.php";

if (preg_match("/http/", $url_files)) {
    $ch = curl_init($url_files);

    curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . "/cookie.txt");
    curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . "/cookie.txt");

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
    $out = curl_exec($ch);
    $image_sv = 'картинка' . $nm . '.jpg';
    $img_sc = file_put_contents($image_sv, $out);
    curl_close($ch);
}


$api = new ImageToText();
$api->setVerboseMode(true);

//your anti-captcha.com account key
$api->setKey("*********");

//setting file
$api->setFile("картинка.jpg");

if (!$api->createTask()) {
    $api->debout("API v2 send failed - " . $api->getErrorMessage(), "red");
    return false;
}

$taskId = $api->getTaskId();


if (!$api->waitForResult()) {
    $api->debout("could not solve captcha", "red");
    $api->debout($api->getErrorMessage());
} else {
    $captchaText = $api->getTaskSolution();
//        echo "\nresult: $captchaText\n\n";

}

echo $captchaText;

curl_setopt($curl, CURLOPT_POSTFIELDS, "type=jobseeker&email=evgeniy.petroff@gmail.com&pass=eee2006&keystring=" . $captchaText . "&remember_me=1&submit=1");

$html = curl_exec($curl);

var_dump($html);


if (substr_count($html, 'В доступе отказано') > 0) {
    array_unshift($logfile_content, array('time' => date("Y-m-d H:i:s"), 'name' => 'В доступе отказано'));
    LogWrite();
    die('Ошибка авторизации');
}

curl_setopt($curl, CURLOPT_REFERER, "http://www.job-mo.ru/jobseekers/resume.php");
curl_setopt($curl, CURLOPT_URL, "http://www.job-mo.ru/jobseekers/resume.php?update=" . $resume_id);
$html = curl_exec($curl);

preg_match('/<\/a><\/p><p class="small">([0-9\.\, \:]*)<\/p><br><\/td>/Uis', $html, $date);


if (!isset($date[1])) {
    array_unshift($logfile_content, array('time' => date("Y-m-d H:i:s"), 'name' => 'Ошибка чтения даты обновления'));
    LogWrite();
    die('Ошибка чтения даты обновления');
}

$date = date("Y-m-d H:i", strtotime($date[1]));

array_unshift($logfile_content, array('time' => date("Y-m-d H:i:s"), 'name' => 'Установлена дата обновления ' . $date));
LogWrite();

echo 'Установлена дата обновления ' . $date;

function LogWrite()
{
    global $logfile_content;
    $fh = fopen('job-mo.log', "w+");
    $logfile_content = serialize($logfile_content);
    fwrite($fh, $logfile_content);
    fclose($fh);
}

?>


