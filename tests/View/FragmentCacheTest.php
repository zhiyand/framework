<?php

use Mockery as m;
use Illuminate\View\FragmentCache;

class FragmentCacheTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function test_it_loads_dependencies_properly()
    {
        list($store, $files) = $this->getViewCacheArgs();

        $files->shouldReceive('exists')->once()->with('tree.json')->andReturn(true);
        $files->shouldReceive('get')->once()->with('tree.json')->andReturn('{"foo":["bar","baz"]}');

        $cache = new FragmentCache($store, $files, 'tree.json');

        $this->assertEquals(['foo' => ['bar', 'baz']], $cache->getTree());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_it_screams_if_dependencies_file_does_not_exist()
    {
        list($store, $files) = $this->getViewCacheArgs();

        $files->shouldReceive('exists')->once()->with('tree.json')->andReturn(false);
        $cache = new FragmentCache($store, $files, 'tree.json');
    }

    public function test_it_generates_fragment_id_based_on_model_view_serial()
    {

        list($store, $files) = $this->getViewCacheArgs();

        $files->shouldReceive('exists')->once()->with('tree.json')->andReturn(true);
        $files->shouldReceive('get')->once()->with('tree.json')->andReturn('{"view1":[["foo"],["bar"]]}');
        $files->shouldReceive('lastModified')->andReturn(100);
        $cache = new FragmentCache($store, $files, 'tree.json');

        $model = m::mock('stdClass');
        $model->shouldReceive('cacheKey')->andReturn('key');

        $this->assertEquals(sha1('key.view1.100.bar.100'), $cache->getFragmentId($model, 'view1', 1));
    }

    public function test_it_can_update_view_dependencies()
    {
        $cache = $this->getFragmentCache();

        $cache->addDependency('foo', 0, 'zigzag');

        $this->assertEquals(['foo' => [['bar', 'zigzag'], ['baz']]], $cache->getTree());

        $cache->addDependency('bear', 0, 'nah');

        $this->assertEquals(['foo' => [['bar', 'zigzag'], ['baz']],
            'bear' => [['nah']]
            ], $cache->getTree());
    }

    public function test_it_can_save_updated_dependencies()
    {
        $cache = $this->getFragmentCache();

        $cache->addDependency('foo', 0, 'zigzag');
        $cache->getFiles()->shouldReceive('put')->once();
        $cache->saveDependency();
    }

    protected function getFragmentCache()
    {
        list($store, $files) = $this->getViewCacheArgs();

        $files->shouldReceive('exists')->once()->with('tree.json')->andReturn(true);
        $files->shouldReceive('get')->once()->with('tree.json')->andReturn('{"foo":[["bar"],["baz"]]}');
        $files->shouldReceive('lastModified')->andReturn(100);

        return new FragmentCache($store, $files, 'tree.json');
    }

    protected function getViewCacheArgs()
    {
        return [
            m::mock('\Illuminate\Contracts\Cache\Store'),
            m::mock('\Illuminate\Filesystem\Filesystem')
        ];
    }
}
