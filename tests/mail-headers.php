<?php
// No WordPress database or SMTP connection: real PHPMailer builds every MIME message.
define('ABSPATH', rtrim(getenv('WORDPRESS_ROOT') ?: '/tmp/scw/wp', '/') . '/');
define('WPINC', 'wp-includes');
require ABSPATH . WPINC . '/PHPMailer/Exception.php';
require ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require ABSPATH . WPINC . '/PHPMailer/SMTP.php';
$GLOBALS['filters'] = []; $GLOBALS['events'] = []; $GLOBALS['logs'] = [];
$GLOBALS['settings'] = ['provider'=>'smtp','from_email'=>'settings@example.test','from_name'=>'Settings Name','host'=>'127.0.0.1','username'=>'synthetic-user','password'=>'synthetic-password'];
function plugin_dir_path($f) { return dirname($f).'/'; }
function plugin_dir_url($f) { return ''; }
function register_activation_hook() {} function register_deactivation_hook() {} function add_action() {}
function get_bloginfo($key) { return $key === 'charset' ? 'UTF-8' : 'Example Site'; }
function get_option($key, $default=[]) { return $GLOBALS['settings']; }
function apply_filters($tag, $value, ...$args) { return isset($GLOBALS['filters'][$tag]) ? $GLOBALS['filters'][$tag]($value, ...$args) : $value; }
function do_action($tag, ...$args) { $GLOBALS['events'][$tag][]=$args; }
function do_action_ref_array($tag, $args) { do_action($tag, ...$args); }
class WP_Error { public $message; function __construct($code, $message, $data) { $this->message=$message; } }
function sanitize_text_field($value) { return strip_tags($value); }
function maybe_serialize($value) { return is_array($value) || is_object($value) ? serialize($value) : $value; }
function current_time($format) { return '2026-11-07 00:00:00'; }
class CaptureDatabase { public $prefix='test_'; public $insert_id=1; function insert($table,$row) { $GLOBALS['logs'][]=$row; } }
$GLOBALS['wpdb']=new CaptureDatabase();
class CaptureMailer extends PHPMailer\PHPMailer\PHPMailer {
    public static $last;
    public function postSend() { self::$last=$this; return true; }
}
require dirname(__DIR__).'/cybersmtp.php';
require dirname(__DIR__).'/includes/providers/class-provider-abstract.php';
class CyberSMTP_Provider_SMTP extends CyberSMTP_Provider_Abstract {
    public function send($data) { return $this->send_smtp($data, '127.0.0.1', 587, 'tls'); }
    protected function get_phpmailer() { return new CaptureMailer(true); }
}
function check($condition, $description) { if (!$condition) { throw new RuntimeException($description); } echo "PASS $description\n"; }
function send_sample($headers='', $to='recipient@example.test') {
    CaptureMailer::$last=null;
    return wp_mail($to, 'Hello Björn', '<p>Björn</p>', $headers);
}
foreach (['', "\r\n\n", ['', ' ']] as $headers) {
    check(send_sample($headers), 'empty header variants send');
    check(CaptureMailer::$last->CharSet==='UTF-8', 'default charset UTF-8');
}
$headers=['From: Caller <caller@example.test>', 'Reply-To: "Doe, Jane" <jane@example.test>, other@example.test', 'Cc: "Smith, John" <john@example.test>, cc@example.test', 'Bcc: hidden@example.test', 'Content-Type: text/html; charset="UTF-8"', 'X-Trace: sample', ''];
check(send_sample($headers, '"Doe, Jo" <jo@example.test>, recipient@example.test'), 'structured display-name addresses send');
$mail=CaptureMailer::$last; $mime=$mail->getSentMIMEMessage();
check(count($mail->getToAddresses())===2 && $mail->getToAddresses()[0][1]==='Doe, Jo', 'quoted-comma To preserves display name');
check(count($mail->getCcAddresses())===2 && count($mail->getReplyToAddresses())===2, 'Cc and Reply-To preserve every mailbox');
check(isset($mail->getAllRecipientAddresses()['hidden@example.test']) && !preg_match('/^Bcc:/mi',$mime), 'Bcc reaches envelope without visible header');
check(preg_match_all('/^From:/mi',$mime)===1 && $mail->From==='caller@example.test', 'one caller From header');
check($mail->ContentType==='text/html' && $mail->CharSet==='UTF-8', 'HTML and quoted charset honored');
check(count($mail->getCustomHeaders())===1 && $mail->getCustomHeaders()[0][0]==='X-Trace', 'only legitimate custom headers retained');
$logged=end($GLOBALS['logs']);
check(unserialize($logged['headers'])===$headers, 'actual logger serializes original headers for resend');
$logged['headers']=unserialize($logged['headers']);
check(send_sample($logged['headers']), 'logged headers can be resent');
check(count(CaptureMailer::$last->getCcAddresses())===2 && count(CaptureMailer::$last->getBccAddresses())===1, 'resend preserves Cc and Bcc');
$GLOBALS['filters']['wp_mail_from_name']=fn($name)=>'Filtered Name';
check(send_sample() && CaptureMailer::$last->FromName==='Filtered Name', 'From name filter works independently');
$GLOBALS['filters']=[];
check(send_sample(['From: caller@example.test']) && CaptureMailer::$last->FromName==='Settings Name', 'email-only From keeps configured name');
$GLOBALS['filters']['pre_wp_mail']=fn()=>false;
check(!send_sample() && CaptureMailer::$last===null, 'pre_wp_mail can short-circuit without transport');
$GLOBALS['filters']=[];
check(!send_sample('', 'not-an-address'), 'invalid recipient fails without transport');
$errors=$GLOBALS['events']['wp_mail_failed'];
check(end($errors)[0]->message!=='', 'invalid recipient produces useful failure action');
require dirname(__DIR__).'/includes/providers/class-provider-cybermail.php';
class CaptureCyberMail extends CyberSMTP_Provider_CyberMail { protected function get_phpmailer() { return new CaptureMailer(true); } }
check(send_sample(['Cc'=>'copy@example.test','Bcc'=>'secret@example.test','Reply-To'=>'reply@example.test','Content-Type'=>'text/html; charset=UTF-8']), 'associative header arrays send');
check(count(CaptureMailer::$last->getCcAddresses())===1 && count(CaptureMailer::$last->getBccAddresses())===1 && CaptureMailer::$last->ContentType==='text/html', 'associative structured headers preserved');
check(send_sample('Content-Type: text/calendar; charset=UTF-8'), 'calendar content sends');
check(strpos(CaptureMailer::$last->getSentMIMEMessage(), 'Content-Type: text/calendar')!==false, 'non-HTML MIME content type preserved');
$serialized=end($GLOBALS['logs'])['headers'];
check(send_sample(unserialize($serialized)) && CaptureMailer::$last->ContentType==='text/calendar', 'string headers survive real logger serialization and resend');
$provider=new CaptureCyberMail(array_merge($GLOBALS['settings'], ['mode'=>'smtp']));
$parsed=cybersmtp_parse_headers($headers);
$data=['to'=>['recipient@example.test'],'subject'=>'Test','message'=>'Body','headers'=>$parsed['custom'],'from_email'=>'caller@example.test','from_name'=>'Caller','cc'=>$parsed['cc'],'bcc'=>$parsed['bcc'],'reply_to'=>$parsed['reply_to'],'charset'=>'UTF-8'];
$result=$provider->send($data);
check($result['success'] && $result['provider']==='cybermail-smtp', 'CyberMail SMTP fallback uses same safe builder');
check(count(CaptureMailer::$last->getCcAddresses())===2 && count(CaptureMailer::$last->getBccAddresses())===1, 'CyberMail SMTP fallback preserves recipient envelopes');
check(CaptureMailer::$last->Host==='127.0.0.1' && CaptureMailer::$last->Port===587 && CaptureMailer::$last->SMTPSecure==='tls' && CaptureMailer::$last->SMTPAuth && CaptureMailer::$last->Username==='synthetic-user' && CaptureMailer::$last->Password==='synthetic-password', 'CyberMail SMTP transport settings unchanged');
function wp_json_encode($value) { return json_encode($value); }
function wp_remote_post($url, $args) { $GLOBALS['api_payload']=json_decode($args['body'],true); return ['response'=>['code'=>202],'body'=>'{"success":true,"data":{"message_id":"synthetic"}}']; }
function is_wp_error($value) { return false; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
$provider=new CaptureCyberMail(array_merge($GLOBALS['settings'], ['mode'=>'api','api_key'=>'synthetic-not-a-credential']));
check($provider->send($data)['success'], 'CyberMail API contract can be built without HTTP');
check($GLOBALS['api_payload']['from']==='caller@example.test' && strpos($GLOBALS['api_payload']['cc'],'john@example.test')!==false && $GLOBALS['api_payload']['bcc']==='hidden@example.test', 'CyberMail API receives structured sender and recipients');
echo "All header tests passed; no network transport invoked.\n";
