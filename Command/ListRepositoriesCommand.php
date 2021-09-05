<?php

namespace Modera\FileRepositoryBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Modera\FileRepositoryBundle\Entity\Repository;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class ListRepositoriesCommand extends ContainerAwareCommand
{
    use TableTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('modera:file-repository:list')
            ->setDescription('Shows all available repositories')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var EntityManagerInterface $om */
        $om = $this->getContainer()->get('doctrine.orm.entity_manager');

        $rows = [];
        foreach ($om->getRepository(Repository::clazz())->findAll() as $repository) {
            /* @var Repository $repository */

            $config = $repository->getConfig();

            $rows[] = [
                $repository->getId(),
                $repository->getName(),
                $repository->getLabel() ? $repository->getLabel() : '-',
                $config['filesystem'],
                isset($config['overwrite_files']) && true == $config['overwrite_files']? 'Enabled' : 'Disabled',
                $config['storage_key_generator'],
            ];
        }

        $this->renderTable(
            $output,
            ['#', 'Name', 'Label', 'Filesystem', 'Overwrite files', 'Storage key generator'],
            $rows
        );
    }
}
