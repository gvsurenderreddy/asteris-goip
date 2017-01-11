<?php
/**
 * Created by IntelliJ IDEA.
 * User: user
 * Date: 04.10.15
 * Time: 0:25
 */
if($_GET['token']<>"PasSword") die("Не передан token"); //password form
if(empty($_GET['time'])) die("Не передан параметр время");
if(empty($_GET['text'])) die("Не передан параметр текст СМС");

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
include 'dbconfig.php';

$conn = mysqli_connect($dbhost, $dbuser, $dbpass) or die ('Error connecting to mysql');
mysqli_select_db($conn, $dbname);

$time = $_GET['time']; 
$text1 = trim($_GET['text']);
if (iconv_strlen($text1,'UTF-8') < 15 or iconv_strlen($text1,'UTF-8') > 60) {
    exit ("Сообщение должно состоять не менее 15 и не более 60 символов");
}
$textarr = explode("\n",wordwrap($text1, 60,"\n",1));

$calldate = date("Y-m-d");
$strSQL = "SELECT DISTINCT src FROM asterisk.cdr WHERE (dst = 'support' AND calldate >= '$calldate $time:00')";
$res = mysqli_query($conn, $strSQL);

function sendSms($value,$text,$counter){
global $goip_addr, $goip_user, $goip_password;
    $context = stream_context_create(array(
        'http' => array(
            'header' => "Authorization: Basic " . base64_encode("$goip_user:$goip_password")
        )
    ));
    $data = file_get_contents($goip_addr . "/default/en_US/tools.html?type=sms", false, $context);
    preg_match_all("|name=\"smskey\" value=\"(.*?)\">|is", $data, $smskey);

    $postdata = http_build_query(
        array(
            'line' => $counter,
            'smskey' => $smskey[1][0],
            'action' => 'SMS',
            'telnum' => $value,
            'smscontent' => $text,
            'send' => 'Send',
        )
    );
    $context = stream_context_create(
        array('http' =>
            array(
                'method' => 'POST',
                'header' => "Authorization: Basic " . base64_encode("$goip_user:$goip_password") . "\r\n" .
                    "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postdata
            )
        )
    );
    file_get_contents($goip_addr . "/default/en_US/sms_info.html?type=sms", false, $context);

}

while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
    $numbers[] = ($row['src']);
}
$counter=1;
$i=0;
echo "<table border='1'>";
echo "<tr><td>Номер</td><td>Текст СМС</td></tr>";
foreach ($numbers as &$value)
{foreach ($textarr as &$text){
    echo "<tr><td>$value</td><td>$text</td></tr>";
    sendSms($value,$text,$counter); 
	sleep(2);}

    $counter++;
    $i++;
    if ($counter > 7){
        sleep(2);
        $counter =1;}
}
echo "</table>";
mysqli_close($conn); die("$i SMS отправленны");
