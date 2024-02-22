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

        $dispatcher->addListener(\Commerce::EVENT_ORDER_BEFORE_LOAD, [$this, 'setDynamicTaxMode']);

        // Add composer libraries to the about section
        $dispatcher->addListener(\Commerce::EVENT_DASHBOARD_LOAD_ABOUT, [$this, 'addLibrariesToAbout']);
    }

    public function setDynamicTaxMode(Order $event)
    {
        $sessionKey = 'commerce_dynamictaxmode';
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

        /**
         * ISSUE: If this is a session cart order, then this doesn't get saved!
         */
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

    public function addLibrariesToAbout(PageEvent $event): void
    {
        $lockFile = dirname(__DIR__, 2) . '/composer.lock';
        if (file_exists($lockFile)) {
            $section = new SimpleSection($this->commerce);
            $section->addWidget(new ComposerPackages($this->commerce, [
                'lockFile' => $lockFile,
                'heading' => $this->adapter->lexicon('commerce.about.open_source_libraries') . ' - ' . $this->adapter->lexicon('commerce_dynamictaxmode'),
                'introduction' => '',
            ]));

            $about = $event->getPage();
            $about->addSection($section);
        }
    }
}
