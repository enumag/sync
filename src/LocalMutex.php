<?php

namespace Amp\Sync;

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;

final class LocalMutex implements Mutex
{
    /** @var bool */
    private $locked = false;

    /** @var \Amp\Deferred[] */
    private $queue = [];

    /** {@inheritdoc} */
    public function acquire(): Promise
    {
        if (!$this->locked) {
            $this->locked = true;
            return new Success(new Lock(0, \Closure::fromCallable([$this, 'release'])));
        }

        $this->queue[] = $deferred = new Deferred;
        return $deferred->promise();
    }

    private function release()
    {
        if (!empty($this->queue)) {
            $deferred = \array_shift($this->queue);
            $deferred->resolve(new Lock(0, \Closure::fromCallable([$this, 'release'])));
            return;
        }

        $this->locked = false;
    }
}