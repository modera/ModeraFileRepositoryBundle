<?php


namespace Modera\FileRepositoryBundle\Command;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * @internal
 *
 * @author Sergei Lissovski <sergei.lissovski@nowinnovations.com>
 */
trait TableTrait
{
    /**
     * @param OutputInterface $output
     * @param array $headers
     * @param array $rows
     */
    private function renderTable(OutputInterface $output, array $headers, array $rows)
    {
        $table = null;

        $isSymfony2 = substr(Kernel::VERSION, 0, 1) == '2';
        if ($isSymfony2) {
            /* @var \Symfony\Component\Console\Helper\TableHelper $table */
            $table = $this->getHelperSet()->get('table');
        } else {
            $table = new \Symfony\Component\Console\Helper\Table($output);
        }
        $table
            ->setHeaders($headers)
            ->setRows($rows)
        ;
        $table->render($output);
    }
}