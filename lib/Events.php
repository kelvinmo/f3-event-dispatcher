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

namespace F3\EventDispatcher;

use \Throwable;
use F3\Prefab;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Event dispatcher
 */
class Events implements EventDispatcherInterface {
    use Prefab;

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