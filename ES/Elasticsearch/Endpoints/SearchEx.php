<?php
/**
 * Created by PhpStorm.
 * User: Liqian
 * Date: 2015/12/15
 * Time: 16:41
 */

namespace Elasticsearch\Endpoints;

use Elasticsearch\Endpoints\Search;

class SearchEx extends Search{
    /**
     * @return string
     * 我们的Elasticsearch为了满足跨库消重开发的一个新的插件searchex，需要支持按_searchex的搜索
     */
    protected function getURI()
    {
        $index = $this->index;
        $type = $this->type;
        $uri   = "/_searchex";

        if (isset($index) === true && isset($type) === true) {
            $uri = "/$index/$type/_searchex";
        } elseif (isset($index) === true) {
            $uri = "/$index/_searchex";
        } elseif (isset($type) === true) {
            $uri = "/_all/$type/_searchex";
        }

        return $uri;
    }
}