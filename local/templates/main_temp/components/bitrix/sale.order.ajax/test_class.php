<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
	die();
}
 
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Loader;
 
class customOrderComponent extends CBitrixComponent
{
	/**
	 * @var \Bitrix\Sale\Order
	 */
	public $order;
 
	protected $errors = [];
 
	function __construct($component = null)
	{
		parent::__construct($component);
 
		if(!Loader::includeModule('sale')){
			$this->errors[] = 'No sale module';
		};
 
		if(!Loader::includeModule('catalog')){
			$this->errors[] = 'No catalog module';
		};
	}
 
	function onPrepareComponentParams($arParams)
	{
		if (isset($arParams['PERSON_TYPE_ID']) && intval($arParams['PERSON_TYPE_ID']) > 0) {
			$arParams['PERSON_TYPE_ID'] = intval($arParams['PERSON_TYPE_ID']);
		} else {
			if (intval($this->request['payer']['person_type_id']) > 0) {
				$arParams['PERSON_TYPE_ID'] = intval($this->request['payer']['person_type_id']);
			} else {
				$arParams['PERSON_TYPE_ID'] = 1;
			}
		}
 
		return $arParams;
	}
 
	protected function createVirtualOrder()
	{
		global $USER;
 
		try {
			$siteId = \Bitrix\Main\Context::getCurrent()->getSite();
			$basketItems = \Bitrix\Sale\Basket::loadItemsForFUser(
				\CSaleBasket::GetBasketUserID(), 
				$siteId
			)
				->getOrderableItems();
 
			if (count($basketItems) == 0) {
				LocalRedirect(PATH_TO_BASKET);
			}
 
			$this->order = \Bitrix\Sale\Order::create($siteId, $USER->GetID());
			$this->order->setPersonTypeId($this->arParams['PERSON_TYPE_ID']);
			$this->order->setBasket($basketItems);
		} catch (\Exception $e) {
			$this->errors[] = $e->getMessage();
		}
	}
 
	function executeComponent()
	{
		$this->createVirtualOrder();
	}
 
}