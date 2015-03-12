<?php

namespace ZabbixReports\MainBundle\Twig;


class ZbxStatsExtension extends ExtensionBase {

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'Zabbix Statistics Extensions';
    }


    public function getFunctions()
    {
        /* @var $log LoggerInterface */
        $log = $this->container->get('logger');

        static $data=array();

        $log->debug("registering twig zabbix stats add extension");

        $f1 = function ($key,$value) use ($log,&$data) {

            $log->debug("start function zbx_stats_add", array($key,$value));

            $data[$key][]=$value;

            $log->debug("end function zbx_stats_add", array(
                $data
            ));

            return $data;
        };

        $f2 = function ($args) use ($log,&$data) {

            $log->debug("start function zbx_stats_reset", array($args));

            foreach(array_keys($data) as $key) {
                if(preg_match($args,$key)) {
                    $data[$key]=array();
                }
            }

            $log->debug("end function zbx_stats_reset", array(
                $data
            ));

            return $data;
        };

        $f3 = function ($args) use ($log,&$data) {

            $log->debug("start function zbx_stats_average", array($args));

            $stats = new \PHPStats\Stats();

            $res = $stats->average($data[$args]);

            $log->debug("end function zbx_stats_average", array(
                $res
            ));

            return $res;
        };

        $f4 = function ($args) use ($log,$data) {

            $log->debug("start function zbx_stats_gaverage", array($args));

            $stats = new \PHPStats\Stats();

            $res = $stats->gaverage($data[$args]);

            $log->debug("end function zbx_stats_gaverage", array(
                $res
            ));

            return $res;
        };

        $f5 = function ($args) use ($log,$data) {

            $log->debug("start function zbx_stats_sum", array($args));

            $stats = new \PHPStats\Stats();

            $res = $stats->sum($data[$args]);

            $log->debug("end function zbx_stats_sum", array(
                $res
            ));

            return $res;
        };

        $res = array(
            new \Twig_SimpleFunction('zbx_stats_add', $f1),
            new \Twig_SimpleFunction('zbx_stats_reset', $f2),
            new \Twig_SimpleFunction('zbx_stats_average', $f3),
            new \Twig_SimpleFunction('zbx_stats_gaverage', $f4),
            new \Twig_SimpleFunction('zbx_stats_sum', $f5),
        );
        $log->debug("done registering twig zabbix stats extension");
        return $res;

    }

}