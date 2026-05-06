<?php
namespace ryunosuke\microute\http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFile extends \Symfony\Component\HttpFoundation\File\UploadedFile implements UploadedFileInterface
{
    public static function fromSymfonyFile(\Symfony\Component\HttpFoundation\File\UploadedFile $file): UploadedFile
    {
        return new UploadedFile($file->getPathname(), $file->getClientOriginalName(), $file->getClientMimeType(), $file->getError());
    }

    public function getStream(): StreamInterface
    {
        return new FileStream(parent::getPathname());
    }

    public function getError(): int
    {
        return parent::getError();
    }

    public function getSize(): int
    {
        return parent::getSize();
    }

    public function getClientFilename(): ?string
    {
        return parent::getClientOriginalName();
    }

    public function getClientMediaType(): ?string
    {
        return parent::getClientMimeType();
    }

    public function moveTo(string $targetPath): void
    {
        // Use this method as an alternative to move_uploaded_file(). This method is guaranteed to work in both SAPI and non-SAPI environments.
        if (PHP_SAPI === 'cli') {
            rename(parent::getPathname(), $targetPath);
            return;
        }
        move_uploaded_file(parent::getPathname(), $targetPath); // @codeCoverageIgnore
    }
}
