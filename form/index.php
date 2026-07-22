<?
// require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
// $APPLICATION->SetTitle("form");


define('STOP_STATISTICS', true);
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
$GLOBALS['APPLICATION']->RestartBuffer();

?>
<?
CModule::IncludeModule("iblock");
$PROP = array();

//debug($_REQUEST);

//echo "11111111111111111111111111111111111111111111";





  $comment = $_REQUEST['comment'];
  $number_dogovor = $_REQUEST['number_dogovor'];
  $password = $_REQUEST['password'];
  $name = trim($_REQUEST['product-name']);
  $price = $_REQUEST['product-price'];
  $count = $_REQUEST['product-count'];
  $user_name = $_REQUEST['name'];
  $phone = trim($_REQUEST['phone']);
  $product_kod = trim($_REQUEST['product-kod']);
  $email = trim($_REQUEST['email']);


  // SERVICES

  $service_name = trim($_REQUEST['service-name']);


  $PROP['phone'] = $phone;
  $PROP['password'] = $password;
  $PROP['number_dogovor'] = $number_dogovor;
  $PROP['comment'] = $comment;
  $PROP['modal_name'] = $_REQUEST['modal-name'];
  $PROP['name_product'] = $name;	
  $PROP['price'] = $price;
  $PROP['count'] = $count;
  $PROP['user_name'] = $user_name;
  $PROP['product_kod'] = $product_kod;
  $PROP['service_name'] = $service_name;
  $PROP['email'] = $email;


function checkError()
{

	$_REQUEST['phone'] = preg_replace("/[^,.0-9]/", '', $_REQUEST['phone']);

	// echo json_encode($_REQUEST['phone']);
	
	if(!empty($_REQUEST['phone']) && strlen($_REQUEST['phone']) == 11){

		$res = array('Error' => 'N', 'Text' => 'Спасибо !', 'phone' => strlen($_REQUEST['phone']) );

		echo json_encode($res);

	}else{

		$res = array('Error' => 'Y', 'Text' => 'Поле телефона не заполнено!',  'phone' => strlen($_REQUEST['phone']) );

		echo json_encode($res);

		exit;
	}

}





switch ($_REQUEST['modal-name']) {

	case 'Поиск товара':
		
		$IBLOCK_ID = 51;

		checkError();


		break;


	case 'Заказ товара Автокаталог':
		
		$IBLOCK_ID = 41;

		checkError();


		break;

	case 'Заказ услуги':
	
		$IBLOCK_ID = 44;

		checkError();

		# code...
		break;
	case 'остались вопросы ?':
		
		$IBLOCK_ID = 40;

		checkError();


		break;

	case 'Заказать звонок':
		
		$IBLOCK_ID = 45;

		checkError();


		break;

	case 'Поставщикам':

		$IBLOCK_ID = 46;

		checkError();
		
		break;

	case 'Оптовым клиентам':

		$IBLOCK_ID = 47;

		checkError();

		# code...
		break;

	case 'Технический осмотр':

		$IBLOCK_ID = 48;

		checkError();
		
		# code...
		break;

	case 'Написать нам':

		$IBLOCK_ID = 22;
		# code...
		break;

	case 'Оформить заказ':

		$IBLOCK_ID = 23;
		# code...
		break;

	case 'Получить расчет на почту':

		$IBLOCK_ID = 24;
		# code...
		break;
	
	default:

		$IBLOCK_ID = 48;

		checkError();
		

		//  если не равно одним из них то все формы сюда



		# code...
		break;
}



if(empty($user_name)){
	$user_name = $number_dogovor;
}



//$message = "У вас новая заявка на сайте ! Наименование блока:". $_REQUEST['modal-name']  . "\r\n" .
//	"Наименование: " . $user_name . "\r\n" .
//	"Телефон: ". $phone . "\r\n";
//// На случай если какая-то строка письма длиннее 70 символов мы используем wordwrap()
//$message = wordwrap($message, 70, "\r\n");
//// Отправляем
//
//if (mail('tat-trud@inbox.ru', $_REQUEST['modal-name'] . 'С Сайта:Lider', $message)) {
//	echo 'Отправлено';
//}
//else {
//	echo 'Не отправлено';
//}

$mailFields = array('TYPE'=>$_REQUEST['modal-name'], 'NAME' => $user_name, 'PHONE' => $phone, 'MAIL' => $email, 'COMMENT' => $comment);


/* дальше используем метод CEvent::Send() или CEvent::SendImmediate()*/

CEvent::Send('FROM_FORM_USLIGY', 's1', $mailFields, 'N', 51);


$el = new CIBlockElement;

// $PROP = array();
// $PROP[12] = "Белый";  // свойству с кодом 12 присваиваем значение "Белый"
// $PROP[3] = 38;        // свойству с кодом 3 присваиваем значение 38

$arLoadProductArray = Array(
  "MODIFIED_BY"    => $USER->GetID(), // элемент изменен текущим пользователем
  "IBLOCK_SECTION_ID" => 0,          // элемент лежит в корне раздела
  "IBLOCK_ID"      => $IBLOCK_ID,
  "PROPERTY_VALUES"=> $PROP,
  "NAME"           => $user_name . ':' . $phone ,
  "ACTIVE"         => "Y",            // активен
  "PREVIEW_TEXT"   => "",
  "DETAIL_TEXT"    => "",
  "DETAIL_PICTURE" => CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"]."/image.gif")
  );

if($PRODUCT_ID = $el->Add($arLoadProductArray)){
  echo json_decode("New ID: ".$PRODUCT_ID);
}
else{
  echo json_decode("Error: ".$el->LAST_ERROR);
}

// echo "11111111111";

?>
<?//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>