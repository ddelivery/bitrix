<?php
/**
 * User: dnap
 * Date: 10.10.14
 * Time: 11:59
 */

use Bitrix\Sale\Delivery\OrderDeliveryTable;
use DDelivery\Sdk\DDeliverySDK;

if(!class_exists('DDeliveryShopEx', true)) {
    /** @noinspection PhpUndefinedClassInspection */
    class DDeliveryShopEx extends DDeliveryShop{};
}

class DDeliveryAdminShop extends DDeliveryShopEx {
    public function getPhpScriptURL()
    {
        return '/bitrix/admin/ddelivery.ddelivery_ajax.php?'.http_build_query($this->formData, "", "&");
    }

    /**
     * @param \DDelivery\Order\DDeliveryOrder $order
     * @throws \Bitrix\Main\ArgumentException
     */
    public function onFinishChange($order)
    {
        global $APPLICATION;
        $point = $order->getPoint();

        if( $order->type == DDeliverySDK::TYPE_SELF ){
            $replaceData = array(
                '%1' => $order->cityName,
                '%2' => $point['address'],
                '%3' => $point['delivery_company_name'],
                '%4' => $point['_id'],
                '%5' => $point['type'] == 1 ?'Постомат':'ПВЗ',
            );
            $replaceData = $APPLICATION->ConvertCharsetArray($replaceData, 'UTF-8', SITE_CHARSET);

            $comment = GetMessage('DDELIVERY_ABOUT_SELF', $replaceData);
        }else if( $order->type == DDeliverySDK::TYPE_COURIER ){
            $replaceData = array(
                '%1' => $order->getFullAddress(),
                '%2' => $point['delivery_company_name']);
            $replaceData = $APPLICATION->ConvertCharsetArray($replaceData, 'UTF-8', SITE_CHARSET);
            $comment = GetMessage('DDELIVERY_ABOUT_COURIER', $replaceData);
        }else{
            $comment = 'error';
        }

        $orderId = $this->formData['bx_order_id'];
        $params = array('DD_ABOUT' => $comment, 'DD_LOCAL_ID' => $order->localId);
        $orderDeliveryTableData = OrderDeliveryTable::getList(array('filter' => array('ORDER_ID' => $orderId)))->fetch();
        if($orderDeliveryTableData) {
            OrderDeliveryTable::update($orderDeliveryTableData['ID'], array('PARAMS' => serialize($params)));
        }else{
            OrderDeliveryTable::add(array('ORDER_ID' => $orderId, 'PARAMS' => serialize($params)));
        }


    }


} 