<?
use DDelivery\DDeliveryUI;

define("STOP_STATISTICS", true);

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$saleModulePermissions = $APPLICATION->GetGroupRight("sale");
if ($saleModulePermissions == "D")
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

header('Content-Type: text/html; charset=utf-8');

CModule::IncludeModule("sale");
$ddeliveryConfig = CSaleDeliveryHandler::GetBySID('ddelivery')->Fetch();
//$ddOrderId = $_REQUEST['order_id'];


$formData = array('bx_order_id' => $_REQUEST['bx_order_id']);
if(isset($_REQUEST['order_id'])){
    $formData['order_id'] = $_REQUEST['order_id'];
}

$dbBasketItems = CSaleBasket::GetList(
    array("ID" => "ASC"),
    array(
        "ORDER_ID" => $_REQUEST['bx_order_id']
    ),
    false,
    false,
    array('PRODUCT_ID', 'PRICE', 'QUANTITY', 'NAME')
);
while($arBasket = $dbBasketItems->Fetch()) {
    $itemList[] = $arBasket;
}

$IntegratorShop = new DDeliveryAdminShop($ddeliveryConfig['CONFIG']['CONFIG'], $itemList, $formData);
try{
    $ddeliveryUI = new DdeliveryUI($IntegratorShop);
    $order = $ddeliveryUI->initOrder($_REQUEST['order_id']);
    // В зависимости от параметров может выводить полноценный html или json
    $ddeliveryUI->render(isset($_REQUEST) ? $_REQUEST : array());
}catch (Exception $e){
    echo $e->getMessage();
    //throw $e;
}


require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_after.php");
?>