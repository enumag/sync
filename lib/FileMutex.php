<?php

namespace Amp\Sync;

use function Amp\delay;

/**
 * A cross-platform mutex that uses exclusive files as the lock mechanism.
 *
 * This mutex implementation is not always atomic and depends on the operating
 * system's implementation of file creation operations. Use this implementation
 * only if no other mutex types are available.
 *
 * This implementation avoids using [flock()](http://php.net/flock)
 * because flock() is known to have some atomicity issues on some systems. In
 * addition, flock() does not work as expected when trying to lock a file
 * multiple times in the same process on Linux. Instead, exclusive file creation
 * is used to create a lock file, which is atomic on most systems.
 *
 * @see http://php.net/fopen
 */
class FileMutex implements Mutex
{
    private const LATENCY_TIMEOUT = 10;

    /** @var string The full path to the lock file. */
    private $fileName;

    /**
     * Creates a new mutex.
     *
     * @param string|null
     */
    public function __construct(string $fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * {@inheritdoc}
     */
    public function acquire(): Lock
    {
        // Try to create the lock file. If the file already exists, someone else
        // has the lock, so set an asynchronous timer and try again.
        while (($handle = @\fopen($this->fileName, 'xb')) === false) {
            delay(self::LATENCY_TIMEOUT);
        }

        // Return a lock object that can be used to release the lock on the mutex.
        $lock = new Lock(0, \Closure::fromCallable([$this, 'release']));

        \fclose($handle);

        return $lock;
    }

    /**
     * Releases the lock on the mutex.
     *
     * @throws SyncException If the unlock operation failed.
     */
    protected function release(): void
    {
        $success = @\unlink($this->fileName);

        if (!$success) {
            throw new SyncException('Failed to unlock the mutex file.');
        }
    }
}
