<?php

namespace ZabbixReports\MainBundle\Twig;

/**
 * Class ZbxUtilExtension implements misc. utilities for zabbixreports.
 * @package ZabbixReports\MainBundle\Twig
 */
class ZbxUtilExtension extends ExtensionBase {

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'Zabbix Utility Extensions';
    }

    /*
 * (non-PHPdoc) @see Twig_Extension::getFunctions()
 */
    public function getFunctions()
    {
        /* @var $log LoggerInterface */
        $log = $this->container->get('logger');

        $log->debug("registering twig zabbix utility extension");

        $res = array(
            new \Twig_SimpleFunction ('secs_to_dateinterval', function ($secs) {

                $dt1 = new \DateTime ();
                $dt2 = clone $dt1;
                $dt2->add(new \DateInterval ('PT' . $secs . 'S'));
                return date_diff($dt1, $dt2);
            })
        );
        $log->debug("done registering twig zabbix utility extension");
        return $res;
    }

}