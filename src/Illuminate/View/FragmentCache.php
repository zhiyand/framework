<?php

namespace Illuminate\View;

use Illuminate\Contracts\Cache\Store;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

class FragmentCache {

    /**
     * The actual cache store.
     *
     * @var \Illuminate\Contracts\Cache\Store
     */
    protected $cache;

    /**
     * The filesystem.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The dependency tree
     *
     * @var array
     */
    protected $tree;

    /**
     * The path to the file holding the dependency tree
     *
     * @var string
     */
    protected $treePath;

    /**
     * Whether the dependency tree is touched or not.
     *
     * @var bool
     */
    protected $touched = false;

    protected $finder;

    public function __construct(Store $cache, Filesystem $files, ViewFinderInterface $finder, $treePath)
    {
        $this->cache = $cache;
        $this->files = $files;
        $this->finder = $finder;
        $this->treePath = $treePath;

        if( ! $files->exists($treePath)){
            throw new \InvalidArgumentException('View dependency tree file does not exist.');
        }

        $this->tree = (array)json_decode($files->get($treePath));
    }

    /**
     * Get the dependency tree.
     *
     * @return array
     */
    public function getTree()
    {
        return $this->tree;
    }

    /**
     * Get the filesystem instance.
     *
     * @return Filesystem
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Get the cache store.
     *
     * @return Store
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param object $model Object responds to `cacheKey()`
     * @param string $view  The compiled view path
     * @param integer $serial The serial number of the fragment in $view
     * @return string
     */
    public function getFragmentId($model, $view, $serial)
    {
        $hash = basename($view, ".php");
        $parts = [
            $model->cacheKey(),
            $hash,
            $this->files->lastModified($view)
        ];

        foreach (Arr::get($this->tree, "$hash.$serial", []) as $v) {
            $parts[] = $v;
            $parts[] = $this->files->lastModified($this->finder->find($v));
        }

        return sha1(join('.', $parts));
    }


    /**
     * Add view dependency
     *
     * @param string $view The compiled view path
     * @param string $serial The serial number of the fragment
     * @param string $dependency The partial that the $view depends on
     */
    public function addDependency($view, $serial, $dependency)
    {
        $view = basename($view, ".php");

        $key = "$view.$serial";

        if (Arr::has($this->tree, $key)) {
            if ( ! in_array($dependency, $this->tree[$view][$serial])) {
                $this->tree[$view][$serial][] = $dependency;
            }
        }else{
            Arr::set($this->tree, $key, [$dependency]);
        }

        $this->touched = true;
    }

    /**
     * Persist the dependency tree to filesystem
     */
    public function saveDependency()
    {
        if ($this->touched) {
            $this->files->put($this->treePath, json_encode($this->tree));
        }
    }
}