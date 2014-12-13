<?php
namespace ZabbixReports\MainBundle\Twig;

/**
 * Class ZabbixGraphExtension implements zbx_graph_* TWIG functions.
 *
 * @package ZabbixReports\MainBundle\Twig
 */
class ZbxGraphExtension extends ExtensionBase
{

    /*
     * (non-PHPdoc) @see Twig_ExtensionInterface::getName()
     */
    public function getName()
    {
        return 'Zabbix Graph Extension';
    }

    /*
     * (non-PHPdoc) @see Twig_Extension::getFunctions()
     */
    public function getFunctions()
    {

        /* @var $log LoggerInterface */
        $log = $this->logger;

        $log->debug("registering twig zabbix graph extension");

        $res = array(
            new CachingTwigFunction('zbx_graph_*', function (\Twig_Environment $env, $context, $method, $args) use ($log) {

                $log->debug("start function zbx_graph_$method", $args);

                if ($method == "custom") {
                    $res = $this->getGraphImageById("chart2", $args);
                } else if ($method == "item") {
                    $res = $this->getGraphImageById("chart", $args);
                } else if ($method == "service") {
                    $res = $this->getGraphImageById("chart5", $args);
                } else {
                    throw new \RuntimeException("Unknown graph method: $method");
                }

                $log->debug("end function zbx_graph_$method", array(
                    $res
                ));

                return $res;
            }, $this->container),
        );
        $log->debug("done registering twig zabbix graph extension");
        return $res;
    }

    /**
     * Gets ZABBIX graph data from graphX.php
     *
     * @param $params associative
     *            array of URL parameters passed to ZABBIX graph2.php, atleast graphid is mandatory
     * @param $type one
     *            of "chart", "chart2", "chart5", etc...
     * @return temporary filename that contains graph data
     */
    public function getGraphImageById($type, $params)
    {

        /* @var $log LoggerInterface */
        $log = $this->container->get('logger');

        /* @var $zbx \ZabbixApi */
        $zbx = $this->zbx;

        $log->debug("fetching $type", $params);

        $urcmp = array();
        foreach ($params as $k => $v) {
            $urlcmp [] = "$k=" . urlencode($v);
        }
        $urlargs = implode("&", $urlcmp);

        $url = $this->zbx_url . "/$type.php?$urlargs";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Expect:'
        ));
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, "zbx_sessionid=" . $zbx->getAuth());
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; ZabbixReports)");

        $res = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $is_html = (strpos($res, "html>") > 0);

        if ($is_html) {
            $log->error("got html response from graph url: $url");
            return "ERROR: GOT HTML RESPONSE FROM URL $url";
        } else if ($info != false && !$is_html) {
            $size = strlen($res);
            $code = $info ['http_code'];

            $log->debug("HTTP $code got $size bytes in " . $info ['total_time'] . ' seconds from ' . $info ['url']);

            $outfile = tempnam(sys_get_temp_dir(), "zabbixreports");
            file_put_contents($outfile, $res);

            $log->debug("saved graph data as $outfile");
            return "$outfile";
        } else {
            $log->error("could not get $url");
            return "ERROR: COULD NOT GET $url";
        }
    }


}