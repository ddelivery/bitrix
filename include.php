<?
if(!CModule::IncludeModule("sale") || !CModule::IncludeModule("catalog")) {
    exit;
}

IncludeModuleLangFile(__FILE__);

function ddeliveryFromCp1251($str) {
    if (!defined('BX_UTF')) {
        return $str;
    }
    global $APPLICATION;
    return $APPLICATION->ConvertCharset($str, 'cp1251', 'utf-8');
}

\Bitrix\Main\Loader::registerAutoLoadClasses(
    'ddelivery.ddelivery',
    array(
        'DDeliveryShop' => 'DDeliveryShop.php',
        'DDeliveryEvents' => 'DDeliveryEvents.php',
    )
);
