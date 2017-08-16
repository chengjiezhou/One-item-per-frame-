<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/11/6
 * Time: 10:28
 */
require_once LIBS_PATH.'Elasticsearch'.DIRECTORY_SEPARATOR.'Autoloader.php';
require_once LIBS_PATH.'GuzzleHttp'.DIRECTORY_SEPARATOR.'Autoloader.php';
require_once LIBS_PATH.'Psr'.DIRECTORY_SEPARATOR.'Autoloader.php';
require_once LIBS_PATH.'react'.DIRECTORY_SEPARATOR.'Autoloader.php';
class elasticsearch {
    private $client_bulider;
    public function __construct(){
        //自动加载类
        $auto_elc = new \Elasticsearch\Autoloader();
        $auto_elc->register();
        $auto_guz = new \GuzzleHttp\Autoloader();
        $auto_guz->register();
        $auto_psr = new \Psr\Autoloader();
        $auto_psr->register();
        $auto = new \React\Autoloader();
        $auto->register();
    }

    public function open($config){
        $this->connect($config);
    }
    /**
     * @param $config 为了迎合工厂类的并没有实际的作用
     * php客户端是使用http协议发送请求的，所以这个地方并没有实际的创建起链接，只是为客户端设置了链接参数
     **/
    protected function connect($config){
        $host = $this->get_host();
        $this->client_bulider = \Elasticsearch\ClientBuilder::create()->setHosts([$host])->build();
    }

    public function find($filter){
        try{
            return $this->client_bulider->searchEx($filter);
//            return $this->client_bulider->search($filter);
        }catch(Exception $e){
            setlog("find error : elasticseach {$e->getMessage()}",'error');
        }
    }

    public function put($filter){
        try{
            return $this->client_bulider->index($filter);
        }catch(Exception $e){
            setlog("put error : elasticseach {$e->getMessage()}",'error');
        }
    }

    public function update($filter){
        try{
            return $this->client_bulider->update($filter);
        }catch(Exception $e){
            setlog("update error : elasticseach {$e->getMessage()}",'error');
        }
    }
    
    public function delete($filter){
        try{
            return $this->client_bulider->delete($filter);
        }catch(Exception $e){
            setlog("delete error : elasticseach {$e->getMessage()}",'error');
        }
    }
    
    /**
     * 判断索引是否存在
     * @param $index 索引库
     * @param $throw_crash 是否抛出异常，默认是false不抛
     */
    public function exist_index($index = '',$throw_crash = false){
        if($index=='') throw new Exception('index is empty');
        try{
            return $this->client_bulider->indices()->exists(array('index'=>$index));
        }catch(Exception $e){
            setlog("exist_index error : elasticsearch :".$e->getMessage(),'error');
            if($throw_crash) throw $e;
        }
    }

    public function close(){
        $this->client_bulider = null;
    }

    /**
     * 从redis获取目前的可用host,如果redis里边不存在会使用配置文件的第一个host
     * */
    public function get_host(){
        $redis = pc_base::load_model('redis_model');
        $host = $redis->get_cache_redis('es_host');
        if($host){
            return $host;
        }else{
            $config = pc_base::load_config('database','elasticsearch');
            return $config['hostname'][0];
        }
    }

    /**
     * 取得7d
     * */
    public function get_host_day(){
        $redis = pc_base::load_model('redis_model');
        $host = $redis->get_cache_redis('es_host_day');
        if($host){
            return $host;
        }else{
            $config = pc_base::load_config('database','elasticsearch');
            return $config['hostname_day'][0];
        }
    }
    /**
     * 将es的host重置为某个固定的值
     * */
    public function reset_host($host){
        $this->client_bulider = \Elasticsearch\ClientBuilder::create()->setHosts([$host])->build();
    }
    /**
     * 清除缓存
     */
    public function clear_caches($filter = array()){
        try{
            return $this->client_bulider->clearCache($filter);
        }catch(Exception $e){
            setlog("clear_caches error : elasticseach {$e->getMessage()}",'error');
        }
    }

    /*
     * deleteByQuery,按条件删除数据
     * */
    public function deleteByQuery($filter){
        try{
            return $this->client_bulider->deleteByQuery($filter);
        }catch(Exception $e){
            setlog("deleteByQuery error : elasticseach {$e->getMessage()}",'error');
        }
    }

    /*
    * refresh indices
    */
    public function refresh($filter){
        try{
            return $this->client_bulider->refresh($filter);
        }catch(Exception $e){
            setlog("refresh error : elasticseach {$e->getMessage()}",'error');
        }
    }
}