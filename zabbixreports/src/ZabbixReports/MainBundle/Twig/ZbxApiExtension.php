<?php

namespace ZabbixReports\MainBundle\Twig;

use Psr\Log\LoggerInterface;

/**
 * Class ZbxApiExtension wraps ZabbixApi and provides zbx_api_* TWIG functions.
 *
 * @package ZabbixReports\MainBundle\Twig
 */
class ZbxApiExtension extends ExtensionBase
{

    /*
     * (non-PHPdoc) @see Twig_ExtensionInterface::getName()
     */
    public function getName()
    {
        return 'Zabbix API Extensions';
    }

    /*
     * (non-PHPdoc) @see Twig_Extension::getFunctions()
     */
    public function getFunctions()
    {
        /* @var $log LoggerInterface */
        $log = $this->container->get('logger');

        $log->debug("registering twig zabbix api extension");

        $f = function (\Twig_Environment $env, $context, $method, $args) use ($log) {

            $method = str_replace('_', '.', $method);
            $log->debug("start function zbx_api_$method", $args);

            /* @var $zbx \ZabbixApi */
            $zbx = $this->zbx;
            // $zbx->printCommunication(true);
            $res = $zbx->request($method, $args);

            $log->debug("end function zbx_api_$method", array(
                $res
            ));

            return $res;
        };

        $res = array(
            new CachingTwigFunction('zbx_api_*', $f, $this->container)
        );
        $log->debug("done registering twig zabbix api extension");
        return $res;
    }


}
