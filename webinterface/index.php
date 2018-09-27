<?PHP
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if(in_array('sha512', hash_algos())) {
	ini_set('session.hash_function', 'sha512');
}
if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") {
	ini_set('session.cookie_secure', 1);
	if(!headers_sent()) {
		header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload;");
	}
}
session_start();

require_once('../other/config.php');
require_once('../other/phpcommand.php');

function enter_logfile($logpath,$timezone,$loglevel,$logtext,$norotate = false) {
	$file = $logpath.'ranksystem.log';
	if ($loglevel == 1) {
		$loglevel = "  CRITICAL  ";
	} elseif ($loglevel == 2) {
		$loglevel = "  ERROR     ";
	} elseif ($loglevel == 3) {
		$loglevel = "  WARNING   ";
	} elseif ($loglevel == 4) {
		$loglevel = "  NOTICE    ";
	} elseif ($loglevel == 5) {
		$loglevel = "  INFO      ";
	} elseif ($loglevel == 6) {
		$loglevel = "  DEBUG     ";
	}
	$loghandle = fopen($file, 'a');
	fwrite($loghandle, DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''))->setTimeZone(new DateTimeZone($timezone))->format("Y-m-d H:i:s.u ").$loglevel.$logtext."\n");
	fclose($loghandle);
	if($norotate == false && filesize($file) > 5242880) {
		$loghandle = fopen($file, 'a');
		fwrite($loghandle, DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''))->setTimeZone(new DateTimeZone($timezone))->format("Y-m-d H:i:s.u ")."  NOTICE    Logfile filesie of 5 MiB reached.. Rotate logfile.\n");
		fclose($loghandle);
		$file2 = "$file.old";
		if (file_exists($file2)) unlink($file2);
		rename($file, $file2);
		$loghandle = fopen($file, 'a');
		fwrite($loghandle, DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''))->setTimeZone(new DateTimeZone($timezone))->format("Y-m-d H:i:s.u ")."  NOTICE    Rotated logfile...\n");
		fclose($loghandle);
	}
}

function getclientip() {
	if (!empty($_SERVER['HTTP_CLIENT_IP']))
		return $_SERVER['HTTP_CLIENT_IP'];
	elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	elseif(!empty($_SERVER['HTTP_X_FORWARDED']))
		return $_SERVER['HTTP_X_FORWARDED'];
	elseif(!empty($_SERVER['HTTP_FORWARDED_FOR']))
		return $_SERVER['HTTP_FORWARDED_FOR'];
	elseif(!empty($_SERVER['HTTP_FORWARDED']))
		return $_SERVER['HTTP_FORWARDED'];
	elseif(!empty($_SERVER['REMOTE_ADDR']))
		return $_SERVER['REMOTE_ADDR'];
	else
		return false;
}

if(($last_access = $mysqlcon->query("SELECT `last_access`,`count_access` FROM `$dbname`.`config`")->fetchAll()) === false) {
	$err_msg .= print_r($mysqlcon->errorInfo(), true);
}

if(($last_access[0]['last_access'] + 1) >= time()) {
	$again = $last_access[0]['last_access'] + 2 - time();
	$err_msg = sprintf($lang['errlogin2'],$again);
	$err_lvl = 3;
} elseif ($last_access[0]['count_access'] >= 10) {
	enter_logfile($logpath,$timezone,3,sprintf($lang['brute'], getclientip()));
	$err_msg = $lang['errlogin3'];
	$err_lvl = 3;
	$bantime = time() + 299;
	if($mysqlcon->exec("UPDATE `$dbname`.`config` SET `last_access`='$bantime',`count_access`='0'") === false) { }
} elseif (isset($_POST['username']) && $_POST['username'] == $webuser && password_verify($_POST['password'], $webpass)) {
	$_SESSION[$rspathhex.'username'] = $webuser;
	$_SESSION[$rspathhex.'password'] = $webpass;
	$_SESSION[$rspathhex.'clientip'] = getclientip();
	$_SESSION[$rspathhex.'newversion'] = $newversion;
	enter_logfile($logpath,$timezone,6,sprintf($lang['brute2'], getclientip()));
	if($mysqlcon->exec("UPDATE `$dbname`.`config` SET `count_access`='0'") === false) { }
	header("Location: //".$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']), '/\\')."/bot.php");
	exit;
} elseif(isset($_POST['username'])) {
	$nowtime = time();
	enter_logfile($logpath,$timezone,5,sprintf($lang['brute1'], getclientip(), $_POST['username']));
	if($mysqlcon->exec("UPDATE `$dbname`.`config` SET `last_access`='$nowtime',`count_access`=`count_access` + 1") === false) { }
	$err_msg = $lang['errlogin'];
	$err_lvl = 3;
}

if(isset($_SESSION[$rspathhex.'username']) && $_SESSION[$rspathhex.'username'] == $webuser && $_SESSION[$rspathhex.'password'] == $webpass) {
	header("Location: //".$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']), '/\\')."/bot.php");
	exit;
}

require_once('nav.php');
?>
		<div id="page-wrapper">
<?PHP if(isset($err_msg)) error_handling($err_msg, $err_lvl); ?>
			<div class="container-fluid">
				<div id="login-overlay" class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header">
						  <h4 class="modal-title" id="myModalLabel"><?PHP echo $lang['isntwiusrh']; ?></h4>
						</div>
						<div class="modal-body">
							<div class="row">
								<div class="col-xs-12">
									<form id="loginForm" method="POST">
										<div class="form-group">
											<label for="username" class="control-label"><?PHP echo $lang['user']; ?>:</label>
											<div class="input-group">
												<span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
												<input type="text" class="form-control" name="username" placeholder="" maxlength="64" autofocus>
											</div>
										</div>
										<div class="form-group">
											<label for="password" class="control-label"><?PHP echo $lang['pass']; ?>:</label>
											<div class="input-group">
												<span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
												<input type="password" class="form-control" name="password" placeholder="<?PHP echo $lang['pass']; ?>">
											</div>
										</div>
										<br>
										<p>
											<button type="submit" class="btn btn-success btn-block"><?PHP echo $lang['login']; ?></button>
										</p>
										<p class="small text-right">
											<a href="resetpassword.php"><?PHP echo $lang['pass5']; ?></a>
										</p>
									</form>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
</html>