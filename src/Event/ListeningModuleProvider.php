<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Event;

use Inpsyde\Modularity\Module\ListeningModule;
use Psr\EventDispatcher\ListenerProviderInterface;

class ListeningModuleProvider implements ListenerProviderInterface
{
    /**
     * @var list<array{callable, \ReflectionType|null}>
     */
    private $listeners = [];

    /**
     * @param ListeningModule $module
     * @return void
     */
    public function addModule(ListeningModule $module): void
    {
        $callbacks = $module->listeners();
        foreach ($callbacks as $callback) {
            if (!is_callable($callback)) {
                continue;
            }
            switch (true) {
                case is_array($callback):
                    $ref = new \ReflectionMethod($callback[0], $callback[1]);
                    break;
                case (is_object($callback) && !($callback instanceof \Closure)):
                    $ref = new \ReflectionMethod($callback, '__invoke');
                    break;
                case (is_string($callback) && (substr_count($callback, '::') === 1)):
                    $ref = new \ReflectionMethod($callback);
                    break;
                default:
                    /** @var callable-string|\Closure $callback */
                    $ref = new \ReflectionFunction($callback);
                    break;
            }

            if ($ref->getNumberOfRequiredParameters() > 1) {
                continue;
            }

            $params = $ref->getParameters();
            $this->listeners[] = [$callback, $params ? reset($params)->getType() : null];
        }
    }

    /**
     * @param object $event
     * @return list<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $matching = [];
        foreach ($this->listeners as [$callback, $type]) {
            if (!$type || $this->matchReflectionType($event, $type)) {
                $matching[] = $callback;
            }
        }

        return $matching;
    }

    /**
     * @param mixed $value
     * @param \ReflectionType $type
     * @return bool
     */
    private function matchReflectionType($value, \ReflectionType $type): bool
    {
        try {
            $typeString = @(string)$type;
            (PHP_MAJOR_VERSION < 8 && $type->allowsNull()) and $typeString = "?{$typeString}";
            /** @psalm-suppress UnusedFunctionCall */
            eval(sprintf('$checker = function(%s $val) {};', $typeString));
            /** @var callable $checker */
            $checker($value);

            return true;
        } catch (\TypeError $error) {
            return false;
        }
    }
}
