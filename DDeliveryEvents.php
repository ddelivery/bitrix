<?php
/**
 * User: DnAp
 * Date: 16.05.14
 * Time: 12:56
 */

use Bitrix\Sale\Delivery\OrderDeliveryTable;
use DDelivery\DDeliveryUI;
use DDelivery\Sdk\DDeliverySDK;

include_once(__DIR__.'/application/bootstrap.php');

class DDeliveryEvents
{
    function Init()
    {
        include(__DIR__.'/install/version.php');
        /** @var $arModuleVersion string[] */
        $select = GetMessage('DDELIVERY_SELECT');
        $html = '
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
            "NAME" => GetMessage('DDELIVERY_NAME'),
            "DESCRIPTION" => GetMessage('DDELIVERY_DESCRIPTION'),
            "DESCRIPTION_INNER" => GetMessage('DDELIVERY_DESCRIPTION_INNER'),
            "BASE_CURRENCY" => "RUB",//COption::GetOptionString("sale", "default_currency", "RUB"),

            "HANDLER" => '/bitrix/modules/ddelivery.ddelivery/DDeliveryEvents.php',

            /* Handler methods */
            "DBGETSETTINGS" => array(__CLASS__, "GetSettings"),
            "DBSETSETTINGS" => array(__CLASS__, "SetSettings"),
            "GETCONFIG" => array(__CLASS__, "GetConfig"),

            "COMPABILITY" => array(__CLASS__, "Compability"),
            "CALCULATOR" => array(__CLASS__, "Calculate"),

            "PROFILES" => array(
                "all" => array(
                    "TITLE" => 'ddelivery.ru',
                    "DESCRIPTION" => $html,
                    "RESTRICTIONS_WEIGHT" => array(0),
                    "RESTRICTIONS_SUM" => array(0),
                ),
            ),
            "GETEXTRAINFOPARAMS" => array(__CLASS__, "GetExtraInfoParams"),
            "GETORDERSACTIONSLIST" => array(__CLASS__, "GetOrdersActionsList"),
        );
    }
    /* Настройки */
    function GetConfig()
    {
        global $APPLICATION;
        $dbProps = CSaleOrderProps::GetList(
            array("SORT" => "ASC"),
            array(
                "ACTIVE" => 'Y',
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

        $paymentTypes = array();
        $dbPtype = CSalePaySystem::GetList($arOrder = Array("SORT"=>"ASC", "PSA_NAME"=>"ASC"), Array( "ACTIVE"=>"Y"));
        while ($pType = $dbPtype->Fetch()) {
            $paymentTypes[$pType['ID']] = $pType['NAME'].' ('.$pType['LID'].')';
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
                "general" => GetMessage('DDELIVERY_CONFIG_GROUPS_GENERAL'),
                "type" => GetMessage('DDELIVERY_CONFIG_GROUPS_TYPE'),
                "price" => GetMessage("DDELIVERY_CONFIG_GROUPS_PRICE")
            ),
            "CONFIG" => array(
                "API_KEY"=> array(
                    "TYPE" => "STRING",
                    "DEFAULT" => '',
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_API_KEY'),
                    "GROUP" => "general",
                    "POST_TEXT"=> GetMessage('DDELIVERY_CONFIG_API_KEY_DESCRIPTION').$jsHack,
                ),
                "TEST_MODE"=> array(
                    "TYPE" => "DROPDOWN",
                    "DEFAULT" => 1,
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_MODE'),
                    "VALUES" => array(
                        1 => GetMessage('DDELIVERY_CONFIG_MODE_STAGE'),
                        0 => GetMessage('DDELIVERY_CONFIG_MODE_CLIENT'),
                    ),
                    "POST_TEXT"=> GetMessage('DDELIVERY_CONFIG_MODE_POST_TEXT'),
                    "GROUP" => "general",
                ),
                "DECLARED_PERCENT"=>array(
                    "TYPE" => "INTEGER",
                    "DEFAULT" => "100",
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_DECLARED_PERCENT_TITLE'),
                    'POST_TEXT' => GetMessage('DDELIVERY_CONFIG_DECLARED_PERCENT_POST_TEXT'),
                    "GROUP" => "general",
                    'CHECK_FORMAT' => 'NUMBER',
                ),

                "POST_PAYMENT"=>array(
                    "TYPE" => "MULTISELECT",
                    "DEFAULT" => "",
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_POST_PAYMENT_TITLE'),
                    'POST_TEXT' => '',
                    'VALUES' => $paymentTypes,
                    "GROUP" => "general",
                ),
                "TEMPLATE"=> array(
                    "TYPE" => "DROPDOWN",
                    "DEFAULT" => 'default',
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_TEMPLATE_TITLE'),
                    "VALUES" => array(
                        'default' => GetMessage('DDELIVERY_CONFIG_TEMPLATE_DEFAULT'),
                        'blue' => GetMessage('DDELIVERY_CONFIG_TEMPLATE_BLUE'),
                    ),
                    "GROUP" => "general",
                ),

                "SECTION_PROP" => array(
                    'TYPE' => 'SECTION',
                    'TITLE' => GetMessage('DDELIVERY_CONFIG_SECTION_PROP_TITLE'),
                    "GROUP" => "general",
                ),

                "SEND_STATUS" => array(
                    "TYPE" => 'DROPDOWN',
                    "DEFAULT" => "P",
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_SEND_STATUS_TITLE'),
                    "POST_TEXT" => GetMessage('DDELIVERY_CONFIG_SEND_STATUS_POST_TEXT'),
                    "GROUP" => "general",
                ),

                "PROP_FIO" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "FIO",
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_PROP_FIO'),
                    "GROUP" => "general",
                    'POST_TEXT' => '',
                ),
                "PROP_PHONE" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "PHONE",
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_PROP_PHONE'),
                    "GROUP" => "general",
                ),

                "PROP_ADDRESS" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "ADDRESS",
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_PROP_ADDRESS'),
                    "GROUP" => "general",
                ),
                "PROP_ZIP_CODE" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "ZIP",
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_PROP_ZIP_CODE'),
                    "GROUP" => "general",
                    'POST_TEXT' => '',
                ),
                "PROP_CORP" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "CORP",
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_PROP_CORP'),
                    "GROUP" => "general",
                    'POST_TEXT' => '',
                ),
                "PROP_FLAT" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "FLAT",
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_PROP_FLAT'),
                    "GROUP" => "general",
                    'POST_TEXT' => '',
                ),
                "PROP_HOUSE" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "HOUSE",
                    "TITLE" => GetMessage('DDELIVERY_CONFIG_PROP_HOUSE'),
                    "GROUP" => "general",
                    'POST_TEXT' => '',
                ),

            )
        );

        // Создаем вкладки для инфоблоков
        $cCatalog = new CCatalog();
        $res = $cCatalog->GetList();
        while($catalog = $res->Fetch() ) {
            $key = 'IBLOCK_'.$catalog['OFFERS_IBLOCK_ID'];

            $arConfig['CONFIG'][$key.'_SECTION']= array(
                'TYPE'=>'SECTION',
                'TITLE'=> GetMessage('DDELIVERY_CUSTOM_FIELDS', array('#IBLOCK_NAME#' => $catalog['NAME'])),
                "GROUP" => "general",
            );

            $articulDefault = null;

            $iblockProperty = array(0 => GetMessage('DDELIVERY_DEFAULT'));
            $res = CIBlockProperty::GetList(Array(), Array( "IBLOCK_ID"=>$catalog['OFFERS_IBLOCK_ID']));
            while($prop = $res->Fetch()){
                $iblockProperty[$prop['ID']] = $prop['NAME'];
                if($prop['CODE'] == 'ARTNUMBER'){
                    $articulDefault = $prop['ID'];
                }
            }


            foreach(array('X', 'Y', 'Z', 'W') as $key2){
                $arConfig['CONFIG'][$key.'_'.$key2] = array(
                    "TYPE" => "DROPDOWN",
                    "TITLE" => GetMessage('DDELIVERY_'.$key2),
                    "GROUP" => "general",
                    "DEFAULT" => 0,
                    "VALUES" => $iblockProperty,
                );
            }

            $iblockProperty[0] = GetMessage('DDELIVERY_NONE');
            $arConfig['CONFIG'][$key.'_ARTICUL'] = array(
                "TYPE" => "DROPDOWN",
                "TITLE" => GetMessage('DDELIVERY_ARTICUL'),
                "GROUP" => "general",
                "DEFAULT" => $articulDefault,
                "VALUES" => $iblockProperty,
            );

        }


        $arConfig['CONFIG'] +=  array(
            "SECTION_DEFAULT" => array(
                'TYPE'=>'SECTION',
                'TITLE'=>GetMessage('DDELIVERY_DEFAULT_SIZE'),
                "GROUP" => "general",
                'CHECK_FORMAT' => 'NUMBER',
            ),

            "DEFAULT_X" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "100",
                "TITLE" => GetMessage('DDELIVERY_CONFIG_DEFAULT_X'),
                "GROUP" => "general",
                'CHECK_FORMAT' => 'NUMBER',
            ),
            "DEFAULT_Z" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "100",
                "TITLE" => GetMessage('DDELIVERY_CONFIG_DEFAULT_Z'),
                "GROUP" => "general",
                'CHECK_FORMAT' => 'NUMBER',
            ),
            "DEFAULT_Y" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "100",
                "TITLE" => GetMessage('DDELIVERY_CONFIG_DEFAULT_Y'),
                "GROUP" => "general",
                'CHECK_FORMAT' => 'NUMBER',
            ),
            "DEFAULT_W" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "100",
                "TITLE" => GetMessage('DDELIVERY_CONFIG_DEFAULT_W'),
                "GROUP" => "general",
                'CHECK_FORMAT' => 'NUMBER',
            ),
            // Таб службы доставки
            'COMPANY_TITLE' => array (
                'TYPE' => 'SECTION',
                'TITLE' => GetMessage('DDELIVERY_CONFIG_COMPANY_TITLE'),
                'GROUP' => 'type',
            ),
            'SUPPORTED_TYPE' => array(
                "TYPE" => "DROPDOWN",
                "TITLE" => GetMessage('DDELIVERY_CONFIG_SUPPORTED_TYPE'),
                "GROUP" => "type",
                "DEFAULT" => '0',
                "VALUES" => array(
                    0 => GetMessage('DDELIVERY_CONFIG_SUPPORTED_TYPE_ALL'),
                    1 => GetMessage('DDELIVERY_CONFIG_SUPPORTED_TYPE_PVZ'),
                    2 => GetMessage('DDELIVERY_CONFIG_SUPPORTED_TYPE_COURIER'),
                ),
            ),
        );

        $companyList = self::companyList();
        $arConfig['CONFIG']['COMPANY_TITLE_COURIER'] = array(
            'TYPE' => 'SECTION',
            'TITLE' => GetMessage('DDELIVERY_CONFIG_COMPANY_TITLE_COURIER'),
            'GROUP' => 'type',
        );

        foreach ($companyList[DDeliverySDK::TYPE_COURIER] as $key => $company) {
            $arConfig['CONFIG']["COMPANY_". DDeliverySDK::TYPE_COURIER . '_' . $key] = array(
                "TYPE" => "CHECKBOX",
                "DEFAULT" => 'Y',
                "TITLE" => $company,
                "GROUP" => "type",
            );
        }
        $arConfig['CONFIG']['COMPANY_TITLE_SELF'] = array(
            'TYPE' => 'SECTION',
            'TITLE' => GetMessage('DDELIVERY_CONFIG_COMPANY_TITLE_SELF'),
            'GROUP' => 'type',
        );
        foreach ($companyList[DDeliverySDK::TYPE_SELF] as $key => $company) {
            $arConfig['CONFIG']["COMPANY_". DDeliverySDK::TYPE_SELF . '_' . $key] = array(
                "TYPE" => "CHECKBOX",
                "DEFAULT" => 'Y',
                "TITLE" => $company,
                "GROUP" => "type",
            );
        }





        $arConfig['CONFIG']['PRICE_IF_SECTION'] = array (
            'TYPE' => 'SECTION',
            'TITLE' => GetMessage('DDELIVERY_CONFIG_PRICE_IF_SECTION'),
            'GROUP' => 'price',
        );

        for($i=1; $i<=3;$i++) {
            $arConfig['CONFIG'] += array(
                'PRICE_IF_'.$i.'_CONTROL' => array (
                    'TYPE' => 'MULTI_CONTROL_STRING',
                    'MCS_ID' => 'BOX_general_'.$i,
                    'TITLE' => GetMessage('DDELIVERY_FROM'),
                    'GROUP' => 'price',
                ),
                'PRICE_IF_'.$i.'_MIN' => array (
                    'TYPE' => 'STRING',
                    'MCS_ID' => 'BOX_general_'.$i,
                    'POST_TEXT' => ' '.GetMessage('DDELIVERY_TO').' ',
                    'SIZE' => 1,
                    'DEFAULT' => '',
                    'GROUP' => 'price',
                    'CHECK_FORMAT' => 'NUMBER',
                ),
                'PRICE_IF_'.$i.'_MAX' => array (
                    'TYPE' => 'STRING',
                    'MCS_ID' => 'BOX_general_'.$i,
                    'POST_TEXT' => ' '.GetMessage('DDELIVERY_DELIVERY_PRICE').' ',
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
                        1 => GetMessage('DDELIVERY_PRICE_CLIENT_ALL'),
                        2 => GetMessage('DDELIVERY_PRICE_MARKET_ALL'),
                        3 => GetMessage('DDELIVERY_PRICE_MARKET_PRESENT'),
                        4 => GetMessage('DDELIVERY_PRICE_MARKET_RUB'),
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
                'TITLE' => GetMessage('DDELIVERY_AROUND_CONTROL'),
                'GROUP' => 'price',
            ),
            'AROUND' => array (
                'TYPE' => 'DROPDOWN',
                'MCS_ID' => 'AROUND',
                'POST_TEXT' => ' '.GetMessage('DDELIVERY_STEP').' ',
                'DEFAULT' => 1,
                'GROUP' => 'price',
                'VALUES' => array(
                    2 => GetMessage('DDELIVERY_DOWN'),
                    3 => GetMessage('DDELIVERY_UP'),
                    1 => GetMessage('DDELIVERY_ROUND'),
                ),
            ),
            'AROUND_STEP' => array (
                'TYPE' => 'STRING',
                'MCS_ID' => 'AROUND',
                'POST_TEXT' => ' '.GetMessage('DDELIVERY_RUR'),
                'SIZE' => 1,
                'DEFAULT' => '',
                'GROUP' => 'price',
                'CHECK_FORMAT' => 'NUMBER',
            ),
            'PAY_PICKUP' => array(
                'TITLE' => GetMessage('DDELIVERY_PAY_PICKUP'),
                "TYPE" => "CHECKBOX",
                "DEFAULT" => 'N',
                "GROUP" => "price",
            ),
        );

        // var_dump($arConfig);
        //$arConfig = $APPLICATION->ConvertCharsetArray($arConfig, 'cp1251', SITE_CHARSET);
        $arConfig['CONFIG']['SEND_STATUS']['VALUES'] = $sendStatusValues;
        $arConfig['CONFIG']['PROP_FIO']['VALUES'] = $props;
        $arConfig['CONFIG']['PROP_PHONE']['VALUES'] = $props;
        $arConfig['CONFIG']['PROP_ADDRESS']['VALUES'] = $props;
        $props = array_merge(array(GetMessage('DDELIVERY_CONFIG_EMPTY')), $props);

        $arConfig['CONFIG']['PROP_ZIP_CODE']['VALUES'] = $props;
        $arConfig['CONFIG']['PROP_CORP']['VALUES'] = $props;
        $arConfig['CONFIG']['PROP_FLAT']['VALUES'] = $props;
        $arConfig['CONFIG']['PROP_HOUSE']['VALUES'] = $props;
        return $arConfig;
    }

    /**
     * Свойства доставки в админке
     */
    public function GetExtraInfoParams($arOrder, $config, $profileId, $siteId)
    {
        if(empty($arOrder['ID']))
            return;
        return array(
            'DD_ABOUT' => array(
                'TITLE' => '<a href="/bitrix/admin/ddelivery.ddelivery_change_point.php?order_id='.$arOrder['ID'].'">'.GetMessage('DDELIVERY_ABOUT_EDIT').'</a>',
                //'TITLE' => GetMessage('DDELIVERY_ABOUT_DELIVERY'),
            ),
        );
    }



    /**
     * В админке "разрешить доставку" можно создать 2 события
     */
    public function GetOrdersActionsList()
    {
        /*return array(
            'REQUEST_SELF' => 'REQUEST SELF',
            'REQUEST_TAKE' => 'REQUEST TAKE',
        );*/
    }

    /**
     * @param $config
     * @param array $itemList
     * @param array $requestFromData
     * @return DDeliveryShop
     */
    public static function getShopObject($config, $itemList = array(), $requestFromData = array())
    {
        if(class_exists('DDeliveryShopEx', true)){
            return new DDeliveryShopEx($config, $itemList, $requestFromData);
        }

        return new DDeliveryShop($config, $itemList, $requestFromData);
    }




    static public function companyList()
    {
        $companyList = DDeliveryUI::getCompanySubInfo();
        $companyNameList = array();
        global $APPLICATION;
        foreach($companyList as $id => $company) {
            $companyNameList[$id] = $APPLICATION->ConvertCharsetArray($company['name'], 'utf-8', SITE_CHARSET);
        }
        $courier = array(45, 29, 23, 27, 28, 20, 35, 36, 30, 31, 22, 43, 17, 48, 46, 14, 41, 13,44, 40, 26, 24, 49, 50,
            51, 52, 53, 54, 55, 58, 61,63, 66);
        $result = array(DDeliverySDK::TYPE_SELF => array(), DDeliverySDK::TYPE_COURIER => array());
        foreach($courier as $companyId) {
            if(isset($companyNameList[$companyId]))
                $result[DDeliverySDK::TYPE_COURIER][$companyId] = $companyNameList[$companyId];
        }
        $self = array(4, 21, 11, 16, 42, 17, 37, 3, 1, 7, 39, 47, 41, 38, 40, 25);
        foreach($self as $companyId) {
            if(isset($companyNameList[$companyId]))
                $result[DDeliverySDK::TYPE_SELF][$companyId] = $companyNameList[$companyId];
        }
        return $result;
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
                    $IntegratorShop = self::getShopObject($arSettings, array(), array());
                    $ddeliveryUI = new DdeliveryUI($IntegratorShop, true);
                    $ddeliveryUI->cleanCache();
                }
            }

            /*/ ��������� �������� ������ � ������ ��� ���������� � DD
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

    /* */
    static function Calculate($profile, $arConfig, $arOrder = false, $STEP= false, $TEMP = false)
    {
        global $APPLICATION;
        $itemList = array();
        if(!empty($arOrder)
            && !empty($arOrder['ITEMS'][0]['PRODUCT_ID'])
            && !empty($arOrder['ITEMS'][0]['QUANTITY'])
            && !empty($arOrder['ITEMS'][0]['PRICE'])
            && !empty($arOrder['ITEMS'][0]['NAME'])
            ){
            $itemList = $arOrder['ITEMS'];
        }

        if( substr($_SERVER['PHP_SELF'], 0, 14) == '/bitrix/admin/' &&
            substr($_SERVER['PHP_SELF'], 0, 33) != '/bitrix/admin/sale_order_new.php' &&
            substr($_SERVER['PHP_SELF'], 0, 42) != '/bitrix/admin/ddelivery.ddelivery_ajax.php') {
            return array( "RESULT" => "ERROR", 'TEXT' => GetMessage('DDELIVERY_ADMIN_PAGE'));
        }


        if( substr($_SERVER['PHP_SELF'], 0, 33) == '/bitrix/admin/sale_order_new.php' ||
            substr($_SERVER['PHP_SELF'], 0, 42) == '/bitrix/admin/ddelivery.ddelivery_ajax.php'){
            if(isset($_REQUEST['bx_order_id'])) {
                $cmsOrderId = $_REQUEST['bx_order_id'];

            }else{
                $cmsOrderId = $_REQUEST['ORDER_AJAX'] == 'Y' ? $_REQUEST['id'] : $_REQUEST['ID'];
            }
            $orderDeliveryTableData = OrderDeliveryTable::getList(array('filter' => array('ORDER_ID' => $cmsOrderId)))->fetch();
            if(empty($orderDeliveryTableData)) {
                return array( "RESULT" => "ERROR", 'TEXT' => GetMessage('DDELIVERY_ERROR_ORDER'));
            }

            $property = unserialize($orderDeliveryTableData['PARAMS']);
            $ddOrderId = false;
            if(empty($property) || empty($property['DD_LOCAL_ID'])) {
                $property = CSaleOrderPropsValue::GetList(array(), array("ORDER_ID" => $cmsOrderId, 'CODE' => 'DDELIVERY_ID'))->Fetch();
                if(!$property) {
                    return array( "RESULT" => "ERROR", 'TEXT' => GetMessage('DDELIVERY_ERROR_ORDER'));
                }else{
                    $ddOrderId = $property['VALUE'];
                }
            }else{
                $ddOrderId = $property['DD_LOCAL_ID'];
            }
            if (!$ddOrderId) {
                $APPLICATION->ThrowException(GetMessage('DDELIVERY_ADMIN_PAGE'));
                return array( "RESULT" => "ERROR", 'TEXT' => GetMessage('DDELIVERY_ADMIN_PAGE'));
            }

            $dbBasketItems = CSaleBasket::GetList(Array("ID"=>"ASC"), Array("ORDER_ID"=>$cmsOrderId));
            $itemList = array();
            while($arBasket = $dbBasketItems->Fetch()) {
                $itemList[] = $arBasket;
            }
        } else {
            $userId = CSaleBasket::GetBasketUserID();
            $cmsOrderId = "NULL";
            if(!empty($_SESSION['DIGITAL_DELIVERY']) && !empty($_SESSION['DIGITAL_DELIVERY']['ORDER_ID'])) {
                $ddOrderId = $_SESSION['DIGITAL_DELIVERY']['ORDER_ID'];
            }
        }

        if(!empty($ddOrderId))
        {
            if(empty($itemList)){
                // TODO
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
                while($arBasket = $dbBasketItems->Fetch()) {
                    $itemList[] = $arBasket;
                }
                //END TODO
            }
            if(empty($itemList)){
                return array( "RESULT" => "ERROR", 'TEXT' => GetMessage('DDELIVERY_BASKET_EMPTY'));
            }

            $IntegratorShop = self::getShopObject($arConfig, $itemList, array());
            $IntegratorShop->useTaxRate = false;
            $ddeliveryUI = new \DDelivery\DDeliveryUI($IntegratorShop);
            $order = $ddeliveryUI->initOrder($ddOrderId);
            $order->getProductParams();
            $ddeliveryUI->saveFullOrder($order);

            if(!empty($order)){
                $price = $ddeliveryUI->getClientPrice($order->getPoint(), $order) ;
                return array("RESULT" => "OK", 'VALUE'=>(float)$price);
            }else{
                return array( "RESULT" => "ERROR", 'TEXT' => 'Not Find order');
            }
        }
        return array(
            "RESULT" => "ERROR",
            "ERROR" => GetMessage('DDELIVERY_EMPTY_POINT')
        );
    }

    /* �������� ������������ ������� �������� ������ */
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
     * Хук когда происходит сохранение заказа пользователем
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
            global $APPLICATION;

            $ddOrderId = $_SESSION['DIGITAL_DELIVERY']['ORDER_ID'];

            $DDConfig = CSaleDeliveryHandler::GetBySID('ddelivery')->Fetch();

            $IntegratorShop = self::getShopObject($DDConfig['CONFIG']['CONFIG'], array(), array());
            $ddeliveryUI = new DdeliveryUI($IntegratorShop, true);
            $order = $ddeliveryUI->initOrder($ddOrderId);
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
                    '%1' => $order->cityName.' '.$order->getFullAddress(),
                    '%2' => $point['delivery_company_name']);
                $replaceData = $APPLICATION->ConvertCharsetArray($replaceData, 'UTF-8', SITE_CHARSET);
                $comment = GetMessage('DDELIVERY_ABOUT_COURIER', $replaceData);
            }else{
                $comment = 'error';
            }

            $params = array('DD_ABOUT' => $comment, 'DD_LOCAL_ID' => $ddOrderId);
            $orderDeliveryTableData = OrderDeliveryTable::getList(array('filter' => array('ORDER_ID' => $iOrderID)))->fetch();
            if($orderDeliveryTableData) {
                OrderDeliveryTable::update($orderDeliveryTableData['ID'], array('PARAMS' => serialize($params)));
            }else{
                OrderDeliveryTable::add(array('ORDER_ID' => $iOrderID, 'PARAMS' => serialize($params)));
            }

        }
        unset($_SESSION["DIGITAL_DELIVERY"]['ORDER_ID']);

        return true;
    }

    /**
     *
     * @param $orderId
     * @param $statusID
     * @return bool
     * @throws Bitrix\Main\DB\Exception
     */
    static function OnSaleBeforeStatusOrder($orderId, $statusID)
    {
        $orderDeliveryTableData = OrderDeliveryTable::getList(array('filter' => array('ORDER_ID' => $orderId)))->fetch();
        if(empty($orderDeliveryTableData)) {
            return true;
        }

        $property = unserialize($orderDeliveryTableData['PARAMS']);
        if(empty($property) || empty($property['DD_LOCAL_ID'])) {
            $property = CSaleOrderPropsValue::GetList(array(), array("ORDER_ID" => $orderId, 'CODE' => 'DDELIVERY_ID'))->Fetch();
            if(!$property) {
                return true;
            }else{
                $ddLocalId = $property['VALUE'];
            }
        }else{
            $ddLocalId = $property['DD_LOCAL_ID'];
        }

        global $APPLICATION;

        try{
            $DDConfig = CSaleDeliveryHandler::GetBySID('ddelivery')->Fetch();
            if($statusID != $DDConfig['CONFIG']['CONFIG']['SEND_STATUS']['VALUE']) {
                return true;
            }
            $cmsOrder = CSaleOrder::GetByID($orderId);
            if($cmsOrder['DELIVERY_ID'] != 'ddelivery:all')
                return true;

            $IntegratorShop = self::getShopObject($DDConfig['CONFIG']['CONFIG'], array(), array());
            $ddeliveryUI = new DdeliveryUI($IntegratorShop, true);
            $order = $ddeliveryUI->initOrder($ddLocalId);


            $bxOrder = CSaleBasket::GetList(array(), array(
                'ORDER_ID' => $orderId,
                ),
                false,
                false,
                array('PRODUCT_ID', 'PRICE', 'QUANTITY', 'NAME')
            );
            $bxItems = array();
            while($bxItem = $bxOrder->Fetch()) {
                $bxItems[] = $bxItem;
            }

            $order->setProducts($IntegratorShop->itemListToDDCart($bxItems));
            $point = $order->getPoint();
            $newPoint = false;
            if( $order->type == DDeliverySDK::TYPE_SELF ){
                $newPoint = $ddeliveryUI->calculateSelfPointPrice($order, $point['_id']);
                $newPoint = isset($newPoint[0]) ? $newPoint[0] : false;
            }else if( $order->type == DDeliverySDK::TYPE_COURIER ) {
                $resultPoints = $ddeliveryUI->calculateCourierPrices($order);
                foreach($resultPoints as $resultPoint) {
                    if($resultPoint['delivery_company'] == $point['delivery_company']) {
                        $newPoint = $resultPoint;
                    }
                }
            }
            if($newPoint) {
                $order->setPoint($newPoint);
            }else{
                $APPLICATION->ThrowException(GetMessage('DDELIVERY_SAVE_STATUS_ERROR_ORDER_NOT_LOAD'));
                return false;
            }

            if(empty($order) || $order->ddeliveryID) {
                $APPLICATION->ThrowException(GetMessage('DDELIVERY_SAVE_STATUS_ERROR_ORDER_NOT_LOAD'));
                return false;
            }
            $order->localStatus = $statusID;

            /**
             * @var \DDelivery\Order\DDeliveryOrder $order
             */
            $order->shopRefnum = $orderId;
            $order->paymentVariant = $cmsOrder['PAY_SYSTEM_ID'];
            $ddeliveryOrderID = $ddeliveryUI->sendOrderToDD( $order);
            $ddeliveryUI->saveFullOrder($order);
            if(!$ddeliveryOrderID) {
                $APPLICATION->ThrowException(GetMessage('DDELIVERY_SAVE_STATUS_ERROR_NOT_SAVE'));
                return false;
            }
            CSaleOrder::Update($orderId, array("TRACKING_NUMBER" => $ddeliveryOrderID));
            return true;
        }  catch(\DDelivery\DDeliveryException $e)  {
            $APPLICATION->ThrowException(GetMessage('DDELIVERY_SAVE_STATUS_ERROR_EXCEPTION', array('%1' => $APPLICATION->ConvertCharset($e->getMessage(), 'UTF-8', SITE_CHARSET))));
            return false;
        }

    }
}