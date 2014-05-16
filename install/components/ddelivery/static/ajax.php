<?
use DDelivery\DDeliveryUI;

define("STOP_STATISTICS", true);

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

// @TODO ���������� ��� � ��������

header('Content-Type: text/html; charset=utf-8');

CModule::IncludeModule("sale");
$ddeliveryConfig = CSaleDeliveryHandler::GetBySID('DigitalDelivery')->Fetch();
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


$ddeliveryUI = new DDeliveryUI($IntegratorShop);
// � ����������� �� ���������� ����� �������� ����������� html ��� json
$ddeliveryUI->render(isset($_REQUEST) ? $_REQUEST : array());



require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_after.php");
?>