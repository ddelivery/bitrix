<?php
/**
 * User: DnAp
 * Date: 16.05.14
 * Time: 12:56
 */

include_once(__DIR__.'/application/bootstrap.php');

class DDeliveryEvents
{
    function Init()
    {
        include(__DIR__.'/install/version.php');
        /** @var $arModuleVersion string[] */

        $html = '
            <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
            <script type="text/javascript" src="/bitrix/modules/ddelivery/install/components/ddelivery/static/jquery.the-modal.js"></script>

            <script src="/bitrix/modules/ddelivery/install/components/ddelivery/static/include.js" language="javascript" charset="utf-8"></script>
            <script src="/bitrix/modules/ddelivery/install/components/ddelivery/static/js/ddelivery.js" language="javascript" charset="utf-8"></script>

            <span id="ddelivery">
                <span><script>
                    document.write(DDeliveryIntegration.getStatus());
                </script></span>
                <a href="javascript:DDeliveryIntegration.openPopup()">�������</a>
            </span>';
        $html = str_replace(array("\n", "\r"), array(' ', ''), $html);

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

            /* ������ �������� */
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
    /* ������ ������������ ������ �������� */
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
                    "POST_TEXT"=> '���� ����� �������� � ������ �������� DDelivery.ru, ������������������ �� ����� (��� ����� ��������).'.$jsHack,
                ),
                "TEST_MODE"=> array(
                    "TYPE" => "DROPDOWN",
                    "DEFAULT" => 1,
                    "TITLE" => '����� ������',
                    "VALUES" => array(
                        1 => '������������(stage)',
                        0 => '������(client)',
                    ),
                    "POST_TEXT"=> "��� ������� ������ ������, ����������� ���������� ����� ������������",
                    "GROUP" => "general",
                ),
                "DECLARED_PERCENT"=>array(
                    "TYPE" => "INTEGER",
                    "DEFAULT" => "100",
                    "TITLE" => '����� % �� ��������� ������ ����������',
                    'POST_TEXT' => '�� ������ ������� ��������� ��������� ��� ���������� ��������� �������� �� ���� �������� ������� ���������.',
                    "GROUP" => "general",
                    'CHECK_FORMAT' => 'NUMBER',
                ),

                "SECTION_PROP" => array(
                    'TYPE'=>'SECTION',
                    'TITLE'=>'������������ �����',
                    "GROUP" => "general",
                ),

                "SEND_STATUS" => array(
                    "TYPE" => 'DROPDOWN',
                    "VALUES" => $sendStatusValues,
                    "DEFAULT" => "P",
                    "TITLE" => '������ ��� ��������',
                    "POST_TEXT" => '<br>�������� ������, ��� ������� ������ �� ����� ������� ����� ������� � DDelivery.<br>
                            �������, ��� �������� �������� ���������� ��������� ����� �� ��������� ������� ����.',
                    "GROUP" => "general",
                ),

                "PROP_FIO" => array(
                    "TYPE"=>"DROPDOWN",
                    "DEFAULT" => "FIO",
                    "TITLE" => GetMessage('DIGITAL_DELIVERY_CONFIG_PROP_FIO'),
                    "GROUP" => "general",
                    "VALUES" => $props,
                    'POST_TEXT' => '�������� ����, ��������������� ���� ��� � ����� �������',
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

        // ���������� ���������
        $cCatalog = new CCatalog();
        $res = $cCatalog->GetList();
        while($catalog = $res->Fetch() ) {
            $key = 'IBLOCK_'.$catalog['OFFERS_IBLOCK_ID'];

            $arConfig['CONFIG'][$key.'_SECTION']= array(
                'TYPE'=>'SECTION',
                'TITLE'=> '������������ ����� ��� ��������� "'.$catalog['NAME'].'"',
                "GROUP" => "general",
            );

            $iblockProperty = array(0 => GetMessage('DIGITAL_DELIVERY_DEFAULT'));
            $res = CIBlockProperty::GetList(Array(), Array( "IBLOCK_ID"=>$catalog['OFFERS_IBLOCK_ID']));
            while($prop = $res->Fetch()){
                $iblockProperty[$prop['ID']] = $prop['NAME'];
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


        // ��������� ��������� ������
        $arConfig['CONFIG'] +=  array(
            "SECTION_DEFAULT" => array(
                'TYPE'=>'SECTION',
                'TITLE'=>'�������� �� ���������',
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
            // ������� ��������
            'SUPPORTED_TYPE' => array(
                "TYPE" => "DROPDOWN",
                "TITLE" => '������� ��������',
                "GROUP" => "type",
                "DEFAULT" => '0',
                "VALUES" => array(
                    0 => '��� � �������',
                    1 => '��� DDelivery',
                    2 => '������� DDelivery',
                ),
            ),
        );

        $arConfig['CONFIG']['PRICE_IF_SECTION'] = array (
            'TYPE' => 'SECTION',
            'TITLE' => '�������� �������� ��������, ������� �� �� ������ ������� ���������� ��� ����� ��������',
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
            'TITLE' => '��� �������� ��������� �������� � ����������� �� ������� ������.� ���. �� ������ ����� ���������
                ������� ��������, ����� ������ ���� ������������� ��������.',
            'GROUP' => 'price',
        );

        for($i=1; $i<=3;$i++) {
            $arConfig['CONFIG'] += array(
                'PRICE_IF_'.$i.'_CONTROL' => array (
                    'TYPE' => 'MULTI_CONTROL_STRING',
                    'MCS_ID' => 'BOX_general_'.$i,
                    'TITLE' => '��',
                    'GROUP' => 'price',
                ),
                'PRICE_IF_'.$i.'_MIN' => array (
                    'TYPE' => 'STRING',
                    'MCS_ID' => 'BOX_general_'.$i,
                    'POST_TEXT' => ' �� ',
                    'SIZE' => 1,
                    'DEFAULT' => '',
                    'GROUP' => 'price',
                    'CHECK_FORMAT' => 'NUMBER',
                ),
                'PRICE_IF_'.$i.'_MAX' => array (
                    'TYPE' => 'STRING',
                    'MCS_ID' => 'BOX_general_'.$i,
                    'POST_TEXT' => ' ��������� �������� ',
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
                        1 => '������ ���������� ���',
                        2 => '������� ���������� ���',
                        3 => '������� ���������� % �� ��������� ��������',
                        4 => '������� ���������� ���. �� ��������� ��������',
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
                'TITLE' => '���������� ���� �������� ��� ����������',
                'GROUP' => 'price',
            ),
            'AROUND' => array (
                'TYPE' => 'DROPDOWN',
                'MCS_ID' => 'AROUND',
                'POST_TEXT' => ' ��� ',
                'DEFAULT' => 1,
                'GROUP' => 'price',
                'VALUES' => array(
                    2 => '����',
                    3 => '�����',
                    1 => '��������������',
                ),
            ),
            'AROUND_STEP' => array (
                'TYPE' => 'STRING',
                'MCS_ID' => 'AROUND',
                'POST_TEXT' => ' ���.',
                'SIZE' => 1,
                'DEFAULT' => '',
                'GROUP' => 'price',
                'CHECK_FORMAT' => 'NUMBER',
            ),
            'PAY_PICKUP' => array(
                'TITLE' => '�������� ��������� ������ � ���� ��������',
                "TYPE" => "CHECKBOX",
                "DEFAULT" => 'N',
                "GROUP" => "type",
            ),
        );

        // var_dump($arConfig);

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
            16 => 'IM Logistics ����������',
            22 => 'IM Logistics ��������',
            17 => 'IMLogistics',
            3 => 'Logibox',
            14 => 'Maxima Express',
            1 => 'PickPoint',
            7 => 'QIWI Post',
            13 => '���',
            26 => '���� �������',
            25 => '���� ������� ���������',
            24 => '���� ������',
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
                    self::clearCache();
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
            CSaleOrderProps::UpdateOrderPropsRelations($id, 'DigitalDelivery:all', "D");
            */

        }
        COption::SetOptionString('ddelivery', 'setings', $string);

        return $string;
    }

    private static function clearCache(){
        // @todo ������� ���
    }

    /* ����������� ��������� ��������*/
    static function Calculate($profile, $arConfig, $arOrder = false, $STEP= false, $TEMP = false)
    {

        if(substr($_SERVER['PHP_SELF'], 0, 14) == '/bitrix/admin/'){
            return array( "RESULT" => "ERROR", 'ERROR' => '� �� ���� ������� ��������� �������� � �������');
        }

        if(!empty($_SESSION['DIGITAL_DELIVERY']) && !empty($_SESSION['DIGITAL_DELIVERY']['ORDER_ID']))
        {
            // TODO ������� ����� �������
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
            if(empty($itemList)){
                return array( "RESULT" => "ERROR", 'ERROR' => '������� � ������ �����');
            }
            //END TODO


            $IntegratorShop = new DDeliveryShop($arConfig, $itemList, array());
            $ddeliveryUI = new \DDelivery\DDeliveryUI($IntegratorShop);
            $ddeliveryUI->initIntermediateOrder($_SESSION['DIGITAL_DELIVERY']['ORDER_ID']);
            $price = $ddeliveryUI->getOrder()->getPoint()->getDeliveryInfo()->clientPrice;

            return array("RESULT" => "OK", 'VALUE'=>$price);
        }

        return array(
            "RESULT" => "ERROR",
            "ERROR" => GetMessage('DIGITAL_DELIVERY_EMPTY_POINT')
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
     * ������� ���������� ����� �������� �����. �� ���������� ������ sqlite ID
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

        if($arOrder["DELIVERY_ID"]=="DigitalDelivery:all" && !empty($_SESSION['DIGITAL_DELIVERY']['ORDER_ID']))
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

    static function OnSaleStatusOrder($orderId, $statusID)
    {
        var_dump(1);
        die();
    }
}