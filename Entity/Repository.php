<?php

namespace Modera\FileRepositoryBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gaufrette\Filesystem;
use Modera\FileRepositoryBundle\Exceptions\InvalidRepositoryConfig;
use Modera\FileRepositoryBundle\Intercepting\DefaultInterceptorsProvider;
use Modera\FileRepositoryBundle\Intercepting\OperationInterceptorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Every repository is associated with one underlying Gaufrette filesystem.
 *
 * @ORM\Entity
 * @ORM\Table(
 *     name="modera_filerepository_repository",
 *     indexes={
 *         @ORM\Index(name="name_idx", columns={"name"})
 *     }
 * )
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class Repository
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Stores configuration for this repository. Some standard configuration properties:.
     *
     *  * filesystem  -- DI service ID which points to \Gaufrette\Filesystem which will be used to store files for
     *                   this repository.
     *  * storage_key_generator -- DI service ID of class which implements {@class StorageKeyGeneratorInterface}.
     *
     * @ORM\Column(type="array")
     *
     * @var array
     */
    private $config = array();

    /**
     * @ORM\Column(type="string")
     */
    private $name;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $label;

    /**
     * @ORM\OneToMany(targetEntity="StoredFile", mappedBy="repository", cascade={"PERSIST", "REMOVE"})
     */
    private $files;

    /**
     * @ORM\Column(name="internal", type="boolean")
     */
    private $internal = false;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param string $name
     * @param array  $config
     */
    public function __construct($name, array $config)
    {
        $this->name = $name;
        $this->setConfig($config);

        $this->files = new ArrayCollection();
    }

    /**
     * @return OperationInterceptorInterface[]
     */
    private function getInterceptors()
    {
        /* @var DefaultInterceptorsProvider $provider */
        $provider = $this->container->get('modera_file_repository.intercepting.interceptors_provider');

        return $provider->getInterceptors($this);
    }

    /**
     * @internal
     */
    public function beforePut(\SplFileInfo $file, callable $filter = null, array $context = array())
    {
        $filter = $filter ?: function () {
            return true;
        };

        foreach ($this->getInterceptors() as $interceptor) {
            if ($filter($interceptor)) {
                $interceptor->beforePut($file, $this, $context);
            }
        }
    }

    /**
     * @internal
     */
    public function onPut(StoredFile $storedFile, \SplFileInfo $file, callable $filter = null, array $context = array())
    {
        $filter = $filter ?: function () {
            return true;
        };

        foreach ($this->getInterceptors() as $interceptor) {
            if ($filter($interceptor)) {
                $interceptor->onPut($storedFile, $file, $this, $context);
            }
        }
    }

    /**
     * @internal
     */
    public function afterPut(StoredFile $storedFile, \SplFileInfo $file, callable $filter = null, array $context = array())
    {
        $filter = $filter ?: function () {
            return true;
        };

        foreach ($this->getInterceptors() as $interceptor) {
            if ($filter($interceptor)) {
                $interceptor->afterPut($storedFile, $file, $this, $context);
            }
        }
    }

    /**
     * @private
     *
     * @param ContainerInterface $container
     */
    public function init(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @deprecated Use native ::class property
     *
     * @return string
     */
    public static function clazz()
    {
        @trigger_error(sprintf(
            'The "%s()" method is deprecated. Use native ::class property.',
            __METHOD__
        ), \E_USER_DEPRECATED);

        return get_called_class();
    }

    /**
     * @return Filesystem
     */
    public function getFilesystem()
    {
        $map = $this->container->get('knp_gaufrette.filesystem_map');

        return $map->get($this->config['filesystem']);
    }

    /**
     * @param \SplFileInfo $file
     * @param array        $context
     *
     * @return string
     */
    public function generateStorageKey(\SplFileInfo $file, array $context)
    {
        return $this->container->get($this->config['storage_key_generator'])->generateStorageKey($file, $context);
    }

    /**
     * @param \SplFileInfo $file
     * @param array        $context
     *
     * @return StoredFile
     */
    public function createFile(\SplFileInfo $file, array $context = array())
    {
        $result = new StoredFile($this, $file, $context);
        $result->init($this->container);
        $this->files->add($result);

        return $result;
    }

    /**
     * @param array $config
     */
    public function setConfig(array $config)
    {
        if (!isset($config['filesystem'])) {
            throw InvalidRepositoryConfig::create('filesystem', $config);
        }
        if (!isset($config['storage_key_generator'])) {
            $config['storage_key_generator'] = 'modera_file_repository.repository.uniqid_key_generator';
        }

        $this->config = $config;
    }

    // boilerplate:

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @private Since 2.56.0
     *
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return mixed
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $files
     */
    public function setFiles($files)
    {
        $this->files = $files;
    }

    /**
     * @return StoredFile[]|Collection
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param bool $internal
     */
    public function setInternal($internal)
    {
        $this->internal = $internal;
    }

    /**
     * @return bool
     */
    public function isInternal()
    {
        return $this->internal;
    }
}
