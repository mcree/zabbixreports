<?php

namespace ZabbixReports\MainBundle\Twig;


use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CachingTwigFunction implements a simple wrapper around \Twig_SimpleFunction utilizing a static array as value cache.
 * @package ZabbixReports\MainBundle\Twig
 */
class CachingTwigFunction extends \Twig_SimpleFunction
{

    /**
     * @param $name String twig function name
     * @param $callable callable realization of twig function (expect needs_context and needs_environment to be enabled)
     * @param ContainerInterface $cnt Symfony container reference
     */
    public function __construct($name, $callable, ContainerInterface $cnt)
    {
        $options = array(
            'needs_context' => true,
            'needs_environment' => true
        );

        $c = function (\Twig_Environment $env, $context) use ($name, $callable, $cnt) {
            /* @var $log LoggerInterface */
            $log = $cnt->get('logger');

            static $cache;

            if (!is_array($cache)) {
                $cache = array();
            }

            $ckey = serialize(array_slice(func_get_args(), 2));

            //var_dump($args);

            if (array_key_exists($ckey, $cache)) {
                $cval = $cache [$ckey];
                $log->debug("using cached results for " . func_get_arg(2) . " " . $ckey);
                return $cval;
            }

            $res = call_user_func_array($callable, func_get_args());
            $cache [$ckey] = $res;

            return $res;
        };

        parent::__construct($name, $c, $options);
    }


}