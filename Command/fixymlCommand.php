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

        $this->renameFile($files, $output);

        $this->fixContent($files, $output, $this->locateModels($files, $namespace));

        $output->writeln('All fixed!');
    }

    private function locateModels($files, $namespace) {
        $locator = array();
        foreach ($files as $file) {
            $modelName = $this->getShortName($file);
            $locator[$modelName] = "$namespace\\{$file['bundle']}\\Entity\\{$modelName}";
        }
        return $locator;
    }

    private function getShortName($file) {
        list($modelName) = explode('.', $file['name']);
        return $modelName;
    }

    private function fixContent($files, $output, $modelLocations) {
        foreach ($files as $path => $file) {


            $output->write(sprintf('  > Checking of: <info>%s</info> ... ', $file['name']));
            $yml = \Symfony\Component\Yaml\Yaml::parse($path);

            $shortName = $this->getShortName($file);

            if (isset($yml[$shortName])) {
                $yml[$modelLocations[$shortName]] = $yml[$shortName];
                unset($yml[$shortName]);
            }

            $modelData = $yml[$modelLocations[$shortName]];
            foreach (array('oneToOne', 'oneToMany', 'manyToOne') as $relationType) {
                if (isset($modelData[$relationType])) {
                    foreach ($modelData[$relationType] as $relationName => $relationData) {
                        if (isset($relationData['targetEntity']) && strpos($relationData['targetEntity'], '\\') === false && isset($modelLocations[$relationData['targetEntity']])) {
                            $yml[$modelLocations[$shortName]][$relationType][$relationName]['targetEntity'] = $modelLocations[$relationData['targetEntity']];
                        }
                        if(isset($relationData['cascade'])){
                            foreach ($relationData['cascade'] as $k => $cascadeVal) {
                                $yml[$modelLocations[$shortName]][$relationType][$relationName]['cascade'][$k] = strtolower(substr($cascadeVal,7));
                            }
                        }
                    }
                }
            }

            if (isset($modelData['manyToOne'])) {
                foreach ($modelData['manyToOne'] as $relationName => $relationData) {
                    if ($relationData['joinColumns'] == null) {
                        $field = strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $relationName)). '_id';
                        $yml[$modelLocations[$shortName]]['manyToOne'][$relationName]['joinColumns'] = array($field => array('referencedColumnName' => 'id'));
                        if(isset($yml[$modelLocations[$shortName]]['fields'][$field])){
                            unset($yml[$modelLocations[$shortName]]['fields'][$field]);
                        }
                    }
                }
            }

            if (isset($modelData['discriminatorMap'])) {
                foreach ($modelData['discriminatorMap'] as $modelName => $descriminator) {
                    if (isset($modelLocations[$descriminator])) {
                        $yml[$modelLocations[$shortName]]['discriminatorMap'][$modelName] = $modelLocations[$descriminator];
                    }
                }
            }

            file_put_contents($path, \Symfony\Component\Yaml\Yaml::dump($yml, 10));
            $output->writeln('ok');
        }
    }

    private function renameFile(&$files, OutputInterface $output) {
        foreach ($files as $path => $file) {
            $fileParts = explode('.', $file['name']);
            if ($fileParts[1] == 'dcm') {
                $newName = $fileParts[0] . '.orm.' . $fileParts[2];
                $output->write(sprintf('  > Fixing name: <info>%s</info> to <info>%s</info> ... ', $file['name'], $newName));
                if (!rename($path, $file['path'] . $newName)) {
                    throw new \Exception('Cann\'t to rename metadata file ' . $file['name']);
                }
                $output->writeln('ok');
                $file['name'] = $newName;
                $files[$file['path'] . $newName] = $file;
                unset($files[$path]);
            }
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
                                $modelFiles[$metadataDir . DIRECTORY_SEPARATOR . $metadataModelFileName] = array(
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