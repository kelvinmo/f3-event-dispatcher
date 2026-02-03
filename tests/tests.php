<?php

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


?>