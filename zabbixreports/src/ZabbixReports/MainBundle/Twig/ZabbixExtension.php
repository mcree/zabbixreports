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
	protected $zbx_url;
	
	/**
	 * Create Zabbix TWIG extension instance
	 *
	 * @param ContainerInterface $container
	 *        	Symfony container reference
	 * @param unknown $zbx_url
	 *        	ZABBIX base URL (eg: http://tl.d/zabbix)
	 * @param unknown $zbx_username
	 *        	ZABBIX user name
	 * @param unknown $zbx_password
	 *        	ZABBUX password
	 */
	public function __construct(ContainerInterface $container, $zbx_url, $zbx_username, $zbx_password) {
		$this->container = $container;
		$this->zbx_url = $zbx_url;
		
		$this->logger = $this->container->get ( 'logger' );
		
		try {
			
			$this->logger->debug ( "connecting to $zbx_url as $zbx_username" );
			
			// connect to Zabbix API
			$this->zbx = new \ZabbixApi ( $zbx_url . "/api_jsonrpc.php", $zbx_username, $zbx_password );
		} catch ( Exception $e ) {
			
			// Exception in ZabbixApi catched
			$this->logger->critical ( "Exception in ZabbixApi", array (
					$e->getMessage () 
			) );
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
		$this->logger->debug ( "registering twig zabbix extension" );
		
		return array (
				new \Twig_SimpleFunction ( 'zabbix_*', function () {
					
					/* @var $logger LoggerInterface */
					$logger = $this->container->get ( 'logger' );
					
					$method = str_replace ( '_', '.', func_get_arg ( 2 ) );
					$args = func_get_arg ( 3 );
					
					$logger->debug ( "start function zabbix_$method", $args );
					
					if ($method == "graph") {
						$res = $this->getGraphImageById ( "chart2", $args );
					} else if ($method == "itemgraph") {
						$res = $this->getGraphImageById ( "chart", $args );
					} else if ($method == "servicegraph") {
						$res = $this->getGraphImageById ( "chart5", $args );
					} else if ($method == "service.get.deep") {
						$res = $this->zabbix_service_get_deep ( $args );
					} else {
						/* @var $zbx \ZabbixApi */
						$zbx = $this->zbx;
						// $zbx->printCommunication(true);
						$res = $zbx->request ( $method, $args );
					}
					
					$logger->debug ( "end function zabbix_$method", array (
							$res 
					) );
					
					return $res;
				}, array (
						'needs_context' => true,
						'needs_environment' => true 
				) ),
				new \Twig_SimpleFunction ( 'secs_to_dateinterval', function ($secs) {
					
					$dt1 = new \DateTime ();
					$dt2 = clone $dt1;
					$dt2->add ( new \DateInterval ( 'PT' . $secs . 'S' ) );
					return date_diff ( $dt1, $dt2 );
				} ) 
		);
	}
	
	/**
	 * Recursively walk the Zabbix IT Service tree and collect all serviceids directly or indirectly under a list of given services
	 *
	 * @param unknown $serviceids
	 *        	array of service ids to walk from
	 * @return array of service ids
	 */
	public function zabbix_service_get_deep($servideids) {
		/* @var $zbx \ZabbixApi */
		$zbx = $this->zbx;
		
		$res = $zbx->request ( "service.get", array (
				"serviceids" => $servideids,
				"selectDependencies" => "extend" 
		) );
		
		$ids = array ();
		foreach ( $res as $r ) {
			$ids [] = $r->serviceid;
			
			$dwnids = array ();
			foreach ( $r->dependencies as $dep ) {
				$dwnids [] = $dep->servicedownid;
			}
			$a = $this->zabbix_service_get_deep ( $dwnids );
			
			$ids = array_merge ( $ids, $a );
		}
		
		return $ids;
	}
	
	// https://zabbix2.hbit.sztaki.hu/zabbix/chart.php?itemid=23699&period=2592000&stime=20140730123648&updateProfile=1&profileIdx=web.item.graph&profileIdx2=23699&sid=c2bc8d1b26333f3e&width=1616
	
	/**
	 * Gets ZABBIX graph data from graphX.php
	 *
	 * @param $params associative
	 *        	array of URL parameters passed to ZABBIX graph2.php, atleast graphid is mandatory
	 * @param $type one
	 *        	of "chart", "chart2", "chart5", etc...
	 * @return temporary filename that contains graph data
	 */
	public function getGraphImageById($type, $params) {
		
		/* @var $logger LoggerInterface */
		$logger = $this->container->get ( 'logger' );
		
		/* @var $zbx \ZabbixApi */
		$zbx = $this->zbx;
		
		$logger->debug ( "fetching $type $graphid", $params );
		
		$urcmp = array ();
		foreach ( $params as $k => $v ) {
			$urlcmp [] = "$k=" . urlencode ( $v );
		}
		$urlargs = implode ( "&", $urlcmp );
		
		$url = $this->zbx_url . "/$type.php?$urlargs";
		
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_VERBOSE, false );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, array (
				'Expect:' 
		) );
		curl_setopt ( $ch, CURLOPT_BINARYTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_COOKIE, "zbx_sessionid=" . $zbx->getAuth () );
		curl_setopt ( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; ZabbixReports)" );
		
		$res = curl_exec ( $ch );
		$info = curl_getinfo ( $ch );
		curl_close ( $ch );
		
		$is_html = (strpos ( $res, "html>" ) > 0);
		
		if ($is_html) {
			$logger->error ( "got html response from graph url: $url" );
			return "ERROR: GOT HTML RESPONSE FROM URL $url";
		} else if ($info != false && ! $is_html) {
			$size = strlen ( $res );
			$code = $info ['http_code'];
			
			$logger->debug ( "HTTP $code got $size bytes in " . $info ['total_time'] . ' seconds from ' . $info ['url'] );
			
			$outfile = tempnam ( sys_get_temp_dir (), "zabbixreports" );
			file_put_contents ( $outfile, $res );
			
			$logger->debug ( "saved graph data as $outfile" );
			return "$outfile";
		} else {
			$logger->error ( "could not get $url" );
			return "ERROR: COULD NOT GET $url";
		}
	}
}
