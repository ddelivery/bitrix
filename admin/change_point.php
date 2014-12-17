<?php
/**
 * User: dnap
 * Date: 08.10.14
 * Time: 10:07
 */
use Bitrix\Sale\Delivery\OrderDeliveryTable;
use DDelivery\DDeliveryUI;
use DDelivery\Order\DDStatusProvider;

/**
 * @var CMain $APPLICATION
 * @var CUser $USER
 */

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/prolog.php");
$saleModulePermissions = $APPLICATION->GetGroupRight("sale");
if ($saleModulePermissions == "D")
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$orderId = (int)isset($_REQUEST['order_id']) ? $_REQUEST['order_id'] : 0;
if(!$orderId) {
    LocalRedirect('/bitrix/admin/sale_order.php?lang='.LANG);
}

IncludeModuleLangFile(__FILE__);
$MODULE_ID = 'ddelivery.ddelivery';

$APPLICATION->SetTitle(GetMessage("DDELIVERY_NAME"));
require($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/prolog_admin_after.php");


$ddeliveryConfig = CSaleDeliveryHandler::GetBySID('ddelivery')->Fetch();

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

$orderDeliveryTableData = OrderDeliveryTable::getList(array('filter' => array('ORDER_ID' => $orderId)))->fetch();
if($orderDeliveryTableData) {
    $orderDeliveryParams = unserialize($orderDeliveryTableData['PARAMS']);
    $IntegratorShop = new DDeliveryAdminShop($ddeliveryConfig['CONFIG']['CONFIG'], $itemList, $formData);
    try{
        $ddeliveryUI = new DdeliveryUI($IntegratorShop);
        $order = $ddeliveryUI->initOrder($orderDeliveryParams['DD_LOCAL_ID']);
        if($order->ddStatus == DDStatusProvider::ORDER_CONFIRMED) {
            CAdminMessage::ShowMessage(array('MESSAGE' => GetMessage("DDELIVERY_ORDER_IN_PROGRESS"), 'TYPE' => 'ERROR'));
            $orderDeliveryTableData = false;
        }
    }catch (Exception $e){
        CAdminMessage::ShowMessage(array('MESSAGE' => $APPLICATION->ConvertCharset($e->getMessage(), 'utf-8', SITE_CHARSET), 'TYPE' => 'ERROR'));
    }
}else{
    $orderDeliveryParams = false;
}


$aTabs = array(
    array("DIV"=>"tab1", "TAB"=>GetMessage("DDELIVERY_TAB_NAME")),
);
$editTab = new CAdminTabControl("editTab", $aTabs);

$editTab->Begin();
$editTab->BeginNextTab();?>
<script src="/bitrix/components/ddelivery/static/js/ddelivery.js" charset="UTF-8"></script>
<tr class=heading>
    <td colspan=2>
        <?
        if($orderDeliveryParams){
            echo $orderDeliveryParams['DD_ABOUT'];
        }else{
            echo GetMessage("DDELIVERY_EMPTY_POINT");
        }
        //$order->ddStatus = DDStatusProvider::ORDER_IN_PROGRESS;
        ?>
    </td>
</tr>
<?if($orderDeliveryTableData && $orderDeliveryParams):?>
    <tr>
        <td colspan="2">
            <div id="ddeliveryIframe">Loading...</div>
        </td>
    </tr>
    <script>
        BX.ready(function () {
            BX.adminMenu.GlobalMenuClick('store');
            <?
            $url = '/bitrix/admin/ddelivery.ddelivery_ajax.php?&bx_order_id='.$orderId;
            if($orderDeliveryParams && $orderDeliveryParams['DD_LOCAL_ID']):
                $url .= '&order_id='.$orderDeliveryParams['DD_LOCAL_ID'];
            endif;?>
            var callbacks = {
                close: function(){
                    document.location.href='/bitrix/admin/sale_order_detail.php?ID=<?=$orderId?>';
                },
                change: function(data) {
                    document.location.href='/bitrix/admin/sale_order_detail.php?ID=<?=$orderId?>';
                }
            };
            DDelivery.delivery('ddeliveryIframe', '<?=$url?>', {}, callbacks);
        });

    </script>
<?endif;?>

<?$editTab->End();

require($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
?>
