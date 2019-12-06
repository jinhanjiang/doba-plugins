<?php
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
        if(! $this->conn) throw new \Exception("FTP server connection failed", 98021001); 
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
        $this->up(array('path'=>$downpath, 'newpath'=>$newpath));  
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
        $path_arr  = explode('/', $path);// 取目录数组  
        $file_name = array_pop($path_arr);// 弹出文件名  
        $path_div  = count($path_arr);// 取层数  
  
        $new_path = '';
        foreach($path_arr as $key=>$val)// 创建目录
        {  
            $new_path .= (0==$key?'':'/').$val;
            if(! $this->isdir($new_path))  
            {  
                $tmp = @ftp_mkdir($this->conn, $val);  
                if($tmp === FALSE) {  
                    throw new \Exception("Directory creation failed, please check the permissions and path is correct!");
                }  
            }  
            @ftp_chdir($this->conn, $val);  
        }  
        for($i=1; $i <= $path_div; $i++) { // 回退到根  
            @ftp_cdup($this->conn);  
        }  
    }    

    /**
     * http://php.net/manual/en/wrappers.ftp.php
     * 方法：判断远程目录是否存在(code:17)
     * @param $path 路径
     */
    public function isdir($path) {
        return is_dir("ftp://{$this->user}:{$this->pass}@{$this->host}:{$this->port}/{$path}");
    }

    /**
     * 方法：判断远程文件是否存在(code:18)
     * @param $file 文件全路径
     */
    public function isfile($file) {
        return is_file("ftp://{$this->user}:{$this->pass}@{$this->host}:{$this->port}/{$file}");
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