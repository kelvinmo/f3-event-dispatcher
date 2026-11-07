<?php

use PHPUnit\Framework\TestCase;

/* -------------------------------------------------------------------------
 * Mapping
 * ------------------------------------------------------------------------- */
class MapTest extends TestCase {
    function createListeners() {
        $listeners = new Listeners();
        $listeners->map(TestListener::class);
        return $listeners;
    }

    function testHintedEvent() {
        $listeners = $this->createListeners();
        $event = new FooEvent();

        foreach ($listeners->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
        $this->assertEquals('foo', implode($event->getResults()));
    }

    function testCustomEvent() {
        $listeners = $this->createListeners();
        $event = new TestGenericEvent('custom_event');

        foreach ($listeners->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
        $this->assertEquals('custom', implode($event->getResults()));
    }

    function testInvalidEvents() {
        $listeners = $this->createListeners();

        $event = new BarEvent();
        foreach ($listeners->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
        $this->assertEquals('', implode($event->getResults()));

        $event = new BazEvent();
        foreach ($listeners->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
        $this->assertEquals('', implode($event->getResults()));
    }
}


?>