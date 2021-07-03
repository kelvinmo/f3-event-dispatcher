<?php

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * A generic event that provides the name of the event through the
 * {@link getEventName()} method.
 */
interface GenericEvent {
    /**
     * @return string the name of the event
     */
    public function getEventName();
}

/**
 * Listener provider
 */
class Listeners extends Prefab implements ListenerProviderInterface {
    protected $listeners = [];

    /**
     * Adds a listener
     * 
     * @param $event string the name of the event (usually the name of the
     * event class)
     * @param $listener string|callable the listener as a F3 callable string
     * or PHP callable
     * @param $priority int the priority
     */
    public function on($event, $listener, $priority = 0) {
        if (!is_string($event)) {
            throw new InvalidArgumentException('$event not a string');
        }
        if (!(is_string($listener) || is_callable($listener))) {
            throw new InvalidArgumentException('$listener not a callable');
        }

        if (isset($this->listeners[$event])) {
            $this->listeners[$event][$priority][] = $listener;
        } else {
            $this->listeners[$event] = [ $priority => [$listener] ];
        }

        krsort($this->listeners[$event]);
    }

    /**
     * {@inheritdoc}
     */
    public function getListenersForEvent(object $event): iterable {
        $f3 = \Base::instance();
        if ($event instanceof GenericEvent) {
            $event_name = $event->getEventName();
        } else {
            $event_name = get_class($event);
        }

        if (!isset($this->listeners[$event_name])) return [];

        foreach ($this->listeners[$event_name] as $priority => $listeners) {
            foreach ($listeners as $listener) {
                if (is_string($listener)) {
                    $listener = $f3->grab($listener);
                }

                yield $listener;
            }
        }
    }
}

/**
 * Event dispatcher
 */
class Events extends Prefab implements EventDispatcherInterface {
    /** @var ListenerProviderInterface */
    protected $provider;

    /**
     * Creates the event dispatcher
     * 
     * @param $provider ListenerProviderInterface the listener provider
     */
    public function __construct(ListenerProviderInterface $provider = null) {
        if ($provider == null) {
            $this->provider = new Listeners();
        } else {
            $this->provider = $provider;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch($event) {
        $is_stoppable = $event instanceof StoppableEventInterface;

        if ($is_stoppable && $event->isPropagationStopped()) {
            return $event;
        }

        foreach ($this->provider->getListenersForEvent($event) as $listener) {
            try {
                $listener($event);
            } catch (Throwable $e) {
                throw $e;
            }

            if ($is_stoppable && $event->isPropagationStopped()) {
                return $event;
            }
        }
        return $event;
    }
}
?>