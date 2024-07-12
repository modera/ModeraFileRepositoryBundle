<?php

namespace Modera\FileRepositoryBundle\File;

use Symfony\Component\Mime\MimeTypes;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2021 Modera Foundation
 */
class Base64File extends \SplFileObject
{
    protected string $filename;

    protected ?string $extension = null;

    protected ?string $mimeType = null;

    public function __construct(string $base64, ?string $filename = null)
    {
        static::validateURI($base64);

        $this->filename = $filename ?: \sprintf('%d', \time());
        $this->extension = static::extractExtension($base64);
        $this->mimeType = static::extractMimeType($base64);

        parent::__construct($base64);
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getExtension(): string
    {
        return $this->extension ?? '';
    }

    public function getMimeType(): string
    {
        return $this->mimeType ?? '';
    }

    public function getContents(): string
    {
        return @\file_get_contents($this->getPathname()) ?: '';
    }

    /**
     * @param string[] $mimeTypes
     */
    public static function isMimeTypeAllowed(string $mimeType, array $mimeTypes = []): bool
    {
        foreach ($mimeTypes as $mime) {
            if ($mime === $mimeType) {
                return true;
            }

            if ($discrete = \strstr($mime, '/*', true)) {
                if (\strstr($mimeType, '/', true) === $discrete) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function extractMimeType(string $base64): ?string
    {
        /** @var int $length */
        $length = \strpos($base64, ';');

        return \explode(':', \substr($base64, 0, $length))[1] ?? null ?: null;
    }

    public static function extractExtension(string $base64): ?string
    {
        $extension = MimeTypes::getDefault()->getExtensions(static::extractMimeType($base64) ?? '')[0] ?? null;

        return \filter_var($extension, \FILTER_SANITIZE_URL) ?: null;
    }

    public static function validateURI(string $base64): void
    {
        if (!\preg_match('/^data:([a-z0-9][a-z0-9\!\#\$\&\-\^\_\+\.]{0,126}\/[a-z0-9][a-z0-9\!\#\$\&\-\^\_\+\.]{0,126}(;[a-z0-9\-]+\=[a-z0-9\-]+)?)?(;base64)?,[a-z0-9\!\$\&\\\'\,\(\)\*\+\,\;\=\-\.\_\~\:\@\/\?\%\s]*\s*$/i', $base64)) {
            throw new \UnexpectedValueException('The provided "data:" URI is not valid.');
        }
    }
}
