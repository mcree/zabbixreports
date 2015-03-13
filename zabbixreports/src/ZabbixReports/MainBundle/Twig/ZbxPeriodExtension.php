<?php

namespace ZabbixReports\MainBundle\Twig;
use ZabbixReports\MainBundle\Cache\ZbxCache;

/**
 * Class ZbxPeriodExtension
 * @package ZabbixReports\MainBundle\Twig
 */
class ZbxPeriodExtension extends ExtensionBase {

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'Zabbix Event Period Extensions';
    }

    static function format_secs($secs)
    {
        $dt1 = new \DateTime ();
        $dt2 = clone $dt1;
        $dt2->add(new \DateInterval ('PT' . $secs . 'S'));
        return date_diff($dt1, $dt2)->format('%ad %H:%I:%S');
    }


    function period_get_stats($data) {
        /* @var $log LoggerInterface */
        $log = $this->container->get('logger');

        $log->debug("start function zbx_period_get_stats", $data);

        $stats=array();

        $s = new \PHPStats\Stats();

        foreach($data as $period) {
            $secs['all'][]=$period["secs"];
            $secs[$period["state"]][]=$period["secs"];
        }

        foreach($secs as $key => $value) {
            $stats[$key]["values"] = $value;
            $stats[$key]["count"] = count($value);
            $stats[$key]["max"] = max($value);
            $stats[$key]["min"] = min($value);
            $stats[$key]["average"] = $s->average($value);
        }

        $log->debug("end function zbx_period_get_stats", $stats);

        return $stats;
    }

    /*
     * (non-PHPdoc) @see Twig_Extension::getFunctions()
     */
    public function getFunctions()
    {
        /* @var $log LoggerInterface */
        $log = $this->container->get('logger');

        /* @var $cache ZbxCache */
        $cache = $this->container->get('zbx_cache');

        $log->debug("registering twig zabbix period extension");

        /**
         * Generate sequence of time periods from sequence of events for a given closed time interval.
         *
         * Mandatory keys in $args:
         * - objectids
         * - time_from
         * - time_till
         *
         * Allowed keys in $args:
         * - groupids
         * - hostids
         * - object
         * - source
         *
         * @param \Twig_Environment $env
         * @param $context
         * @param $args
         * @return mixed
         */
        $f = function (\Twig_Environment $env, $context, $args) use ($log,$cache) {

            $log->debug("start function zbx_period_get", $args);

            /* @var $zbx \ZabbixApi */
            $zbx = $this->zbx;
            // $zbx->printCommunication(true);

            if(array_key_exists("objectids",$args)) {
                $objectids = $args["objectids"];
            } else { // todo: run query for object ids
                throw new \RuntimeException("query arguments 'objectids', 'time_from' and 'time_till' are mandatory");
            }

            $time_from = $args["time_from"];
            $time_till = $args["time_till"];

            $allowed = ["groupids","hostids","object","source"];
            foreach($allowed as $evarg) {
                if(array_key_exists($evarg,$args)) {
                    $evargs[$evarg] = $args[$evarg];
                }
            }
            $objectids = array_unique($objectids);

            $data=array();

            // treat events by each source object individually
            foreach($objectids as $objectid) {

                if($objectid == 0) {
                    continue;
                }

                // query last event before time interval to get initial state
                $evargs["output"]="extend";
                $evargs["sortfield"]="clock";
                $evargs["sortorder"]="DESC";
                $evargs["objectids"]=$objectid;
                $evargs["time_from"]=$time_till;
                $evargs["limit"]=1;
                unset($evargs["time_till"]);
                $evs = $zbx->request('event.get', $evargs);

                // synthesize initial event record (all OK, 0 secs)
                $irec=array(
                    "start_clock" => $time_from,
                    "end_clock" => $time_from,
                    "secs" => 0,
                    "acknowledged" => 0,
                    "objectid" => $objectid,
                    "prev_state" => 0,
                    "state" => 0,
                    "next_state" => 0
                );
//                if(count($evs) == 0) {
//                    $irec["prev_state"]=0;
//                    $irec["state"]=0;
//                    $irec["next_state"]=0;
//                } else {
//                    $irec["prev_state"]=0;
//                    $irec["state"]=$evs[0]->value;
//                    $irec["acknowledged"]=$evs[0]->acknowledged;
//                }
                $tmpres=array($irec);

                //$log->debug("zbx_period_get initial period for object $objectid: [".$irec["start_clock"]."-".$irec["end_clock"]." (".ZbxPeriodExtension::format_secs($irec["secs"])."s) ".$irec["prev_state"]."->".$irec["state"]." ack:".$irec["acknowledged"]."]");

                // query events in time interval
                $evargs["output"]="extend";
                $evargs["sortfield"]="clock";
                $evargs["sortorder"]="ASC";
                $evargs["objectids"]=$objectid;
                $evargs["time_from"]=$time_from;
                $evargs["time_till"]=$time_till;
                $evargs["selectHosts"]="extend";
                $evargs["selectRelatedObject"]="extend";
                $evargs["select_acknowledges"]="extend";
                unset($evargs["limit"]);
                $evs = $zbx->request('event.get', $evargs);

                //print"<pre>"; var_dump($evs);

                // enumerate internal periods
                $rec = array();
                $sumsecs=0;
                $lastev=null;
                foreach($evs as $ev) {

                    //$log->debug("zbx_period_get found event eventid:".$ev->eventid." clock:".strftime("%c",$ev->clock)." value:".$ev->value);

                    $rec["start_clock"]=$irec["end_clock"];
                    $rec["end_clock"]=$ev->clock;
                    $rec["secs"]=$rec["end_clock"]-$rec["start_clock"];
                    $rec["prev_state"]=$irec["state"];
                    $rec["state"]=$irec["next_state"];
                    $rec["next_state"]=$ev->value;
                    $rec["acknowledged"]=$ev->acknowledged;
                    $rec["event"]=$ev;
                    $rec["objectid"]=$objectid;

                    //$log->debug("zbx_period_get found period for object $objectid: [".strftime("%c",$rec["start_clock"])."-".strftime("%c",$rec["end_clock"])." (".ZbxPeriodExtension::format_secs($rec["secs"])."s) ".$rec["prev_state"]."->[".$rec["state"]."]->".$rec["next_state"]." ack:".$rec["acknowledged"]."]");

                    $sumsecs+=$rec["secs"];
                    $tmpres[]=$rec;
                    $irec=$rec; // store previous record as new initial
                    $lastev=$ev;
                }

                // synthesize last event record
                $rec["start_clock"]=$irec["end_clock"];
                $rec["end_clock"]=$time_till;
                $rec["secs"]=$rec["end_clock"]-$rec["start_clock"];
                $rec["prev_state"]=$irec["state"];
                $rec["state"]=$irec["next_state"];
                $rec["next_state"]=$irec["next_state"];
                $rec["acknowledged"]=0;
                $rec["event"]=$lastev;
                $rec["objectid"]=$objectid;
                $sumsecs+=$rec["secs"];
                $tmpres[]=$rec;
                //$log->debug("zbx_period_get found closing period for object $objectid: [".strftime("%c",$rec["start_clock"])."-".strftime("%c",$rec["end_clock"])." (".ZbxPeriodExtension::format_secs($rec["secs"])."s) ".$rec["prev_state"]."->[".$rec["state"]."]->".$rec["next_state"]." ack:".$rec["acknowledged"]."]");
                //$log->debug("zbx_period_get internim processing object $objectid, total time: ".ZbxPeriodExtension::format_secs($sumsecs));

                // merge periods with unchanged state
                $irec=array_shift($tmpres);
                $mergedres=array();
                $lastrec=null;
                while(count($tmpres)>0) {
                    $rec = array_shift($tmpres);
                    if($irec["state"] == $rec["state"]) {
                        $irec["end_clock"] = $rec["end_clock"];
                        $irec["secs"]+=$rec["secs"];
                        //$log->debug("zbx_period_get merging to period for object $objectid: [".$irec["start_clock"]."-".$irec["end_clock"]." (".ZbxPeriodExtension::format_secs($irec["secs"])."s) ".$irec["prev_state"]."->".$irec["state"]." ack:".$irec["acknowledged"]."]");
                        //$log->debug("zbx_period_get merging from period for object $objectid: [".$rec["start_clock"]."-".$rec["end_clock"]." (".ZbxPeriodExtension::format_secs($rec["secs"])."s) ".$rec["prev_state"]."->".$rec["state"]." ack:".$rec["acknowledged"]."]");
                        $lastrec=$rec;
                    } else {
                        //$irec["next_state"]=$rec["state"];
                        $mergedres[]=$irec;
                        $irec=$rec;
                    }
                }
                if($lastrec != $irec) {
                    //$log->debug("adding last rec");
                    //$irec["next_state"]=$rec["state"];
                    $mergedres[]=$irec;
                } else {
                    //$rec=$lastrec;
                    //$log->debug("zbx_period_get skipping last rec: [".strftime("%c",$rec["start_clock"])."-".strftime("%c",$rec["end_clock"])." (".ZbxPeriodExtension::format_secs($rec["secs"])."s) ".$rec["prev_state"]."->[".$rec["state"]."]->".$rec["next_state"]." ack:".$rec["acknowledged"]."]");
                    //$rec=$irec;
                    //$log->debug("zbx_period_get skipping last rec: [".strftime("%c",$rec["start_clock"])."-".strftime("%c",$rec["end_clock"])." (".ZbxPeriodExtension::format_secs($rec["secs"])."s) ".$rec["prev_state"]."->[".$rec["state"]."]->".$rec["next_state"]." ack:".$rec["acknowledged"]."]");
                }

                // cleanup zero len periods
                foreach($mergedres as $key => $rec) {
                    if($rec["secs"]==0) {
                        unset($rec[$key]);
                    }
                }

                // dump final
                $sumsecs=0;
                foreach($mergedres as $rec) {
                    $sumsecs+=$rec["secs"];
                    $log->debug("zbx_period_get result period for object $objectid: [".strftime("%c",$rec["start_clock"])."-".strftime("%c",$rec["end_clock"])." (".ZbxPeriodExtension::format_secs($rec["secs"])."s) ".$rec["prev_state"]."->[".$rec["state"]."]->".$rec["next_state"]." ack:".$rec["acknowledged"]."]");
                }

                $log->debug("zbx_period_get finished processing object $objectid, total time: ".ZbxPeriodExtension::format_secs($sumsecs));
                //$res[$objectid] = $mergedres;
                $data = array_merge($data,$mergedres);
            }


            $res = array("data" => $data, "stats" => $this->period_get_stats($data));

            $log->debug("end function zbx_period_get", array(
                $res
            ));

            return $res;
        };

        $f2 = function (\Twig_Environment $env, $context, $args) use ($log,$cache) {
            $log->debug("start function zbx_period_query", $args);

            $res = $args["periods"];

            if(array_key_exists("sortby",$args)) {
                $sortby = $args["sortby"];
            } else {
                $sortby = false;
            }
            if(array_key_exists("filterby",$args)) {
                $filterby = $args["filterby"];
                $filterval = $args["filterval"];
            } else {
                $filterby = false;
            }
            if(array_key_exists("limit",$args)) {
                $limit = $args["limit"];
            } else {
                $limit = false;
            }

            if($filterby!=false) {
                $res = array_filter($res, function ($rec) use ($filterby, $filterval) {
                    if ($rec[$filterby] == $filterval)
                        return true;
                    else
                        return false;
                });
            }

            if($sortby!=false) {
                usort($res, function ($rec1, $rec2) use ($sortby) {
                    if ($rec1[$sortby] == $rec2[$sortby])
                        return 0;
                    if ($rec1[$sortby] < $rec2[$sortby])
                        return 1;
                    else
                        return -1;
                });
            }

            if($limit!=false) {
                $res = array_slice($res, 0, $limit);
            }

            $log->debug("end function zbx_period_query", array(
                $res
            ));

            return $res;
        };

        $c = new \ReflectionClass($this);
        $m = $c->getMethod("period_get_stats");
        $f3 = $m->getClosure($this);

        $res = array(
            new CachingTwigFunction('zbx_period_get', $f, $this->container),
            new CachingTwigFunction('zbx_period_query', $f2, $this->container),
            new CachingTwigFunction('zbx_period_get_stats', $f3, $this->container)
        );
        $log->debug("done registering twig zabbix period extension");
        return $res;
    }


}