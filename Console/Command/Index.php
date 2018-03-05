<?php

namespace Mygento\Configsync\Console\Command;

class Index extends \Symfony\Component\Console\Command\Command
{
    protected $configInterface;
    protected $scopeConfig;
    protected $yaml;
    protected $output;
    protected $displayDiag;

    public function __construct(
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Symfony\Component\Yaml\Parser $yaml
    ) {
        parent::__construct();
        $this->configInterface = $configInterface;
        $this->scopeConfig = $scopeConfig;
        $this->yaml = $yaml;
    }

    protected function configure()
    {
        $this
            ->setName('setup:config:sync')
            ->setDescription('A module to store Magento configuration'
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
            )
            ->addOption(
                'detailed',
                null,
                \Symfony\Component\Console\Input\InputArgument::OPTIONAL,
                'Display detailed information (1 - display, otherwise - not display).',
                getcwd()
            )
        ;

        parent::configure();
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $this->output = $output;

        if ($input->getOption('detailed') == '1') {
            $this->displayDiag = 1;
        }
        
        $env = $input->getArgument('env');
        $yamlFile = $input->getArgument('config_yaml_file');

        if (!file_exists($yamlFile)) {
            throw new \Exception('File ' . $yamlFile . ' does not exists.');
        }
        if (!is_readable($yamlFile)) {
            throw new \Exception('File ' . $yamlFile . ' is not readable.');
        }

        $envData = $this->yaml->parse(file_get_contents($yamlFile));
        if (!$this->isFileCorrect($envData)) {
            throw new \Exception(
                "File format is incorrect.\r\n\r\n"
                ."For example the correct format:\r\n\r\n"
                ."production:\r\n"
                ."    default:\r\n"
                ."        web/secure/base_url: https://domain.com/\r\n"
                ."        web/secure/use_in_frontend: 1"
            );
        }
        $totalValues = 0;
        $importedValues = 0;

        if (!isset($envData[$env])) {
            throw new \Exception('Environment "' . $env . '" doesn\'t exists.');
        }
        $scopeData = $envData[$env];

        foreach ($scopeData as $scopeKey => $data) {
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
                    $this->diag('<question>New value: ' . $newValue . '</question>');
                    $importedValues++;
                }
                $totalValues++;
                $this->diag('');
            }
        }

        $output->writeln('<info>Total config values: ' . $totalValues . '.</info>');
        $output->writeln('<info>Imported: ' . $importedValues . '.</info>');

        return 0;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function isFileCorrect($envData)
    {
        if (!is_array($envData)) {
            return false;
        }

        foreach ($envData as $env => $scopeData) {
            if (!is_array($scopeData)) {
                return false;
            }

            foreach ($scopeData as $scopeKey => $data) {
                if (!is_array($data)) {
                    return false;
                }

                foreach ($data as $path => $value) {
                    if (is_string($value) && is_numeric($value) && is_null($value)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function diag($str)
    {
        if ($this->displayDiag) {
            $this->output->writeln($str);
        }
    }

    public static function extractFromScopeKey($scopeKey)
    {
        $scopeKeyParts = explode("-", $scopeKey);
        $scopeKeyPartsCount = count($scopeKeyParts);

        if ($scopeKeyPartsCount == 1) {
            return [
                'scope'   => $scopeKeyParts[0],
                'scopeId' => 0,
            ];
        }
        return [
            'scope'   => join("-", array_slice($scopeKeyParts, 0, $scopeKeyPartsCount - 1)),
            'scopeId' => $scopeKeyParts[$scopeKeyPartsCount-1],
        ];
    }
}
