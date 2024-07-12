<?php

namespace Modera\FileRepositoryBundle\Exceptions;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class InvalidRepositoryConfig extends \RuntimeException
{
    private string $missingConfigurationKey;

    /**
     * @var array<mixed>
     */
    private array $config = [];

    /**
     * @param array<mixed> $config
     */
    public static function create(string $missingConfigurationKey, array $config): InvalidRepositoryConfig
    {
        $e = new self('This configuration property must be provided: '.$missingConfigurationKey);
        $e->setMissingConfigurationKey($missingConfigurationKey);
        $e->setConfig($config);

        return $e;
    }

    /**
     * @param array<mixed> $config
     */
    private function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @return array<mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    private function setMissingConfigurationKey(string $missingConfigurationKey): void
    {
        $this->missingConfigurationKey = $missingConfigurationKey;
    }

    public function getMissingConfigurationKey(): string
    {
        return $this->missingConfigurationKey;
    }
}
