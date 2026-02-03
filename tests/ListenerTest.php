<?php

use PHPUnit\Framework\TestCase;

class ListenerTest extends TestCase {
    function testMultipleEvents() {
        $listeners = new Listeners();

        $listeners->on(FooEvent::class, function($event) { $event->addResult('foo'); });
        $listeners->on(BarEvent::class, function($event) { $event->addResult('bar'); });

        $event = new FooEvent();
        foreach ($listeners->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
        $this->assertEquals('foo', implode($event->getResults()));

        
    }

    function testSubclassEvent() {
        $listeners = new Listeners();

        $listeners->on(FooEvent::class, function($event) { $event->addResult('foo'); });

        $event = new FooSubclassEvent();
        foreach ($listeners->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
        $this->assertEquals('foo', implode($event->getResults()));
    }

    function testPriorities() {
        $listeners = new Listeners();

        $listeners->on(FooEvent::class, function($event) { $event->addResult('1'); }, 1);
        $listeners->on(FooEvent::class, function($event) { $event->addResult('2'); }, 2);

        $event = new FooEvent();
        foreach ($listeners->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
        $this->assertEquals('21', implode($event->getResults()));
    }

    function testGenericEvent() {
        $listeners = new Listeners();

        $listeners->on('foo', function($event) { $event->addResult('foo'); }, 1);
        $listeners->on('bar', function($event) { $event->addResult('bar'); }, 2);

        $event = new TestGenericEvent('foo');
        foreach ($listeners->getListenersForEvent($event) as $listener) {
            $listener($event);
        }
        $this->assertEquals('foo', implode($event->getResults()));
    }

    function testF3Callable() {
        $listeners = new Listeners();

        $listeners->on(FooEvent::class, 'TestF3::staticRoute');
        $listeners->on(BarEvent::class, 'TestF3->objectRoute');

        $foo = new FooEvent();
        foreach ($listeners->getListenersForEvent($foo) as $listener) {
            $listener($foo);
        }
        $bar = new BarEvent();
        foreach ($listeners->getListenersForEvent($bar) as $listener) {
            $listener($bar);
        }
        $this->assertEquals('static', implode($foo->getResults()));
        $this->assertEquals('object', implode($bar->getResults()));
    }
}


?>