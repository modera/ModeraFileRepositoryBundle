<?php

namespace Modera\FileRepositoryBundle\Helper;

use Modera\FileRepositoryBundle\File\Base64File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;

/**
 * @author    Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2022 Modera Foundation
 *
 * Example:
 * if (\is_array($source)) {
 *     $file = ConvertSourceToBase64File::fromArray($source);
 * } elseif (\strpos($source, 'data:') === 0) {
 *     $file = ConvertSourceToBase64File::fromData($source);
 * } elseif (\filter_var($source, \FILTER_VALIDATE_URL)) {
 *     $file = ConvertSourceToBase64File::fromURL($source);
 * } else {
 *     $file = ConvertSourceToBase64File::fromFile($source);
 * }
 */
final class ConvertSourceToBase64File
{
    /**
     * @param array{
     *     'mimeType'?: string,
     *     'fileContent'?: string,
     *     'fileName'?: string,
     * } $source
     */
    public static function fromArray(array $source): ?Base64File
    {
        $required = ['mimeType', 'fileContent'];
        if (\count(\array_intersect_key(\array_flip($required), $source)) === \count($required)) {
            /** @var array{
             *     'mimeType': string,
             *     'fileContent': string,
             *     'fileName'?: string
             * } $source */
            $base64 = \sprintf('data:%s;base64,%s', $source['mimeType'], $source['fileContent']);

            return new Base64File($base64, $source['fileName'] ?? static::generateFilename($base64));
        }

        return null;
    }

    public static function fromData(string $source): ?Base64File
    {
        try {
            return new Base64File($source, static::generateFilename($source));
        } catch (\UnexpectedValueException $e) {
        }

        return null;
    }

    public static function fromFile(string $source): ?Base64File
    {
        $base64 = static::fileAsBase64($source);
        if ($base64) {
            return new Base64File($base64, static::extractFilename($source) ?? static::generateFilename($base64));
        }

        return null;
    }

    public static function fromURL(string $source): ?Base64File
    {
        $base64 = static::fetchAsBase64($source);
        if ($base64) {
            return new Base64File($base64, static::extractFilename($source) ?? static::generateFilename($base64));
        }

        return null;
    }

    private static function generateFilename(string $base64): string
    {
        /** @var callable $callback */
        $callback = 'trim';

        return \implode('.', \array_filter(\array_map($callback, [
            \sprintf('%d', \time()),
            Base64File::extractExtension($base64),
        ])));
    }

    private static function extractFilename(string $source): ?string
    {
        $url = \parse_url($source);
        $filename = isset($url['path']) ? \basename($url['path']) : null;

        return $filename ?: null;
    }

    private static function fileAsBase64(string $source): ?string
    {
        if (\is_file($source) && $contents = @\file_get_contents($source) ?: null) {
            $mimeType = null;
            $ext = \pathinfo($source, \PATHINFO_EXTENSION);
            if ($ext) {
                $mimeTypes = MimeTypes::getDefault()->getMimeTypes($ext);
                if (count($mimeTypes)) {
                    $mimeType = $mimeTypes[0];
                }
            }
            if (!$mimeType) {
                $mimeType = MimeTypes::getDefault()->guessMimeType($source);
            }
            if ($mimeType) {
                return \sprintf('data:%s;base64,%s', $mimeType, \base64_encode($contents));
            }
        }

        return null;
    }

    private static function fetchAsBase64(string $source): ?string
    {
        $context = \stream_context_create([
            'http' => [
                'ignore_errors' => true,
            ],
        ]);
        if ($contents = \file_get_contents($source, false, $context) ?: null) {
            /** @var ?string[] $http_response_header */
            if (isset($http_response_header) && \is_array($http_response_header) && \count($http_response_header)) {
                \preg_match('{HTTP\/\S*\s(\d{3})}', \array_shift($http_response_header), $matches);
                $status = (int) $matches[1];
                if (Response::HTTP_OK === $status) {
                    $headers = [];
                    foreach ($http_response_header as $value) {
                        if (false !== ($matches = \explode(':', $value, 2))) {
                            $headers[\trim($matches[0])] = \trim($matches[1]);
                        }
                    }
                    $mimeType = $headers['Content-Type'] ?? null;
                    if (!$mimeType) {
                        $url = \parse_url($source);
                        $ext = isset($url['path']) ? \pathinfo($url['path'], \PATHINFO_EXTENSION) : null;
                        if ($ext) {
                            $mimeTypes = MimeTypes::getDefault()->getMimeTypes($ext);
                            if (count($mimeTypes)) {
                                $mimeType = $mimeTypes[0];
                            }
                        }
                    }
                    if ($mimeType) {
                        return \sprintf('data:%s;base64,%s', $mimeType, \base64_encode($contents));
                    }
                }
            }
        }

        return null;
    }
}
