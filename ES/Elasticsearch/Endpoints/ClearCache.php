<?php
/**
 * Created by PhpStorm.
 * User: liqian
 * Date: 2016/5/27
 * Time: 13:31
 */

namespace Elasticsearch\Endpoints;


class ClearCache extends AbstractEndpoint
{
    /**
     * @return string
     */
    protected function getURI()
    {
        $uri   = "/_clearcache";

        return $uri;
    }

    /**
     * @return string[]
     */
    protected function getParamWhitelist()
    {
        return array(
        );
    }

    /**
     * @return string
     */
    protected function getMethod()
    {
        return 'GET';
    }
}