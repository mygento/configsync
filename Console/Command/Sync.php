<?php

/**
 * @author Mygento Team
 * @copyright 2017-2019 Mygento (https://www.mygento.ru)
 * @package Mygento_Configsync
 */

namespace Mygento\Configsync\Console\Command;

class Sync extends \Symfony\Component\Console\Command\Command
{
    const DELETE = '%DELETE%';

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configInterface;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * @param \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct();
        $this->configInterface = $configInterface;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('setup:config:sync')
            ->setDescription('Sync Magento configuration'
                . 'with multiple environments in the version control')
            ->addArgument(
                'env',
                \Symfony\Component\Console\Input\InputArgument::REQUIRED,
                'Environment for import.'
            )
            ->addArgument(
                'config_yaml_file',
                \Symfony\Component\Console\Input\InputArgument::REQUIRED,
                'The YAML file containing the configuration settings.'
            );

        parent::configure();
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $this->output = $output;

        $envData = $this->getEnvData(
            $input->getArgument('config_yaml_file'),
            $input->getArgument('env')
        );
        if (!$envData) {
            return 0;
        }

        $totalValues = 0;
        $importedValues = 0;

        foreach ($envData as $scopeKey => $data) {
            if (!preg_match('/^(default|(websites|stores)-\d+)$/', $scopeKey)) {
                $this->diag('<error>Skipped scope: ' . $scopeKey . '</error>');
                continue;
            }

            $scopeKeyExtracted = $this->extractFromScopeKey($scopeKey);
            $scope = $scopeKeyExtracted['scope'];
            $scopeId = $scopeKeyExtracted['scopeId'];

            $this->diag('<bg=yellow>Scope: ' . $scope . '</>');
            $this->diag('<bg=yellow>Scope Id: ' . $scopeId . '</>');
            $this->diag('');

            foreach ($data as $path => $newValue) {
                $currentValue = $this->scopeConfig->getValue($path, $scope, $scopeId);
                $this->diag('Path: <comment>' . $path . '</comment>');
                $this->diag('Current value: <comment>' . $currentValue . '</comment>');
                $this->diag('New value: <comment>' . $newValue . '</comment>');

                if ($currentValue && $newValue === self::DELETE) {
                    $this->configInterface->deleteConfig($path, $scope, $scopeId);
                    $line = sprintf(
                        '<info>[%s] %s -> DELETED</info>',
                        $scopeKey,
                        $path,
                        $newValue ?: 'null'
                    );

                    $this->output->writeln($line);
                    $importedValues++;

                    $totalValues++;
                    $this->diag('');

                    continue;
                }

                //if has changes
                if ($currentValue != $newValue && $newValue !== self::DELETE) {
                    $this->configInterface
                        ->saveConfig($path, $newValue, $scope, $scopeId);
                    $line = sprintf(
                        '<info>[%s] %s -> %s</info>',
                        $scopeKey,
                        $path,
                        $newValue ?: 'null'
                    );
                    $this->output->writeln($line);
                    $importedValues++;
                }
                $totalValues++;
                $this->diag('');
            }
        }

        $this->diag('<info>Total config values: ' . $totalValues . '.</info>');
        $this->diag('<info>Imported: ' . $importedValues . '.</info>');

        return 0;
    }

    /**
     * @param string $yamlFile
     * @param string $env
     * @return array
     */
    private function getEnvData($yamlFile, $env)
    {
        // @codingStandardsIgnoreLine
        if (!file_exists($yamlFile)) {
            throw new \Exception('File ' . $yamlFile . ' does not exists.');
        }
        // @codingStandardsIgnoreLine
        if (!is_readable($yamlFile)) {
            throw new \Exception('File ' . $yamlFile . ' is not readable.');
        }

        $data = \Spyc::YAMLLoad($yamlFile);
        if (!$this->isFileCorrect($data)) {
            throw new \Exception(
                "File format is incorrect.\r\n\r\n"
                . "For example the correct format:\r\n\r\n"
                . "production:\r\n"
                . "    default:\r\n"
                . "        web/secure/base_url: https://domain.com/\r\n"
                . '        web/secure/use_in_frontend: 1'
            );
        }

        if (!isset($data[$env])) {
            $this->output->writeln(
                '<info>The environment doesn\'t exists in the file.'
                . ' Nothing to import</info>'
            );

            return 0;
        }

        return $data[$env];
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @param mixed $envData
     * @return bool
     */
    private function isFileCorrect($envData): bool
    {
        if (!is_array($envData)) {
            return false;
        }

        foreach ($envData as $scopeData) {
            if (!is_array($scopeData)) {
                return false;
            }

            foreach ($scopeData as $data) {
                if (!is_array($data)) {
                    continue;
                }

                foreach ($data as $value) {
                    if ($value === null || (!is_string($value) && !is_numeric($value))) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param string $str
     */
    private function diag($str)
    {
        $this->output->writeln(
            $str,
            \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE
        );
    }

    /**
     * @param string $scopeKey
     * @return array
     */
    private function extractFromScopeKey($scopeKey): array
    {
        $scopeKeyParts = explode('-', $scopeKey);
        $scopeKeyPartsCount = count($scopeKeyParts);

        if ($scopeKeyPartsCount == 1) {
            return [
                'scope' => $scopeKeyParts[0],
                'scopeId' => 0,
            ];
        }

        return [
            'scope' => join('-', array_slice($scopeKeyParts, 0, $scopeKeyPartsCount - 1)),
            'scopeId' => $scopeKeyParts[$scopeKeyPartsCount - 1],
        ];
    }
}
