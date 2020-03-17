<?php

namespace Fajuu\Serve\Commands;

use Flarum\Console\AbstractCommand;
use Symfony\Bundle\WebServerBundle\WebServer;
use Symfony\Bundle\WebServerBundle\WebServerConfig;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class ServeCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('serve')
      ->setDescription('Serve the application on the PHP development server')
      ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on.', '127.0.0.1')
      ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port to serve the application on.', 80);
    }

    protected function fire()
    {
        $input = $this->input;
        $output = $this->output;

        $io = new SymfonyStyle($input, $output);

        $callback = null;
        $disableOutput = false;

        if ($output->isQuiet()) {
            $disableOutput = true;
        } else {
            $callback = function ($type, $buffer) use ($output) {
                if (Process::ERR === $type && $output instanceof ConsoleOutputInterface) {
                    $output = $output->getErrorOutput();
                }
                $output->write($buffer, false, OutputInterface::OUTPUT_RAW);
            };
        }

        try {
            $server = new WebServer();

            $config = new WebServerConfig(app()->publicPath(), 'dev', $input->getOption('host').':'.$input->getOption('port'));

            $getAddress = 'http://'.$config->getAddress();
            $message = sprintf('Server listening on %s', $getAddress);

            if ('' !== ($displayAddress = $config->getDisplayAddress())) {
                $message = sprintf('Server listening on all interfaces, port %s -- see http://%s', $config->getPort(), $displayAddress);
            }
            $io->success($message);

            if(!empty(app('flarum.config'))) {
                if (!$this->checkAddress($getAddress, app('flarum.config')['url']) and $this->parseAddress(app('flarum.config')['url']) == '127.0.0.1:80') {
                    $io->note('Use address from configuration: '.app('flarum.config')['url']);
                }

                if ($this->checkAddress($getAddress, app('flarum.config')['url'])) {
                    $io->warning('The url in `config.php` is different from the server address. This may cause flarum errors.');
                }
            }

            if (ini_get('xdebug.profiler_enable_trigger')) {
                $io->comment('Xdebug profiler trigger enabled.');
            }
            $io->comment('Quit the server with CONTROL-C.');

            $exitCode = $server->run($config, $disableOutput, $callback);
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return 1;
        }

        return $exitCode;
    }

    protected function checkAddress($serverAddr, $configAddr)
    {
        $configAddr = $this->parseAddress($configAddr);
        $serverAddr = $this->parseAddress($serverAddr);

        if ($serverAddr == $configAddr) {
            return false; // if the same
        }

        return true; // else
    }

    protected function parseAddress($addr)
    {
        if (strpos($addr, 'http://') !== false) {
            $addr = str_replace('http://', null, $addr);
            $port = 80;
        }
        if (strpos($addr, 'https://') !== false) {
            $addr = str_replace('https://', null, $addr);
            $port = 443;
        }

        if (strpos($addr, ':') !== false) {
            $port = (int) explode(':', $addr, 2)[1];
            $addr = explode(':', $addr, 2)[0];
        }

        if ($addr == 'localhost' or $addr == '127.0.0.1') {
            $addr = '127.0.0.1';
        }

        return $addr.':'.$port;
    }
}
