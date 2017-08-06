<?php
/**
* WordPress发布/更新文章、提交/审核评论自动清理阿里云CDN缓存 By 张戈博客
* 文章地址：https://zhangge.net/5112.html
* 转载请保留原文出处，谢谢合作！
*/

add_action('publish_post', 'cleanByPublish', 0);
add_action('comment_post', 'CleanByComments',0);
add_action('comment_unapproved_to_approved', 'CleanByApproved',0);

use Cdn\Request\V20141111 as Cdn; 
include_once dirname(__FILE__).'/aliyun-php-sdk-core/Config.php';

class Aliyun {
	protected $accessKey;
	protected $accessSecret;
	//初始化
	public function __construct($accessKey,$accessSecret){
	    //日志开关，1打开，0关闭
	    $this->logSwitch    = 1;
	    //清理缓存记录的日志文件，可以自行修改到其他路径
        $this->logFile      = '/tmp/cleanAlyCdnCache.log';
		//阿里云的accessKey，请按实际填写
		$this->accessKey    = '7QFzBBrvD1ktcqPy';
		//阿里云的accessSecret，请按实际填写
		$this->accessSecret = 'YHn8YJEeSYDTLxRuuVctlFZptoQH2b';
	}
	//初始化一个AliyunClient
	public function  getClient(){
		$iClientProfile = DefaultProfile::getProfile("cn-hangzhou",$this->accessKey, $this->accessSecret);
		$client = new DefaultAcsClient($iClientProfile);
		return $client;
	}
	// 记录日志
	public function cleanLog($msg) {
        // global $logFile, $logSwitch;
        if ($this->logSwitch == 0 ) return;
        date_default_timezone_set('Asia/Shanghai');
        file_put_contents($this->logFile, date('[Y-m-d H:i:s]: ') . $msg . PHP_EOL, FILE_APPEND);
        return $msg;
    }
    /**
     * 刷新阿里云CDN缓存
     * $client: 初始化SDK实例
     * $type  : 刷新类型，单页面或目录，即 File or Directory
     * $url   : 要刷新的页面或目录地址
     */
	public function refreshCache($client,$type,$url){
	    $freshCacheRequest = new Cdn\RefreshObjectCachesRequest();
        $freshCacheRequest->setObjectType($type); // or Directory
        $freshCacheRequest->setObjectPath("$url");
        try {
            // $resp = $client->execute($freshCacheRequest);
            $resp = $client->getAcsResponse($freshCacheRequest);
            if(!isset($resp->Code))
            {    
                //刷新成功
                $this->cleanLog($url." CleanUP success!");
            }
            else 
            {
                //刷新失败
                $code = $resp->Code;
                $message = $resp->Message;
                $this->cleanLog($url." CleanUP failed: ".$message);
            }
        }
        catch (Exception $e) {
            $this->cleanLog($url." CleanUP Exception: ".$e);
        }
	}
}

//发布/更新文章清理文章、首页、分类及标签页面缓存
function cleanByPublish($post_ID){
    $aliyun   = new Aliyun();
    $client   = $aliyun->getClient();
    $post_url = get_permalink($post_ID);
    $home_url = home_url().'/';
    //清理文章缓存
    $response = $aliyun->refreshCache($client,"File",$post_url);
    //清理首页缓存
    $response = $aliyun->refreshCache($client,"File",$home_url);
    
    //清理相关分类页面缓存（若不需要请注释以下6行）
    if ( $categories = wp_get_post_categories( $post_ID ) ) {
		foreach ( $categories as $category_id ) {
		    $cat_url  = get_category_link( $category_id );
		    $response = $aliyun->refreshCache($client,"File",$cat_url);
	    }
	}
	
    //清理相关标签页面缓存（若不需要请注释以下5行）
	if ( $tags = get_the_tags( $post_ID ) ) {
	    foreach ( $tags as $tag ) {
			$tag_url = get_tag_link( $tag->term_id );
		    $response = $aliyun->refreshCache($client,"File",$tag_url);
		}
	}
}

//提交评论清理文章缓存
function CleanByComments($comment_id) {
    $aliyun   = new Aliyun();
    $client   = $aliyun->getClient();
    $comment  = get_comment($comment_id);
    $post_url = get_permalink($comment->comment_post_ID);
    $response = $aliyun->refreshCache($client,"File",$post_url);
}

//评论审核通过清理文章缓存
function CleanByApproved($comment){
    $aliyun   = new Aliyun();
    $client   = $aliyun->getClient();
    $post_url = get_permalink($comment->comment_post_ID);
    $response = $aliyun->refreshCache($client,"File",$post_url);
}