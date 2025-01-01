<?php
/*
 * Fat-Free event dispatcher
 *
 * Copyright (C) Kelvin Mo 2021-2025
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 * 
 */

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * A generic event that provides the name of the event through the
 * {@link getEventName()} method.
 */
interface GenericEventInterface {
    /**
     * @return string the name of the event
     */
    public function getEventName();
}

/**
 * Listener provider
 */
class Listeners extends Prefab implements ListenerProviderInterface {
    /** @var array<mixed> $listeners */
    protected $listeners = [];

    /**
     * Adds a listener
     * 
     * @param string $event the name of the event (usually the name of the
     * event class)
     * @param string|callable $listener the listener as a F3 callable string
     * or PHP callable
     * @param int $priority the priority
     * @return void
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
     * Maps a listener class.
     * 
     * This method uses reflection on listener class to add event listeners.
     * A method will be mapped if:
     * 
     * 1. It is a `public` method (whether or not it is also `static`)
     * 2. The name of the method starts with `on`
     * 3. The method takes exactly one parameter, and the parameter is type-hinted
     *    with an event class.
     * 
     * The name of the event the method will listen to depends on the name of
     * the method.  If the name of the method is the same as the short name
     * of the type hint of the parameter, then the name of the event is the
     * fully qualified name of the parameter type.  Otherwise, the name of the
     * event is the method name converted to snake case.
     * 
     * For example, `public function onFooEvent(\Some\Namespace\FooEvent $event)`
     * will map to `Some\Namespace\FooEvent`, whereas
     * `public function onSomeOtherEvent(\Some\Namespace\FooEvent $event)` will
     * map to `some_other_event`.
     * 
     * @param object|class-string $listener_class the listener class (either the class
     * name or the instantiated object)
     * @param int $priority the priority
     * @return void
     */
    public function map($listener_class, $priority = 0) {
        $reflection = new ReflectionClass($listener_class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $name = $method->getName();
            $params = $method->getParameters();

            // Remove methods that do not have exactly 1 hinted parameter
            // or do not start with 'on' followed by an uppercase character
            if (count($params) != 1) continue;
            if (!$params[0]->hasType() || !($params[0]->getType() instanceof ReflectionNamedType)) continue;
            if ((strlen($name) < 3) || (substr($name, 0, 2) != 'on') || (strtolower($name[2]) == $name[2])) continue;

            $method_base_name = substr($name, 2);
            /** @var class-string $param_type_name */
            $param_type_name = $params[0]->getType()->getName(); // This is a namespaced name
            $param_short_name = (new ReflectionClass($param_type_name))->getShortName();

            // If the base name of the method (the part after 'on') matches the
            // short name of the hinted parameter, then we use the full name
            // of the hinted parameter as the event name. Otherwise we convert
            // the base name to snake case and use that as the event name
            if ($method_base_name == $param_short_name) {
                $event_name = $param_type_name;
            } else {
                $event_name = strtolower(preg_replace('/(?!^)\p{Lu}/u','_\0', $method_base_name));
            }

            // Get name of listener
            if (is_object($listener_class)) {
                $callable = [ $listener_class, $name ];
            } elseif ($method->isStatic()) {
                $callable = "$listener_class::$name";
            } else {
                $callable = "$listener_class->$name";
            }

            /** @var callable $callable */
            $this->on($event_name, $callable, $priority);
        }
    }

    /**
     * {@inheritdoc}
     * 
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable {
        $f3 = \Base::instance();
        $event_names = [];
        $all_listeners = [];

        if ($event instanceof GenericEventInterface) {
            $event_names = [ $event->getEventName() ];
        } else {
            $event_names = [ get_class($event) ];

            $refl = new \ReflectionClass($event);
            while ($parent = $refl->getParentClass()) {
                $event_names[] = $parent->getName();
                $refl = $parent;
            }
        }

        foreach ($event_names as $event_name) {
            if (!isset($this->listeners[$event_name])) continue;
            foreach ($this->listeners[$event_name] as $priority => $listeners) {
                if (isset($all_listeners[$priority])) {
                    $all_listeners[$priority] = array_merge($all_listeners[$priority], $listeners);
                } else {
                    $all_listeners[$priority] = $listeners;
                }
            }
        }

        if (count($all_listeners) == 0) return [];
        krsort($all_listeners);

        foreach ($all_listeners as $priority => $listeners) {
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
     * @param ListenerProviderInterface $provider the listener provider
     */
    public function __construct(ListenerProviderInterface $provider = null) {
        if ($provider == null) {
            $this->provider = Listeners::instance();
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

            /** @var StoppableEventInterface $event */
            if ($is_stoppable && $event->isPropagationStopped()) {
                return $event;
            }
        }
        return $event;
    }
}
?>