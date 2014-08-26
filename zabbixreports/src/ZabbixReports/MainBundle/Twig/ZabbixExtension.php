<?php

namespace ZabbixReports\MainBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

// load ZabbixApi
require 'ZabbixApiAbstract.class.php';
require 'ZabbixApi.class.php';

class ZabbixExtension extends \Twig_Extension {

	/* @var $logger LoggerInterface */
	protected $logger;
	
	/* @var $container ContainerInterface */
	protected $container;
	
	/* @var $zbx ZabbixApi */
	protected $zbx;
	
	public function __construct(ContainerInterface $container, $zbx_url, $zbx_username, $zbx_password) {
		$this->container = $container;

		$this->logger = $this->container->get ( 'logger' );
		
		try {
		
			// connect to Zabbix API
			$this->zbx = new ZabbixApi($zbx_url, $zbx_username, $zbx_password);
		
		} catch(Exception $e) {
		
			// Exception in ZabbixApi catched
			$this->logger->critical("Exception in ZabbixApi",array($e->getMessage()));
		
		}
		
	}
	
	/*
	 * (non-PHPdoc) @see Twig_ExtensionInterface::getName()
	 */
	public function getName() {
		return 'Zabbix Extensions';
	}
	
	/*
	 * (non-PHPdoc) @see Twig_Extension::getFunctions()
	 */
	public function getFunctions() {

		/* @var $this->logger LoggerInterface */
		$this->logger->debug("registering twig zabbix extension");
		
		return array (
				new \Twig_SimpleFunction ( 'zabbix_*', function () {
					
					/* @var $logger LoggerInterface */
					$logger = $this->container->get ( 'logger' );
					
					$method = str_replace('_', '.',func_get_arg(2));
					$args = func_get_arg(3);
					
					$logger->debug("start function zabbix_$method",$args);
					
					/* @var $zbx ZabbixApi */
					$zbx = $this->zbx;
					
					//$zbx->printCommunication(true);
					
					//$zbx->serviceGet(array("output"=>"extend"));
					
					$res = $zbx->request($method,$args);
					
					$logger->debug("end function zabbix_$method",array($res));

					return $res;
					
				}, array (
						'needs_context' => true,
						'needs_environment' => true 
				) ),
				new \Twig_SimpleFunction('secs_to_dateinterval', function($secs) {
					
					$dt1 = new \DateTime();
					$dt2 = clone $dt1;
					$dt2->add(new \DateInterval('PT'.$secs.'S'));
					return date_diff($dt1, $dt2);
				})
		);
	}
}
