<?
use DDelivery\DDeliveryUI;

define("STOP_STATISTICS", true);

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

// @TODO зафигачить все в компонет

header('Content-Type: text/html; charset=utf-8');

CModule::IncludeModule("sale");
$ddeliveryConfig = CSaleDeliveryHandler::GetBySID('ddelivery')->Fetch();
$cart = array();


$dbBasketItems = CSaleBasket::GetList(
    array("ID" => "ASC"),
    array(
        "FUSER_ID" => CSaleBasket::GetBasketUserID(),
        "LID" => SITE_ID,
        "ORDER_ID" => "NULL"
    ),
    false,
    false,
    array('PRODUCT_ID', 'PRICE', 'QUANTITY', 'NAME')
);
$itemList = array();
while($arBasket = $dbBasketItems->Fetch()) {
    $itemList[] = $arBasket;
}


$db_props = CSaleOrderProps::GetList(
    array("SORT" => "ASC"),
    array(
        "CODE" => 'DDELIVERY_ID',
        "ACTIVE" => 'Y',
    )
);

$props = $db_props->Fetch();
foreach($_REQUEST['formData'] as $key => $value) {

}



$IntegratorShop = new DDeliveryShop($ddeliveryConfig['CONFIG']['CONFIG'], $itemList, $_REQUEST['formData']);
try{
    $ddeliveryUI = new DDeliveryUI($IntegratorShop);
    $IntegratorShop->setDDeliveryUI($ddeliveryUI);
    // В зависимости от параметров может выводить полноценный html или json
    $ddeliveryUI->render(isset($_REQUEST) ? $_REQUEST : array());
}catch (Exception $e){
    var_dump($e);
}


require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_after.php");
?>