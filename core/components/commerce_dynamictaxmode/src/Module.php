<?php

namespace modmore\Commerce_DynamicTaxMode;

use modmore\Commerce\Admin\Configuration\About\ComposerPackages;
use modmore\Commerce\Admin\Sections\SimpleSection;
use modmore\Commerce\Admin\Widgets\Form\TextField;
use modmore\Commerce\Events\Admin\PageEvent;
use modmore\Commerce\Events\Order;
use modmore\Commerce\Modules\BaseModule;
use modmore\Commerce\Dispatcher\EventDispatcher;

require_once dirname(__DIR__) . '/vendor/autoload.php';

class Module extends BaseModule
{
    public const DEFAULT_SESSION_KEY = 'commerce_dynamictaxmode';

    public function getName(): string
    {
        $this->adapter->loadLexicon('commerce_dynamictaxmode:default');
        return $this->adapter->lexicon('commerce_dynamictaxmode');
    }

    public function getAuthor(): string
    {
        return 'modmore';
    }

    public function getDescription(): string
    {
        return $this->adapter->lexicon('commerce_dynamictaxmode.description');
    }

    public function initialize(EventDispatcher $dispatcher): void
    {
        // Load our lexicon
        $this->adapter->loadLexicon('commerce_dynamictaxmode:default');

        $root = dirname(__DIR__);
        $path = $root . '/model/';
        $this->adapter->loadPackage('commerce_dynamictaxmode', $path);

        $dispatcher->addListener(\Commerce::EVENT_ORDER_BEFORE_LOAD, [$this, 'setDynamicTaxMode']);
    }

    public function setDynamicTaxMode(Order $event): void
    {
        $sessionKey = self::DEFAULT_SESSION_KEY;
        $module = $this->adapter->getObject(\comModule::class, [
            'class_name' => 'modmore\Commerce_DynamicTaxMode\Module',
        ]);
        if ($module && !empty($module->getProperty('session_key'))) {
            $sessionKey = $module->getProperty('session_key');
        }

        // Check if session key is set
        $sessionValue = $_SESSION[$sessionKey] ?? '';
        if (empty($sessionValue)) {
            return;
        }

        $order = $event->getOrder();
        $order->set('is_inclusive', $sessionValue === 'inclusive');
        $order->save();
    }

    public function getModuleConfiguration(\comModule $module): array
    {
        $fields = [];
        $fields[] = new TextField($this->commerce, [
            'name' => 'properties[session_key]',
            'label' => $this->adapter->lexicon('commerce_dynamictaxmode.session_key'),
            'description' => $this->adapter->lexicon('commerce_dynamictaxmode.session_key.description'),
            'value' => $module->getProperty('session_key', 'commerce_dynamictaxmode'),
        ]);
        return $fields;
    }
}
