<?php

/**
 * @author Mygento Team
 * @copyright 2017-2020 Mygento (https://www.mygento.ru)
 * @package Mygento_Configsync
 */

namespace Mygento\Configsync\Console\Command;

use Symfony\Component\Console\Question\ChoiceQuestion;

class Dump extends \Symfony\Component\Console\Command\Command
{
    const CONFIG_DIR = 'config';

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configInterface;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    private $directoryList;

    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    private $file;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * @param \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Filesystem\DirectoryList $directoryList
     * @param \Magento\Framework\Filesystem\Io\File $file
     */
    public function __construct(
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Filesystem\DirectoryList $directoryList,
        \Magento\Framework\Filesystem\Io\File $file
    ) {
        parent::__construct();
        $this->configInterface = $configInterface;
        $this->scopeConfig = $scopeConfig;
        $this->directoryList = $directoryList;
        $this->file = $file;
    }

    /**
     * @inheritdoc
     */
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
            )
            ->addArgument(
                'filename',
                \Symfony\Component\Console\Input\InputArgument::OPTIONAL,
                'Name of the output file (in the shop/config dir).'
            );

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $this->output = $output;
        $section = $input->getArgument('section');
        $env = $input->getArgument('env');
        $filename = $input->getArgument('filename');

        $this->output->writeln("<info>Starting dump. Section: {$section}. Env: {$env}</info>");

        $conf = $this->scopeConfig->get('system', 'default/' . $section);

        if (empty($conf)) {
            throw new \Exception('No config.');
        }

        $dump = [];
        foreach ($conf as $index => $item) {
            $addPrefix = function ($key) use ($index, $section) {
                return "${section}/${index}/${key}";
            };
            $keys = array_map($addPrefix, array_keys($item));
            $values = array_values($item);

            $group = array_combine($keys, $values);
            $dump = array_merge($dump, $group);
        }

        $dump = \Spyc::YAMLDump(['default' => $dump]);
        $body = str_replace('    ', '        ', $dump);
        $content = "${env}: \r\n    ${body}";
        $dir = $this->directoryList->getRoot() . DIRECTORY_SEPARATOR
            . self::CONFIG_DIR . DIRECTORY_SEPARATOR;
        $filename = $filename ?? "{$section}_{$env}.yml";

        $this->file->open(['path' => $dir]);

        //Should we overwrite the file?
        if ($this->file->fileExists($filename)) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'File already exists! Overwrite?',
                ['y' => 'Yes', 'n' => 'No'],
                'n'
            );
            $answer = $helper->ask($input, $output, $question);

            if ($answer === 'n') {
                $this->output->writeln('<error>Stop dumping.</error>');

                return 0;
            }
            $this->output->writeln('<info>Trying to overwrite file...</info>');
        }

        $this->file->write($filename, $content);
        $this->output->writeln('<info>Done</info>');

        return 0;
    }
}
