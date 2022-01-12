<?php

namespace Modera\FileRepositoryBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Modera\FileRepositoryBundle\Entity\StoredFile;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class DownloadFileCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('modera:file-repository:download-file')
            ->setDescription('Downloads a file to local filesystem')
            ->addArgument('file_id', InputArgument::REQUIRED)
            ->addArgument('local_path', InputArgument::REQUIRED)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var StoredFile $storedFile */
        $storedFile = $this->em->getRepository(StoredFile::clazz())->find($input->getArgument('file_id'));
        if (!$storedFile) {
            throw new \RuntimeException(sprintf('Unable to find a file with ID "%s".', $input->getArgument('file_id')));
        }

        $localPath = $input->getArgument('local_path');

        $output->writeln('Downloading the file ...');

        ob_start();
        $result = file_put_contents($localPath, $storedFile->getContents());
        $errorOutput = ob_get_clean();

        if (false !== $result) {
            $output->writeln(sprintf(
                '<info>File from repository "%s" has been successfully downloaded and stored locally at %s</info>',
                $storedFile->getRepository()->getName(), $localPath
            ));
        } else {
            $output->writeln('<error>Something went wrong, we were unable to save a file locally: </error>');
            $output->writeln($errorOutput);
        }

        return 0;
    }
}
