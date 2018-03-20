<?php

namespace Mygento\Configsync\Console\Command;

class Dump extends \Symfony\Component\Console\Command\Command
{
    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configInterface;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Symfony\Component\Yaml\Yaml
     */
    private $yaml;

    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    private $directoryList;

    protected $output;

    public function __construct(
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Filesystem\DirectoryList $directoryList,
        \Symfony\Component\Yaml\Yaml $yaml
    ) {
        parent::__construct();
        $this->configInterface = $configInterface;
        $this->scopeConfig = $scopeConfig;
        $this->yaml = $yaml;
        $this->directoryList = $directoryList;
    }

    protected function configure()
    {
        $this
            ->setName('setup:config:dump')
            ->setDescription('Export Magento configuration')
            ->addArgument(
                'env',
                \Symfony\Component\Console\Input\InputArgument::REQUIRED,
                'Environment name.'
            )
            ->addArgument(
                'section',
                \Symfony\Component\Console\Input\InputArgument::REQUIRED,
                'Name of the section to export its config.'
            );

        parent::configure();
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $this->output = $output;
        $section      = $input->getArgument('section');
        $env          = $input->getArgument('env');

        $this->output->writeln("<info>Starting dump. Section: {$section}. Env: {$env}</info>");

        $conf = $this->scopeConfig->get('system', 'default/' . $section);

        if (empty($conf)) {
            throw new \Exception('No config.');
        }

        $dump = [];
        foreach ($conf as $index => $item) {
            $addPrefix = function ($key) use ($index, $section) {
                return "$section/$index/$key";
            };
            $keys      = array_map($addPrefix, array_keys($item));
            $values    = array_values($item);

            $group = array_combine($keys, $values);
            $dump  = array_merge($dump, $group);
        }

        $dump   = $this->yaml->dump(['default' => $dump]);
        $body   = str_replace('    ', '        ', $dump);
        $output = "$env: \r\n    $body";

        $root     = $this->directoryList->getRoot();
        $filename = "{$root}/config/{$section}_{$env}.yml";

        file_put_contents($filename, $output);

        $this->output->writeln('<info>Done</info>');

        return 0;
    }
}
