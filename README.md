# Event Dispatcher for the Fat-Free Framework

This is a simple [PSR-14] compliant event dispatcher and listener provider
library for the [Fat-Free Framework][F3].

## Requirements

- PHP 7.2 or later
- Fat-Free Framework 3.5 or later

## Usage

### Listener Provider

The listener provider is implemented by the `\Listeners` class.  `Listeners`
is a subclass of Fat-Free's `\Prefab` class.

```php
$listeners = \Listeners::instance();
```

To add a listener, call the `on()` method.  The name of the event is
specified in the first parameter and the listener in the second
parameter.

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

A generic event implements `GenericEventInterface` and provides the
name of the event through the `getEventName()` method.

```php
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

### Event Dispatcher

The event dispatcher is implemented by the `\Events` class.  `Events`
is a subclass of Fat-Free's `\Prefab` class.

```php
$dispatcher = \Events::instance();
```

By default, `Events` uses the `Listeners` listener provider included in this
library.  To use a different listener provider, pass the provider as
an argument in the constructor.

```php
use League\Event\PrioritizedListenerRegistry;

$listenerProvider = new PrioritizedListenerRegistry();
$dispatcher = \Events::instance($listenerProvider);
```

To use the event dispatcher, call the standard PSR-14 `dispatch()` method:

```php
$dispatcher->dispatch(new FooEvent());
```

## Licence

GPL 3

[PSR-14]: https://www.php-fig.org/psr/psr-14/
[F3]: https://fatfreeframework.com/
[`call()`]: https://fatfreeframework.com/3.7/base#call
