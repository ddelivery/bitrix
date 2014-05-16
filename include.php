<?
if(!CModule::IncludeModule("sale") || !CModule::IncludeModule("catalog")) {
    exit;
}

IncludeModuleLangFile(__FILE__);

\Bitrix\Main\Loader::registerAutoLoadClasses(
    'ddelivery',
    array(
        'DDeliveryShop' => 'DDeliveryShop.php',
        'DDeliveryEvents' => 'DDeliveryEvents.php',
    )
);
