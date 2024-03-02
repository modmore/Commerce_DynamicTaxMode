<?php

use modmore\Commerce\Admin\Widgets\Form\PricingField;
use modmore\Commerce\Pricing\Exceptions\InvalidPriceTypeException;
use modmore\Commerce\Pricing\Interfaces\ItemPricingInterface;
use modmore\Commerce\Pricing\ProductPricing;

class DynamicTaxModeProduct extends comProduct
{
    protected ?comModule $module = null;
    protected string $sessionKey = '';

    public function __construct(xPDO &$xpdo)
    {
        parent::__construct($xpdo);

        // Get session key
        if (empty($this->sessionKey)) {
            $this->sessionKey = \modmore\Commerce_DynamicTaxMode\Module::DEFAULT_SESSION_KEY;
            $this->module = $this->adapter->getObject(comModule::class, [
                'class_name' => 'modmore\Commerce_DynamicTaxMode\Module',
            ]);
            if ($this->module && !empty($this->module->getProperty('session_key'))) {
                $this->sessionKey = $this->module->getProperty('session_key');
            }
        }
    }

    /**
     * @param comCurrency $currency
     * @return ItemPricingInterface
     */
    public function getBusinessPricing(comCurrency $currency): ?ItemPricingInterface
    {
        $instance = $this->getBusinessPricingInstance($currency);
        if (!$instance instanceof ItemPricingInterface) {
            $legacyPrice = $this->getPrice();
            $newPrice = new \modmore\Commerce\Pricing\Price($currency, $legacyPrice->getPriceForCurrency($currency));
            $instance = new ProductPricing($currency, $newPrice);
        }

        return $instance;
    }

    /**
     * @param ItemPricingInterface $pricing
     * @return bool
     */
    public function saveBusinessPricing(ItemPricingInterface $pricing): bool
    {
        $data = $pricing->serialize();
        $currency = $pricing->getCurrency();
        $currencyCode = $currency->get('alpha_code');

        $rawPricing = $this->getRawBusinessPricing();
        $rawPricing[$currencyCode] = $data;
        $this->setRawBusinessPricing($rawPricing);
        return $this->save();
    }

    /**
     * @param array $rawValue
     * @param comCurrency $currency
     * @return ProductPricing|null
     */
    protected function loadPricingInstance(array $rawValue, comCurrency $currency): ?ProductPricing
    {
        $instance = null;
        $currencyCode = $currency->get('alpha_code');
        if (array_key_exists($currencyCode, $rawValue)) {
            try {
                $instance = ProductPricing::unserialize(
                    $currency,
                    is_array($rawValue[$currencyCode])
                        ? $rawValue[$currencyCode]
                        : (array)json_decode($rawValue[$currencyCode], true)
                );
            } catch (InvalidPriceTypeException $e) {
                $this->adapter->log(
                    1,
                    'Could not create Pricing instance for product '
                    . $this->get('id') . ' with currency ' . $currencyCode . ', received ' . get_class($e)
                    . ' with message: ' . $e->getMessage() . ' // ' . $e->getTraceAsString()
                );
            }
        }

        return $instance;
    }

    /**
     * Retrieves either normal pricing instance, or business pricing instance, based on is_inclusive or session value
     * @param comCurrency $currency
     * @return ProductPricing|null
     */
    protected function getPricingInstance(comCurrency $currency): ?ProductPricing
    {
        // 1. Check for comOrder::is_inclusive
        $order = null;
        if (isset($_SESSION[comOrder::SESSION_NAME]) && $_SESSION[comOrder::SESSION_NAME] > 0) {
            // Check the session for an ID
            $orderId = (int)$_SESSION[comOrder::SESSION_NAME];
            $order = $this->adapter->getObject(comOrder::class, [
                'id' => $orderId,
                'test' => $this->commerce->isTestMode(),
            ]);
        } elseif (isset($_COOKIE[comOrder::COOKIE_NAME]) && !empty($_COOKIE[comOrder::COOKIE_NAME])) {
            // Check the cookies for a more complex secret
            $orderSecret = (string)$_COOKIE[comOrder::COOKIE_NAME];
            $order = $this->adapter->getObject(comOrder::class, [
                'secret' => $orderSecret,
                'test' => $this->commerce->isTestMode(),
            ]);
        }
        if ($order instanceof comOrder) {
            $taxMode = $order->get('is_inclusive') ? 'inclusive' : 'exclusive';
        }

        // 2. As a backup, check for dynamic tax mode inclusive/exclusive session value
        if (empty($taxMode)) {
            $taxMode = $_SESSION[$this->sessionKey] ?? '';
        }

        $raw = $taxMode === 'inclusive' ? $this->getRawBusinessPricing() : $this->getRawPricing();

        return $this->loadPricingInstance($raw, $currency);
    }

    /**
     * Retrieve normal pricing instance
     * @param comCurrency $currency
     * @return ProductPricing|null
     */
    protected function getNormalPricingInstance(comCurrency $currency): ?ProductPricing
    {
        return $this->checkPricingInstance(
            $currency,
            $this->loadPricingInstance($this->getRawPricing(), $currency),
        );
    }

    /**
     * Retrieve business pricing instance
     * @param comCurrency $currency
     * @return ProductPricing|null
     */
    protected function getBusinessPricingInstance(comCurrency $currency): ?ProductPricing
    {
        return $this->checkPricingInstance(
            $currency,
            $this->loadPricingInstance($this->getRawBusinessPricing(), $currency),
        );
    }

    /**
     * Make sure we have a pricing instance
     * @param comCurrency $currency
     * @param ProductPricing|null $instance
     * @return ProductPricing
     */
    protected function checkPricingInstance(comCurrency $currency, ProductPricing $instance = null): ProductPricing
    {
        if (!$instance instanceof ItemPricingInterface) {
            $legacyPrice = $this->getPrice();
            $newPrice = new \modmore\Commerce\Pricing\Price($currency, $legacyPrice->getPriceForCurrency($currency));
            $instance = new ProductPricing($currency, $newPrice);
        }

        return $instance;
    }

    /**
     * @return array
     */
    public function getRawBusinessPricing(): array
    {
        return $this->getProperty('pricing_business') ?? [];
    }

    /**
     * Stores the raw per-currency business pricing information.
     * @param array $pricing
     */
    protected function setRawBusinessPricing(array $pricing)
    {
        $this->setProperty('pricing_business', json_encode($pricing));
    }

    /**
     * @return array
     */
    public function getModelFields(): array
    {
        $fields = parent::getModelFields();

        $enabledCurrencies = $this->commerce->getEnabledCurrencies();
        /** @var ItemPricingInterface $pricing */
        $normalPricing = [];
        $businessPricing = [];
        foreach ($enabledCurrencies as $currencyCode => $currency) {
            $normalPricing[$currencyCode] = $this->getNormalPricingInstance($currency);
            $businessPricing[$currencyCode] = $this->getBusinessPricingInstance($currency);
        }
        foreach ($fields as $k => $field) {
            if ($field->getName() === 'pricing') {
                // The reason we're overriding the normal pricing field as well is because getPricingInstance()
                // for this product is dynamic. Here, we ALWAYS want the normal price.
                $normalPricingField = new PricingField($this->commerce, [
                    'name' => 'pricing',
                    'label' => $this->adapter->lexicon('commerce.price'),
                    'pricing' => $normalPricing,
                ]);
                $businessPricingField = new PricingField($this->commerce, [
                    'name' => 'properties[pricing_business]',
                    'label' => $this->adapter->lexicon('commerce_dynamictaxmode.business_price'),
                    'pricing' => $businessPricing,
                ]);

                // Overwrite the existing pricing field, replacing it with the above two.
                array_splice($fields, $k, 1, [$normalPricingField, $businessPricingField]);
                break;
            }
        }

        return $fields;
    }
}
