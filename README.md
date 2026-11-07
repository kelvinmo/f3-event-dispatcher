# Event Dispatcher for the Fat-Free Framework

This is a simple [PSR-14] compliant event dispatcher and listener provider
library for the [Fat-Free Framework][F3].

[![Latest Stable Version](https://poser.pugx.org/kelvinmo/f3-event-dispatcher/v/stable)](https://packagist.org/packages/kelvinmo/f3-event-dispatcher)
[![build](https://github.com/kelvinmo/f3-event-dispatcher/workflows/CI/badge.svg)](https://github.com/kelvinmo/f3-event-dispatcher/actions?query=workflow%3ACI)

## Requirements

- PHP 8.2 or later
- Fat-Free Framework 4.0 or later

## Installation

You can install via [Composer](http://getcomposer.org/).

```sh
composer require kelvinmo/f3-event-dispatcher
```

## Usage

### Listener Provider

The listener provider is implemented by the `F3\EventDispatcher\Listeners` class.
`Listeners` uses Fat-Free's `F3\Prefab` trait.

```php
$listeners = F3\EventDispatcher\Listeners::instance();
```

To add a listener, call the `on()` method.  The name of the event is
specified in the first parameter and the listener in the second
parameter.

As required by [PSR-14], if the name of the event is the name of a
class, then the listener will also be triggered for all subclasses
of that event class.

The listener can be a PHP callable, or a string that can be resolved
by Fat-Free's [`call()`] method

```php
// Object method
$listeners->on(FooEvent::class, 'Bar->listener');

// Static method
$listeners->on(FooEvent::class, 'Bar::listener');

// PHP callable
$listeners->on(FooEvent::class, [ $object, 'listener' ]);

// Closure
$listeners->on(FooEvent::class, function($event) {
    // listener
});
```

The `on()` method also takes a third, optional parameter, specifying the
priority which the listeners should be called.  Listeners are called
from the highest priority to the lowest.

```php
// Baz->listener is called first, then Bar->listener
$listeners->on(FooEvent::class, 'Bar->listener', 10);
$listeners->on(FooEvent::class, 'Baz->listener', 20);
```

You can use `Listeners` with any [PSR-14]-compliant event dispatcher.

#### Generic Events

Sometimes it is too cumbersome to create a new event class for every
single event.  You can use *generic events* to group a set of related
events into a single class.

A generic event implements `F3\EventDispatcher\GenericEventInterface`
and provides the name of the event through the `getEventName()` method.

```php
use F3\EventDispatcher\GenericEventInterface;

class BarEvent implements GenericEventInterface {
    private $eventName;

    public function __construct($eventName) {
        $this->eventName = $eventName;
    }

    public function getEventName() {
        return $this->eventName;
    }
}

$listeners->on('foo', 'Baz->listener');
$event = new BarEvent('foo');
```

#### Adding Listeners via Reflection

You can also add listeners using reflection by the `map()` method.  This
method takes a name or an instantiated object of a class.  A listener
will be added based on a method of this class if:

1. It is a `public` method (whether or not it is also `static`)
2. The name of the method starts with `on`
3. The method takes exactly one parameter, and the parameter is type-hinted
   with an event class.

The name of the event the method will listen to depends on the name of
the method.  If the name of the method is the same as the short name
of the type hint of the parameter, then the name of the event is the
fully qualified name of the parameter type.  Otherwise, the name of the
event is the method name converted to snake case, in which case
the event type must be a generic event (i.e. implements
`GenericEventInterface`).

```php
class TestListener {
    // Will be mapped to FooEvent (with namespace)
    public function onFooEvent(FooEvent $event) {
    }

    // Will be mapped to custom_event
    // (BarEvent must implement GenericEventInterface)
    public function onCustomEvent(BarEvent $event) {
    }
}

$listeners = F3\EventDispatcher\Listeners::instance();
$listeners->map(TestListener::class);
```

### Event Dispatcher

The event dispatcher is implemented by the `F3\EventDispatcher\Events` class.
`Events` uses Fat-Free's `\Prefab` trait.

```php
$dispatcher = F3\EventDispatcher\Events::instance();
```

By default, `Events` uses the `Listeners` listener provider included in this
library.  To use a different listener provider, pass the provider as
an argument in the constructor.

```php
use League\Event\PrioritizedListenerRegistry;

$listenerProvider = new PrioritizedListenerRegistry();
$dispatcher = F3\EventDispatcher\Events::instance($listenerProvider);
```

To use the event dispatcher, call the standard PSR-14 `dispatch()` method:

```php
$dispatcher->dispatch(new FooEvent());
```

## Licence

GPL 3 or later

[PSR-14]: https://www.php-fig.org/psr/psr-14/
[F3]: https://fatfreeframework.com/
[`call()`]: https://fatfreeframework.com/4.0/base#call
