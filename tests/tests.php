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

class FooEvent extends TestEvent {}
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