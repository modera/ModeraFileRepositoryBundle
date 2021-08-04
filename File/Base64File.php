<?php

namespace Modera\FileRepositoryBundle\File;

use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeExtensionGuesser;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2021 Modera Foundation
 */
class Base64File extends \SplFileObject
{
    /**
     * @var string
     */
    protected $filename;

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var string
     */
    protected $mimeType;

    /**
     * @param $base64
     * @param null|string $filename
     */
    public function __construct($base64, $filename = null)
    {
        static::validateURI($base64);

        $this->filename = $filename ?: time();
        $this->extension = static::extractExtension($base64);
        $this->mimeType = static::extractMimeType($base64);

        parent::__construct($base64);
    }

    /**
     * {@inheritdoc}
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * {@inheritdoc}
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * @param string $mimeType
     * @param array $mimeTypes
     * @return bool
     */
    static public function isMimeTypeAllowed($mimeType, $mimeTypes = [])
    {
        foreach ($mimeTypes as $mime) {
            if ($mime === $mimeType) {
                return true;
            }

            if ($discrete = strstr($mime, '/*', true)) {
                if (strstr($mimeType, '/', true) === $discrete) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $base64
     * @return string
     */
    static public function extractMimeType($base64)
    {
        return explode(':', substr($base64, 0, strpos($base64, ';')))[1];
    }

    /**
     * @param string $base64
     * @return string
     */
    static public function extractExtension($base64)
    {
        $guesser = new MimeTypeExtensionGuesser();
        $extension = $guesser->guess(static::extractMimeType($base64));

        return filter_var($extension, \FILTER_SANITIZE_URL);
    }

    /**
     * @param string $base64
     */
    static public function validateURI($base64)
    {
        if (!preg_match('/^data:([a-z0-9][a-z0-9\!\#\$\&\-\^\_\+\.]{0,126}\/[a-z0-9][a-z0-9\!\#\$\&\-\^\_\+\.]{0,126}(;[a-z0-9\-]+\=[a-z0-9\-]+)?)?(;base64)?,[a-z0-9\!\$\&\\\'\,\(\)\*\+\,\;\=\-\.\_\~\:\@\/\?\%\s]*\s*$/i', $base64)) {
            throw new \UnexpectedValueException('The provided "data:" URI is not valid.');
        }
    }
}
