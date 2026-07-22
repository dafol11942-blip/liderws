<? require_once ($_SERVER['DOCUMENT_ROOT']."/bitrix/modules/main/include.php");?>
<?
use Bitrix\Sale;
if (CModule::IncludeModule("sale") && CModule::IncludeModule("catalog"))
{

    function get_arBasketItems(){

        $arBasketItems = array();

        $dbBasketItems = CSaleBasket::GetList(
                array(
                        "NAME" => "ASC",
                        "ID" => "ASC"
                    ),
                array(
                        "FUSER_ID" => CSaleBasket::GetBasketUserID(),
                        "LID" => 's1',
                        "ORDER_ID" => "NULL"
                    ),
                false,
                false,
                array("ID", "CALLBACK_FUNC", "MODULE", 
                      "PRODUCT_ID", "QUANTITY", "DELAY", 
                      "CAN_BUY", "PRICE", "WEIGHT")
            );
        while ($arItems = $dbBasketItems->Fetch())
        {
            if (strlen($arItems["CALLBACK_FUNC"]) > 0)
            {
                CSaleBasket::UpdatePrice($arItems["ID"], 
                                         $arItems["CALLBACK_FUNC"], 
                                         $arItems["MODULE"], 
                                         $arItems["PRODUCT_ID"], 
                                         $arItems["QUANTITY"]);
                $arItems = CSaleBasket::GetByID($arItems["ID"]);
            }

            $arBasketItems[] = $arItems;
        }

        return $arBasketItems;

    }





  if (isset($_POST['PRODUCT_ID'])&&isset($_POST['QUANTITY'])) {
    $PRODUCT_ID = intval($_POST['PRODUCT_ID']);

    $QUANTITY = intval($_POST['QUANTITY']);
    Add2BasketByProductID(
      $PRODUCT_ID,
      $QUANTITY,
      false
    );

    $arBasketItems = get_arBasketItems();

    function get_all_price($arr)
    {
        $result = 0;

        foreach ($arr as $key => $value) {
            $result += $value['QUANTITY'] * $value['PRICE'];
        }

        return $result;
    }

    echo get_all_price($arBasketItems);



  }

  elseif ($_POST["PRODUCT_ID"] && $_POST["ajaxAction"] == 'delete_all'){

     $productId = $_POST["PRODUCT_ID"]; // id нашего товара

      // получаем корзину пользователя
      $basket = Sale\Basket::loadItemsForFUser(
        Sale\Fuser::getId(),
        Bitrix\Main\Context::getCurrent()->getSite()
      );

      /** @var Sale\BasketItem $basketItem */
      foreach ($basket as $basketItem) {
          if ($basketItem->getProductId() == $productId) {
              $basketItem->delete();
          }
      }

      $basket->save();

      // CSaleBasket::Delete($_POST["PRODUCT_ID"]);

     $arBasketItems = get_arBasketItems();

     


      function get_all_price($arr)
      {
          $result = 0;

          foreach ($arr as $key => $value) {
              $result += $value['QUANTITY'] * $value['PRICE'];
          }

          return $result;
      }

      echo get_all_price($arBasketItems);

  }

  elseif ($_POST["PRODUCT_ID"] && $_POST["ajaxAction"] == 'delete'){


    $productId = $_POST["PRODUCT_ID"]; // id нашего товара

    // получаем корзину пользователя
    $basket = Sale\Basket::loadItemsForFUser(
      Sale\Fuser::getId(),
      Bitrix\Main\Context::getCurrent()->getSite()
    );

    /** @var Sale\BasketItem $basketItem */
    foreach ($basket as $basketItem) {
        if ($basketItem->getProductId() == $productId) {
            $basketItem->setField('QUANTITY', $basketItem->getQuantity() - 1);
        }
    }

    $basket->save();

    // CSaleBasket::Delete($_POST["PRODUCT_ID"]);

   $arBasketItems = get_arBasketItems();

   


    function get_all_price($arr)
    {
        $result = 0;

        foreach ($arr as $key => $value) {
            $result += $value['QUANTITY'] * $value['PRICE'];
        }

        return $result;
    }

    echo get_all_price($arBasketItems);

    // echo FormatCurrency(get_all_price($arBasketItems), "RUB");

  }

  else {
    echo "Нет параметров ";
  }


}
else {
  echo "Не подключены модули";
}
?>