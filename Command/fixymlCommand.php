<?php

namespace Limitium\ORMBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\DependencyInjection\Loader;

class FixymlCommand extends ContainerAwareCommand {
    protected function configure() {
        $this
            ->setName('ORM:fixyml')
            ->setDescription('Fix ORM Designer names and namespace')
            ->addArgument('namespace', InputArgument::REQUIRED, 'What is namespace? Example: "Acme"');

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $namespace = $input->getArgument('namespace');


        $files = $this->getModelFiles($namespace);
        $modelLocations = $this->locateModels($files, $namespace);
        foreach ($files as $file) {
            $this->renameFile($file, $output);
            $this->fixContent($file, $output, $namespace, $modelLocations);
        }
        $output->writeln('All fixed!');
    }

    private function locateModels($files, $namespace) {
        $locator = array();
        foreach ($files as $file) {
            list($modelName) = explode('.', $file['name']);
            $locator[$modelName] = "$namespace\\{$file['bundle']}\\Entity\\{$modelName}";
        }
        return $locator;
    }

    private function fixContent($file, $output, $namespace, $modelLocations) {
        $yml = \Symfony\Component\Yaml\Yaml::parse($file['path'] . $file['name']);
        foreach ($yml as $modelName => $modelData) {
            $output->write(sprintf('  > Check content of: <info>%s</info> ... ', $file['name']));
            if (strpos($modelName, '\\') === false) {
                unset($yml[$modelName]);
                $yml[$modelLocations[$modelName]] = $modelData;
            }
            foreach(array('oneToOne','oneToMany','manyToOne') as $relationType){
                if(isset($modelData[$relationType])){
                    foreach ($modelData[$relationType] as $relationName => $relationData) {
                        if(isset($relationData['targetEntity']) && strpos($relationData['targetEntity'], '\\') === false && isset($modelLocations[$relationData['targetEntity']])){
                            $yml[$modelName][$relationType][$relationName]['targetEntity'] = $modelLocations[$relationData['targetEntity']];
                        }
                    }

                }
            }
            file_put_contents($file['path'] . $file['name'], \Symfony\Component\Yaml\Yaml::dump($yml, 4));
            $output->writeln('ok');
        }
    }

    private function renameFile(&$file, OutputInterface $output) {
        $fileParts = explode('.', $file['name']);
        if ($fileParts[1] == 'dcm') {
            $newName = $fileParts[0] . '.orm.' . $fileParts[2];
            $output->write(sprintf('  > Fixing name: <info>%s</info> to <info>%s</info> ... ', $file['name'], $newName));
            if (!rename($file['path'] . $file['name'], $file['path'] . $newName)) {
                throw new \Exception('Cann\'t to rename metadata file ' . $file['name']);
            }
            $output->writeln('ok');
            $file['name'] = $newName;
        }
    }

    private function getModelFiles($namespace) {
        $kernel = $this->getApplication()->getKernel();
        $bundlesDir = $kernel->getRootDir() . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $namespace . DIRECTORY_SEPARATOR;

        $modelFiles = array();
        foreach (scandir($bundlesDir) as $bundleName) {
            if (is_dir($bundlesDir . $bundleName) && !in_array($bundleName, array('..', '.', 'ORMBundle'))) {
                $metadataDir = $bundlesDir . $bundleName . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'doctrine';
                if (file_exists($metadataDir) && is_dir($metadataDir)) {
                    foreach (scandir($metadataDir) as $metadataModelFileName) {
                        $metadataModelFile = $metadataDir . DIRECTORY_SEPARATOR . $metadataModelFileName;
                        if (is_file($metadataModelFile)) {
                            $fileParts = explode('.', $metadataModelFileName);
                            if (sizeof($fileParts) == 3) {
                                $modelFiles[] = array(
                                    'bundle' => $bundleName,
                                    'path' => $metadataDir . DIRECTORY_SEPARATOR,
                                    'name' => $metadataModelFileName
                                );
                            }
                        }
                    }
                }
            }
        }

        return $modelFiles;
    }
}