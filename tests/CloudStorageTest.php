<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class CloudStorageTest extends \PHPUnit\Framework\TestCase
{
    public function testSaveImage(){
        $ret = CloudStorage::saveImagesByUrl('http://www.baidu.com/img/baidu_jgylogo3.gif');
        var_dump($ret);
        $this->assertNotFalse($ret);
    }

    public function testTmpFile(){
        $tmpfile = CloudStorage::getImagesTmpFile('http://www.baidu.com/img/baidu_jgylogo3.gif');

        if (class_exists('finfo')) {
            $finfo =  new finfo(FILEINFO_MIME);
            var_dump( $finfo->file($tmpfile));
        }
//        else {
//            if (function_exists('exif_imagetype')) {
//                $exif_type = @exif_imagetype($tmpfile);
//                if ($exif_type == IMAGETYPE_GIF) {
//                    $mime = 'image/gif';
//                } elseif ($exif_type == IMAGETYPE_JPEG) {
//                    $mime = 'image/jpeg';
//                } elseif ($exif_type == IMAGETYPE_PNG) {
//                    $mime = 'image/png';
//                } elseif ($exif_type == IMAGETYPE_BMP) {
//                    $mime = 'image/bmp';
//                }
//                var_dump($mime);
//            }
//        }
        $this->assertTrue(file_exists($tmpfile));
        unlink($tmpfile);
    }

    public function testBaidu()
    {
        $file_path = APP_PATH.'/test.html';
        $object_name = '/test/test.html';

        //上传百度云
        $ret = CloudStorage::saveToBaidu($file_path,$object_name);
        $this->assertEquals( Di::getDefault()->get('config')->Baidu->url.$object_name, $ret);

        //内容一致
        $content = CloudStorage::getFromBaidu($object_name);
        $this->assertEquals(file_get_contents($file_path), $content);


        //文件存在
        $check = CloudStorage::remoteExists($object_name,null,'baidu');
        $this->assertNotFalse($check);

        // 删除文件
        $result = CloudStorage::delFromBaidu($object_name);
        $this->assertEquals(true,$result);

        // 删除后不存在
        $check = CloudStorage::remoteExists($object_name,null,'baidu');
        $this->assertFalse($check);
    }

    public function testAliOss()
    {
        $domain_url = Di::getDefault()->get('config')->Storage->alioss_domain_url;
        $domain_url = trim($domain_url,'/');

        $file_path = APP_PATH.'/test.html';
        $object_name = '/test/test.html';

        //上传阿里云
        $ret = CloudStorage::saveToAliOss($file_path,$object_name);
        $this->assertEquals( $domain_url.$object_name, $ret,'上传成功');

        //内容一致
        $content = CloudStorage::getFromAliOss($object_name);
        $this->assertEquals(file_get_contents($file_path), $content,'上传内容一致');


        //文件存在
        $check = CloudStorage::remoteExists($object_name,null,'oss');
        $this->assertNotFalse($check,'上传后文件存在');

        // 删除文件
        $result = CloudStorage::delFromAliOss($object_name);
        $this->assertEquals(true,$result,'删除文件成功');

        // 删除后不存在
        $check = CloudStorage::remoteExists($object_name,null,'oss');
        $this->assertFalse($check,'删除文件后不存在');
    }

    public function testQiniu()
    {
        $file_path = APP_PATH.'/test.html';
        $object_name = '/test/test.html';

        //写入七牛文件
        $ret = CloudStorage::saveToQiniu($file_path,$object_name);
        $this->assertEquals(Di::getDefault()->get('config')->Qiniu->url.$object_name,$ret);

        //文件内容相同
        $content = CloudStorage::getFromQiniu($object_name);
        $this->assertEquals(file_get_contents($file_path), $content);


        //文件存在
        $exists = CloudStorage::remoteExists($object_name,null,'qiniu');
        $this->assertNotFalse($exists);

        //删除文件
        $result = CloudStorage::delFromQiniu($object_name);
        $this->assertEquals($result,true);

        //删除后不存在
        $exists = CloudStorage::remoteExists($object_name,null,'qiniu');
        $this->assertFalse($exists);

    }


}