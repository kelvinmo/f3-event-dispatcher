<?php

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

class Listeners extends Prefab implements ListenerProviderInterface {
    protected $listeners = [];

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

    public function getListenersForEvent(object $event): iterable {
        $f3 = \Base::instance();
        $event_name = get_class($event);

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

class Events extends Prefab implements EventDispatcherInterface {
    /** @var ListenerProviderInterface */
    protected $provider;

    public function __construct($provider = null) {
        if ($provider == null) {
            $this->provider = new Listeners();
        } else {
            $this->provider = $provider;
        }
    }

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