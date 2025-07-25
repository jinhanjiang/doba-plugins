<?php

namespace Doba\Plugin\Adapter;

class FtpAdapter extends IAdapter {

    private $conn = null;
    private $configs = array();

    private $host = '127.0.0.1';
    private $port = 21;
    private $user = 'root';
    private $pass = '';

    /*
        传入参数格式
         array(
            'default'=>array('host'=>'127.0.0.1', 'port'=>'21', 'user'=>'root', 'pass'=>'')
        )
     */
    public function __construct($configs = array()) {
        $this->configs = $configs;
        $this->start();
    }

    public function start($key = 'default') {
        if(! extension_loaded('ftp')) throw new \Exception('ftp extension not loaded');
        $key = isset($this->configs['key']) ? $this->configs['key'] : 'default';
        if(isset($this->configs[$key]['host'])) $this->host = $this->configs[$key]['host'];
        if(isset($this->configs[$key]['port'])) $this->port = $this->configs[$key]['port'];
        if(isset($this->configs[$key]['user'])) $this->user = $this->configs[$key]['user'];
        if(isset($this->configs[$key]['pass'])) $this->pass = $this->configs[$key]['pass'];
        return $this;
    }

    private function connect()
    {
        if($this->conn) return true;
        $this->conn = ftp_connect($this->host, $this->port);
        if(! $this->conn) throw new \Exception("FTP server connection failed"); 
        if(! @ftp_login($this->conn, $this->user,$this->pass)) throw new \Exception("FTP server login failed");
        @ftp_pasv($this->conn, 1); // 打开被动模拟  
    }
  
    /** 
     * 方法：列出文件 
     * @param $path    服务器路径 
     * @param $detail  返回详细信息
     */  
    public function ls($path, $detail=true)  
    {  
        $this->connect();
        return $detail ? @ftp_rawlist($this->conn, $path) : @ftp_nlist($this->conn, $path);
    }  
  
    /** 
     * 方法：上传文件
     * @param $path    本地路径 
     * @param $newpath 上传路径 
     */  
    public function put($path, $newpath)  
    {  
        $this->connect();
        $this->mkdirs($newpath);  //若目标目录不存在则新建
        $onoff = @ftp_put($this->conn, $newpath, $path, FTP_BINARY);  
        if(! $onoff) throw new \Exception("File upload failed, please check the permissions and path is correct!");  
    }  
    
    /** 
     * 方法：下载文件
     * @param $localfile  本地器文件路径 
     * @param $serverfile 服务器文件路径 
     */  
    public function get($localfile, $serverfile)  
    {  
        $this->connect();
        $onoff = @ftp_get($this->conn, $localfile, $serverfile, FTP_BINARY);  
        if(! $onoff) throw new \Exception("File download failed, please check permissions and path is correct!");  
    }  
  
    /** 
     * 方法：移动文件
     * @param $path    原路径 
     * @param $newpath 新路径 
     */  
    public function move($path, $newpath)  
    {  
        $this->connect();
        $this->mkdirs($newpath);  
        $onoff = @ftp_rename($this->conn, $path, $newpath);  
        if(! $onoff) throw new \Exception("File move failed, please check permissions and path is correct!");  
    }  
  
    /** 
     * 方法：复制文件
     * 说明：由于FTP无复制命令,本方法变通操作为：下载后再上传到新的路径 
     * @param $path    原路径 
     * @param $newpath 新路径 
     */  
    public function copy($path, $newpath)  
    {  
        $this->connect();
        $downpath = (strtoupper(substr(PHP_OS,0,3))==='WIN') ? "C:/tmp.dat" : "/tmp/tmp.dat";  
        $onoff = @ftp_get($this->conn, $downpath, $path, FTP_BINARY);// 下载  
        if(! $onoff) throw new \Exception("File copy failed, please check permissions and path is correct!");  
        $this->put($downpath, $newpath);  
    }  
  
    /** 
     * 方法：删除文件
     * @param $path 路径 
     */  
    public function del($path)  
    {  
        $this->connect();
        $onoff = @ftp_delete($this->conn, $path);  
        if(! $onoff) throw new \Exception("File delete failed, please check permissions and path is correct!");  
    }
  
    /** 
     * 方法：生成目录
     * @param $path 路径 
     */  
    public function mkdirs($path)
    {  
        $this->connect();
        // 取目录数组  
        $paths  = explode('/', $path); array_pop($paths);// 弹出文件名  
  
        $newpath = implode('/', $paths);
        if($this->isdir($newpath)) return true;

        $newpath = ''; $chdirs = array();
        foreach($paths as $idx => $ph)
        {
            $newpath .= (0 == $idx ? '' : '/').$ph;
            if(! $this->isdir($newpath))
            {
                foreach($chdirs as $chdir) {
                    @ftp_chdir($this->conn, $chdir);
                }
                $mkflag = @ftp_mkdir($this->conn, $ph);  
                if($mkflag === FALSE) {  
                    throw new \Exception("Directory creation failed, please check the permissions and path is correct!");
                }  
                for($i = 0; $i < count($chdirs); $i ++) {
                    @ftp_cdup($this->conn);
                }
            }
            $chdirs[] = $ph;
        }
        return true;
    }    

    /**
     * http://php.net/manual/en/wrappers.ftp.php
     * 以下注释的两个方法(isdir, isfile)在有些条件下，是能正常执行的。但有不能生效的情况。
     * 方法：判断远程目录是否存在
     * @param $path 路径
     */
    // public function isdir($path) {
    //     return is_dir("ftp://{$this->user}:{$this->pass}@{$this->host}:{$this->port}/{$path}");
    // }
    /**
     * 方法：判断远程文件是否存在
     * @param $file 文件全路径
     */
    // public function isfile($file) {
    //     return is_file("ftp://{$this->user}:{$this->pass}@{$this->host}:{$this->port}/{$file}");
    // }

    public function isdir($path)
    {
        $path = preg_replace('/^\//', '', $path);
        $name = basename($path); $path = dirname($path);
        $files = $this->scandir($path);
        if(is_array($files)) 
            foreach($files as $fname => $data) {
            if($fname == $name) {
                return ($data['type'] == 'directory') ? true : false;
            }
        }
        return false;
    }

    public function isfile($path)
    {
        $path = preg_replace('/^\//', '', $path);
        $name = basename($path); $path = dirname($path);
        $files = $this->scandir($path);
        if(is_array($files)) 
            foreach($files as $fname => $data){
            if($fname == $name) {
                return ($data['type'] == 'file') ? true : false;
            }
        }
        return false;
    }

    private function scandir($path) {
        $this->connect();
        if(! $path) $path = "."; 
        if(is_array($children = @ftp_rawlist($this->conn, $path))){
            $items = array();
            foreach($children as $name => $child){
                $chunks = preg_split("/\s+/", $child);
                list($item['rights'], $item['number'], $item['user'], $item['group'], $item['size'], $item['month'], $item['day'], $item['time']) = $chunks;
                $item['type'] = $chunks[0][0] === 'd' ? 'directory' : 'file';
                array_splice($chunks, 0, 8);
                $items[implode(" ", $chunks)] = $item;
            }
            return $items;
        }
        return false;
    }
  
    /** 
     * 方法：关闭FTP连接 
     */  
    public function close() {  
        if($this->conn) {
            @ftp_close($this->conn); $this->conn = null;
        }
    }  
}