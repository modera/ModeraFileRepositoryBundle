<?php

namespace Modera\FileRepositoryBundle\Command;

use Modera\FileRepositoryBundle\Repository\FileRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class PutFileCommand extends Command
{
    private FileRepository $fr;

    public function __construct(FileRepository $fr)
    {
        $this->fr = $fr;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('modera:file-repository:put-file')
            ->setDescription('Puts a file to a repository')
            ->addArgument('repository', InputArgument::REQUIRED)
            ->addArgument('local_path', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $repositoryName */
        $repositoryName = $input->getArgument('repository');
        $repository = $this->fr->getRepository($repositoryName);
        if (!$repository) {
            throw new \RuntimeException(\sprintf('Unable to find a repository with name "%s"', $repositoryName));
        }

        /** @var string $localPath */
        $localPath = $input->getArgument('local_path');
        if (!\file_exists($localPath) || !\is_readable($localPath)) {
            throw new \RuntimeException(\sprintf('Unable to find a file "%s" or it is not readable', $localPath));
        }

        $output->writeln(\sprintf('Uploading "%s" to repository "%s"', $localPath, $repository->getName()));

        $storedFile = $this->fr->put($repository->getName(), new \SplFileInfo($localPath));

        $output->writeln(\sprintf('<info>Done! File id: %d</info>', $storedFile->getId()));

        return 0;
    }
}
