<?php
/**
 * User: DnAp
 * Date: 13.05.14
 * Time: 11:48
 */

// ƒобавл€ет свойство товару и делает его прив€заным к DD
$id = CSaleOrderProps::add(array (
    'ID' => '20',
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