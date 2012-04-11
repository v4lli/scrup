<?
# Example receiver of Scrup files
#
# Install by putting this file on your web server and give the web server 
# user write permissions to the directory in which you put this script.
#
$SECRET = ""; # Set to "" if you don't want authentication
$MAXLENGTH = 4096000; # 4 MB
$HTTP_LINK = true; # Set to true if you want all image links to point to the unsecure version
function rsperr($msg='', $st='400 Bad Request') {
	header('HTTP/1.1 '.$st);
	exit($msg);	
}
function pathfromid($id, $suffix='') {
	return substr($id,0,2).'/'.substr($id,2).$suffix;
}
# Build path and url
if (!isset($_GET['name']) || !trim($_GET['name']))
	$_GET['name'] = strval(time());
$id = substr(base_convert(md5($_GET['name'].' '.$_SERVER['REMOTE_ADDR']), 16, 36),0,15);
$suffix = strrchr($_GET['name'], '.');
$path = pathfromid($id, $suffix);
$abspath = dirname(realpath(__FILE__)).'/'.$path;
$url = (isset($_SERVER['HTTPS'] && $HTTP_LINK != true) ? 'https://' : 'http://')
	. $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $path;

// Check if the secret is correct
if(!empty($SECRET) && $_SERVER['HTTP_X_SCRUP_AUTH'] != base64_encode($SECRET)) {
    rsperr("Bad authentication credentials!", "401 Unauthorized");
}

# make dir if needed
$dirpath = dirname($abspath);
if (!file_exists($dirpath) && @mkdir($dirpath, 0775) === false)
	rsperr('failed to mkdir '.$dirpath, '500 Internal Server Error');

# Save input to file
$dstf = @fopen($abspath, 'w');
if (!$dstf)
	rsperr('unable to write to '.$dirpath, '500 Internal Server Error');
$srcf = fopen('php://input', 'r');
$size = stream_copy_to_stream($srcf, $dstf, $MAXLENGTH);
fclose($dstf);

# No input?
if ($size === 0) {
	@unlink($path);
	rsperr('empty input');
}
elseif ($size >= $MAXLENGTH) {
	@unlink($path); # because it's probably broken
	rsperr('Request entity larger than or equal to '.$MAXLENGTH.' B',
		'413 Request Entity Too Large');
}

# Respond with the url
header('HTTP/1.1 201 Created');
header('Content-Type: text/plain; charset=utf-8');
header('Content-Length: '.strlen($url));
echo $url;
?>
