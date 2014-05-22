<?php
/**
 * User: DnAp
 * Date: 13.05.14
 * Time: 11:48
 */

IncludeModuleLangFile(__FILE__);


Class ddelivery extends CModule
{
    public $MODULE_ID = "ddelivery";
    public $MODULE_GROUP_RIGHTS = 'N';
    public $NEED_MAIN_VERSION = '14.5.0';
    public $NEED_MODULES = array('catalog', 'sale');

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

        if (is_array($this->NEED_MODULES) && !empty($this->NEED_MODULES)) {
            foreach ($this->NEED_MODULES as $module) {
                if (!IsModuleInstalled($module)) {
                    $this->ShowForm('ERROR', GetMessage('DIGITAL_DELIVERY_NEED_MODULES', array('#MODULE#' => $module)));
                    return;
                }
            }
        }
        if (strlen($this->NEED_MAIN_VERSION) <= 0 || version_compare(SM_VERSION, $this->NEED_MAIN_VERSION) >= 0) {
            $eventManager = \Bitrix\Main\EventManager::getInstance();

            $eventManager->registerEventHandlerCompatible('sale', 'onSaleDeliveryHandlersBuildList', $this->MODULE_ID, 'DDeliveryEvents', 'Init');
            $eventManager->registerEventHandlerCompatible('sale', 'OnOrderNewSendEmail', $this->MODULE_ID, 'DDeliveryEvents', 'OnOrderNewSendEmail');
            $eventManager->registerEventHandlerCompatible('sale', 'OnSaleStatusOrder', $this->MODULE_ID, 'DDeliveryEvents', 'OnSaleStatusOrder');
            if(!symlink(__DIR__."/components/ddelivery", $_SERVER["DOCUMENT_ROOT"]."/bitrix/components/ddelivery")){
                CopyDirFiles(__DIR__."/components", $_SERVER["DOCUMENT_ROOT"]."/bitrix/components", true, true);
            }
            RegisterModule($this->MODULE_ID);

            CModule::IncludeModule("sale");


            $this->ShowForm('OK', GetMessage('MOD_INST_OK'), true);
        }
        else
            $this->ShowForm('ERROR', GetMessage('DIGITAL_DELIVERY_NEED_RIGHT_VER', array('#NEED#' => $this->NEED_MAIN_VERSION)));
    }

    private function ShowForm($typeIn, $messageIn, $installOkIn = false) {
        global $APPLICATION, $type, $message, $installOk;
        $installOk = $installOkIn;
        $type = $typeIn;
        $message = $messageIn;
        $APPLICATION->SetTitle(GetMessage('DIGITAL_DELIVERY_MODULE_NAME'));
        $APPLICATION->IncludeAdminFile(GetMessage("CATALOG_INSTALL_TITLE"), __DIR__."/step1.php");
    }

    public function DoUninstall() {
        if ($GLOBALS['APPLICATION']->GetGroupRight('main') < 'W')
            return;
        $eventManager = \Bitrix\Main\EventManager::getInstance();

        $eventManager->unRegisterEventHandler('sale', 'onSaleDeliveryHandlersBuildList', $this->MODULE_ID, 'DDeliveryEvents', 'Init');
        $eventManager->unRegisterEventHandler('sale', 'OnOrderNewSendEmail', $this->MODULE_ID, 'DDeliveryEvents', 'OnOrderNewSendEmail');
        $eventManager->unRegisterEventHandler('sale', 'OnSaleStatusOrder', $this->MODULE_ID, 'DDeliveryEvents', 'OnSaleStatusOrder');


        if(is_link($_SERVER["DOCUMENT_ROOT"]."/bitrix/components/ddelivery")) {
            unlink($_SERVER["DOCUMENT_ROOT"]."/bitrix/components/ddelivery");
        }else{
            DeleteDirFilesEx("/bitrix/components/ddelivery");
        }

        UnRegisterModule($this->MODULE_ID);
        $this->ShowForm('OK', GetMessage('MOD_UNINST_OK'));

    }
}
