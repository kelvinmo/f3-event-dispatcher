<?php

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;

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

        restore_error_handler();
        restore_exception_handler();
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