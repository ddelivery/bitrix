<?php
/**
 * User: DnAp
 * Date: 14.05.14
 * Time: 10:42
 * @var CMain $APPLICATION
 */
use Bitrix\Main\Config\Configuration;
use DDelivery\Order\DDeliveryProduct;

class DDeliveryShop extends \DDelivery\Adapter\PluginFilters
{
    protected $config;
    protected $itemList;
    protected $formData;
    protected $orderProps = null;
    /**
     * @var \DDelivery\DDeliveryUI
     */
    private $ddeliveryUI;

    /**
     * @var bool
     */
    public $useTaxRate = true;

    /**
     * @param array $config
     * @param array $itemList
     * @param $formData
     */
    public function __construct($config, $itemList, $formData)
    {
        if(defined("BX_UTF")) {
            global $APPLICATION;
            $this->itemList = $APPLICATION->ConvertCharsetArray($itemList, 'utf-8', SITE_CHARSET);
            $this->config = $APPLICATION->ConvertCharsetArray($config, 'utf-8', SITE_CHARSET);
            $this->formData = $APPLICATION->ConvertCharsetArray($formData, 'utf-8', SITE_CHARSET);
        }else{
            $this->itemList = $itemList;
            $this->config = $config;
            $this->formData = $formData;
        }
    }

    /**
     * Настройки базы данных
     * @return array
     */
    public function getDbConfig()
    {
        $configurations = Configuration::getInstance()->get('connections');
        $mysql = $configurations['default'];
        return array(
            'type' => self::DB_MYSQL,
            'dsn' => 'mysql:host='.$mysql['host'].';dbname='.$mysql['database'],
            'user' => $mysql['login'],
            'pass' => $mysql['password'],
            'prefix' => 'ddelivery_',
        );
    }

    protected function getOrderProps()
    {
        if($this->orderProps === null) {
            $this->orderProps = array();
            if(isset($this->formData["PERSON_TYPE"])) {
                $db_props = CSaleOrderProps::GetList( array(),
                    array(
                        "PERSON_TYPE_ID" => $this->formData["PERSON_TYPE"],
                    )
                );

                while($prop = $db_props->Fetch()){
                    $this->orderProps[] = $prop;
                }
            }
        }
        return $this->orderProps;

    }

    protected function config($key)
    {
        if(isset($this->config[$key])){
            if(isset($this->config[$key]['VALUE'])) {
                return $this->config[$key]['VALUE'];
            }elseif(isset($this->config[$key]['DEFAULT'])) {
                return isset($this->config[$key]['DEFAULT']);
            }
        }
        return  null;
    }

    /**
     * Верните true если нужно использовать тестовый(stage) сервер
     * @return bool
     */
    public function isTestMode()
    {
        return (bool)$this->config('TEST_MODE');
    }

    /**
     * @param \DDelivery\DDeliveryUI $ddeliveryUI
     */
    public function setDDeliveryUI(\DDelivery\DDeliveryUI $ddeliveryUI)
    {
        $this->ddeliveryUI = $ddeliveryUI;
    }

    /**
     * Конвертирует в utf8 занные, если в битриксе не включен UTF8
     * @param string[]|string $string
     * @return string[]|string
     */
    static private function toUtf8($string)
    {
        if(defined('BX_UTF')){
            return $string;
        }
        global $APPLICATION;
        return $APPLICATION->convertCharsetarray($string, 'CP1251', 'UTF-8');
    }

    /**
     * Возвращает товары находящиеся в корзине пользователя, будет вызван один раз, затем закеширован
     * @return DDeliveryProduct[]
     */
    protected function _getProductsFromCart()
    {
        $productsDD = array();
        $iblockElIds = array();

        foreach($this->itemList as $item) {
            $iblockElIds[] = $item['PRODUCT_ID'];
        }

        $rsProducts = CCatalogProduct::GetList(
            array(),
            array('ID' => $iblockElIds),
            false,
            false,
            array('ID', 'ELEMENT_IBLOCK_ID', 'WIDTH', 'HEIGHT', 'LENGTH', 'WEIGHT')
        );
        $productList = array();
        while ($arProduct = $rsProducts->Fetch()){
            $productList[$arProduct['ID']] = $arProduct;
        }

        foreach($this->itemList as $item) {
            $product = $productList[$item['PRODUCT_ID']];
            $iblock = $product['ELEMENT_IBLOCK_ID'];

            $elProperty  = array(
                'WIDTH' => $this->config('IBLOCK_'.$iblock.'_X'),
                'HEIGHT' => $this->config('IBLOCK_'.$iblock.'_Y'),
                'LENGTH' => $this->config('IBLOCK_'.$iblock.'_Z'),
                'WEIGHT' => $this->config('IBLOCK_'.$iblock.'_W'),
            );
            $size = array(
                'WIDTH' => $this->config('DEFAULT_X'),
                'HEIGHT' => $this->config('DEFAULT_Y'),
                'LENGTH' => $this->config('DEFAULT_Z'),
                'WEIGHT' => $this->config('DEFAULT_W'),
            );

            if($elProperty['WIDTH'] || $elProperty['LENGTH'] || $elProperty['HEIGHT'] || $elProperty['WEIGHT']) {
                $iblockElPropDB = CIBlockElement::GetProperty($iblock, $item['PRODUCT_ID'], array(), array('ID' => array_values($elProperty)));
                while($iblockElProp = $iblockElPropDB->Fetch()) {
                    foreach($elProperty as $k => $v) {
                        if($iblockElProp['ID'] == $v) {
                            $size[$k] = $iblockElProp['VALUE'];
                        }
                    }
                }
            }

            foreach($elProperty as $k => $v) {
                if(!$v && !empty($product[$k])){
                    $size[$k] = $product[$k];
                }
            }

            $productsDD[] = new DDeliveryProduct(
                $item['PRODUCT_ID'],	//	int $id id товара в системе и-нет магазина
                $size['WIDTH']/10,	//	float $width длинна
                $size['HEIGHT']/10,	//	float $height высота
                $size['LENGTH']/10,	//	float $length ширина
                $size['WEIGHT']/100,	//	float $weight вес кг
                $item['PRICE'],	//	float $price стоимостьв рублях
                $item['QUANTITY'],	//	int $quantity количество товара
                $this->toUtf8($item['NAME']),	//	string $name Название вещи
                '-' // @todo
            );
        }
        return $productsDD;
    }

    /**
     * Меняет статус внутреннего заказа cms
     *
     * @param $cmsOrderID - id заказа
     * @param $status - статус заказа для обновления
     *
     * @return bool
     */
    public function setCmsOrderStatus($cmsOrderID, $status)
    {
        // TODO: Implement setCmsOrderStatus() method.
    }

    /**
     * Возвращает API ключ, вы можете получить его для Вашего приложения в личном кабинете
     * @return string
     */
    public function getApiKey()
    {
        return $this->config('API_KEY');
    }

    /**
     * Должен вернуть url до каталога с статикой
     * @return string
     */
    public function getStaticPath()
    {
        return '/bitrix/components/ddelivery/static/';
    }

    /**
     * URL до скрипта где вызывается DDelivery::render
     * @return string
     */
    public function getPhpScriptURL()
    {
        // Тоесть до этого файла
        return '/bitrix/components/ddelivery/static/ajax.php?'.http_build_query(array('formData'=>$this->formData), "", "&");
    }

    /**
     * Возвращает путь до файла базы данных, положите его в место не доступное по прямой ссылке
     * @return string
     */
    public function getPathByDB()
    {
        return '';
    }

    /**
     * Метод будет вызван когда пользователь закончит выбор способа доставки
     *
     * @param \DDelivery\Order\DDeliveryOrder $order
     * @return void
     */
    public function onFinishChange( $order)
    {
        $_SESSION['DIGITAL_DELIVERY']['ORDER_ID'] = $order->localId;
    }


    /**
     * Какой процент от стоимости страхуется
     * @return float
     */
    public function getDeclaredPercent()
    {
        return $this->config('DECLARED_PERCENT');
    }

    /**
     * Должен вернуть те компании которые показываются в курьерке
     * см. список компаний в DDeliveryUI::getCompanySubInfo()
     * @return int[]
     */
    public function filterCompanyPointCourier()
    {
        $result = array();
        foreach($this->config as $name => $data) {
            if(substr($name, 0, 8) == 'COMPANY_' && $data['VALUE'] == 'Y'){
                $result[] = (int)substr($name, 8);
            }
        }

        return $result;
    }

    /**
     * Должен вернуть те компании которые показываются в самовывозе
     * см. список компаний в DDeliveryUI::getCompanySubInfo()
     * @return int[]
     */
    public function filterCompanyPointSelf()
    {
        $result = array();
        foreach($this->config as $name => $data) {
            if(substr($name, 0, 8) == 'COMPANY_' && $data['VALUE'] == 'Y'){
                $result[] = (int)substr($name, 8);
            }
        }
        return $result;
    }

    /**
     * Возвращаем способ оплаты константой PluginFilters::PAYMENT_, предоплата или оплата на месте. Курьер
     * @return int
     */
    public function filterPointByPaymentTypeCourier()
    {
        return self::PAYMENT_POST_PAYMENT;
        // выбираем один из 3 вариантов(см документацию или комменты к констатам)
        return self::PAYMENT_POST_PAYMENT;
        return self::PAYMENT_PREPAYMENT;
        return self::PAYMENT_NOT_CARE;
        // TODO: Implement filterPointByPaymentTypeCourier() method.
    }

    /**
     * Возвращаем способ оплаты константой PluginFilters::PAYMENT_, предоплата или оплата на месте. Самовывоз
     * @return int
     */
    public function filterPointByPaymentTypeSelf()
    {
        return self::PAYMENT_POST_PAYMENT;
        // выбираем один из 3 вариантов(см документацию или комменты к констатам)
        return self::PAYMENT_POST_PAYMENT;
        return self::PAYMENT_PREPAYMENT;
        return self::PAYMENT_NOT_CARE;
        // TODO: Implement filterPointByPaymentTypeSelf() method.
    }

    /**
     * Если true, то не учитывает цену забора
     * @return bool
     */
    public function isPayPickup()
    {
        return $this->config('PAY_PICKUP') == 'Y';
    }

    /**
     * Метод возвращает настройки оплаты фильтра которые должны быть собраны из админки
     *
     * @return array
     */
    public function getIntervalsByPoint()
    {
        $return = array();
        for($i=1 ; $i<=3 ; $i++) {
            $return[] = array(
                'min' => $this->config('PRICE_IF_'.$i.'_MIN'),
                'max' => $this->config('PRICE_IF_'.$i.'_MAX'),
                'type' => $this->config('PRICE_IF_'.$i.'_TYPE'),
                'amount' => $this->config('PRICE_IF_'.$i.'_AOMUNT'),
            );
        }
        return $return;
    }

    /**
     * Тип округления
     * @return int
     */
    public function aroundPriceType()
    {
        switch($this->config('AROUND')) {
            case 2:
                return self::AROUND_FLOOR;
            case 3:
                return self::AROUND_CEIL;
            case 1:
            default:
                return self::AROUND_ROUND;
        }
    }

    public function aroundPrice($price)
    {
        $price = parent::aroundPrice($price);
        if($this->useTaxRate) {
            $DDConfig = CSaleDeliveryHandler::GetBySID('ddelivery')->Fetch();
            $taxRate = $DDConfig['TAX_RATE'];
            $price = round($price*(1+($taxRate/100)), 2);
            if($DDConfig['PROFILES']['all']['TAX_RATE']) {
                $taxRate = $DDConfig['PROFILES']['all']['TAX_RATE'];
                $price = round($price*(1+($taxRate/100)), 2);
            }
        }
        return $price;
    }


    /**
     * Шаг округления
     * @return float
     */
    public function aroundPriceStep()
    {
        $result = (float) $this->config('AROUND_STEP');
        if($result == 0)
            $result = 1;
        return $result;
    }

    /**
     * описание собственных служб доставки
     * @return string
     */
    public function getCustomPointsString()
    {
        return '';
    }

    public function getCourierRequiredFields()
    {
        return parent::getCourierRequiredFields() & ~ self::FIELD_EDIT_SECOND_NAME & ~ self::FIELD_REQUIRED_SECOND_NAME;
    }

    public function getSelfRequiredFields()
    {
        return parent::getSelfRequiredFields() & ~ self::FIELD_EDIT_SECOND_NAME & ~ self::FIELD_REQUIRED_SECOND_NAME;
    }

    public function isStatusToSendOrder($status)
    {
        return $status == $this->config('SEND_STATUS');
    }

    /**
     * Если вы знаете имя покупателя, сделайте чтобы оно вернулось в этом методе
     * @return string|null
     */
    public function getClientFirstName() {
        foreach($this->getOrderProps() as $prop){
            if($prop['IS_PAYER'] == 'Y' && !empty($this->formData['ORDER_PROP_'.$prop['ID']])) {
                return $this->formData['ORDER_PROP_'.$prop['ID']];
            }
        }
        return null;
    }

    /**
     * Если вы знаете фамилию покупателя, сделайте чтобы оно вернулось в этом методе
     * @return string|null
     */
    public function getClientLastName() {
        return null;
    }

    /**
     * Если вы знаете телефон покупателя, сделайте чтобы оно вернулось в этом методе. 11 символов, например 79211234567
     * @return string|null
     */
    public function getClientPhone() {
        foreach($this->getOrderProps() as $prop){
            if($prop['CODE'] == 'PHONE' && !empty($this->formData['ORDER_PROP_'.$prop['ID']])) {
                $phone = preg_replace('/[^0-9]/', '', $this->formData['ORDER_PROP_'.$prop['ID']]);
                if(strlen($phone) && $phone{0} == '8') {
                    $phone{0} = 7;
                }
                return $phone;
            }
        }
        return null;
    }

    /**
     * Верни массив Адрес, Дом, Корпус, Квартира. Если не можешь можно вернуть все в одном поле и настроить через get*RequiredFields
     * @return string[]
     */
    public function getClientAddress() {
        $return = array(array());
        foreach($this->getOrderProps() as $prop){
            if($prop['CODE'] == 'ADDRESS' && !empty($this->formData['ORDER_PROP_'.$prop['ID']])) {
                $return[0] = $this->formData['ORDER_PROP_'.$prop['ID']];
                break;
            }
        }

        return $return;
    }

    /**
     * Верните id города в системе DDelivery
     * @return int
     */
    public function getClientCity()
    {
        $return = false;
        foreach($this->getOrderProps() as $prop){
            if($prop['IS_LOCATION'] == 'Y' && !empty($this->formData['ORDER_PROP_'.$prop['ID'].'_val'])) {
                $return = strtolower($this->formData['ORDER_PROP_'.$prop['ID'].'_val']);
                $return = explode(',', trim(str_replace('Россия', '', $return), "\t\n\r\0\x0B ,"));
            }
        }
        if($return) {
            $cityRes = $this->ddeliveryUI->sdk->getAutoCompleteCity($return[0]);
            if($cityRes && !empty($cityRes->response)) {
                return $cityRes->response[0];
            }
        }
        // Если нет информации о городе, оставьте вызов родительского метода.
        return parent::getClientCity();
    }

    /**
     * Возвращает поддерживаемые магазином способы доставки
     * @return array
     */
    public function getSupportedType()
    {
        switch($this->config('SUPPORTED_TYPE')) {
            case 1:
                return array(
                    \DDelivery\Sdk\DDeliverySDK::TYPE_SELF
                );
            case 2:
                return array(
                    \DDelivery\Sdk\DDeliverySDK::TYPE_COURIER
                );
        }
        return array(
            \DDelivery\Sdk\DDeliverySDK::TYPE_COURIER,
            \DDelivery\Sdk\DDeliverySDK::TYPE_SELF
        );
    }

    private function getPaymentVariants($order)
    {
        CModule::IncludeModule('sale');
        $dbResultList = CSalePaySystem::GetList( array(), array(), false, false, array("ID", "NAME") );
        $result = array();
        while($paymentSystem = $dbResultList->Fetch()) {
            $result[] = $paymentSystem['ID'];
        }
        return $result;
    }

    public function getCourierPaymentVariants($order)
    {
        return $this->getPaymentVariants($order);
    }


    public function getSelfPaymentVariants($order)
    {
        return $this->getPaymentVariants($order);
    }


}