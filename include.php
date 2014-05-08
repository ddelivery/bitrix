<?
CModule::IncludeModule("sale");
CModule::IncludeModule("catalog");

IncludeModuleLangFile(__FILE__);

include_once(__DIR__.'/application/bootstrap.php');

if (!function_exists('str_utf8')) {
    function str_utf8($str) {
        if (defined('BX_UTF')) {
            return $str;
        }
        global $APPLICATION;
        return $APPLICATION->ConvertCharset($str, 'utf-8', SITE_CHARSET);
    }
}


class CDigitalDelivery
{
    function Init()
    {
        include(__DIR__.'/install/version.php');
        /** @var $arModuleVersion string[] */

        $options = COption::GetOptionString('delivery', 'ddelivery');

        if($options && $options = unserialize($options)){
            $products = array();
            /**
             * @var dDeliveryProduct $object
             */
            $object = self::Calc($options, $products);

            $html = GetMessage('DIGITAL_DELIVERY_PROFILE_DESCRIPTION');

            $html.='
<link rel="stylesheet" href="/ddelivery/stylesheet.css" type="text/css" media="screen" />
<script src="/ddelivery/ddelivery.js?'.$arModuleVersion['VERSION'].'" charset="UTF-8"></script>
';

            $html = str_replace(array("\n", "\r"), array(' ', ''), $html);
        }else{
            $html = GetMessage('DIGITAL_DELIVERY_NOT_INSTALL');
        }
        return array(
            /* Basic description */
            "SID" => "DigitalDelivery",
            "NAME" => GetMessage('DIGITAL_DELIVERY_NAME'),
            "DESCRIPTION" => GetMessage('DIGITAL_DELIVERY_DESCRIPTION'),
            "DESCRIPTION_INNER" => GetMessage('DIGITAL_DELIVERY_DESCRIPTION_INNER'),
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
                    "TITLE" => GetMessage('DIGITAL_DELIVERY_PROFILE_NAME'),
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
                    "POST_TEXT"=> 'Ключ можно получить в личном кабинете DDelivery.ru, зарегестрировашись на сайте (для новых клиентов).',
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
                ),

                "SECTION_PROP" => array(
                    'TYPE'=>'SECTION',
                    'TITLE'=>'Соответствие полей',
                    "GROUP" => "general",
                ),

                "SEND_STATUS" => array(
                    "TYPE" => 'DROPDOWN',
                    "VALUES" => $sendStatusValues,
                    "DEFAULT" => "P",
                    "TITLE" => 'Статус для отправки',
                    "POST_TEXT" => '<br>Выберите статус, при котором заявки из вашей системы будут уходить в DDelivery.<br>
                            Помните, что отправка означает готовность отгрузить заказ на следующий рабочий день.',
                    "GROUP" => "general",
                ),

                "PROP_FIO" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "FIO",
                    "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_PROP_FIO'),
                    "GROUP" => "general",
                    "VALUES" => $props,
                    'POST_TEXT' => 'Выберите поле, соответствующее полю ФИО в вашей системе',
                ),
                "PROP_PHONE" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "PHONE",
                    "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_PROP_PHONE'),
                    "GROUP" => "general",
                    "VALUES" => $props,
                ),
            )
        );

        // Перебираем инфоблоки
        $cCatalog = new CCatalog();
        $res = $cCatalog->GetList();
        while($catalog = $res->Fetch() ){
            $key = 'IBLOCK_'.$catalog['IBLOCK_ID'];

            $arConfig['CONFIG'][$key.'_SECTION']= array(
                'TYPE'=>'SECTION',
                'TITLE'=> 'Соответствие полей для инфоблока "'.$catalog['NAME'].'"',
                "GROUP" => "general",
            );

            $iblockProperty = array(0 => GetMessage('DIGITAL_DELIVERY_DEFAULT'));
            $res = CIBlockProperty::GetList(Array(), Array( "IBLOCK_ID"=>$catalog['IBLOCK_ID']));
            while($prop = $res->Fetch()){
                $iblockProperty[$prop['ID']] = $prop['NAME'];
            }

            $arConfig['CONFIG_GROUPS'][$key] = $catalog['NAME'];
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
            ),

            "DEFAULT_X" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "",
                "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_DEFAULT_X'),
                "GROUP" => "general",
            ),
            "DEFAULT_Z" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "",
                "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_DEFAULT_Z'),
                "GROUP" => "general"
            ),
            "DEFAULT_Y" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "",
                "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_DEFAULT_Y'),
                "GROUP" => "general"
            ),
            "DEFAULT_W" => array(
                "TYPE" => "INTEGER",
                "DEFAULT" => "",
                "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_DEFAULT_W'),
                "GROUP" => "general"
            ),
            // Способы доставки
            'TYPE_' => array(
                "TYPE" => "DROPDOWN",
                "TITLE" => GetMessage('DIGITAL_DELIVERY_'.$key2),
                "GROUP" => "type",
                "DEFAULT" => 0,
                "VALUES" => array(
                    '1,2' => 'ПВЗ и курьеры',
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




        $companyList = array(6 => GetMessage("DIGITAL_DELIVERY_CONFIG_GROUPS_COMPANY_SDEC"),
            4 => "Boxberry",
            11 => "Hermes",
            2 => "IM Logistics",
            16 => GetMessage("DIGITAL_DELIVERY_CONFIG_GROUPS_COMPANY_IM_LOG2"),
            17 => GetMessage("DIGITAL_DELIVERY_CONFIG_GROUPS_COMPANY_IM_LOG3"),
            3 => "Logibox",
            14 => "Maxima Express",
            1 => "PickPoint",
            7 => "QIWI",
        );

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
                'DEFAULT' => '',
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
        );

        // var_dump($arConfig);

        return $arConfig;
    }

    function GetSettings($strSettings)
    {
        return unserialize($strSettings);
    }

    function SetSettings($arSettings)
    {
        $string = serialize($arSettings);
        if($arSettings){
            $oldSetting = COption::GetOptionString('delivery', 'ddelivery', $string);
            if($oldSetting) {
                $oldSetting = unserialize($oldSetting);
                if( $oldSetting && $oldSetting['API_KEY'] != $arSettings['API_KEY']){
                    self::clearCache();
                }
            }
        }
        COption::SetOptionString('delivery', 'ddelivery', $string);

        return $string;
    }

    private static function clearCache(){
        unlink($_SERVER["DOCUMENT_ROOT"].'/upload/ddelivery_cache.dat');
    }

    /**
     * @param $arConfig
     * @return CDigitalDeliveryProduct
     */
    static function Calc($arConfig, &$products = array())
    {
        return array();
        $arSelect = array('ID');

        foreach($arConfig as $name => $val){
            if(preg_match('/^IBLOCK_[0-9]+_([XYZW])$/', $name, $math) > 0){
                $arSelect[$math[1]] = 'PROPERTY_'.(is_array($val) ? $val['VALUE'] : $val);
            }
        }

        $CSB = new CSaleBasket();
        $res = $CSB->GetList(array(), array("FUSER_ID" => $CSB->GetBasketUserID(), "LID" => SITE_ID, "ORDER_ID" => "NULL"));
        //$products  = array();
        while($product = $res->Fetch()){
            if( $product['DELAY'] == 'N'){
                $products[$product['PRODUCT_ID']] = new dDeliveryProduct($arConfig, $product['QUANTITY'], $product['NAME'].' ('.$product['QUANTITY'].' шт)');
            }
        }
        $CIBlockElement = new CIBlockElement();
        $res = $CIBlockElement->GetList(
            Array(),
            array('ID' => array_keys($products)),
            false, Array(), $arSelect);

        while($element = $res->Fetch()){
            foreach($arSelect as $key => $val){
                if(!empty($element[$val.'_VALUE'])){ // PROPERTY_8_VALUE
                    $products[$element['ID']]->$key = $element[$val.'_VALUE'];
                }
            }
        }
        //var_dump($arConfig);
        //var_dump($products);

        $result = dDeliveryProduct::merge($products);
        return $result;
    }

    /* Калькуляция стоимости доставки*/
    static function Calculate($profile, $arConfig, $arOrder = false, $STEP= false, $TEMP = false)
    {
        if($_REQUEST['DELIVERY_ID'] != "DigitalDelivery:all"){
            return array("RESULT" => "ERROR");
        }
        //$res = self::Calc($arConfig);

        if($STEP == 1){
            return array(
                "RESULT" => "NEXT_STEP",
                //"VALUE" => 1
            );
        }
        if(!empty($_SESSION['ddelivery']) && !empty($_SESSION['ddelivery']['price'])){
            return array(
                "RESULT" => "OK",
                "VALUE" => $_SESSION['ddelivery']['price']
            );
        }

        return array(
            "RESULT" => "ERROR",
            "ERROR" => GetMessage('DIGITAL_DELIVERY_EMPTY_POINT')
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

    public static function getPrice($point, $save=false)
    {
        $options = COption::GetOptionString('delivery', 'ddelivery');
        $options = unserialize($options);
        $products = array();
        /**
         * @var dDeliveryProduct $object
         */
        $object = self::Calc($options, $products);
        $descr = array();
        foreach($products as $product){
            $descr[] = $product;
        }

        $data = array('width' => $object->X, 'height' => $object->Z, 'length'=>$object->Y, 'weight'=>$object->W,
            'point'=>$point, 'description' => $object->description
        );
        if($save)
            $_SESSION['DIGITAL_DELIVERY']['DATA'] = $data;

        $dDeliveryLib = new dDeliveryLib($options['API_KEY']);
        $data = $dDeliveryLib->apiPrice($object, $point);

        if(!$data && !$data['success']){
            return false;
        }
        $dataSource = $data;
        $i=1;
        while($i <= 3){
            if(($options['PRICE_IF_'.$i] == '>' && $data['response']['delivery_price'] > $options['PRICE_SUM_'.$i])
                || ($options['PRICE_IF_'.$i] == '<' && $data['response']['delivery_price'] < $options['PRICE_SUM_'.$i]) )
            {
                if($options['PRICE_TYPE_'.$i] == 'A') {
                    $data['response']['delivery_price'] = 0;
                }elseif($options['PRICE_TYPE_'.$i] == 'F') {
                    if($options['PRICE_VALUE_'.$i] > 100){
                        $options['PRICE_VALUE_'.$i] = 100;
                    }
                    $data['response']['delivery_price'] = round($data['response']['delivery_price'] * (1 - ($options['PRICE_VALUE_'.$i] / 100)));
                }elseif($options['PRICE_TYPE_'.$i] == 'M') {
                    if($options['PRICE_VALUE_'.$i] > $data['response']['delivery_price']){
                        $data['response']['delivery_price'] = 0;
                    }else{
                        $data['response']['delivery_price'] = $data['response']['delivery_price'] - $options['PRICE_VALUE_'.$i];
                    }
                }elseif($options['PRICE_TYPE_'.$i] != 'AC'){
                    // фигня, а не правило, пропускаем
                    continue;
                }
                break;
            }
            $i++;
        }

        $userData = array(
            'products' => $products,
            'package' => $object,
            'response' => $data,
            'responseSource' => $dataSource,
        );

        foreach(GetModuleEvents("digital.delivery", "getPrice", true)  as $arEvent){
            ExecuteModuleEventEx($arEvent, array($$userData));
        }

        return $userData['response'];
    }

    function OnOrderNewSendEmail($iOrderID, $eventName, $arFieldsUpdate){
        $cso = new CSaleOrder();
        $arFields = $cso->GetByID($iOrderID);
        if($arFields["DELIVERY_ID"]=="DigitalDelivery:all" && !empty($_SESSION['DIGITAL_DELIVERY']['DATA']))
        {
            $options = self::getOptions();

            $dataTmp = $_SESSION['DIGITAL_DELIVERY']['DATA'];

            // @TODO переделать из костыля
            $paymentPrice = '';
            if($arFields['PAY_SYSTEM_ID'] == 1){ // оплата у нас
                $paymentPrice = $arFields['PRICE'];
            }else{ // оплата где-то там.
            }

            $db_vals = CSaleOrderPropsValue::GetList(
                array("SORT" => "ASC"),
                array(
                    "ORDER_ID" => $iOrderID,
                    "CODE" => array($options['PROP_FIO'], $options['PROP_PHONE'])
                )
            );
            $name = '';
            $phone = '';
            while($prop = $db_vals->Fetch()){
                if($prop['CODE'] == $options['PROP_FIO']){
                    $name = $prop['VALUE'];
                }elseif($prop['CODE'] == $options['PROP_PHONE']){
                    $phone = $prop['VALUE'];
                }
            }

            $dDeliveryLib = new dDeliveryLib($options['API_KEY']);
            $dDeliveryLib->apiOrderCreate(
                $dataTmp['width'], $dataTmp['height'], $dataTmp['length'], $dataTmp['weight'],
                $dataTmp['point'], round(($arFields['PRICE']/100) * $options['ASSESSED_VALUE']),
                $paymentPrice, $name, $phone, 'OrderId: '.$iOrderID."\n"
            );


        }
        unset($_SESSION["DIGITAL_DELIVERY"]['DATA']);
    }
}

AddEventHandler("sale", "onSaleDeliveryHandlersBuildList", array('CDigitalDelivery', 'Init'));