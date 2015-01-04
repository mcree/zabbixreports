<?php

namespace ZabbixReports\MainBundle\Cache;


use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\FilesystemCache;
use Psr\Log\LoggerInterface;
use SQLite3;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ZbxCache extends CacheProvider {

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ContainerInterface
     */
    private $container;

    private $db;

    function __construct(ContainerInterface $container, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->container = $container;

        $cdir = $container->getParameter("zbx_cachedir");
        if(!is_dir($cdir)) {
            mkdir($cdir,0777,true);
        }
        //parent::__construct($cdir);
        $logger->debug("using cache: $cdir");
        $this->db = new SQLite3("$cdir/bzbx_cache.sqlite");

        $this->db->exec("create table if not exists cache (".
            "id text primary key,".
            "val text,".
            "ts numeric".
            ")");

        // housekeeping
        $ts = time();
        $this->db->exec("delete from cache where ts<$ts");
        $this->db->exec("vacuum");
    }

    function __destruct()
    {
        $this->db->close();
    }


    /**
     * Fetches an entry from the cache.
     *
     * @param string $id The id of the cache entry to fetch.
     *
     * @return string|boolean The cached data or FALSE, if no cache entry exists for the given id.
     */
    protected function doFetch($id)
    {
        $id = $this->db->escapeString($id);
        $val = $this->db->querySingle("select val from cache where id='$id'");
        if($val!==null) {
            return unserialize($val);
        } else {
            return false;
        }
    }

    /**
     * Tests if an entry exists in the cache.
     *
     * @param string $id The cache id of the entry to check for.
     *
     * @return boolean TRUE if a cache entry exists for the given cache id, FALSE otherwise.
     */
    protected function doContains($id)
    {
        $id = $this->db->escapeString($id);
        $id = $this->db->querySingle("select id from cache where id='$id'");
        if($id===false || $id===null) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Puts data into the cache.
     *
     * @param string $id The cache id.
     * @param string $data The cache entry/data.
     * @param int $lifeTime The lifetime. If != 0, sets a specific lifetime for this
     *                           cache entry (0 => infinite lifeTime).
     *
     * @return boolean TRUE if the entry was successfully stored in the cache, FALSE otherwise.
     */
    protected function doSave($id, $data, $lifeTime = 3600)
    {
        $id = $this->db->escapeString($id);
        $data = $this->db->escapeString(serialize($data));
        if($lifeTime==0) {
            $ts = PHP_INT_MAX;
        } else {
            $ts = time() + $lifeTime;
        }
        return $this->db->exec("insert or replace into cache (id, val, ts) values ('$id','$data','$ts')");
    }

    /**
     * Deletes a cache entry.
     *
     * @param string $id The cache id.
     *
     * @return boolean TRUE if the cache entry was successfully deleted, FALSE otherwise.
     */
    protected function doDelete($id)
    {
        $id = $this->db->escapeString($id);
        return $this->db->exec("delete from cache where id='$id'");
    }

    /**
     * Flushes all cache entries.
     *
     * @return boolean TRUE if the cache entry was successfully deleted, FALSE otherwise.
     */
    protected function doFlush()
    {
        return $this->db->exec("delete from cache");
    }

    /**
     * Retrieves cached information from the data store.
     *
     * @since 2.2
     *
     * @return array|null An associative array with server's statistics if available, NULL otherwise.
     */
    protected function doGetStats()
    {
        // TODO: Implement doGetStats() method.
    }
}