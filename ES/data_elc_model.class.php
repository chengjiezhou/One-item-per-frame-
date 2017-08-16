<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/11/6
 * Time: 11:09
 * access to elasticsearch
 */

class data_elc_model
{
    private $db_config;
    private $client;

    public function __construct()
    {
        /***创建elasticsearch对象**/
        $this->db_config = pc_base::load_config('database');
        $this->index_pre = get_pre();
        pc_base::load_sys_class('db_factory', '', 0);
        $this->client = db_factory::get_instance($this->db_config)->get_database('elasticsearch');
    }

    /**
     *  liqian
     *  web 3.2
     *  获取data表的详细信息, 每次查询的json数据会返回总数 hits.total
     **/
    function get_data_detail($where = array(), $page = 1, $page_num = 20, $is_distinct = true,$is_recall=true)
    {
        $start_time = '';
        $end_time = '';
        if(isset($where['start_time']) && $where['start_time']!=''){
            $start_time = $where['start_time'];
            $end_time = $where['end_time'];
        }elseif(isset($where['start_add_time']) && $where['start_add_time']!=''){
            if($is_recall==false){
                $start_time = $where['start_add_time'];
                $end_time = $where['end_add_time'];
            }
        }
        if ($start_time == '' || $end_time == '') return false;
        $start_num = 0;
        if ($page_num <= 0) $page_num = 10;
        if ($page > 0) $start_num = ($page - 1) * $page_num;
        /***获取查询队列***/
        $query['query'] = $this->_query($where, $is_distinct,true);
        /***获取这个时间范围之内用到的index(库)名***/
        $body = array_merge($query, array('from' => $start_num, 'size' => $page_num));
        if($where['sort'] == '') {
            $body['sort'] = array('release_date' => array('order' => 'desc'));  //排序
        }else{
            $body['sort'] = array($where['sort'] => array('order' => 'desc'));  //排序
        }
        $body_json = json_encode($body);
        $index_join = get_index($where['topic_id'], $start_time, $end_time, $this->client,$is_recall);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = 'data';
            $params['body'] = $body_json;
            $res = $this->client->find($params);
           // file_put_contents("json.txt",$body_json,FILE_APPEND);

//            print_r($index_join);
//            print_r($body_json);
//            print_r($res);
        }
        return $res;
    }
    /*
     * 获取数据统计值
     * @params $where array 查询条件
     * @params $is_distinct 是否需要按照title_crc消重
     * @params $is_recall 是否要检测回溯任务的库
     * */
    function get_data_count($where = array(),$is_distinct = false,$is_recall=true,$distinct_field='url_crc')
    {
        $start_time = '';
        $end_time = '';
        if(isset($where['start_time']) && $where['start_time']!=''){
            $start_time = $where['start_time'];
            $end_time = $where['end_time'];
        }elseif(isset($where['start_add_time']) && $where['start_add_time']!=''){
            if($is_recall==false){
                $start_time = $where['start_add_time'];
                $end_time = $where['end_add_time'];
            }
        }
        if ($start_time == '' || $end_time == '') return false;
        /***获取查询队列***/
        $query['query'] = $this->_query($where, $is_distinct);
        $query['query']['filtered']['filter'] = $this->set_url_crc_distinct($distinct_field);
        $body = $query;
        $body_json = json_encode($body);
//        print_r($body_json);
        /***获取这个时间范围之内用到的index(库)名***/
        $index_join = get_index($where['topic_id'], $start_time, $end_time, $this->client,$is_recall);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = 'data';
            $params['search_type'] = 'count';
            $params['body'] = $body_json;
            $res = $this->client->find($params);
        }
        return $res;
    }

    /**
     * 生成query
     * $is_distinct 是否消重，true 表示需要消重，false 表示不需要消重
     * **/
    function _query($where, $is_distinct = 'true',$type=false)
    {
        if (empty($where)) return $query = array('match_all' => array());
        if ($where['like'] == NULL) {
            $where['like'] = "";
        }
        $where['search_type'] = $where['search_type'] ? $where['search_type'] : '';
        //只有索引数组才可以生成json数组
        $query_child = array(
            'bool' => array(
                'must' => array(
                    0 => array(
                        'has_child' => array(
                            'type' => 'lable',
                            'query' => array(
                                'bool' => array(
                                    'must' => array(
                                        0 => array(
                                            'term' => array(
                                                'lable.keywords_code' => $where['topic_id']
                                            )
                                        )
                                    )
                                )
                            )
                        )
                    ),
                ),
            )
        );
        $query['filtered']['query'] = $query_child;
        $source_type = $this->get_sourceType_query($where['source_type']);
        // $like = $this->set_multi_match($where['like'], '100%'); 
        $like = $this->set_single_match($where['like'],$where['search_type'], '100%'); //因新需求  支持单类型查询(标题/企业/特征词/来源)
        $title_crc = $this->set_title_crc($where['title_crc']);
        $url_crc = $this->set_url_crc($where['url_crc']);
        $relativity = $this->set_relativity($where['relativity']);
        $tag = $this->set_tag($where['tag']);
        $media_name = $this->set_media_name($where['media_name']);
//        $media_name_not = $this->set_media_name_not($where['media_name_not']);
        if (!empty($source_type)) {
            $query['filtered']['query']['bool']['must'][0]['has_child']['query']['bool']['must'][] = $source_type;
            if($where['is_relativity'] == 'true'){//查询正负面
                $query['filtered']['query']['bool']['must_not'][0]['has_child']['query']['bool']['must'][] = $source_type;
            }
        }
        if (!empty($media_name)) {
            $query['filtered']['query']['bool']['must'][0]['has_child']['query']['bool']['must'][] = $media_name;
            if($where['is_relativity'] == 'true'){//查询正负面
                $query['filtered']['query']['bool']['must_not'][0]['has_child']['query']['bool']['must'][] = $media_name;
            }
        }
        if ($is_distinct=='true' || $type) { //数据表显示
                $query['filtered']['query']['bool']['must'][0]['has_child']['inner_hits'] = array('size' => 99999);
        }
        if (!empty($relativity)) {
            $query['filtered']['query']['bool']['must'][0]['has_child']['query']['bool']['must'][] = $relativity;
            if($where['is_relativity'] == 'true'){//查询正负面
                if($where['relativity']=='1'){
                    $relativity = $this->set_relativity('-1');
                }elseif($where['relativity']=='0'){
                    $relativity = $this->set_relativity('1,-1');
                }
                $query['filtered']['query']['bool']['must_not'][0]['has_child']['query']['bool']['must'][] = $relativity;
                $query['filtered']['query']['bool']['must_not'][0]['has_child']['type'] = 'lable';
                $query['filtered']['query']['bool']['must_not'][0]['has_child']['query']['bool']['must'][] = array('term' => array(
                    'lable.keywords_code' => $where['topic_id'])
                );
            }
        }
        if (!empty($title_crc)) {
            $query['filtered']['query']['bool']['must'][0]['has_child']['query']['bool']['must'][] = $title_crc;
        }
        if (!empty($url_crc)) {
            $query['filtered']['query']['bool']['must'][0]['has_child']['query']['bool']['must'][] = $url_crc;
        }
        if (!empty($tag)) {
            $query['filtered']['query']['bool']['must'][0]['has_child']['query']['bool']['must'][] = $tag;
            if($where['is_relativity'] == 'true'){
                $query['filtered']['query']['bool']['must_not'][0]['has_child']['query']['bool']['must'][] = $tag;
            }
        }
        if(isset($where['task_id']) && $where['task_id']!=''){
            $task_id = $this->set_task_id($where['task_id']);
            $query['filtered']['query']['bool']['must'][0]['has_child']['query']['bool']['must'][] = $task_id;
        }
        if(isset($where['start_time']) && $where['start_time']!=''){
            $release_date = $this->set_release_date($where['start_time'],$where['end_time']);
            $query['filtered']['query']['bool']['must'][0]['has_child']['query']['bool']['must'][] = $release_date;
            if($where['is_relativity'] == 'true'){
                $query['filtered']['query']['bool']['must_not'][0]['has_child']['query']['bool']['must'][] = $release_date;
            }
        }
        if(isset($where['start_add_time']) && $where['start_add_time']!=''){
            $add_time = $this->set_add_time($where['start_add_time'],$where['end_add_time']);
            $query['filtered']['query']['bool']['must'][0]['has_child']['query']['bool']['must'][] = $add_time;
        }
        //依title_crc 消重
        if ($is_distinct) {
            $query['filtered']['filter'] = $this->set_title_crc_distinct();
        }
        if ($is_distinct === 'url_crc') {
            $query['filtered']['filter'] = $this->set_url_crc_distinct();
        }
        if ($is_distinct === 'download_title_crc') {
            $query['filtered']['filter'] = $this->set_download_title_crc();
        }
        if (!empty($like)) {
            if($where['search_type']){  //如果 是单类型查询（标题、企业、特征词、来源）
                if ( $where['search_type'] == 'feature_words' || $where['search_type'] == 'enterprise' ) { //如果是特征词和企业
                    $query['filtered']['query']['bool']['must'][0]['has_child']['query']['bool']['must'][] = $like;
                }else{  //标题 或 来源
                    $query['filtered']['query']['bool']['must'][] = $like;
                }
            }else{//全部类型
                $query['filtered']['query']['bool']['should'][] = $like;                
            }
            $query['filtered']['query']['bool']['minimum_should_match'] = 1;
        }
        return $query;
    }

    /**
     * 获取查询标签的数组
     * $tag_arr,string,每个标签以逗号相隔
     * */
    function get_tag_query($tags = '')
    {
        if ($tags == '') return array();
        $tags = explode(',', $tags);
        foreach ($tags as $tag) {
            $data[] = array(
                'term' => array(
                    'lable.key' => backslash_count($tag) . $tag
                )
            );
        }
        return $data;
    }
    /**
     * 媒体排行用到
     */
    function set_media_name_not($media_name_not){
        if ($media_name_not == '') return;
        $media_name_not = explode(',', $media_name_not);
        return array(
            'terms' => array(
                'media_name' => $media_name_not
            )
        );
    }
    /**
     * $id,数组起始key
     * $source_type，string,每种资源类型以逗号相隔,ex：1,4
     * */
    function get_sourceType_query($source_type = '')
    {
        if ($source_type == '') return;
        $source_type = explode(',', $source_type);
        return array(
            'terms' => array(
                'lable.source_type' => $source_type
            )
        );
    }

    /**
     * 正负面
     * */
    function set_relativity($relativity = '')
    {
        if ($relativity == NULL || $relativity == '') return;
        $relativity = explode(',', $relativity);
        return array(
            'terms' => array(
                'lable.relativity' => $relativity
            )
        );
    }

    /**
     * 实时任务
     * */
    function set_task_id($task_id = '')
    {
        if ($task_id == NULL || $task_id == '') return;
        return array(
            'term' => array(
                'lable.task_id' => $task_id
            )
        );
    }

    /**
     * 发布日期
     * */
    function set_release_date($start_time,$end_time)
    {
        if ($start_time == NULL || $start_time == '') return;
        return array(
            'range' => array(
                'release_date' => array(
                    'from' => $start_time,
                    'to' => $end_time
                )
            )
        );
    }

    /**
     * 发布日期
     * */
    function set_add_time($start_time,$end_time)
    {
        if ($start_time == NULL || $start_time == '') return;
        return array(
            'range' => array(
                'add_time' => array(
                    'from' => $start_time,
                    'to' => $end_time
                )
            )
        );
    }



    function set_title_crc($title_crc = '' ,$type = 'lable.')
    {
        if ($title_crc == '') return;
        return array(
            'term' => array(
                $type.'title_crc' => $title_crc

            )
        );
    }

    function set_url_crc($url_crc = '')
    {
        if ($url_crc == '') return;
        return array(
            'terms' => array(
                'lable.url_crc' => $url_crc

            )
        );
    }
    /**
     * 标签
     * */
    function set_tag($key = '', $type = 'lable')
    {
        if ($key == '') return;
        //此处是为了查询多标签条件 用到的地方有图表透视图
        $key_arr = explode(',', $key);
        foreach ($key_arr as $k => $v) {
            $keys[] = backslash_count($v) . $v;
        }
        return array(
            'terms' => array(
                $type . '.key' => $keys
            )
        );
    }
    /**
     * 设置media_name
     */
    function set_media_name($media_name){
        if ($media_name == '') return;
        $media_name = explode(',',$media_name);
        return array(
            'terms' => array(
                'media_name'=>$media_name
            )
        );
    }
    /**
     * 设置多字段搜索
     * minimum为匹配精度
     **/
    function set_multi_match($like = '', $minimum = '100%')
    {
        if ($like == '') return;
        return array(
            array(
                'has_child' => array(
                    'type' => 'lable',
                    'query' => array(
                        'bool' => array(
                            'must' => array(
                                array(
                                    'multi_match' =>
                                        array(
                                            'query' => $like,
                                            'type' => 'best_fields',
                                            'fields' => array(
                                                'feature_words'
                                            ),
                                            'minimum_should_match' => $minimum
                                        )
                                )
                            )
                        )
                    ),
                )
            ),
            array(
                'multi_match' =>
                    array(
                        'query' => $like,
                        'type' => 'best_fields',
                        'fields' => array(
                            'title', 'media_name'
                        ),
                        'minimum_should_match' => $minimum
                    )
            )
        );
    }

    /**
     * 因需求变动 新方法 2016/09/20 smt
     * 设置单字段搜索 支持多字段
     * minimum为匹配精度
     * search_type 字段类型 默认空(全部) 、feature_words(特征词)、title(标题)、media_name(来源)、enterprise(企业)
     * like 查询内容
     **/
    function set_single_match($like = '', $search_type = '', $minimum = '100%')
    {
        if ($like == '') return;
        $query = array();
        if ( $search_type == 'feature_words' || $search_type == 'enterprise' ) { //去lable表中查询
            if ($search_type == 'enterprise') {
                $search_type = 'entity_name';//企业名称
            }
            $query = [
                    'multi_match'=>[
                        'query' => $like,
                        'type' => 'phrase_prefix',
                        'fields'=>[ $search_type ],
                        'minimum_should_match' => $minimum
                ]
            ];
        }elseif ( $search_type == 'title' || $search_type == 'media_name' ) { 
            $query = [
                'multi_match' =>[
                    'query' => $like,
                    'type' => 'phrase_prefix',
                    'fields' =>[ $search_type ],
                    'minimum_should_match' => $minimum
                ]
            ];
        }else{ //全部字段类型
            $query[0] = [
                'has_child'=>[
                    'type'  =>'lable',
                    'query' =>[
                        'bool'=>[
                            'must'=>[
                                [
                                    'multi_match'=>[
                                        'query' => $like,
                                        'type' => 'phrase_prefix',
                                        'fields'=>[
                                            'feature_words','entity_name'
                                        ],
                                        'minimum_should_match' => $minimum
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            $query[1] = [
                'multi_match' =>[
                    'query' => $like,
                    'type' => 'phrase_prefix',
                    'fields' =>[
                        'title', 'media_name'
                    ],
                    'minimum_should_match' => $minimum
                ]
            ];
        }
        
        return $query;
    }


    function set_title_crc_distinct()
    {
        return array(
            "distinct" => array(
                "field" => "title_crc",
                "elimination" => "1"
            )
        );
    }
    /*
     * 采集词排序消重
     */
    function set_download_title_crc(){
        return array(
            "distinct" => array(
                "field"=>"title_crc",
                "sortfield"=>"download_date",
                "elimination"=> "1"
            )
        );
    }
    function set_url_crc_distinct($distinct_field='url_crc')
    {
        return array(
            "distinct" => array(
                "field" => $distinct_field,
                "elimination" => "1"
            )
        );
    }
    /*
     * 通过title_crc匹配所有相似数据返回
     * 修改正负面时使用
     */
    function get_all_similar($title_crc, $topic_id, $start_time, $end_time)
    {
        if ($title_crc == '' || $start_time == '' || $end_time == '' || $topic_id == '') return;
        $query = array(
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array('term' =>
                            array('title_crc' => $title_crc)),
                        array('term' =>
                            array('keywords_code' => $topic_id)),
                        array('range' => array(
                            'release_date' => array(
                                'from' => $start_time,
                                'to' => $end_time
                            )
                        )
                        )
                    )
                )
            ),
            'from'=>'0',
            'size'=>'10000'
        );

        $index_join = get_index($topic_id, $start_time, $end_time, $this->client);
        $body_json = json_encode($query);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = 'lable';
            $params['body'] = $body_json;
            $res = $this->client->find($params);
        }
        return $res;
    }

    /**
     * 修改文章的正负面属性
     * $index 由年月组成 ex "201511"
     * */
    function update_relativity($index, $id = '', $relativity = '', $title_crc,$type='lable')
    {
        if ($id == '' || $relativity == '' || $title_crc == ''||$index =='') return false;
        $index = $this->check_index($index);
        $params['index'] = $index;
        $params['type'] = $type;
        $params['routing'] = $title_crc; //update 和 put 必须指定路由
        $params['id'] = $id;
        $params['body'] = json_encode(array('doc' => array("relativity" => $relativity)));
        $res = $this->client->update($params);
        if (!empty($res)) return true;
        else return false;
    }

    /**
     * 通过库名检测es集群并且切换
     */
    function check_index($index){
        $config         = pc_base::load_config('database','elasticsearch');
        $index_day_pre  = $config['index_day_pre'];
        if(strstr($index,$index_day_pre)){
            toggle_es('second',$this->client);
        }else{
            toggle_es('first',$this->client);
        }
        return $index;
    }
    /**
     * 查询正负面
     * @param $url_crc array()
     * @param $topicid string 主题号
     * @param $start_time，$end_time 时间范围
     * @param $relativity 正负面
     * @param $search_type 查询类型
     * @param $recall 是否包含回溯库
     * @param $time 是否按日期查询
     */
    function search_relativity($url_crc, $topicid, $start_time,$end_time, $relativity = '', $search_type,$recall=true,$time = true)
    {
        if ($url_crc == '' || $start_time == '' || $end_time == '' || $topicid == '') return '';
        $query = array(
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array('terms' =>
                            array('url_crc' => $url_crc)),
                        array('term' =>
                            array('keywords_code' => $topicid))
                    )
                )
            ),
            "aggs" => array(
                'a' => array(
                    'terms' => array(
                        'field' => 'url_crc',
                        "size" => 0
                    ),
                    'aggs' => array(
                        'relativity' => array(
                            'hsum' => array(
                                'field' => 'lable.relativity'
                            )
                        )
                    )
                )
            )
        );
        $relativity = $this->set_relativity($relativity);
        if (!empty($relativity)) {
            $query['query']['bool']['must'][] = $relativity;
        }
        if($time){
            $query['query']['bool']['must'][] = $this->set_release_date($start_time, $end_time);
        }
        $index_join = get_index($topicid, $start_time, $end_time, $this->client,$recall);
        $body_json = json_encode($query);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = 'lable';
            if ($search_type == 'num') {
                $params['search_type'] = 'count';
            }
            $params['body'] = $body_json;
//            print_r($body_json);
            $res = $this->client->find($params);
        }
        return $res;
    }

    /**
     * 查询相似数量
     * $search_type为num时查询相似数量
     * num为title_crc的数量
     */
    function search_similars($title_crc, $topicid, $start_time, $end_time, $num = 50, $search_type,$source_type,$media_name,$stat_type,$recall = true)
    {
        if ($title_crc == '' || $start_time == '' || $end_time == '' || $topicid == '') return false;
        $query = array(
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array(
                            'has_child' => array(
                                'type' => 'lable',
                                'query' => array(
                                    'bool' => array(
                                        'must' => array(
                                            0 => array(
                                                'range' => array(
                                                    'lable.release_date' => array(
                                                        'from' => $start_time,
                                                        'to' => $end_time
                                                    )
                                                )
                                            ),
                                            1 => array(
                                                'term' => array(
                                                    'lable.keywords_code' => $topicid
                                                )
                                            )
                                        )
                                    )
                                )
                            )
                        ),
                    )
                )
            )
        );
        if($source_type !=''){
            $source_type = explode(',',$source_type);
            $query['query']['bool']['must'][] = array(
                'terms' =>
                    array(
                        'source_type' => $source_type)
            );
        }
        if($media_name !=''){
            $query['query']['bool']['must'][] = array(
                'term' =>array('media_name'=>$media_name)
            );
        }
        $query['query']['bool']['must'][] = array(
            'terms' =>
                array(
                    'title_crc' => $title_crc)
        );
        $querys['query']['filtered']  = $query;
        $index_join = get_index($topicid, $start_time, $end_time, $this->client,$recall);
        $querys['query']['filtered']['filter'] = $this->set_url_crc_distinct();
        if($stat_type ==4){
            $querys['query']['filtered']['filter'] = $this->set_title_crc_distinct();
        }
        $querys['from'] = 0;
        $querys['size'] = 10000;
        if ($search_type == 'num') {
            $querys['aggs'] = array(
                'similar' => array(
                    'hiterms' => array(
                        'field' => 'title_crc',
                        'size' => $num,
                        'distinct' => 'url_crc',
                        'hierarchy' => -1,
                        "elimination" => "1",
                    ),
                )
            );
        };
        $body_json = json_encode($querys);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = 'data';
            if ($search_type == 'num') {
                $params['search_type'] = 'count';
            }
            $params['body'] = $body_json;
            $res = $this->client->find($params);
        }
//        print_r($index_join);
//        print_r($body_json);
        return $res;
    }

    /*
     * 删除数据
     */
    function delete_data($id, $index, $type = 'lable')
    {
        if ($id == '' || $index == '') return false;
        $index=$this->check_index($index);
        $params['index'] = $index;
        $params['type'] = $type;
        $params['id'] = $id;
        $res = $this->client->delete($params);
        if (!empty($res)) return true;
        else return false;
    }

    /**
     * 插入数据 用于标无效恢复数据
     * 二期
     * */
    function put_data($index, $data, $type = 'lable')
    {
        $index=$this->check_index($index);
        $params['index'] = $index;
        $params['type'] = $type;
        $params['id'] = $data['_id'];
        if ($type == 'weibo_lable') {
            $params['routing'] = $data['siteurl_crc']; //update 和 put 必须指定路由
        } else {
            $params['routing'] = $data['title_crc']; //update 和 put 必须指定路由
        }
        $params['parent'] = $data['parentId'];
        unset($data['_id']);
        unset($data['_index']);
        unset($data['title']);
        unset($data['url']);
        $params['body'] = json_encode($data);
        $res = $this->client->put($params);
        if (!empty($res)) return true;
        else return false;
    }

    /**
     * 根据title_crc查询data数据以及对应的lable,需要去lable表获取主题号联查
     * 标无效使用
     */
    function get_more_data($title_crc, $start_time, $end_time, $topicid, $num = 9999, $type = 'data')
    {
        if ($title_crc == '' || $start_time == '' || $end_time == '' || $topicid == '') {
            return;
        }
        $query = array(
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array(
                            'has_child' => array(
                                'type' => 'lable',
                                'query' => array(
                                    'bool' => array(
                                        'must' => array(
                                            0 => array(
                                                'term' => array(
                                                    'lable.keywords_code' => $topicid
                                                )
                                            )
                                        )
                                    )
                                )
                            )
                        ),
                        array('terms' =>
                            array('title_crc' => $title_crc)
                        ),
                        array('range' => array(
                            'release_date' => array(
                                'from' => $start_time,
                                'to' => $end_time
                            )
                        )
                        )
                    ),
                )
            ),
            'size' => $num
        );
        $index_join = get_index($topicid, $start_time, $end_time, $this->client);
        $query['query']['bool']['must'][0]['has_child']['inner_hits'] = array('size' => 99999);
        $body_json = json_encode($query);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = $type;
            $params['body'] = $body_json;
            $res = $this->client->find($params);
        }
        return $res;
    }

    /**
     * 标无效使用通过时间url-crc拿到data数据
     */
    function get_invalid_data($url_crc, $start_time, $end_time, $topicid, $type = 'data')
    {
        if ($url_crc == '' || $start_time == '' || $end_time == '' || $topicid == '') {
            return;
        }
        $query = array(
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array('terms' =>
                            array('url_crc' => $url_crc)
                        ),
                        array('range' => array(
                            'release_date' => array(
                                'from' => $start_time,
                                'to' => $end_time
                            )
                        )
                        )
                    ),
                )
            ),
            'size' => 100
        );
        $index_join = get_index($topicid, $start_time, $end_time, $this->client);
        $body_json = json_encode($query);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = $type;
            $params['body'] = $body_json;
            $res = $this->client->find($params);
        }
        return $res;
    }

    /**
     *  获得媒体资源排行的各个分类的正负面 reg:资讯/论坛/博客/微博
     *
     * @param array $param
     * @author xueyufeng
     * <pre>
     *      keywords_code => 主题code
     *      start_time    => 开始时间
     *      end_time      => 结束时间
     * </pre>
     * @return array
     * @example array array(
     *      key         => 正负面 -1负 0中性 1正
     *      doc_count   => 总数
     * )
     */
    function get_media_ranking($param)
    {
        if (empty($param['keywords_code']) || empty($param['start_time']) || empty($param['end_time'])) return array();
        //print_r($param);
        $query = array(
            'query' => array(
                'bool' => array(
                    'must' => array(
                        0 => array(
                            'term' => array(
                                //主题code
                                'keywords_code' => $param['keywords_code']
                            )
                        ),
                        1 => array(
                            'term' => array(
                                //未反馈
                                'isFeedback' => 0
                            )
                        ),
                        2 => array(
                            //范围
                            'range' => array(
                                //数据发布时间
                                'release_date' => array(
                                    'from' => $param['start_time'],
                                    'to' => $param['end_time']
                                )
                            )
                        )
                    )
                )
            ),
            'aggs' => array(
                'key' => array(
                    //统计类型， 这个名称是我们在内置的terms 统计上的扩充， 就是按层级统计的意思
                    'hiterms' => array(
                        'field' => 'media_name',
                        'size' => 10,  //取全部
                        'distinct' => "url_crc", //消重
                        'elimination' => '1',
                        'fastDistinct' => false, //消重传给子系统
                        'elimination' => 1,
                        'hierarchy' => -1, //无标签层
                    ),
                    'aggs' => array(
                        'res' => array(
                            //统计类型， 这个值表示我们定义的统计正负面
                            'relativities' => array(
                                'field' => 'relativity', //统计字段， 就是正负面的字段
                                'idfield' => 'url_crc',  //统计正负面时的文档主键字段
                            )
                        )
                    )
                )
            )
        );
        //资源类型
        if (isset($param['source_type']) && is_numeric($param['source_type'])) {
            $query['query']['bool']['must'][3] = array(
                'term' => array(
                    'source_type' => $param['source_type']
                )
            );
        }
        isset($param['debug']) && print_r(json_encode($query));
        $res = $this->client->find(array(
            'index' => get_index($param['keywords_code'], $param['start_time'], $param['end_time'], $this->client),
            'type' => 'lable',
            'search_type' => 'count',
            'body' => json_encode($query)
        ));

        if (!empty($res['aggregations']['key']['buckets'])) {
            return $res['aggregations']['key']['buckets'];
        } else {
            return array();
        }
    }

    /**
     * 根据url_crc查询data单一数据,需要去lable表获取主题号联查
     * 预览使用
     */
    function get_one_data($url_crc, $start_time, $end_time, $topicid, $type = 'data')
    {
        if ($url_crc == '' || $start_time == '' || $end_time == '' || $topicid == '') {
            return;
        }
        $query = array(
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array(
                            'has_child' => array(
                                'type' => 'lable',
                                'query' => array(
                                    'bool' => array(
                                        'must' => array(
                                            0 => array(
                                                'range' => array(
                                                    'lable.release_date' => array(
                                                        'from' => $start_time,
                                                        'to' => $end_time
                                                    )
                                                )
                                            ),
                                            1 => array(
                                                'term' => array(
                                                    'lable.keywords_code' => $topicid
                                                )
                                            )
                                        )
                                    )
                                )
                            )
                        ),
                        array('terms' =>
                            array('url_crc' => $url_crc)
                        ),
                        array('range' => array(
                            'release_date' => array(
                                'from' => $start_time,
                                'to' => $end_time
                            )
                        )
                        )
                    ),
                )
            )
        );
        $index_join = get_index($topicid, $start_time, $end_time, $this->client);
        $body_json = json_encode($query);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = $type;
            $params['body'] = $body_json;
            $res = $this->client->find($params);
        }
        return $res;
    }

    /**
     * 查询dpt
     * url_crc为数组
     */
    function search_dpt($url_crc, $topicid, $start_time, $end_time,$is_recall=true)
    {
        if ($url_crc == '' || $start_time == '' || $end_time == '' || $topicid == '') return;
        $query = array(
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array('terms' =>
                            array('url_crc' => $url_crc)
                        )
                    )
                )
            )
        );
        $index_join = get_index($topicid, $start_time, $end_time, $this->client,$is_recall);
        $body_json = json_encode($query);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = 'dpt_newest';
            $params['body'] = $body_json;
//            print_r($body_json);
            $res = $this->client->find($params);
        }
        return $res;
    }

    /**
     * 查询lable url_crc需要为数据
     */
    function search_lable($url_crc, $topicid, $start_time, $end_time,$tag ='')
    {
        if ($url_crc == '' || $start_time == '' || $end_time == '' || $topicid == '') return;
        $query = array(
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array('terms' =>
                            array('url_crc' => $url_crc)),
                        array('term' =>
                            array('keywords_code' => $topicid)),
                        array('range' => array(
                            'release_date' => array(
                                'from' => $start_time,
                                'to' => $end_time
                            )
                        )
                        )
                    )
                )
            ),
            'from'=>'0',
            'size'=>'10000'

        );
        $tag = $this->set_tag($tag);
        if(!empty($tag)){
            $query['query']['bool']['must'][]=$tag;
        }
        $index_join = get_index($topicid, $start_time, $end_time, $this->client);
        $body_json = json_encode($query);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = 'lable';
            $params['body'] = $body_json;
            $res = $this->client->find($params);
        }
        return $res;
    }

    /***
     * 查询weibo_lable user
     * where是个数组 必备start_time,end_time,topic_id
     */
    function search_weibo_lable($where, $isdistinct, $search_type = 'data',$page = 1, $page_num = 50)
    {
        if ($where['start_time'] == '' || $where['end_time'] == '' || $where['topic_id'] == '') return;
        $query = array(
            'query' => array(
                'filtered' => array(
                    'query' => array(
                        'joinquery' => array(
                            'type' => 'weibo_user',
                            'field' => 'siteurl_crc',
                            'forign' => 'siteurl_crc',
                            'query' => array(
                                'bool' => array(
                                    'must' => array(
                                        array('term' => array('keywords_code' => $where['topic_id']),
                                        ),
                                        array('range' => array('release_date' => array('from' => $where['start_time'], 'to' => $where['end_time']))
                                        ),
                                        array('term' => array('source_type' => '4'))
                                    )
                                )
                            ),
                            'query_inner_hits' => array(
                                array('name' => 'e',
                                    '_parent' => 'siteurl_crc',
                                    '_inner' => 'siteurl_crc',
                                    'size' => 1000
                                )
                            )
                        )
                    )
                )
            )
        );
        $start_num = 0;
        if ($page_num <= 0) $page_num = 10;
        if ($page > 0) $start_num = ($page - 1) * $page_num;
        $query = array_merge($query, array('from' => $start_num, 'size' => $page_num));
        $query['sort'] = array('release_date' => array('order' => 'desc'));  //排序
        $tag = $this->set_tag($where['tag'], 'weibo_lable');
        if ($isdistinct != '') {
            $query['query']['filtered']['filter'] = $this->set_distinct($isdistinct);
        }
        if ($where['gender']!='') {
            if($where['gender'] =='2'||$where['gender']=='1'){
                $gender = $this->set_gender($where['gender']);
                $query['query']['filtered']['query']['joinquery']['query_join']['bool']['must'][] = $gender;
            }else {
                $query['query']['filtered']['query']['joinquery']['query_join']['bool']['must_not'][] = array('terms' => array('gender' => array(1,2)));
            }
        }
        if ($where['province']!='') {
            $query['query']['filtered']['query']['joinquery']['query_join']['bool']['must'][] = $this->set_province($where['province']);
        }
        if($where['min']!=''&&$where['max']!=''){
            $query['query']['filtered']['query']['joinquery']['query_join']['bool']['must'][] = $this->set_year($where);
        }
        if($where['title_crc']!=''){
            $query['query']['filtered']['query']['joinquery']['query']['bool']['must'][] = array(
                'term' => array(
                    'title_crc' => $where['title_crc']
                )
            );
        }
        if (isset($tag)) {
            $query['query']['filtered']['query']['joinquery']['query']['bool']['must'][] = $tag;
        };
        $index_join = get_index($where['topic_id'], $where['start_time'], $where['end_time'], $this->client);
        $body_json = json_encode($query);
//        print_r($body_json);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = 'weibo_lable';
            $params['body'] = $body_json;
            if ($search_type == 'count') $params['search_type'] = 'count';
            $res = $this->client->find($params);
        }
        return $res;
    }

    function search_weibo_similars($where,$isdistinct){
        if ($where['start_time'] == '' || $where['end_time'] == '' || $where['topic_id'] == '') return;
        $query = array(
            'query' => array(
                'filtered' => array(
                    'query' => array(
                        'joinquery' => array(
                            'type' => 'weibo_user',
                            'field' => 'siteurl_crc',
                            'forign' => 'siteurl_crc',
                            'query' => array(
                                'bool' => array(
                                    'must' => array(
                                        array('term' => array('keywords_code' => $where['topic_id']),
                                        ),
                                        array('range' => array('release_date' => array('from' => $where['start_time'], 'to' => $where['end_time']))
                                        ),
                                        array('term' => array('source_type' => '4')),
                                        array('terms' =>
                                            array('title_crc' => $where['title_crc'])),
                                    )
                                )
                            ),
                            'query_inner_hits' => array(
                                array('name' => 'e',
                                    '_parent' => 'siteurl_crc',
                                    '_inner' => 'siteurl_crc',
                                    'size' => 1000
                                )
                            )
                        )
                    )
                )
            )
        );
        $tag = $this->set_tag($where['tag'], 'weibo_lable');
        if ($isdistinct != '') {
            $query['query']['filtered']['filter'] = $this->set_distinct($isdistinct);
        }
        if ($where['gender']!='') {
            if($where['gender'] =='2'||$where['gender']=='1'){
                $gender = $this->set_gender($where['gender']);
                $query['query']['filtered']['query']['joinquery']['query_join']['bool']['must'][] = $gender;
            }else {
                $query['query']['filtered']['query']['joinquery']['query_join']['bool']['must_not'][] = array('terms' => array('gender' => array(1,2)));
            }
        }
        if ($where['province']!='') {
            $query['query']['filtered']['query']['joinquery']['query_join']['bool']['must'][] = $this->set_province($where['province']);
        }
        if($where['min']!=''&&$where['max']!=''){
            $query['query']['filtered']['query']['joinquery']['query_join']['bool']['must'][] = $this->set_year($where);
        }
        if (isset($tag)) {
            $query['query']['filtered']['query']['joinquery']['query']['bool']['must'][] = $tag;
        };
        $query['aggs'] = array(
            'similar' => array(
                'hiterms' => array(
                    'field' => 'title_crc',
                    'size' => $where['num'],
                    'distinct' => 'url_crc',
                    'hierarchy' => -1,
                    "elimination" => "1",
                ),
            )
        );
        $index_join = get_index($where['topic_id'], $where['start_time'], $where['end_time'], $this->client);
        $body_json = json_encode($query);
//        print_r($body_json);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = 'weibo_lable';
            $params['body'] = $body_json;
            $res = $this->client->find($params);
        }
        return $res;
    }
    /*
     * 用于weibo_lable
     */
    function set_distinct($type)
    {
        return array(
                'distinct' => array(
                    'field' =>
                        $type,
                        'elimination' => '1'
                )
        );
    }
    /*
     * 设置性别
     */
    function set_gender($gender)
    {
        return array(
            'term' => array(
                'gender' => $gender
            )
        );
    }
    /*
     * 设置地区
     */
    function set_province($province)
    {
        return array(
            'term' => array(
                'province' => $province
            )
        );
    }
    function set_year($where){
        return array(
            'range' => array(
                'birth_year' => array(
                    'from' => $where['min'], 'to' => $where['max']
                )
            )
        );
    }
    /*获取库名字方便外部调用*/
    function index_name($topicid, $start_time, $end_time)
    {
        return $index_join = get_index($topicid, $start_time, $end_time, $this->client);
    }
    //清除es缓存,主要用于标无效和修改正负面
    function clear_caches(){
        return $this->client->clear_caches();
    }
    //刷新es索引,防止变更数据导致数据显示有问题
    function refresh($topic_id,$start_time,$end_time){
        $index_join = get_index($topic_id, $start_time, $end_time, $this->client);
        $params['index'] = $index_join;
        return $this->client->refresh($params);
    }
    /**
     * 查询dpt
     * url_crc为数组
     */
    function search_weibo_user($site_url_crc, $topicid, $start_time, $end_time,$is_recall=true)
    {
        if ($site_url_crc == '' || $start_time == '' || $end_time == '' || $topicid == '') return '';
        $query = array(
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array('terms' =>
                            array('siteurl_crc' => $site_url_crc)
                        )
                    )
                )
            )
        );
        $index_join = get_index($topicid, $start_time, $end_time, $this->client,$is_recall);
        $body_json = json_encode($query);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = 'weibo_user';
            $params['body'] = $body_json;
//            print_r($body_json);
            $res = $this->client->find($params);
        }
        return $res;
    }

    /*
     * 按照时间统计
     * $where = array(start_time,end_time,field,distinct)
     */
    function agg_data($where){
        if ($where['start_time'] == '' || $where['end_time'] == '' || $where['field'] == '') return '';
        $query = array(
            'query' => array(
                'range' => array(
                    'release_date' => array(
                        'from' => $where['start_time'],
                        'to' => $where['end_time']
                        )
                    )
            ),
            'aggs' => array(
                'topic' => array(
                    'hiterms' => array(
                        'field' => $where['field'],
                        'distinct' => $where['distinct'],
                        'elimination' => '1',
                        'size' => 0
                    )
                )
            ),

        );
        $index_join = '';
        $index_joins = select_7d($where['start_time'], $where['end_time'],'');
        toggle_es('second',$this->client);
        foreach($index_joins as $index=>$val){
            if($this->client->exist_index($index)){
                if($index_join=='') $index_join = $index;
                else $index_join .= ','.$index;
            }
        }
        $body_json = json_encode($query);
        if ($index_join != '') {
            $params['index'] = $index_join;
            $params['type'] = 'lable';
            $params['search_type'] = 'count';
            $params['body'] = $body_json;
            toggle_es('second',$this->client);
            $res = $this->client->find($params);
        }
        return $res;
    }

    function exist_index($index){
        if($this->client->exist_index($index)){
            return true;
        }else{
            return false;
        }
    }
}
