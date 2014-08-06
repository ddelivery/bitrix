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
    var $MODULE_ID = "ddelivery.ddelivery";
    public $MODULE_GROUP_RIGHTS = 'N';
    public $NEED_MAIN_VERSION = '14.0.0';
    public $NEED_MODULES = array('catalog', 'sale');

    function GetMessage($name, $aReplace=false)
    {
        return GetMessage($name, $aReplace);
        //return $this->fromCp1251($msg);
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

        $this->MODULE_NAME = $this->GetMessage('DIGITAL_DELIVERY_MODULE_NAME');
        $this->MODULE_DESCRIPTION = $this->GetMessage('DIGITAL_DELIVERY_MODULE_DESCRIPTION');
    }

    public function DoInstall() {
        if ($GLOBALS['APPLICATION']->GetGroupRight('main') < 'W')
            return;

        if (is_array($this->NEED_MODULES) && !empty($this->NEED_MODULES)) {
            foreach ($this->NEED_MODULES as $module) {
                if (!IsModuleInstalled($module)) {
                    $this->ShowForm('ERROR', $this->GetMessage('DIGITAL_DELIVERY_NEED_MODULES', array('#MODULE#' => $module)));
                    return;
                }
            }
        }
        if (strlen($this->NEED_MAIN_VERSION) <= 0 || version_compare(SM_VERSION, $this->NEED_MAIN_VERSION) >= 0) {
            RegisterModuleDependences('sale', 'onSaleDeliveryHandlersBuildList', $this->MODULE_ID, 'DDeliveryEvents', 'Init');
            RegisterModuleDependences('sale', 'OnOrderNewSendEmail', $this->MODULE_ID, 'DDeliveryEvents', 'OnOrderNewSendEmail');
            RegisterModuleDependences('sale', 'OnSaleStatusOrder', $this->MODULE_ID, 'DDeliveryEvents', 'OnSaleStatusOrder');
            if(!symlink(__DIR__."/components/ddelivery", $_SERVER["DOCUMENT_ROOT"]."/bitrix/components/ddelivery")){
                CopyDirFiles(__DIR__."/components", $_SERVER["DOCUMENT_ROOT"]."/bitrix/components", true, true);
            }
            RegisterModule($this->MODULE_ID);

            CModule::IncludeModule("sale");


            $ddeliveryConfig = CSaleDeliveryHandler::GetBySID('ddelivery')->Fetch();

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

            global $DB;

            try{
                $DB->Query('SET NAMES utf8');
                $ddeliveryUI->createTables();

                //// Импорт из ps_dd_cities.sql
                $tempLine = '';
                $lines = file(__DIR__.'/ps_dd_cities.sql');
                foreach ($lines as $line)
                {
                    if (substr($line, 0, 2) == '--' || $line == '')
                        continue;

                    $tempLine .= $line;
                    if (substr(trim($line), -1, 1) == ';')
                    {
                        $DB->Query($tempLine);
                        $tempLine = '';
                    }
                }

            }catch (Exception $e){}




            $this->ShowForm('OK', GetMessage('MOD_INST_OK'), true);
        }
        else
            $this->ShowForm('ERROR', $this->GetMessage('DIGITAL_DELIVERY_NEED_RIGHT_VER', array('#NEED#' => $this->NEED_MAIN_VERSION)));
    }

    private function ShowForm($typeIn, $messageIn, $installOkIn = false) {
        global $APPLICATION, $type, $message, $installOk;
        $installOk = $installOkIn;
        $type = $typeIn;
        $message = $messageIn;
        $APPLICATION->SetTitle($this->GetMessage('DIGITAL_DELIVERY_MODULE_NAME'));
        $APPLICATION->IncludeAdminFile(GetMessage("CATALOG_INSTALL_TITLE"), __DIR__."/step1.php");
    }

    public function DoUninstall() {
        if ($GLOBALS['APPLICATION']->GetGroupRight('main') < 'W')
            return;
        UnRegisterModuleDependences('sale', 'onSaleDeliveryHandlersBuildList', $this->MODULE_ID, 'DDeliveryEvents', 'Init');
        UnRegisterModuleDependences('sale', 'OnOrderNewSendEmail', $this->MODULE_ID, 'DDeliveryEvents', 'OnOrderNewSendEmail');
        UnRegisterModuleDependences('sale', 'OnSaleStatusOrder', $this->MODULE_ID, 'DDeliveryEvents', 'OnSaleStatusOrder');


        if(is_link($_SERVER["DOCUMENT_ROOT"]."/bitrix/components/ddelivery")) {
            unlink($_SERVER["DOCUMENT_ROOT"]."/bitrix/components/ddelivery");
        }else{
            DeleteDirFilesEx("/bitrix/components/ddelivery");
        }

        UnRegisterModule($this->MODULE_ID);
        $this->ShowForm('OK', GetMessage('MOD_UNINST_OK'));

    }
}
