<?php
/**
 * User: DnAp
 * Date: 13.05.14
 * Time: 11:48
 */

IncludeModuleLangFile(__FILE__);


Class digital_delivery extends CModule
{
    public $MODULE_ID = "ddelivery";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;
    public $MODULE_GROUP_RIGHTS = 'N';
    public $NEED_MAIN_VERSION = '';
    public $NEED_MODULES = array('sale');

    function __construct()
    {
        $arModuleVersion = array();

        include("version.php");

        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion))
        {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }

        $this->PARTNER_NAME = GetMessage('DIGITAL_DELIVERY_PARTNER_NAME');
        $this->PARTNER_URI = 'http://ddelivery.ru/';

        $this->MODULE_NAME = GetMessage('DIGITAL_DELIVERY_MODULE_NAME');
        $this->MODULE_DESCRIPTION = GetMessage('DIGITAL_DELIVERY_MODULE_DESCRIPTION');
    }

    public function DoInstall() {
        if ($GLOBALS['APPLICATION']->GetGroupRight('main') < 'W')
            return;

        if (is_array($this->NEED_MODULES) && !empty($this->NEED_MODULES))
            foreach ($this->NEED_MODULES as $module)
                if (!IsModuleInstalled($module))
                    $this->ShowForm('ERROR', GetMessage('DIGITAL_DELIVERY_NEED_MODULES', array('#MODULE#' => $module)));

        if (strlen($this->NEED_MAIN_VERSION) <= 0 || version_compare(SM_VERSION, $this->NEED_MAIN_VERSION) >= 0) {
            $eventManager = \Bitrix\Main\EventManager::getInstance();

            $eventManager->registerEventHandlerCompatible('sale', 'onSaleDeliveryHandlersBuildList', $this->MODULE_ID, 'CDigitalDelivery', 'Init');
            $eventManager->registerEventHandlerCompatible('sale', 'OnOrderNewSendEmail', $this->MODULE_ID, 'CDigitalDelivery', 'OnOrderNewSendEmail');
            $eventManager->registerEventHandlerCompatible('sale', 'OnSaleStatusOrder', $this->MODULE_ID, 'CDigitalDelivery', 'OnSaleStatusOrder');
            // CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/dv_module/install/components", $_SERVER["DOCUMENT_ROOT"]."/bitrix/components", true, true);
            RegisterModule($this->MODULE_ID);

            CModule::IncludeModule("sale");

            /*/ ƒобавл€ет свойство товару и делает его прив€заным к DD
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
            $this->ShowForm('OK', GetMessage('MOD_INST_OK'));
        }
        else
            $this->ShowForm('ERROR', GetMessage('DIGITAL_DELIVERY_NEED_RIGHT_VER', array('#NEED#' => $this->NEED_MAIN_VERSION)));
    }

    public function DoUninstall() {
        if ($GLOBALS['APPLICATION']->GetGroupRight('main') < 'W')
            return;
        $eventManager = \Bitrix\Main\EventManager::getInstance();

        $eventManager->unRegisterEventHandler('sale', 'onSaleDeliveryHandlersBuildList', $this->MODULE_ID, 'CDigitalDelivery', 'Init');
        $eventManager->unRegisterEventHandler('sale', 'OnOrderNewSendEmail', $this->MODULE_ID, 'CDigitalDelivery', 'OnOrderNewSendEmail');
        $eventManager->unRegisterEventHandler('sale', 'OnSaleStatusOrder', $this->MODULE_ID, 'CDigitalDelivery', 'OnSaleStatusOrder');

        //DeleteDirFilesEx("/bitrix/components/dv");

        UnRegisterModule($this->MODULE_ID);
        $this->ShowForm('OK', GetMessage('MOD_UNINST_OK'));

    }
}
