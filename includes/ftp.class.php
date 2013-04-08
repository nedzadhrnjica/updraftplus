<?php
/* from http://www.solutionbot.com/2009/01/02/php-ftp-class/ */
class UpdraftPlus_ftp_wrapper
{
	private $conn_id;
	private $host;
	private $username;
	private $password;
	private $port;
	public  $timeout = 90;
	public  $passive = true;
	public  $system_type = '';
	public $login_type = 'non-encrypted';
 
	public function __construct($host, $username, $password, $port = 21)
	{
		$this->host     = $host;
		$this->username = $username;
		$this->password = $password;
		$this->port     = $port;
	}
 
	public function connect()
	{
		$this->conn_id = ftp_connect($this->host, $this->port);

		if ($this->conn_id === false) return false;
 
		$result = ftp_login($this->conn_id, $this->username, $this->password);
 
		if ($result == true)
		{
			ftp_set_option($this->conn_id, FTP_TIMEOUT_SEC, $this->timeout);
 
			if ($this->passive == true)
			{
				ftp_pasv($this->conn_id, true);
			}
			else
			{
				ftp_pasv($this->conn_id, false);
			}
 
			$this->system_type = ftp_systype($this->conn_id);
 
			return true;
		}
		else
		{
			return false;
		}
	}
 
	public function put($local_file_path, $remote_file_path, $mode = FTP_BINARY, $resume = false, $updraftplus = false) {

		$file_size = filesize($local_file_path);

		$existing_size = 0;
		if ($resume) {
			$existing_size = ftp_size($this->conn_id, $remote_file_path);
			if ($existing_size <=0) { $resume = false; $existing_size = 0; }
			elseif ($updraftplus) {
				$updraftplus->log("File already exists at remote site: size $existing_size. Will attempt resumption.");
				if ($existing_size >= $file_size) {
					$updraftplus->log("File is apparently already completely uploaded");
					return true;
				}
			}
		}

		// From here on, $file_size is only used for logging calculations. We want to avoid divsion by zero.
		$file_size = max($file_size, 1);

		if (!$fh = fopen($local_file_path, 'rb')) return false;
		if ($existing_size) fseek($fh, $existing_size);

		$ret = ftp_nb_fput($this->conn_id, $remote_file_path, $fh, FTP_BINARY, $existing_size);

		// $existing_size can now be re-purposed

		while ($ret == FTP_MOREDATA) {
			if ($updraftplus) {
				$new_size = ftell($fh);
				if ($new_size - $existing_size > 524288) {
					$existing_size = $new_size;
					$percent = round(100*$new_size/$file_size,1);
					$updraftplus->record_uploaded_chunk($percent, '', $local_file_path);
				}
			}
			// Continue upload
			$ret = ftp_nb_continue($this->conn_id);
		}

		fclose($fh);

		if ($ret != FTP_FINISHED) {
			if ($updraftplus) $updraftplus->log("FTP upload: error ($ret)");
			return false;
		}

		return true;

	}
 
	public function get($local_file_path, $remote_file_path, $mode = FTP_BINARY, $resume = false,  $updraftplus = false) {

		$file_last_size = 0;

		if ($resume) {
			if (!$fh = fopen($local_file_path, 'ab')) return false;
			clearstatcache($local_file_path);
			$file_last_size = filesize($local_file_path);
		} else {
			if (!$fh = fopen($local_file_path, 'wb')) return false;
		}

		$ret = ftp_nb_fget($this->conn_id, $fh, $remote_file_path, $mode, $file_last_size);

		if (false == $ret) return false;

		while ($ret == FTP_MOREDATA) {

			if ($updraftplus) {
				$file_now_size = filesize($local_file_path);
				if ($file_now_size - $file_last_size > 524288) {
					$updraftplus->log("FTP fetch: file size is now: ".sprintf("%0.2f",filesize($local_file_path)/1048576)." Mb");
					$file_last_size = $file_now_size;
				}
				clearstatcache($local_file_path);
			}

			$ret = ftp_nb_continue($this->conn_id);
		}

		fclose($fh);

		if ($ret == FTP_FINISHED) {
			if ($updraftplus) $updraftplus->log("FTP fetch: fetch complete");
			return true;
		} else {
			if ($updraftplus) $updraftplus->log("FTP fetch: fetch failed");
			return false;
		} 

	}

	public function chmod($permissions, $remote_filename)
	{
		if ($this->is_octal($permissions)) {
			$result = ftp_chmod($this->conn_id, $permissions, $remote_filename);
			if ($result) {
				return true;
			} else {
				return false;
			}
		} else {
			throw new Exception('$permissions must be an octal number');
		}
	}
 
	public function chdir($directory) {
		ftp_chdir($this->conn_id, $directory);
	}
 
	public function delete($remote_file_path) {
		if (ftp_delete($this->conn_id, $remote_file_path)) {
			return true;
		} else {
			return false;
		}
	}
 
	public function make_dir($directory)
	{
		if (ftp_mkdir($this->conn_id, $directory))
		{
			return true;
		}
		else 
		{
			return false;
		}
	}
 
	public function rename($old_name, $new_name)
	{
		if (ftp_rename($this->conn_id, $old_name, $new_name))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
 
	public function remove_dir($directory)
	{
		if (ftp_rmdir($this->conn_id, $directory))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
 
	public function dir_list($directory)
	{
		$contents = ftp_nlist($this->conn_id, $directory);
		return $contents;
	}
 
	public function cdup()
	{
		ftp_cdup($this->conn_id);
	}
 
	public function current_dir()
	{
		return ftp_pwd($this->conn_id);
	}
 
	private function is_octal($i) 
	{
    	return decoct(octdec($i)) == $i;
	}
 
	public function __destruct()
	{
		if ($this->conn_id)
		{
			ftp_close($this->conn_id);
		}
	}
}
?>