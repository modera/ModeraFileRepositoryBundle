<?php

namespace Modera\FileRepositoryBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Gaufrette\Filesystem;
use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class FileRepository
{
    private EntityManagerInterface $em;

    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        $this->em = $em;
    }

    public function getRepository(string $name): ?Repository
    {
        return $this->em->getRepository(Repository::class)->findOneBy([
            'name' => $name,
        ]);
    }

    public function repositoryExists(string $name): bool
    {
        $q = $this->em->createQuery(\sprintf('SELECT COUNT(e.id) FROM %s e WHERE e.name = ?0', Repository::class));
        $q->setParameter(0, $name);
        /** @var string $result */
        $result = $q->getSingleScalarResult();

        return 0 !== \intval($result);
    }

    /**
     * @param array<mixed> $config For available options see Repository::$config
     */
    public function createRepository(string $name, array $config, ?string $label = null, bool $internal = false): Repository
    {
        $repository = new Repository($name, $config);
        $repository->setLabel($label);
        $repository->setInternal($internal);
        $repository->init($this->container);

        $this->em->persist($repository);
        $this->em->flush();

        return $repository;
    }

    /**
     * @param array<mixed> $context
     *
     * @throws \RuntimeException
     */
    public function put(string $repositoryName, \SplFileInfo $file, array $context = []): StoredFile
    {
        $repository = $this->getRepository($repositoryName);
        if (!$repository) {
            throw new \RuntimeException("Unable to find repository '$repositoryName'.");
        }

        $config = $repository->getConfig();

        if (!$this->isInterceptorDisabled($context, 'before')) {
            $repository->beforePut($file, $this->createInterceptorsFilter($context, 'before'), $context);
        }

        $storedFile = null;
        $overwrite = \is_bool($config['overwrite_files'] ?? null) ? $config['overwrite_files'] : false;
        if ($overwrite) {
            $filename = $file->getFilename();
            if ($file instanceof UploadedFile) {
                $filename = $file->getClientOriginalName();
            }

            /** @var ?StoredFile $storedFile */
            $storedFile = $this->em->getRepository(StoredFile::class)->findOneBy([
                'repository' => $repository->getId(),
                'filename' => $filename,
            ]);
            if ($storedFile) {
                $storedFile->setCreatedAt(new \DateTime('now'));
            } else {
                $overwrite = false;
            }
        }

        if (!$storedFile) {
            $storedFile = $repository->createFile($file, $context);
        }

        $contents = @\file_get_contents($file->getPathname());
        if (false === $contents) {
            throw new \RuntimeException(\sprintf('Unable to read contents of "%s" file!', $file->getPath()));
        }

        if (!$this->isInterceptorDisabled($context, 'put')) {
            $repository->onPut($storedFile, $file, $this->createInterceptorsFilter($context, 'put'), $context);
        }

        $storageKey = $storedFile->getStorageKey();

        /** @var Filesystem $fs */
        $fs = $repository->getFilesystem();

        // physically stored file
        $fs->write($storageKey, $contents, $overwrite);

        try {
            $this->em->persist($storedFile);
            $this->em->flush($storedFile);
        } catch (\Exception $e) {
            if (!$storedFile->getId()) {
                $fs->delete($storageKey);
            }

            throw $e;
        }

        if (!$this->isInterceptorDisabled($context, 'after')) {
            $repository->afterPut($storedFile, $file, $this->createInterceptorsFilter($context, 'after'), $context);
        }

        return $storedFile;
    }

    /**
     * @param array<mixed> $context
     */
    private function isInterceptorDisabled(array $context, string $type): bool
    {
        if (isset($context['disable_interceptors'])) {
            if (\is_bool($context['disable_interceptors'])) {
                return $context['disable_interceptors'];
            } elseif (\is_array($context['disable_interceptors'])) {
                return false !== \array_search($type, $context['disable_interceptors']);
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $context
     */
    private function createInterceptorsFilter(array $context, string $type): callable
    {
        $key = \strtolower($type).'_interceptor_filter';
        if (isset($context[$key]) && \is_callable($context[$key])) {
            return $context[$key];
        } else {
            return function () {
                return true;
            };
        }
    }
}
