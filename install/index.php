<?php
/**
 * User: DnAp
 * Date: 13.05.14
 * Time: 11:48
 */

use DDelivery\DDeliveryUI;

IncludeModuleLangFile(__FILE__);

Class ddelivery_ddelivery extends CModule
{
    const MODULE_ID = "ddelivery.ddelivery";
    var $MODULE_ID = "ddelivery.ddelivery";
    public $MODULE_GROUP_RIGHTS = 'N';
    public $NEED_MAIN_VERSION = '14.5.0';
    public $NEED_MODULES = array('catalog', 'sale');

    function GetMessage($name, $aReplace=false)
    {
        return GetMessage($name, $aReplace);
    }

    function fromCp1251($str) {
        if (!defined('BX_UTF')) {
            return $str;
        }
        global $APPLICATION;
        return $APPLICATION->ConvertCharset($str, 'cp1251', SITE_CHARSET);
    }

    function __construct()
    {
        $arModuleVersion = array();

        include("version.php");

        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion))
        {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }

        $this->PARTNER_NAME = 'DDelivery';
        $this->PARTNER_URI = 'http://ddelivery.ru/';

        $this->MODULE_NAME = $this->GetMessage('DDELIVERY_MODULE_NAME');
        $this->MODULE_DESCRIPTION = $this->GetMessage('DDELIVERY_MODULE_DESCRIPTION');
    }

    function InstallFiles($arParams = array())
    {
        if (is_dir($admin = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'.self::MODULE_ID.'/admin'))
        {
            if ($dir = opendir($admin))
            {
                while (false !== $item = readdir($dir))
                {
                    if ($item == '..' || $item == '.' || $item == 'menu.php')
                        continue;
                    file_put_contents($file = $_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/'.self::MODULE_ID.'_'.$item,
                        '<'.'? require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/'.self::MODULE_ID.'/admin/'.$item.'");?'.'>');
                }
                closedir($dir);
            }
        }
        return true;
    }

    function UnInstallFiles()
    {
        if (is_dir($admin = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'.self::MODULE_ID.'/admin'))
        {
            if ($dir = opendir($admin))
            {
                while (false !== $item = readdir($dir))
                {
                    if ($item == '..' || $item == '.')
                        continue;
                    unlink($_SERVER['DOCUMENT_ROOT'].'/bitrix/admin/'.self::MODULE_ID.'_'.$item);
                }
                closedir($dir);
            }
        }
        return true;
    }

    public function DoInstall() {
        if ($GLOBALS['APPLICATION']->GetGroupRight('main') < 'W')
            return;

        if (is_array($this->NEED_MODULES) && !empty($this->NEED_MODULES)) {
            foreach ($this->NEED_MODULES as $module) {
                if (!IsModuleInstalled($module)) {
                    $this->ShowForm('ERROR', $this->GetMessage('DDELIVERY_NEED_MODULES', array('#MODULE#' => $module, '#NEED#' => $this->NEED_MODULES)));
                    return;
                }
                include($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'.$module.'/install/version.php');
                if(!CheckVersion($arModuleVersion['VERSION'], $this->NEED_MAIN_VERSION)) {
                    $this->ShowForm('ERROR', $this->GetMessage('DDELIVERY_NEED_MODULES', array('#MODULE#' => $module, '#NEED#' => $this->NEED_MAIN_VERSION)));
                    return;
                }
            }
        }

        if(!function_exists('curl_init')) {
            $this->ShowForm('ERROR', $this->GetMessage('DDELIVERY_NEED_MODULES_CURL', array('#MODULE#' => 'cURL')));
            return;
        }
        if (CheckVersion(SM_VERSION, $this->NEED_MAIN_VERSION)) {
            RegisterModuleDependences('sale', 'onSaleDeliveryHandlersBuildList', self::MODULE_ID, 'DDeliveryEvents', 'Init');
            RegisterModuleDependences('sale', 'OnOrderNewSendEmail', self::MODULE_ID, 'DDeliveryEvents', 'OnOrderNewSendEmail');
            RegisterModuleDependences('sale', 'OnSaleBeforeStatusOrder', self::MODULE_ID, 'DDeliveryEvents', 'OnSaleBeforeStatusOrder');
            if(!symlink(__DIR__."/components/ddelivery", $_SERVER["DOCUMENT_ROOT"]."/bitrix/components/ddelivery")){
                CopyDirFiles(__DIR__."/components", $_SERVER["DOCUMENT_ROOT"]."/bitrix/components", true, true);
            }
            RegisterModule(self::MODULE_ID);

            CModule::IncludeModule("sale");


            $ddeliveryConfig = CSaleDeliveryHandler::GetBySID('ddelivery')->Fetch();
            $ddeliveryConfig['ACTIVE'] = 'N';
            CSaleDeliveryHandler::Set('ddelivery', $ddeliveryConfig, false);

            include_once(__DIR__.'/../include.php');
            include_once(__DIR__.'/../DDeliveryEvents.php');
            include_once(__DIR__.'/../DDeliveryShop.php');

            // Добавляем свойства в бд
            CSaleOrderProps::add(array (
                'PERSON_TYPE_ID' => '1',
                'NAME' => 'DDelivery LocalID',
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
            ));


            $IntegratorShop = new DDeliveryShop($ddeliveryConfig['CONFIG']['CONFIG'], array(), array());
            $ddeliveryUI = new DDeliveryUI($IntegratorShop, true);
            $ddeliveryUI->createTables();


            $this->ShowForm('OK', GetMessage('MOD_INST_OK'), true);
        }else{
            $this->ShowForm('ERROR', $this->GetMessage('DDELIVERY_NEED_RIGHT_VER', array('#NEED#' => $this->NEED_MAIN_VERSION)));
        }
    }

    private function ShowForm($typeIn, $messageIn, $installOkIn = false) {
        global $APPLICATION, $type, $message, $installOk;
        $installOk = $installOkIn;
        $type = $typeIn;
        $message = $messageIn;
        $APPLICATION->SetTitle($this->GetMessage('DDELIVERY_MODULE_NAME'));
        $APPLICATION->IncludeAdminFile(GetMessage("CATALOG_INSTALL_TITLE"), __DIR__."/step1.php");
    }

    public function DoUninstall() {
        if ($GLOBALS['APPLICATION']->GetGroupRight('main') < 'W')
            return;

        global $DB;
        $DB->Query("DROP TABLE IF EXISTS ddelivery_orders", false, __FILE__.':'.__LINE__);
        $DB->Query("DROP TABLE IF EXISTS ddelivery_cache", false, __FILE__.':'.__LINE__);
        $DB->Query("DROP TABLE IF EXISTS ddelivery_ps_dd_cities", false, __FILE__.':'.__LINE__);

        UnRegisterModuleDependences('sale', 'onSaleDeliveryHandlersBuildList', self::MODULE_ID, 'DDeliveryEvents', 'Init');
        UnRegisterModuleDependences('sale', 'OnOrderNewSendEmail', self::MODULE_ID, 'DDeliveryEvents', 'OnOrderNewSendEmail');
        UnRegisterModuleDependences('sale', 'OnSaleBeforeStatusOrder', self::MODULE_ID, 'DDeliveryEvents', 'OnSaleBeforeStatusOrder');


        if(is_link($_SERVER["DOCUMENT_ROOT"]."/bitrix/components/ddelivery")) {
            unlink($_SERVER["DOCUMENT_ROOT"]."/bitrix/components/ddelivery");
        }else{
            DeleteDirFilesEx("/bitrix/components/ddelivery");
        }

        UnRegisterModule(self::MODULE_ID);
        $this->ShowForm('OK', GetMessage('MOD_UNINST_OK'));

    }
}
