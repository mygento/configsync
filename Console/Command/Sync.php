<?php

/**
 * @author Mygento Team
 * @copyright 2017-2018 Mygento (https://www.mygento.ru)
 * @package Mygento_Configsync
 */

namespace Mygento\Configsync\Console\Command;

class Sync extends \Symfony\Component\Console\Command\Command
{
    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configInterface;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    protected $output;

    public function __construct(
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct();
        $this->configInterface = $configInterface;
        $this->scopeConfig = $scopeConfig;
    }

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
            if (!preg_match('/^(default|(website|stores)-\d+)$/', $scopeKey)) {
                $this->diag('<error>Skipped scope: ' . $scopeKey . '</error>');
                continue;
            }

            $scopeKeyExtracted = $this->extractFromScopeKey($scopeKey);
            $scope = $scopeKeyExtracted['scope'];
            $scopeId = $scopeKeyExtracted['scopeId'];

            $this->diag('<bg=cyan>Scope: ' . $scope . '</>');
            $this->diag('<bg=cyan>Scope Id: ' . $scopeId . '</>');
            $this->diag('');

            foreach ($data as $path => $newValue) {
                $currentValue = $this->scopeConfig->getValue(
                    $path,
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
                $this->diag('Path: <comment>' . $path . '</comment>');
                $this->diag('Current value: <comment>' . $currentValue . '</comment>');

                //if has changes
                if ($currentValue != $newValue) {
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

    public function getEnvData($yamlFile, $env)
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
     */
    public function isFileCorrect($envData)
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
                    return false;
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

    public function diag($str)
    {
        $this->output->writeln(
            $str,
            \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE
        );
    }

    public static function extractFromScopeKey($scopeKey)
    {
        $scopeKeyParts = explode('-', $scopeKey);
        $scopeKeyPartsCount = count($scopeKeyParts);

        if ($scopeKeyPartsCount == 1) {
            return [
                'scope'   => $scopeKeyParts[0],
                'scopeId' => 0,
            ];
        }
        return [
            'scope'   => join('-', array_slice($scopeKeyParts, 0, $scopeKeyPartsCount - 1)),
            'scopeId' => $scopeKeyParts[$scopeKeyPartsCount-1],
        ];
    }
}
