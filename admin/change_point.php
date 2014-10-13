<?php
/**
 * User: dnap
 * Date: 08.10.14
 * Time: 10:07
 */
use Bitrix\Sale\Delivery\OrderDeliveryTable;
use DDelivery\DDeliveryUI;

/**
 * @var CMain $APPLICATION
 * @var CUser $USER
 */

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/prolog.php");
if(!$USER->IsAdmin())
    $APPLICATION->AuthForm('');

$orderId = (int)isset($_REQUEST['order_id']) ? $_REQUEST['order_id'] : 0;
if(!$orderId) {
    LocalRedirect('/bitrix/admin/sale_order.php?lang=ru');
}

IncludeModuleLangFile(__FILE__);
$MODULE_ID = 'ddelivery.ddelivery';

$APPLICATION->SetTitle(GetMessage("DDELIVERY_NAME"));
require($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/prolog_admin_after.php");


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
        $orderDeliveryTableData = OrderDeliveryTable::getList(array('filter' => array('ORDER_ID' => $orderId)))->fetch();
        if($orderDeliveryTableData){
            $orderDeliveryParams = unserialize($orderDeliveryTableData['PARAMS']);
            echo $orderDeliveryParams['DD_ABOUT'];
        }else{
            echo GetMessage("DDELIVERY_EMPTY_POINT");
            $orderDeliveryParams = false;
        }
        ?>
    </td>
</tr>
<?/*
<tr>
    <td>
        <?
        var_dump($orderDeliveryParams['DD_LOCAL_ID']);
        if($orderDeliveryParams['DD_LOCAL_ID']) {
            $DDConfig = CSaleDeliveryHandler::GetBySID('ddelivery')->Fetch();
            $cmsOrder = CSaleOrder::GetByID($orderId);

            $IntegratorShop = new DDeliveryShop($DDConfig['CONFIG']['CONFIG'], array(), array());
            $ddeliveryUI = new DdeliveryUI($IntegratorShop, true);
            $order = $ddeliveryUI->initOrder($orderDeliveryParams['DD_LOCAL_ID']);
            var_dump($order);
        }
        ?>
    </td>
</tr>*/?>
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
    })

</script>


<?$editTab->End();

require($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
?>
