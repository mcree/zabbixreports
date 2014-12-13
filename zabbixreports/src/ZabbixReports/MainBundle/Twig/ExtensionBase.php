<?php

namespace ZabbixReports\MainBundle\Twig;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use ZabbixReports\MainBundle\ZabbixApi\ZabbixApi;

/**
 * Base class for all zabbix TWIG extensions
 *
 * @author mcree
 */
abstract class ExtensionBase extends \Twig_Extension
{

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
     *            Symfony container reference
     * @param unknown $zbx_url
     *            ZABBIX base URL (eg: http://tl.d/zabbix)
     * @param unknown $zbx_username
     *            ZABBIX user name
     * @param unknown $zbx_password
     *            ZABBUX password
     */
    function __construct(ContainerInterface $container, LoggerInterface $logger, ZabbixApi $zbx)
    {
        $this->logger = $logger;
        $this->container = $container;
        $this->zbx = $zbx;
        $this->zbx_url = $zbx->getApiRootUrl();
    }

    /**
     * @param $callable
     * @return mixed
     */
    public function run_with_cache($callable)
    {
        /* @var $logger LoggerInterface */
        $logger = $this->container->get('logger');

        static $cache;

        if (!is_array($cache)) {
            $cache = array();
        }

        $args = func_get_args();
        array_shift($args);
        $logger->debug("start cached function", $args);
        $ckey = spl_object_hash($callable) . serialize($args);

        if (array_key_exists($ckey, $cache)) {
            $cval = $cache [$ckey];
            return $cval;
        }

        $res = call_user_func_array($callable, $args);
        $cache [$ckey] = $res;

        return $res;
    }

}
