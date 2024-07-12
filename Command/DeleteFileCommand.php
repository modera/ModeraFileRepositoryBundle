<?php

namespace Modera\FileRepositoryBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class DeleteFileCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('modera:file-repository:delete-file')
            ->setDescription('Deletes a file from repository')
            ->addArgument('file_id', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ?StoredFile $storedFile */
        $storedFile = $this->em->find(StoredFile::class, $input->getArgument('file_id'));
        if (!$storedFile) {
            throw new \RuntimeException('Unable to find a file with ID '.$input->getArgument('file_id'));
        }

        $output->writeln(\sprintf(
            'Deleting file "%s" from repository "%s"',
            $storedFile->getFilename(),
            $storedFile->getRepository()->getName()
        ));

        $this->em->remove($storedFile);
        $this->em->flush();

        $output->writeln('<info>Done!</info>');

        return 0;
    }
}
