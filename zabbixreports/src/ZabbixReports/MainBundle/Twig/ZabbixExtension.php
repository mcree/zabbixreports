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
	
	protected $zbx_url, $zbx_username, $zbx_password;
	
	public function __construct(ContainerInterface $container, $zbx_url, $zbx_username, $zbx_password) {
		$this->container = $container;
		$this->zbx_url=$zbx_url;
		$this->zbx_username=$zbx_username;
		$this->zbx_password=$zbx_password;

		$this->logger = $this->container->get ( 'logger' );
		
		try {
		
			// connect to Zabbix API
			$this->zbx = new ZabbixApi($zbx_url."/api_jsonrpc.php", $zbx_username, $zbx_password);
		
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
				}),
				new \Twig_SimpleFunction('zbx_graph', function($id) {
					return $this->getGraphImageById($id);
				})
		);
	}
	
	private $curl_verbose = true;
	private $zabbix_tmp_cookies = "/tmp/";
	
	/**
	 * Originally developed by Mattias Geniar for the Mobile ZBX project: www.MoZBX.net
	 * 
	 * @param unknown $graphid
	 * @param number $period
	 * @return mixed
	 */
	public function getGraphImageById($graphid, $period = 3600)
	{		
		global $arrSettings;
		
		/* @var $logger LoggerInterface */
		$logger = $this->container->get ( 'logger' );
		
		
		// Cookiename
		$filename_cookie = $this->zabbix_tmp_cookies . "zabbix_cookie_" . $graphid . ".txt";
		$logger->debug("cookiejar: $filename_cookie");
		$ch = curl_init();
		// Add the URL of Zabbix to perform the login to
		curl_setopt($ch, CURLOPT_URL, $this->zbx_url."/");
		// Get the value returned from our curl-call, don't default to stdout
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// Send a POST request
		curl_setopt($ch, CURLOPT_POST, true);
		// Increase verbosity for debugging
		curl_setopt($ch, CURLOPT_VERBOSE, $this->curl_verbose);
		// Don't validate SSL certs as must Zabbix installs that have an SSL connection are self-signed
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		// Lighttpd expects this header
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
		if (in_array(substr( $this->zbx->apiinfoVersion(), 0, 3), array('2.2', '2.0', '1.4'))) {
			/* API Version 1.4 = Zabbix 2.0+ */
			$post_data = array(
					'name' => $this->zbx_username,
					'password' => $this->zbx_password,
					'autologin' => 1,
					'request' => '', /* Why is this empty? Zabbix requires it? */
					'enter' => 'Sign in', /* Zabbix also checks the value of this string ... */
			);
		} else {
			$post_data = array(
					'name' => $this->zbx_username,
					'password' => $this->zbx_password,
					'enter' => 'Enter',
			);
		}
		// Add the POST-data
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $filename_cookie);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $filename_cookie);
// 		if ($this->http_auth) {
// 			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
// 			curl_setopt($ch, CURLOPT_USERPWD, $this->zbx_username . ':' . $this->zbx_password);
// 		}
		// Login
		$logger->debug("logging in",array(curl_exec($ch)));
		// To debug this call, comment out the Header-set in graph_img.php on line 32
		// that way, you'll just return plain text data and no image
		//curl_close($ch);
		// Fetch image
		// &period= the time, in seconds, of the graph (so: value of 7200 = a 2 hour graph to be shown)
		// &stime= the time, in PHP's time() format, from when the graph should begin
		// &width= the width of the graph, small enough to fit on mobile devices
		$url = $this->zbx_url. "/charts.php" . "?graphid=" . $graphid . "&width=450&period=" . $period;
		$logger->debug("getting url: $url");
		curl_setopt($ch, CURLOPT_URL, $url);
		$output = curl_exec($ch);
		
		$logger->debug("result: $output");
		
		// Close session
		curl_close($ch);
		// Delete our cookie
		unlink($filename_cookie);
		// Return the image
		return $output;
	}
	
	
}
