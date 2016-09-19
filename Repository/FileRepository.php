<?php

namespace Modera\FileRepositoryBundle\Repository;

use Doctrine\ORM\EntityManager;
use Gaufrette\Filesystem;
use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class FileRepository
{
    /* @var EntityManager $em */
    private $em;
    /* @var ContainerInterface $container */
    private $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->container = $container;
    }

    /**
     * @param string $name
     *
     * @return Repository|null
     */
    public function getRepository($name)
    {
        return $this->em->getRepository(Repository::clazz())->findOneBy(array(
            'name' => $name,
        ));
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function repositoryExists($name)
    {
        $q = $this->em->createQuery(sprintf('SELECT COUNT(e.id) FROM %s e WHERE e.name = ?0', Repository::clazz()));
        $q->setParameter(0, $name);

        return $q->getSingleScalarResult() != 0;
    }

    /**
     * @param string $name
     * @param array  $config For available options see Repository::$config
     * @param string $label
     *
     * @return Repository
     */
    public function createRepository($name, array $config, $label = null)
    {
        $repository = new Repository($name, $config);
        $repository->setLabel($label);
        $repository->init($this->container);

        $this->em->persist($repository);
        $this->em->flush();

        return $repository;
    }

    /**
     * @throws \RuntimeException
     *
     * @param $repositoryName
     * @param \SplFileInfo $file
     * @param array        $context
     *
     * @return \Modera\FileRepositoryBundle\Entity\StoredFile
     */
    public function put($repositoryName, \SplFileInfo $file, array $context = array())
    {
        $repository = $this->getRepository($repositoryName);
        if (!$repository) {
            throw new \RuntimeException("Unable to find repository '$repositoryName'.");
        }

        $config = $repository->getConfig();

        if (!$this->isInterceptorDisabled($context, 'before')) {
            $repository->beforePut($file, $this->createInterceptorsFilter($context, 'before'));
        }

        $storedFile = null;
        $overwrite = isset($config['overwrite_files']) ? $config['overwrite_files'] : false;
        if ($overwrite) {
            $filename = $file->getFilename();
            if ($file instanceof UploadedFile) {
                $filename = $file->getClientOriginalName();
            }

            /* @var StoredFile $storedFile */
            $storedFile = $this->em->getRepository(StoredFile::clazz())->findOneBy(array(
                'repository' => $repository->getId(),
                'filename' => $filename,
            ));
            if ($storedFile) {
                $storedFile->setCreatedAt(new \DateTime('now'));
            } else {
                $overwrite = false;
            }
        }

        if (!$storedFile) {
            $storedFile = $repository->createFile($file, $context);
        }

        $contents = @file_get_contents($file->getPathname());
        if (false === $contents) {
            throw new \RuntimeException(sprintf(
                'Unable to read contents of "%s" file!', $file->getPath()
            ));
        }

        if (!$this->isInterceptorDisabled($context, 'put')) {
            $repository->onPut($storedFile, $file, $this->createInterceptorsFilter($context, 'put'));
        }

        $storageKey = $storedFile->getStorageKey();

        /* @var Filesystem $fs */
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
            $repository->afterPut($storedFile, $file, $this->createInterceptorsFilter($context, 'after'));
        }

        return $storedFile;
    }

    /**
     * @param array $context
     * @param $type
     *
     * @return bool
     */
    private function isInterceptorDisabled(array $context, $type)
    {
        if (isset($context['disable_interceptors'])) {
            if (is_bool($context['disable_interceptors'])) {
                return $context['disable_interceptors'];
            } else {
                return false !== array_search($type, $context['disable_interceptors']);
            }
        }

        return false;
    }

    /**
     * @param array $context
     * @param $type
     *
     * @return callable
     */
    private function createInterceptorsFilter(array $context, $type)
    {
        $key = strtolower($type).'_interceptor_filter';
        if (isset($context[$key]) && is_callable($context[$key])) {
            return $context[$key];
        } else {
            return function () {
                return true;
            };
        }
    }
}
