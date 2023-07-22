<?php

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/* -------------------------------------------------------------------------
 * Mock event classes
 * ------------------------------------------------------------------------- */
class TestEvent implements StoppableEventInterface {
    protected $stoppable = false;
    protected $stopped = false;
    protected $results = [];

    public function __construct($stoppable = false) {
        $this->stoppable = $stoppable;
    }

    public function addResult($result) {
        $this->results[] = $result;
    }

    public function getResults() {
        return $this->results;
    }

    public function isPropagationStopped(): bool {
        return $this->stopped;
    }

    public function stopPropagation() {
        if ($this->stoppable) $this->stopped = true;
        return $this;
    }
}

class TestGenericEvent extends TestEvent implements GenericEventInterface {
    protected $eventName;

    public function __construct($eventName, $stoppable = false) {
        parent::__construct($stoppable);
        $this->eventName = $eventName;
    }

    public function getEventName() {
        return $this->eventName;
    }
}

class FooEvent extends TestEvent {}
class FooSubclassEvent extends FooEvent {}
class BarEvent extends TestEvent {}
class BazEvent extends TestEvent {}

/* -------------------------------------------------------------------------
 * Mock F3 callable object
 * ------------------------------------------------------------------------- */
class TestF3 extends Prefab {
    static function staticRoute($event) {
        $event->addResult('static');
    }

    public function objectRoute($event) {
        $event->addResult('object');
    }
}

/* -------------------------------------------------------------------------
 * Mock map listener
 * ------------------------------------------------------------------------- */
class TestListener {
    // Should register as FooEvent
    public function onFooEvent(FooEvent $event) {
        $event->addResult('foo');
    }

    // Should register as custom_event
    public function onCustomEvent(TestGenericEvent $event) {
        $event->addResult('custom');
    }

    // Should not register (no hint)
    public function onBarEvent($event) {
        $event->addResult('should not happen');
    }

    // Should not register (incorrect method name)
    public function BazEvent(BazEvent $event) {
        $event->addResult('should not happen');
    }

    // Should not register (too many parameters)
    public function onBazEvent(BazEvent $event, $invalid_param) {
        $event->addResult('should not happen');
    }
}

/* -------------------------------------------------------------------------
 * Listeners
 * ------------------------------------------------------------------------- */
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

/* -------------------------------------------------------------------------
 * Events
 * ------------------------------------------------------------------------- */
class EventsTest extends TestCase {
    function testListeners() {
        $provider = new class implements ListenerProviderInterface {
            public function getListenersForEvent(object $event): iterable {
                yield function($event) { $event->addResult('1'); };
                yield function($event) { $event->addResult('2'); };
                yield function($event) { $event->addResult('3'); };
                yield function($event) { $event->addResult('4'); };
                yield function($event) { $event->addResult('5'); };
            }
        };

        $dispatcher = new Events($provider);
        $event = new TestEvent(false);
        $dispatcher->dispatch($event);

        $this->assertEquals('12345', implode($event->getResults()));
    }

    function testStoppableListeners() {
        $provider = new class implements ListenerProviderInterface {
            public function getListenersForEvent(object $event): iterable {
                yield function($event) { $event->addResult('1'); };
                yield function($event) { $event->addResult('2'); };
                yield function($event) { $event->addResult('3'); $event->stopPropagation(); };
                yield function($event) { $event->addResult('4'); };
                yield function($event) { $event->addResult('5'); };
            }
        };

        $dispatcher = new Events($provider);
        $event = new TestEvent(true);
        $dispatcher->dispatch($event);

        $this->assertEquals('123', implode($event->getResults()));
    }

    function testAlreadyStoppedEvent() {
        $provider = new class implements ListenerProviderInterface {
            public function getListenersForEvent(object $event): iterable {
                yield function($event) { $event->addResult('1'); };
                yield function($event) { $event->addResult('2'); };
                yield function($event) { $event->addResult('3'); };
                yield function($event) { $event->addResult('4'); };
                yield function($event) { $event->addResult('5'); };
            }
        };

        $dispatcher = new Events($provider);
        $event = new TestEvent(true);
        $event->stopPropagation();
        $dispatcher->dispatch($event);

        $this->assertEquals('', implode($event->getResults()));
    }

    function testException() {
        $provider = new class implements ListenerProviderInterface {
            public function getListenersForEvent(object $event): iterable {
                yield function($event) { $event->addResult('1'); };
                yield function($event) { $event->addResult('2'); };
                yield function($event) { throw new RuntimeException('Exception here'); };
                yield function($event) { $event->addResult('4'); };
                yield function($event) { $event->addResult('5'); };
            }
        };

        $dispatcher = new Events($provider);
        $event = new TestEvent(false);

        try {
            $dispatcher->dispatch($event);
            $this->fail('Exception not caught');
        } catch (RuntimeException $e) {
            $this->assertEquals('Exception here', $e->getMessage());
        }

        $this->assertEquals('12', implode($event->getResults()));
    }
}

?>