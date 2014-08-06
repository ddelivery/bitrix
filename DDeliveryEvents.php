<?php
/**
 * User: DnAp
 * Date: 16.05.14
 * Time: 12:56
 */

use DDelivery\DDeliveryUI;
use DDelivery\Order\DDeliveryOrder;
use DDelivery\Point\DDeliveryPointCourier;
use DDelivery\Point\DDeliveryPointSelf;
use DDelivery\Sdk\DDeliverySDK;

include_once(__DIR__.'/application/bootstrap.php');

class DDeliveryEvents
{
    function Init()
    {
        include(__DIR__.'/install/version.php');
        /** @var $arModuleVersion string[] */
        $select = ddeliveryFromCp1251('Выбрать');
        $html = '
            <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
            <script src="/bitrix/components/ddelivery/static/include.js" language="javascript" charset="utf-8"></script>
            <script src="/bitrix/components/ddelivery/static/js/ddelivery.js" language="javascript" charset="utf-8"></script>

            <span id="ddelivery">
                <span><script>
                    document.write(DDeliveryIntegration.getStatus());
                </script></span>
                <a href="javascript:DDeliveryIntegration.openPopup()">'.$select.'</a>
            </span>';
        $html = str_replace(array("\n", "\r"), array(' ', ''), $html);

        return array(
            /* Basic description */
            "SID" => "ddelivery",
            "NAME" => ddeliveryFromCp1251(GetMessage('DIGITAL_DELIVERY_NAME')),
            "DESCRIPTION" => ddeliveryFromCp1251(GetMessage('DIGITAL_DELIVERY_DESCRIPTION')),
            "DESCRIPTION_INNER" => ddeliveryFromCp1251(GetMessage('DIGITAL_DELIVERY_DESCRIPTION_INNER')),
            "BASE_CURRENCY" => "RUB",//COption::GetOptionString("sale", "default_currency", "RUB"),

            "HANDLER" => __FILE__,

            /* Handler methods */
            "DBGETSETTINGS" => array(__CLASS__, "GetSettings"),
            "DBSETSETTINGS" => array(__CLASS__, "SetSettings"),
            "GETCONFIG" => array(__CLASS__, "GetConfig"),

            "COMPABILITY" => array(__CLASS__, "Compability"),
            "CALCULATOR" => array(__CLASS__, "Calculate"),

            /* Список профилей */
            "PROFILES" => array(
                "all" => array(
                    "TITLE" => 'ddelivery.ru',//ddeliveryFromCp1251(GetMessage('DIGITAL_DELIVERY_PROFILE_NAME')),
                    "DESCRIPTION" => $html,
                    "RESTRICTIONS_WEIGHT" => array(0),
                    "RESTRICTIONS_SUM" => array(0),
                ),
            )
        );
    }
    /* Запрос конфигурации службы доставки */
    function GetConfig()
    {
        global $APPLICATION;
        $dbProps = CSaleOrderProps::GetList(
            array("SORT" => "ASC"),
            array(
                "ACTIVE" => 'Y',
                "USER_PROPS" => "Y",
                'REQUIED' => 'Y',
            ),
            false,
            false,
            array()
        );
        $props = array();
        while($prop = $dbProps->Fetch()){
            $props[$prop['CODE']] = $prop['NAME'];
        }

        $dbResultList = CSaleStatus::GetList(
            array('SORT' => 'ASC'),
            array("LID" => LANGUAGE_ID),
            false,
            false,
            array("ID", "NAME")
        );
        $sendStatusValues = array();
        while ($arResult = $dbResultList->Fetch()){
            $sendStatusValues[$arResult['ID']] = $arResult['NAME'];
        }

        $jsHack = '<script>
            BX.ready(function() {
                var el = BX("bxlhe_frame_hndl_dscr_all");
                while(el = el.parentNode) {
                    if(el.tagName == "TR")
                        break;
                }
                BX.remove(el);
                el = document.getElementsByName("HANDLER[BASE_CURRENCY]")[0];
                while(el = el.parentNode) {
                    if(el.tagName == "TR")
                        break;
                }
                BX.remove(el);
            });
        </script>';
        $arConfig = array(
            "CONFIG_GROUPS" => array(
                "general" => GetMessage('DIGITAL_DELIVERY_CONFIG_GROUPS_GENERAL'),
                "type" => GetMessage('DIGITAL_DELIVERY_CONFIG_GROUPS_TYPE'),
                "price" => GetMessage("DIGITAL_DELIVERY_CONFIG_GROUPS_PRICE")
            ),
            "CONFIG" => array(
                "API_KEY"=> array(
                    "TYPE" => "STRING",
                    "DEFAULT" => '',
                    "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_API_KEY'),
                    "GROUP" => "general",
                    "POST_TEXT"=> 'Ключ можно получить в личном кабинете DDelivery.ru, зарегестрировашись на сайте (для новых клиентов).'.$jsHack,
                ),
                "API_KEY_KIT"=> array(
                    "TYPE" => "STRING",
                    "DEFAULT" => '',
                    "TITLE" => 'API Ключ КИТ',
                    "GROUP" => "general",
                    "POST_TEXT"=> 'Ключ для компании КИТ',
                ),
                "TEST_MODE"=> array(
                    "TYPE" => "DROPDOWN",
                    "DEFAULT" => 1,
                    "TITLE" => 'Режим работы',
                    "VALUES" => array(
                        1 => 'Тестирование(stage)',
                        0 => 'Боевое(client)',
                    ),
                    "POST_TEXT"=> "Для отладки работы модуля, используйте пожалуйста режим Тестирование",
                    "GROUP" => "general",
                ),
                "DECLARED_PERCENT"=>array(
                    "TYPE" => "INTEGER",
                    "DEFAULT" => "100",
                    "TITLE" => 'Какой % от стоимости товара страхуется',
                    'POST_TEXT' => 'Вы можете снизить оценочную стоимость для уменьшения стоимости доставки за счет снижения размера страховки.',
                    "GROUP" => "general",
                    'CHECK_FORMAT' => 'NUMBER',
                ),

                "SECTION_PROP" => array(
                    'TYPE'=>'SECTION',
                    'TITLE'=>'Соответствие полей (Выберите поля, соответствующее полям в вашей системе)',
                    "GROUP" => "general",
                ),

                "SEND_STATUS" => array(
                    "TYPE" => 'DROPDOWN',
                    "DEFAULT" => "P",
                    "TITLE" => 'Статус для отправки',
                    "POST_TEXT" => '<br>Статус, при котором заявки из вашей системы будут уходить в DDelivery.<br>
                            Помните, что отправка означает готовность отгрузить заказ на следующий рабочий день.',
                    "GROUP" => "general",
                ),

                "PROP_FIO" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "FIO",
                    "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_PROP_FIO'),
                    "GROUP" => "general",
                    'POST_TEXT' => '',
                ),
                "PROP_PHONE" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "PHONE",
                    "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_PROP_PHONE'),
                    "GROUP" => "general",
                ),
            )
        );

        // Перебираем инфоблоки
        $cCatalog = new CCatalog();
        $res = $cCatalog->GetList();
        while($catalog = $res->Fetch() ) {
            $key = 'IBLOCK_'.$catalog['OFFERS_IBLOCK_ID'];

            $arConfig['CONFIG'][$key.'_SECTION']= array(
                'TYPE'=>'SECTION',
                'TITLE'=> 'Соответствие полей для инфоблока "'.$catalog['NAME'].'"',
                "GROUP" => "general",
            );

            $iblockProperty = array(0 => GetMessage('DIGITAL_DELIVERY_DEFAULT'));
            $res = CIBlockProperty::GetList(Array(), Array( "IBLOCK_ID"=>$catalog['OFFERS_IBLOCK_ID']));
            while($prop = $res->Fetch()){
                /*if (defined('BX_UTF')) {
                    $iblockProperty[$prop['ID']] = $APPLICATION->ConvertCharset($prop['NAME'], 'utf-8', 'cp1251');
                }else{*/
                    $iblockProperty[$prop['ID']] = $prop['NAME'];
                //}
            }


            foreach(array('X', 'Y', 'Z', 'W') as $key2){
                $arConfig['CONFIG'][$key.'_'.$key2] = array(
                    "TYPE" => "DROPDOWN",
                    "TITLE" => GetMessage('DIGITAL_DELIVERY_'.$key2),
                    "GROUP" => "general",
                    "DEFAULT" => 0,
                    "VALUES" => $iblockProperty,
                );
            }
        }


        // Дополняем настройки дальше
        $arConfig['CONFIG'] +=  array(
            "SECTION_DEFAULT" => array(
                'TYPE'=>'SECTION',
                'TITLE'=>'Габариты по умолчанию',
                "GROUP" => "general",
                'CHECK_FORMAT' => 'NUMBER',
            ),

            "DEFAULT_X" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "100",
                "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_DEFAULT_X'),
                "GROUP" => "general",
                'CHECK_FORMAT' => 'NUMBER',
            ),
            "DEFAULT_Z" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "100",
                "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_DEFAULT_Z'),
                "GROUP" => "general",
                'CHECK_FORMAT' => 'NUMBER',
            ),
            "DEFAULT_Y" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "100",
                "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_DEFAULT_Y'),
                "GROUP" => "general",
                'CHECK_FORMAT' => 'NUMBER',
            ),
            "DEFAULT_W" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "100",
                "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_DEFAULT_W'),
                "GROUP" => "general",
                'CHECK_FORMAT' => 'NUMBER',
            ),
            // Способы доставки
            'SUPPORTED_TYPE' => array(
                "TYPE" => "DROPDOWN",
                "TITLE" => 'Способы доставки',
                "GROUP" => "type",
                "DEFAULT" => '0',
                "VALUES" => array(
                    0 => 'ПВЗ и курьеры',
                    1 => 'ПВЗ DDelivery',
                    2 => 'Курьеры DDelivery',
                ),
            ),
        );

        $arConfig['CONFIG']['PRICE_IF_SECTION'] = array (
            'TYPE' => 'SECTION',
            'TITLE' => 'Выберите компании доставки, которые вы бы хотели сделать доступными для ваших клиентов',
            'GROUP' => 'type',
        );


        $companyList = self::companyList();

        foreach($companyList as $key => $company){
            $arConfig['CONFIG']["COMPANY_".$key] = array(
                "TYPE" => "CHECKBOX",
                "DEFAULT" => 'Y',
                "TITLE" => $company,
                "GROUP" => "type",
            );
        }




        $arConfig['CONFIG']['PRICE_IF_SECTION'] = array (
            'TYPE' => 'SECTION',
            'TITLE' => 'Как меняется стоимость доставки в зависимости от размера заказа.в руб. Вы можете гибко настроить
                условия доставки, чтобы учесть вашу маркетинговую политику.',
            'GROUP' => 'price',
        );

        for($i=1; $i<=3;$i++) {
            $arConfig['CONFIG'] += array(
                'PRICE_IF_'.$i.'_CONTROL' => array (
                    'TYPE' => 'MULTI_CONTROL_STRING',
                    'MCS_ID' => 'BOX_general_'.$i,
                    'TITLE' => 'От',
                    'GROUP' => 'price',
                ),
                'PRICE_IF_'.$i.'_MIN' => array (
                    'TYPE' => 'STRING',
                    'MCS_ID' => 'BOX_general_'.$i,
                    'POST_TEXT' => ' до ',
                    'SIZE' => 1,
                    'DEFAULT' => '',
                    'GROUP' => 'price',
                    'CHECK_FORMAT' => 'NUMBER',
                ),
                'PRICE_IF_'.$i.'_MAX' => array (
                    'TYPE' => 'STRING',
                    'MCS_ID' => 'BOX_general_'.$i,
                    'POST_TEXT' => ' стоимость доставки ',
                    'SIZE' => 1,
                    'DEFAULT' => '',
                    'GROUP' => 'price',
                    'CHECK_FORMAT' => 'NUMBER',
                ),
                'PRICE_IF_'.$i.'_TYPE' => array (
                    'TYPE' => 'DROPDOWN',
                    'MCS_ID' => 'BOX_general_'.$i,
                    'POST_TEXT' => ' &nbsp; ',
                    'DEFAULT' => '',

                    'VALUES' => array(
                        1 => 'Клиент оплачивает все',
                        2 => 'Магазин оплачивает все',
                        3 => 'Магазин оплачивает % от стоимости доставки',
                        4 => 'Магазин оплачивает руб. от стоимости доставки',
                    ),
                    'GROUP' => 'price',
                ),
                'PRICE_IF_'.$i.'_AOMUNT' => array (
                    'TYPE' => 'STRING',
                    'MCS_ID' => 'BOX_general_'.$i,
                    'SIZE' => 1,
                    'DEFAULT' => '',
                    'GROUP' => 'price',
                    'CHECK_FORMAT' => 'NUMBER',
                ),
            );
        }

        $arConfig['CONFIG'] += array(
            'AROUND_CONTROL' => array (
                'TYPE' => 'MULTI_CONTROL_STRING',
                'MCS_ID' => 'AROUND',
                'TITLE' => 'Округление цены доставки для покупателя',
                'GROUP' => 'price',
            ),
            'AROUND' => array (
                'TYPE' => 'DROPDOWN',
                'MCS_ID' => 'AROUND',
                'POST_TEXT' => ' шаг ',
                'DEFAULT' => 1,
                'GROUP' => 'price',
                'VALUES' => array(
                    2 => 'Вниз',
                    3 => 'Вверх',
                    1 => 'Математическое',
                ),
            ),
            'AROUND_STEP' => array (
                'TYPE' => 'STRING',
                'MCS_ID' => 'AROUND',
                'POST_TEXT' => ' руб.',
                'SIZE' => 1,
                'DEFAULT' => '',
                'GROUP' => 'price',
                'CHECK_FORMAT' => 'NUMBER',
            ),
            'PAY_PICKUP' => array(
                'TITLE' => 'Выводить стоимость забора в цене доставки',
                "TYPE" => "CHECKBOX",
                "DEFAULT" => 'N',
                "GROUP" => "price",
            ),
        );

        // var_dump($arConfig);
        $arConfig = $APPLICATION->ConvertCharsetArray($arConfig, 'cp1251', SITE_CHARSET);
        $arConfig['CONFIG']['SEND_STATUS']['VALUES'] = $sendStatusValues;
        $arConfig['CONFIG']['PROP_FIO']['VALUES'] = $props;
        $arConfig['CONFIG']['PROP_PHONE']['VALUES'] = $props;

        return $arConfig;
    }

    static public function companyList()
    {
        return array(
            4 => 'Boxberry',
            21 => 'Boxberry Express',
            29 => 'DPD Classic',
            23 => 'DPD Consumer',
            27 => 'DPD ECONOMY',
            28 => 'DPD Express',
            20 => 'DPD Parcel',
            30 => 'EMS',
            11 => 'Hermes',
            16 => 'IM Logistics Пушкинская',
            22 => 'IM Logistics Экспресс',
            17 => 'IMLogistics',
            3 => 'Logibox',
            14 => 'Maxima Express',
            1 => 'PickPoint',
            7 => 'QIWI Post',
            13 => 'КТС',
            26 => 'СДЭК Посылка',
            25 => 'СДЭК Посылка Самовывоз',
            24 => 'Сити Курьер',
            40 => 'КИТ',
        );
    }

    function GetSettings($strSettings)
    {
        return unserialize($strSettings);
    }

    function SetSettings($arSettings)
    {
        $string = serialize($arSettings);
        if($arSettings) {
            $oldSetting = COption::GetOptionString('ddelivery', 'setings', $string);
            if($oldSetting) {
                $oldSetting = unserialize($oldSetting);
                if( $oldSetting && $oldSetting['API_KEY'] != $arSettings['API_KEY']){
                    $IntegratorShop = new DDeliveryShop($arSettings, array(), array());
                    $ddeliveryUI = new DdeliveryUI($IntegratorShop, true);
                    $ddeliveryUI->cleanCache();
                }
            }

            /*/ Добавляет свойство товару и делает его привязаным к DD
            $id = CSaleOrderProps::add(array (
                'PERSON_TYPE_ID' => '1',
                'NAME' => 'DDelivery ID',
                'TYPE' => 'TEXT',
                'REQUIED' => 'N',
                'DEFAULT_VALUE' => '',
                'SORT' => '10000',
                'USER_PROPS' => 'N',
                'IS_LOCATION' => 'N',
                'PROPS_GROUP_ID' => '2',
                'IS_EMAIL' => 'N',
                'IS_PROFILE_NAME' => 'N',
                'IS_PAYER' => 'N',
                'IS_LOCATION4TAX' => 'N',
                'IS_ZIP' => 'N',
                'CODE' => 'DDELIVERY_ID',
                'IS_FILTERED' => 'Y',
                'ACTIVE' => 'Y',
                'UTIL' => 'Y',
                'INPUT_FIELD_LOCATION' => '0',
                'MULTIPLE' => 'N',
                'PAYSYSTEM_ID' => '20',
                'DELIVERY_ID' => '20',
            ));
            CSaleOrderProps::UpdateOrderPropsRelations($id, 'ddelivery:all', "D");
            */

        }
        COption::SetOptionString('ddelivery', 'setings', $string);

        return $string;
    }

    /* Калькуляция стоимости доставки*/
    static function Calculate($profile, $arConfig, $arOrder = false, $STEP= false, $TEMP = false)
    {

        if(substr($_SERVER['PHP_SELF'], 0, 14) == '/bitrix/admin/' &&
           substr($_SERVER['PHP_SELF'], 0, 33) != '/bitrix/admin/sale_order_new.php') {
            return array( "RESULT" => "ERROR", 'ERROR' => 'Я не буду считать стоимость доставки в админке');
        }

        if(substr($_SERVER['PHP_SELF'], 0, 33) == '/bitrix/admin/sale_order_new.php'){
            $cmsOrderId = $_REQUEST['ORDER_AJAX'] =='Y' ? $_REQUEST['id'] : $_REQUEST['ID'];
            $dbPropsValue = CSaleOrderPropsValue::GetList(
                array(),
                array("ORDER_ID" => $cmsOrderId, 'CODE'=>'DDELIVERY_ID')
            );
            if (!($arValue = $dbPropsValue->Fetch()) || empty($arValue['VALUE'])) {
                return array( "RESULT" => "ERROR", 'ERROR' => 'Я не буду считать стоимость доставки в админке');
            }
            $ddOrderId = $arValue['VALUE'];
            $order = CSaleOrder::GetByID($cmsOrderId);
            $userId = $order['USER_ID'];
        } else {
            $userId = CSaleBasket::GetBasketUserID();
            $cmsOrderId = "NULL";
            if(!empty($_SESSION['DIGITAL_DELIVERY']) && !empty($_SESSION['DIGITAL_DELIVERY']['ORDER_ID'])) {
                $ddOrderId = $_SESSION['DIGITAL_DELIVERY']['ORDER_ID'];
            }
        }

        if(!empty($ddOrderId))
        {
            // TODO Удалить когда починят
            $dbBasketItems = CSaleBasket::GetList(
                array("ID" => "ASC"),
                array(
                    "FUSER_ID" => $userId,
                    "ORDER_ID" => $cmsOrderId
                ),
                false,
                false,
                array('PRODUCT_ID', 'PRICE', 'QUANTITY', 'NAME')
            );
            $itemList = array();
            while($arBasket = $dbBasketItems->Fetch()) {
                $itemList[] = $arBasket;
            }
            if(empty($itemList)){
                return array( "RESULT" => "ERROR", 'ERROR' => ddeliveryFromCp1251('Корзина в сейсии пуста'));
            }
            //END TODO


            $IntegratorShop = new DDeliveryShop($arConfig, $itemList, array());
            $ddeliveryUI = new \DDelivery\DDeliveryUI($IntegratorShop);
            $orders = $ddeliveryUI->initOrder(array($ddOrderId));
            if(!empty($orders)){
                $point = $orders[0]->getPoint();
                if ($point instanceof DDeliveryPointSelf) {
                    $point = $ddeliveryUI->getSelfPointByID($point->_id, $orders[0]);
                    $IntegratorShop->filterSelfInfo(array($point->getDeliveryInfo()));
                } elseif($point instanceof DDeliveryPointCourier) {
                    $point = $ddeliveryUI->getCourierPointByCompanyID($point->getDeliveryInfo()->delivery_company, $orders[0]);
                }
                $price = $point->getDeliveryInfo()->clientPrice;
                return array("RESULT" => "OK", 'VALUE'=>(float)$price);
            }else{
                return array( "RESULT" => "ERROR", 'ERROR' => 'Not Find order');
            }
        }

        return array(
            "RESULT" => "ERROR",
            "ERROR" => ddeliveryFromCp1251(GetMessage('DIGITAL_DELIVERY_EMPTY_POINT'))
        );
    }

    /* Проверка соответствия профиля доставки заказу */
    function Compability($arOrder, $arConfig)
    {
        return array("all");
    }

    public static function getOptions()
    {
        $options = COption::GetOptionString('delivery', 'ddelivery');
        if(!$options)
            return false;
        $options = unserialize($options);
        return $options;
    }

    /**
     * Событие происходит когда оформлен заказ. Мы дописываем заказу sqlite ID
     * @param $iOrderID
     * @param $eventName
     * @param $arFieldsUpdate
     * @return bool
     */
    static function OnOrderNewSendEmail($iOrderID, $eventName, $arFieldsUpdate)
    {
        if(empty($_SESSION['DIGITAL_DELIVERY']) || empty($_SESSION['DIGITAL_DELIVERY']['ORDER_ID'])) {
            return true;
        }
        $cso = new CSaleOrder();
        $arOrder = $cso->GetByID($iOrderID);

        if($arOrder["DELIVERY_ID"]=="ddelivery:all" && !empty($_SESSION['DIGITAL_DELIVERY']['ORDER_ID']))
        {

            $db_props = CSaleOrderProps::GetList(
                array("SORT" => "ASC"),
                array(
                    "PERSON_TYPE_ID" => $arOrder["PERSON_TYPE_ID"],
                    'CODE' => 'DDELIVERY_ID',
                )
            );
            $property = $db_props->Fetch();

            CSaleOrderPropsValue::Add(array(
                "ORDER_ID" => $iOrderID,
                "ORDER_PROPS_ID" => $property['ID'],
                "NAME" => $property['NAME'],
                "CODE" => $property['CODE'],
                "VALUE" => $_SESSION['DIGITAL_DELIVERY']['ORDER_ID']
            ));
        }
        unset($_SESSION["DIGITAL_DELIVERY"]['ORDER_ID']);

        return true;
    }

    /**
     * Событие происходит когда изеняется статус заказа
     * @param $orderId
     * @param $statusID
     * @throws Bitrix\Main\DB\Exception
     */
    static function OnSaleStatusOrder($orderId, $statusID)
    {
        $property = CSaleOrderPropsValue::GetList(array(), array("ORDER_ID" => $orderId, 'CODE' => 'DDELIVERY_ID'))->Fetch();

        if(!$property)
            return;
        try{
            $DDConfig = CSaleDeliveryHandler::GetBySID('ddelivery')->Fetch();
            if($statusID != $DDConfig['CONFIG']['CONFIG']['SEND_STATUS']['VALUE']) {
                return;
            }
            $cmsOrder = CSaleOrder::GetByID($orderId);

            $IntegratorShop = new DDeliveryShop($DDConfig['CONFIG']['CONFIG'], array(), array());
            $ddeliveryUI = new DdeliveryUI($IntegratorShop, true);
            $order = $ddeliveryUI->initOrder(array($property['VALUE']));
            if(empty($order))
                return;
            /**
             * @var DDeliveryOrder $order
             */
            $order = reset($order);

            if($order->type == DDeliverySDK::TYPE_COURIER && $order->getPoint()->getDeliveryInfo()->get('delivery_company') == 41 ) {
                if(!empty($DDConfig['CONFIG']['CONFIG']['API_KEY_KIT']['VALUE'])) {
                    $DDConfig['CONFIG']['CONFIG']['API_KEY'] = $DDConfig['CONFIG']['CONFIG']['API_KEY_KIT'];
                    $IntegratorShop = new DDeliveryShop($DDConfig['CONFIG']['CONFIG'], array(), array());
                    $ddeliveryUI = new DdeliveryUI($IntegratorShop, true);
                    $order = $ddeliveryUI->initOrder(array($property['VALUE']));
                    if(empty($order))
                        return;
                    /**
                     * @var DDeliveryOrder $order
                     */
                    $order = reset($order);
                }
            }
            $order->localStatus = $statusID;
            /**
             * @var \DDelivery\Order\DDeliveryOrder $order
             */
            $ddeliveryOrderID = $ddeliveryUI->sendOrderToDD( $order, $orderId, $cmsOrder['PAY_SYSTEM_ID']);
            $ddeliveryUI->saveFullOrder($order);
            if(!$ddeliveryOrderID) {
                throw new \Bitrix\Main\DB\Exception("Error save order by DDelivery");
            }
            CSaleOrder::Update($orderId, array("TRACKING_NUMBER" => $ddeliveryOrderID));
        }
        catch(\DDelivery\DDeliveryException $e)
        {
            throw new \Bitrix\Main\DB\Exception("Error save order by DDelivery");
        }

    }
}