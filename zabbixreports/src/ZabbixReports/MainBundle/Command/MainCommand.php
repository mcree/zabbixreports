<?php

namespace ZabbixReports\MainBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\TwigBundle;
use TFox\MpdfPortBundle\Service\MpdfService;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Bundle\TwigBundle\Loader\FilesystemLoader;

class MainCommand extends ContainerAwareCommand {

	/* (non-PHPdoc)
	 * @see \Symfony\Component\Console\Command\Command::configure()
	 */
	protected function configure() {
		$this->setName ( 'zabbixreports:main' )
		->setDescription ( 'Generate PDF Zabbix report based on template' )
		->addOption ( 'template', 't', InputOption::VALUE_REQUIRED, 'Report template' )
		->addOption ( 'out', 'o', InputOption::VALUE_REQUIRED, 'Output PDF filename' )
		->addOption ( 'param', 'p', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Parameter passed to TWIG template in "key:value" format', array () );
	}

	/* (non-PHPdoc)
	 * @see \Symfony\Component\Console\Command\Command::execute()
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$input->validate ();
		
		/* @var $logger LoggerInterface */
		$logger = $this->getContainer ()->get ( 'logger' );
		
		$logger->info ( "Starting..." );
		
		$logger->debug ( "Commandline options:", $input->getOptions () );
		
		// parse template variables:
		$vars = array ();
		foreach ( $input->getOption ( "param" ) as $param ) {
			$var = explode ( ':', $param, 2 );
			if (count ( $var ) != 2) {
				throw new \ErrorException("cannot parse template variable: '$param'");
			} else {
				$logger->debug ( "passing template variable:", $var );
				$vars [$var [0]] = $var [1];
			}
		}
		
		/* @var $mpdfService MpdfService */
		$mpdfService = $this->getContainer ()->get ( 'tfox.mpdfport' );
		
		/* @var $engine TwigEngine */
		$engine = $this->getContainer ()->get ( 'templating' );

		$templatedir = dirname($this->getContainer()->get('kernel')->getRootdir()).DIRECTORY_SEPARATOR."templates";
		$logger->debug("templatedir: $templatedir");

		$template = $input->getOption ( "template" );
		
		/* @var $loader FilesystemLoader */
		$loader = $this->getContainer()->get('twig.loader');
		$loader->setPaths(array(
				$templatedir.DIRECTORY_SEPARATOR."common",
				dirname($template)
		));
		//$this->container->get('twig.loader')->addPath('../../../../web/templates/', $namespace = '__main__');

		$vars["TEMPLATEDIR"]=$templatedir;
		$vars["BASE"]=dirname($template);
		$html = $engine->render ( basename($template), $vars );		
		
		$mpdfopts = array (
				'constructorArgs' => array (), // Constructor arguments. Numeric array. Don't forget about points 2 and 3 in Warning section!
				'writeHtmlMode' => null, // $mode argument for WriteHTML method
				'writeHtmlInitialise' => null, // $mode argument for WriteHTML method
				'writeHtmlClose' => null, // $close argument for WriteHTML method
				'outputFilename' => $input->getOption ( 'out' ), // $filename argument for Output method
				'outputDest' => 'F'  // $dest argument for Output method
				);
				
		$logger->debug ( "HTML Template: $html" );
		
		$pdf = $mpdfService->generatePdf ( $html, $mpdfopts );
		
		$logger->info ( "Done." );
		
		return null;
	}
}