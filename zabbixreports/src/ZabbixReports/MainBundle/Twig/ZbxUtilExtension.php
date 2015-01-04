<?php

namespace ZabbixReports\MainBundle\Twig;

use Symfony\Bundle\TwigBundle\Loader\FilesystemLoader;

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
            }),
            new \Twig_SimpleFunction('data_uri', function($env, $uri, $mime = "image/jpeg") {
                /* @var $env \Twig_Environment */
                $loader = $env->getLoader();
                /* @var $loader FilesystemLoader */
                if($loader->exists($uri)) {
                    $contents = $loader->getSource($uri);
                } else {
                    $contents = file_get_contents($uri);
                }
                $base64   = base64_encode($contents);
                return ('data:' . $mime . ';base64,' . $base64);
            }, array('needs_environment' => true))
        );
        $log->debug("done registering twig zabbix utility extension");
        return $res;
    }

}