<?php

namespace Illuminate\View;

use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;

class FragmentCache {

    /**
     * The actual cache store.
     *
     * @var \Illuminate\Contracts\Cache\Store
     */
    protected $cache;

    /**
     * @var \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected $files;

    protected $fragmentId;

    protected $fragment;

    protected $started = false;

    protected $tree;

    protected $treePath;

    /**
     * Whether the dependency tree is touched or not.
     *
     * @var bool
     */
    protected $touched = false;

    public function __construct(Store $cache, Filesystem $files, $treePath)
    {
        $this->cache = $cache;
        $this->files = $files;
        $this->treePath = $treePath;

        if( ! $files->exists($treePath)){
            throw new \InvalidArgumentException('View dependency tree file does not exist.');
        }

        $this->tree = (array)json_decode($files->get($treePath));
    }

    public function getTree()
    {
        return $this->tree;
    }

    public function getFragmentId()
    {
        return $this->fragmentId;
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function getCache()
    {
        return $this->cache;
    }

    public function setFragment($model, $view, $serial)
    {
        $parts = [
            $model->cacheKey(),
            $view,
            $this->files->lastModified($view)
        ];

        foreach (Arr::get($this->tree, "$view.$serial", []) as $v) {
            $parts[] = $v;
            $parts[] = $this->files->lastModified($v);
        }

        $this->fragmentId = sha1(join('.', $parts));
    }


    public function expired()
    {
        $this->fragment = $this->cache->get($this->fragmentId);

        return ! $this->fragment;
    }

    public function start()
    {
        if ($this->started) {
            throw new \InvalidArgumentException('Cache fragment already started.');
        }
        $this->started = true;
        ob_start();
    }

    public function stop()
    {
        if ($this->started) {
            $this->fragment = ob_get_clean();

            $this->cache->put($this->fragmentId, $this->fragment, 60);
        }
        $this->started = false;
    }

    public function getContent()
    {
        return $this->fragment;
    }

    public function addDependency($view, $serial, $dependency)
    {
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

    public function saveDependency()
    {
        if ($this->touched) {
            $this->files->put($this->treePath, json_encode($this->tree));
        }
    }
}