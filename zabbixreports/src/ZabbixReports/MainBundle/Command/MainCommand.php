<?php

namespace ZabbixReports\MainBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MainCommand extends ContainerAwareCommand {
	
	protected function configure() {
		$this
		->setName ( 'zabbixreports:main' )
		->setDescription ( 'Generate PDF Zabbix report based on template' )
		->addOption ( 'out', 'o', InputOption::VALUE_REQUIRED, 'Output PDF filename' );
	}
	
	protected function execute(InputInterface $input, OutputInterface $output) {
		$input->validate();
		
		$output->writeln( "Starting..." );
		
		/* @var $mpdfService TFox\MpdfPortBundle\Service\MpdfService */
		$mpdfService = $this->getContainer()->get('tfox.mpdfport');
		
		/* @var $engine Symfony\Bundle\TwigBundle\Debug\TimedTwigEngine */
		$engine = $this->getContainer()->get('templating');
		$html = $engine->render("report1/report.html.twig");
		
		$mpdfopts = array(
				'constructorArgs' => array (), // Constructor arguments. Numeric array. Don't forget about points 2 and 3 in Warning section!
				'writeHtmlMode' => null, // $mode argument for WriteHTML method
				'writeHtmlInitialise' => null, // $mode argument for WriteHTML method
				'writeHtmlClose' => null, // $close argument for WriteHTML method
				'outputFilename' => $input->getOption('out'), // $filename argument for Output method
				'outputDest' => 'F'  // $dest argument for Output method
				);
		
		$pdf = $mpdfService->generatePdf($html, $mpdfopts);
		
		$output->writeln("Done.");
		
		return null;
	}
}