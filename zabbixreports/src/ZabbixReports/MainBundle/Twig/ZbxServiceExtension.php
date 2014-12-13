<?php

namespace ZabbixReports\MainBundle\Twig;


class ZbxServiceExtension extends ExtensionBase
{

    /*
     * (non-PHPdoc) @see Twig_ExtensionInterface::getName()
     */
    public function getName()
    {
        return 'Zabbix Service Extensions';
    }

    /*
    * (non-PHPdoc) @see Twig_Extension::getFunctions()
    */
    public function getFunctions()
    {

        /* @var $log LoggerInterface */
        $log = $this->logger;

        $log->debug("registering twig zabbix service extension");

        $res = array(
            new CachingTwigFunction('zbx_service_*', function (\Twig_Environment $env, $context, $method, $args) use ($log) {

                $log->debug("start function zbx_service_$method", $args);

                if ($method == "get_deep") {
                    $res = $this->zabbix_service_get_deep($args);
                } else {
                    throw new \RuntimeException("Unknown service method: $method");
                }

                $log->debug("end function zbx_service_$method", array(
                    $res
                ));
                return $res;
            }, $this->container),
        );
        $log->debug("done registering twig zabbix service extension");
        return $res;
    }

    /**
     * Recursively walk the Zabbix IT Service tree and collect all serviceids directly or indirectly under a list of given services
     *
     * @param unknown $serviceids
     *            array of service ids to walk from
     * @return array of service ids
     */
    public function zabbix_service_get_deep($serviceids)
    {
        static $srvs; // service cache

        /* @var $zbx \ZabbixApi */
        $zbx = $this->zbx;

        if (!isset ($srvs)) {
            $srvs = $zbx->request("service.get", array(
                "selectDependencies" => "extend"
            ));
            /* @var $this ->logger LoggerInterface */
            $this->logger->debug("pre-filled service cache", $srvs);
        }

        $ids = array();
        foreach ($srvs as $r) {
            $id = $r->serviceid;

            if (array_search($id, $serviceids) !== false) {
                $ids [] = $id;
                $dwnids = array();
                foreach ($r->dependencies as $dep) {
                    $dwnids [] = $dep->servicedownid;
                }
                $a = $this->zabbix_service_get_deep($dwnids);

                $ids = array_merge($ids, $a);
            }
        }

        return $ids;
    }


}