<?php

namespace ZabbixReports\MainBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ZabbixApiExtension wraps ZabbixApi and provides zabbix_* TWIG functions.
 *
 * @package ZabbixReports\MainBundle\Twig
 */
class ZabbixApiExtension extends ExtensionBase {

	/*
	 * (non-PHPdoc) @see Twig_ExtensionInterface::getName()
	 */
	public function getName() {
		return 'Zabbix API Extensions';
	}
	
	/*
	 * (non-PHPdoc) @see Twig_Extension::getFunctions()
	 */
	public function getFunctions() {
		
		/* @var $this->logger LoggerInterface */
		$this->logger->debug("registering twig zabbix api extension" );
		
		$res = array (
				new \Twig_SimpleFunction ( 'zabbix_*', function () {

					static $cache;

					if (!is_array($cache)) {
						$cache = array();
					}

					/* @var $logger LoggerInterface */
					$logger = $this->container->get('logger');
					
					$method = str_replace ( '_', '.', func_get_arg ( 2 ) );
					$args = func_get_arg ( 3 );
					$ckey = serialize ( $method ) . serialize ( $args );
					
					if (array_key_exists ( $ckey, $cache )) {
						$cval = $cache [$ckey];
						$logger->debug ( "using cache for function zabbix_$method", array (
								$cval 
						) );
						return $cval;
					}
					
					$logger->debug ( "start function zabbix_$method", $args );
					
					if ($method == "service.get.deep") {
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
					$cache [$ckey] = $res;
					
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
		$this->logger->debug ( "done registering twig zabbix extension" );
                return $res;
        }
	
	/**
	 * Recursively walk the Zabbix IT Service tree and collect all serviceids directly or indirectly under a list of given services
	 *
	 * @param unknown $serviceids
	 *        	array of service ids to walk from
	 * @return array of service ids
	 */
	public function zabbix_service_get_deep($serviceids) {
		static $srvs; // service cache
		
		/* @var $zbx \ZabbixApi */
		$zbx = $this->zbx;
		
		if (! isset ( $srvs )) {
			$srvs = $zbx->request ( "service.get", array (
					"selectDependencies" => "extend" 
			) );
			/* @var $this->logger LoggerInterface */
			$this->logger->debug ( "pre-filled service cache", $srvs );
		}
		
		$ids = array ();
		foreach ( $srvs as $r ) {
			$id = $r->serviceid;
			
			if (array_search ( $id, $serviceids ) !== false) {
				$ids [] = $id;
				$dwnids = array ();
				foreach ( $r->dependencies as $dep ) {
					$dwnids [] = $dep->servicedownid;
				}
				$a = $this->zabbix_service_get_deep ( $dwnids );
				
				$ids = array_merge ( $ids, $a );
			}
		}
		
		return $ids;
	}

}
