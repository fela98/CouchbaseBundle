<?php

namespace Simonsimcity\CouchbaseBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportDdocCommand extends ContainerAwareCommand
{
    /**
     * @var \CouchbaseBucket
     */
    private $couchbase;

    protected function configure()
    {
        $this
            ->setName("couchbase:import-ddoc")
            ->setDescription("Import the design documents for your couchbase installation")
            ->addArgument(
                "connection",
                InputArgument::REQUIRED,
                "The couchbase-connection this change should be applied to.",
                null
            )
            ->addArgument(
                "path",
                InputArgument::OPTIONAL,
                "Where are your design documents (*.ddoc) located (relative to %kernel.root_dir%)?",
                "Resources/couchbase/{connection}/"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Absolute path to the design-documents
        $path = $this->getContainer()->getParameter('kernel.root_dir').DIRECTORY_SEPARATOR
                .$input->getArgument("path");

        $path            = str_replace("{connection}", $input->getArgument('connection'), $path);
        $this->couchbase = $this->getContainer()->get("couchbase.bucket.{$input->getArgument('connection')}");

        try {
            $iterator = new \DirectoryIterator($path);
            $this->importDDocsInFolder($iterator, $output);

            $envPath = $path.$this->getContainer()->get('kernel')->getEnvironment().DIRECTORY_SEPARATOR;
            if (is_dir($envPath)) {
                $iterator = new \DirectoryIterator($envPath);
                $this->importDDocsInFolder($iterator, $output);
            }

        } catch (\CouchbaseException $e) {
            $output->writeln("<error>An error occurred while writing data to couchbase:</error>");
            $output->writeln("<error>{$e->getMessage()}</error>");
        }
    }

    private function importDDocsInFolder(\DirectoryIterator $iterator, OutputInterface $output)
    {
        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === "ddoc") {

                $res = $this->couchbase->manager()->upsertDesignDocument(
                    $fileInfo->getBasename(".ddoc"),
                    file_get_contents($fileInfo->getRealPath())
                );

                if ($res === true) {
                    $output->writeln("<info>Created: ".$fileInfo->getBasename(".ddoc")."</info>");
                } else {
                    $output->writeln("<comment>Not created: ".$fileInfo->getBasename(".ddoc")."</comment>");
                }
            }
        }
    }
}
