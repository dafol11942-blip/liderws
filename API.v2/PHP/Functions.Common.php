<?php
function generateArticleUrl2($Num) {
	$Brand = $_GET['brand'];
	if ($Brand === 'skania') $Brand = 'scania';
	$Num = preg_replace("/[&nbsp;\W]/", "", $Num);
	if (($_GET['clid'] and $_GET['clid'] != 1) or !empty($_GET['pid']) or !empty($_GET['shopid'])) {
		$IdUppend = '';
		if (!empty($_GET['clid'])) $IdUppend .= "&clid={$_GET['clid']}";
		if (!empty($_GET['pid'])) $IdUppend .= "&pid={$_GET['pid']}";
		if (!empty($_GET['shopid'])) $IdUppend .= "&shopid={$_GET['shopid']}";
		$Link = "/redirect.php?brand={$Brand}&num={$Num}{$IdUppend}";
	} else $Link = str_replace(['<%API_URL_BRAND_NAME%>', '<%API_URL_PART_NUMBER%>'], [$Brand, $Num], apiArticlePartLink);
	if (!defined('apiArticlePartLinkTarget') or apiArticlePartLinkTarget == 1) $Target = '_blank'; else $Target = '';
	return "<a href='{$Link}' target='{$Target}'>{$Num}</a>";
}

function generateBrandUrl($Array) {
    if (is_array($Array)) {
        $Num = preg_replace("/[&nbsp;\W]/", "", $Array['number']);
        $Brand = preg_replace("/[\s\W]/", "", $Array['brand']);
        $PartBrand = preg_replace("/[\s\W]/", "", $Array['partbrand']);
        if (($_GET['clid'] and $_GET['clid'] != 1) or $_GET['pid'] or $_GET['shopid']) {
            $Link = "/redirect.php?brand={$Brand}&num={$Num}&partbrand={$PartBrand}" . ($_GET['clid'] ? "&clid={$_GET['clid']}" : "") . ($_GET['pid'] ? "&pid={$_GET['pid']}" : "") . ($_GET['shopid'] ? "&shopid={$_GET['shopid']}" : "");
        } else $Link = str_replace(['<%API_URL_BRAND_NAME%>', '<%API_URL_PART_NUMBER%>'], [$PartBrand, $Num], apiPartWBrandLink);
        if (!defined('apiPartWBrandLinkTarget') or apiPartWBrandLinkTarget == 1) $Target = '_blank'; else $Target = '';
        $res="<a href='{$Link}' target='{$Target}'>{$Num}</a>";
    } else
        $res='APITEST';

	return $res;
}

function generateLink2($LinkArr, $fullLink = true, $IgnoreVin = false) {
	if (!empty($_GET['clid'])) $LinkArr["params"]['clid'] = $_GET['clid'];
	if (!empty($_GET['pid'])) $LinkArr["params"]['pid'] = $_GET['pid'];
	if (!empty($_GET['shopid'])) $LinkArr["params"]['shopid'] = $_GET['shopid'];

	if (!empty($LinkArr['RobotsRedirect'])) {
		$LinkArr["params"]['clid'] = $LinkArr["params"]['pid'] = $LinkArr["params"]['shopid'] = $LinkArr["params"]['rewrite'] = '';
	}

	if (!empty($_GET['CSSManager'])) $LinkArr["params"]['CSSManager'] = $_GET['CSSManager'];
	if (!empty($_GET['cssdomain'])) $LinkArr["params"]['cssdomain'] = $_GET['cssdomain'];
	if (!empty($_GET['platform'])) $LinkArr["params"]['platform'] = $_GET['platform'];
	if (!empty($_GET['vin'])) {
		if (!empty($_GET['CatDefaultPage']) and !$IgnoreVin) {
			unset($LinkArr["params"]['vin']);
		}
	}
	if (empty($LinkArr["params"]["language"]) && isset($_GET["language"])) $LinkArr["params"]["language"] = $_GET["language"];
	global $IlcatsInjections;
	if ($IlcatsInjections) {
		$IlcatsInjection = 'generateLink2';
		include('IlcatsInjections.php');
	}
	if (!empty($LinkArr["params"]['brand']) and in_array($LinkArr["params"]['brand'], ['cataloglist', 'cataloglistTest'])) unset($LinkArr["params"]['brand']);
	if (!empty($LinkArr["params"]['LanguageLink']) and $LanguageLink = $LinkArr["params"]['LanguageLink'] == 1) {
		unset($LinkArr["params"]['LanguageLink'], $LinkArr["params"]['partInfo']);
	}
	if ($LinkArr["params"]) $Params = http_build_query($LinkArr["params"]);
	if (!empty($Params)) $Params = "?" . $Params;

	if (!empty($LinkArr["catRootUrl"])) {
		unset($brand);
		if ($_GET["language"] != 'ru') $Params = '?language=' . $_GET["language"];
	}
	if (empty($brand)) $brand = '';
	if (($brand == '/' or $brand == '/cataloglist' or $brand == '/cataloglistTest') and (!empty($LanguageLink) or !empty($LinkArr["params"]['VinAction']))) $brand = '';

	if (empty($Params)) $Params = '';
	$Link = $brand . '/' . $Params;
	if (!empty($LinkArr['urlAnchor'])) $Link .= '#' . $LinkArr['urlAnchor'];
	if (defined('apiHttpCatalogsPath') and apiHttpCatalogsPath) $Link = '/' . apiHttpCatalogsPath . $Link;

	if ($fullLink) {
		if ((!defined('apiPartUsageTarget') or apiPartUsageTarget == 1) and $_GET['function'] == 'getPartUsage') $Target = '_blank'; else $Target = '';
		if (empty($Title)) $Title = '';
		$Link = "<a href='{$Link}' title='{$Title}' target='{$Target}'>{$LinkArr['linkText']}</a>";
	}
	return $Link;
}

function getApiData($params, $apiKey = apiKey, $apiDomain = apiDomain, $cliId = apiClientId, $apiVersion = apiVersion) {
	//Show($params);
	$partnerClientIp = apiClientIpAddress;
	global $IlcatsInjections;
	if ($IlcatsInjections) {
		$IlcatsInjection = 'getApiData0';
		include('IlcatsInjections.php');
	}
	$st = "?clientId=$cliId&apiKey=$apiKey&apiVersion=$apiVersion&domain=$apiDomain" . "&partnerClientIp=" . $partnerClientIp . "&apiClientUserAgent=" . base64_encode(apiClientUserAgent);
	if ($params['function'] == 'getParts' and apiPartInfo > 0) $params["partInfo"] = apiPartInfo;
	foreach ($params as $key => $val)
		$st .= "&$key=" . $val;
	$url = apiServerUrl . $st;
	if ($IlcatsInjections) {
		$IlcatsInjection = 'getApiData';
		include('IlcatsInjections.php');
	}

	//if (apiClientIpAddress=='91.122.14.90') Show($url);

	$st = file_get_contents($url);
	//Show($st);
	$data = json_decode($st, true);
	return $data;
}

function ImplodeIfArray($Array, $Glue = '', $ReturnScalar = true) {
	return is_array($Array) ? implode($Glue, $Array) : ($ReturnScalar ? $Array : "");
}

function Show($V) {
	echo "<pre>";
	print_r($V);
	echo "</pre>";
}

function ShowApiAnswer($data, $debughash) {
	if ($debughash) {
		//Show(getApiData(['function'=>'checkDebugHash', 'debughash'=>$debughash]));
		if (getApiData(['function' => 'checkDebugHash', 'debughash' => $debughash])['result'])
			if ($data) Show($data);
			else 'Wrong server answer';
	}

}


?>