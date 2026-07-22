<?php

$scriptStartTime = microtime(1);
ob_start();
session_start();

header("Content-Type: text/html; charset=utf-8");

//Значение параметра partInfo по умолчанию
//partInfo default value
$partInfoValue = 1;
//
//
//if (file_exists('underConstruction.php')) {
//	include('underConstruction.php');
//}
if ($IlcatsInjections = file_exists('IlcatsInjections.php')) {
	$IlcatsInjection = 'Index1';
	include('IlcatsInjections.php');
}

if (file_exists('IlcatsInjections2.php')) include_once('IlcatsInjections2.php');

//error_reporting(E_ALL);
include_once('settings.php');

global $apiHttpCatalogsPath;
$apiHttpCatalogsPath='';
if (!empty(apiHttpCatalogsPath)) $apiHttpCatalogsPath='/'.apiHttpCatalogsPath;

include_once('API.v2/PHP/Functions.Common.php');
include_once('API.v2/PHP/Functions.Blocks.php');

if ($IlcatsInjections = file_exists('IlcatsInjections.php')) {
	$IlcatsInjection = 'Index1.2';
	include('IlcatsInjections.php');
}

if (empty($_GET['function'])) $_GET['function'] = 'defaultFunction';
if (empty($_GET['language'])) $_GET['language'] = $_COOKIE['language'] ? $_COOKIE['language'] : "ru";
$vinTmp = (empty($_GET["vin"]) ? array() : array("vin" => $_GET["vin"]));

if (empty($_GET["clid"])) $_GET["clid"] = '';
if (empty($_GET["pid"])) $_GET["pid"] = '';
if (empty($_GET["shopid"])) $_GET["shopid"] = '';
if (!empty($_GET['brand'])) $data = getApiData($_GET);
else $data = getApiData(array_merge(array("function" => "catalogsList", "brand" => 'cataloglist', "apiVersion" => '2.0', "shopClientId" => $_GET["clid"], "catalogId" => $_GET["pid"], "shopid" => $_GET["shopid"], "language" => $_GET["language"]), $vinTmp));
//print_r($data);
if (!empty($_GET["debughash"])) ShowApiAnswer($data, $_GET["debughash"]);


if (!empty($_GET['Ajax']) and $_GET['Ajax'] == 1) {
	$_GET['filterData']     = base64_decode($_GET['filterData']);
	$Answer['filterData']   = $_GET['filterData'];
	$Answer['PageSelector'] = $data['data'][1]['format']($data['data'][1]);
	$Answer['Tiles']        = $data['data'][2]['format']($data['data'][2]);

	//print_r($Answer);
	exit(json_encode($Answer));
}

if ($IlcatsInjections = file_exists('IlcatsInjections.php')) {
	$IlcatsInjection = 'Index1.5';
	include('IlcatsInjections.php');
}

$SiteLabels = $data['siteLabels'];
if (!empty($data['mainMenu'])) $Page['MainMenu'] = MainMenu($data['mainMenu']); else $Page['MainMenu'] = '';
if (!empty($data['availableLanguages'])) $Page['Languages'] = Languages($data['availableLanguages'], $apiActiveLanguages); //else $Page['Languages']="No 'availableLanguages'";
if ($data['data'])
	foreach ($data["data"] as $Data)
		$Page['Content'][] = (!empty($Data['caption']) ? "<h2>{$Data['caption']}</h2>" : "") . $Data['format']($Data, $SiteLabels);
else {
	if ($data["errors"])
		$Page['Content'][] = "<div class='ApiError'>" . ImplodeIfArray($data["errors"], '<br>') . "</div>";
	else $Page['Content'][] = "Wrong answer";
}

if (apiIlcatsIsPlugin) {
	$HtmlTags = array(
		'Start'   => "",
		'HeadEnd' => "",
		'Header'  => array('Start' => "<div class='PageHeader'>", 'End' => "</div>"),
		'Footer'  => array('Start' => "<div class='PageFooter'>", 'End' => "</div>"),
	);
} else {
	$HtmlTags = array(
		'Html'    => array('Start' => "<!DOCTYPE html><html lang='ru'><head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'><meta http-equiv='X-UA-Compatible' content='IE=edge'><meta name='viewport' content='width=device-width, initial-scale=0.7'>", 'End' => '</html>'),
		'HeadEnd' => "</head>",
		'Header'  => array('Start' => "<header>", 'End' => "</header>"),
		'Footer'  => array('Start' => "<footer>", 'End' => "</footer>"),
	);
}

echo $HtmlTags['Html']['Start'];
echo "<meta name='description' content='{$data["metas"]["description"]}'>
    		<meta name='keyword' content='" . ImplodeIfArray($data["metas"]["keywords"], ', ') . "'>
    		<title>{$data["metas"]["title"]}</title>";


if (apiLoadJquery) echo "<script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/JQuery-3.1.0.min.js'></script>";

echo "<script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/JQueryUI-1.12.0/JQueryUI.min.js'></script>
	  <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/JS/JQueryUI-1.12.0/JQueryUI.css'>
	  <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/jquery.scrollTo.190301.min.js'></script>
	  <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/jquery.pep.js'></script>
	  <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/fonts/ProximaNova/Font.css'>
	  <link type='text/css' rel='stylesheet' href='" . $apiHttpCatalogsPath . "/API.v2/CSS/Template2.191230.css'>
	  <script type='text/javascript' src='" . $apiHttpCatalogsPath . "/API.v2/JS/Common2.200410.js'></script>";

$clientId = empty($_GET["clid"]) ? "clid=" . apiClientId : "clid=" . $_GET["clid"];
$hostName = "&domain=" . (!empty($_GET['cssdomain']) ? $_GET['cssdomain'] : $_SERVER["HTTP_HOST"]);
$TestCSS  = (empty($_SESSION['CSSManager']) or empty($_GET['CSSManager'])) ? "" : "&TestCSS=" . $_SESSION['CSSManager'];
echo "<link type='text/css' rel='stylesheet' href='//www.ilcats.ru/getCss.php?" . $clientId . $TestCSS . "'>";
if ($hostName) echo "<link type='text/css' rel='stylesheet' href='//www.ilcats.ru/getCss.php?" . $clientId . $hostName . $TestCSS . "'>";

if ($IlcatsInjections = file_exists('IlcatsInjections.php')) {
	$IlcatsInjection = 'Index2';
	include('IlcatsInjections.php');
}
if (empty($_GET['brand'])) $_GET['brand'] = '';
echo $HtmlTags['HeadEnd'];
echo "<body class='" . $_GET['brand'] . "'>";
if ($IlcatsInjections) {
	$IlcatsInjection = 'Counters';
	include('IlcatsInjections.php');
}
echo "{$HtmlTags['Header']['Start']}<div class='Top'>{$Page['MainMenu']}{$Page['Languages']}</div>", VinForm($data['vinSearchParameters']), $HtmlTags['Header']['End'];
echo "<div id='Body' class='{$data['data'][0]['format']}Body'>";
if ($IlcatsInjections) {
	$IlcatsInjection = 'Advert1';
	include('IlcatsInjections.php');
}
echo "<h1>{$data["stageName"]}</h1>";
if ($data['data'][0]['format'] == 'ifImage') {
	$TempPageContent[0] = $Page['Content'][0];
	array_shift($Page['Content']);
	$TempPageContent[1] = "<div class='Info'>" . ImplodeIfArray($Page['Content']) . "</div>";
	$Page['Content']    = "<div class='ifImage'>" . ImplodeIfArray($TempPageContent) . "</div>";
}
echo ImplodeIfArray($Page['Content']);

if ($IlcatsInjections) {
	$IlcatsInjection = 'Advert2';
	include('IlcatsInjections.php');
}
echo "</div>
	<div id='Dialog'></div>";


echo $HtmlTags['Footer']['Start'];
if ($IlcatsInjections) {
	$IlcatsInjection = 'Index3';
	include('IlcatsInjections.php');
}
if (empty($CatSetup))
	echo "<div>{$data['siteLabels']['advertLinkUrl']}</div>";
else echo $CatSetup;

echo $ErrorFound, $HtmlTags['Footer']['End'];
echo "<script>console.log({$data['serverInfo']['dataGenerateTime']}, ". (microtime(1) - $scriptStartTime) . ")</script>";
echo "</body>";

echo $HtmlTags['Html']['End'];
ob_end_flush();

