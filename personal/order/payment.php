<?
define('STOP_STATISTICS', true);
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$GLOBALS['APPLICATION']->RestartBuffer();
$httpClient = new \Bitrix\Main\Web\HttpClient();
$httpClient->disableSslVerification();
?>

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

111111111111111
<div class="container">
<?

$APPLICATION->IncludeComponent(
	"bitrix:sale.order.payment",
	"",
Array()
);

?>


</div>


<head>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
</head>
<style>
.mb-4 {
	width: 400px;
    margin-top: 4rem;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    margin: 0 auto;
    padding: 2rem;
    background: #f5f5f5;
    border-radius: 6px;
}
.sberbank__content {
	margin: 0 auto;
}

.sberbank__description {
	margin: 0 auto;
	margin-top: 2rem;

}

.btn {
	background: #1fbd54;
	padding: 1rem;
	border-radius: 4px !important;
	border: 1px solid;
	width: 300px;
	color: white;
	border:none;
	height: 60px;
	font-size: 30px;
	display: flex;
	justify-content: center;
	align-items: center;
	margin: 1rem 0;
}


@media (max-width: 405px) {
	.sberbank__content {
		width: 300px;
	}

	.sberbank__payment-link {

		width: 265px;
	}

}

</style>

<?//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>