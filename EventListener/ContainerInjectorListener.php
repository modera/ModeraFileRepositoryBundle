<?php

namespace Modera\FileRepositoryBundle\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Injects a reference to service container to Repository entity whenever it is fetched
 * from database.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class ContainerInjectorListener
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function postLoad(LifecycleEventArgs $event): void
    {
        if ($event->getObject() instanceof Repository) {
            $event->getObject()->init($this->container);
        }
        if ($event->getObject() instanceof StoredFile) {
            $event->getObject()->init($this->container);
        }
    }
}
