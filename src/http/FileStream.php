<?php
namespace ryunosuke\microute\http;

use Psr\Http\Message\StreamInterface;

class FileStream implements StreamInterface
{
    /** @var ?resource */
    private $handle;

    public function __construct(private string $filename)
    {
    }

    public function __toString(): string
    {
        $tell = $this->tell();
        try {
            $this->rewind();
            return $this->getContents();
        }
        finally {
            $this->seek($tell);
        }
    }

    public function tell(): int
    {
        return $this->callOrThrow(false, 'ftell', $this->open());
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->callOrThrow(-1, 'fseek', $this->open(), $offset, $whence);
    }

    public function rewind(): void
    {
        $this->callOrThrow(false, 'rewind', $this->open());
    }

    public function eof(): bool
    {
        if ($this->handle === null) {
            throw new \RuntimeException("failed to feof, resource is not opened");
        }
        return $this->callOrThrow(null, 'feof', $this->open());
    }

    public function detach()
    {
        $result = $this->open();
        $this->close();
        return $result;
    }

    public function close(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }
        $this->callOrThrow(false, 'fclose', $this->handle);
    }

    public function read(int $length): string
    {
        return $this->callOrThrow(false, 'fread', $this->open(), $length);
    }

    public function write(string $string): int
    {
        return $this->callOrThrow(false, 'fwrite', $this->open(), $string);
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function getSize(): ?int
    {
        if ($this->handle === null) {
            return $this->callOrThrow(false, 'filesize', $this->filename);
        }
        return $this->callOrThrow(false, 'fstat', $this->handle)['size'] ?? null;
    }

    public function getMetadata(?string $key = null)
    {
        $metadata = stream_get_meta_data($this->open());
        if ($key === null) {
            return $metadata;
        }
        return $metadata[$key];
    }

    public function getContents(): string
    {
        return $this->callOrThrow(false, 'stream_get_contents', $this->open());
    }

    private function open()
    {
        if (isset($this->handle) && !is_resource($this->handle)) {
            throw new \RuntimeException("failed to fopen, resource is detached");
        }
        return $this->handle ??= $this->callOrThrow(false, 'fopen', $this->filename, 'rb+');
    }

    private function callOrThrow($unexpected, callable $fn, ...$args)
    {
        try {
            $result = $fn(...$args);
        }
        catch (\Throwable $e) {
            throw new \RuntimeException(sprintf("failed to %s unknown error %s", $fn, $e->getMessage()), $e->getCode(), $e);
        }

        if ($result === $unexpected) {
            throw new \RuntimeException(sprintf("failed to %s not expected %s", $fn, var_export($unexpected, true)));
        }
        return $result;
    }
}
