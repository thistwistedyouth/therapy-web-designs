<?php
// Converter tests. Not part of the plugin (never shipped to client sites). Short made-up articles only, no client text.
// Real WordPress core is used for wp_kses and friends; only the database is faked.
//   composer require johnpbloch/wordpress-core --working-dir=/tmp/wpc
//   TWD_WP_CORE=/tmp/wpc/vendor/johnpbloch/wordpress-core php tests/twd-article-publisher/converter-test.php
$WP = rtrim( getenv( 'TWD_WP_CORE' ) ?: '', '/' ) . '/';
if ( ! is_file( $WP . 'wp-includes/kses.php' ) ) { fwrite( STDERR, "Set TWD_WP_CORE to a WordPress core folder (see the comment at the top of this file).\n" ); exit( 2 ); }
define('ABSPATH', $WP); define('WPINC','wp-includes'); define('DAY_IN_SECONDS',86400);
define('TWD_AP_VERSION','1.35.0');
define('WP_DEBUG', false);
require $WP.'wp-includes/version.php';
require $WP.'wp-includes/compat.php';
require $WP.'wp-includes/plugin.php';
require $WP.'wp-includes/class-wp-error.php';
require $WP.'wp-includes/functions.php';
function __($s,$d=''){return $s;} function _n($a,$b,$n,$d=''){return $n==1?$a:$b;} function _x($s,$c,$d=''){return $s;} function esc_html__($s,$d=''){return $s;}
require $WP.'wp-includes/utf8.php';
require $WP.'wp-includes/formatting.php';
require $WP.'wp-includes/class-wp-token-map.php';
foreach(array('class-wp-html-attribute-token','class-wp-html-span','class-wp-html-text-replacement','class-wp-html-decoder','class-wp-html-doctype-info','class-wp-html-token','class-wp-html-stack-event','class-wp-html-open-elements','class-wp-html-active-formatting-elements','class-wp-html-tag-processor','class-wp-html-unsupported-exception','class-wp-html-processor-state','class-wp-html-processor') as $c){ $f=$WP.'wp-includes/html-api/'.$c.'.php'; if(file_exists($f)) require_once $f; }
require $WP.'wp-includes/kses.php';
$plugin = dirname( __DIR__, 2 ) . '/plugins/twd-article-publisher/';

function is_wp_error($x){return $x instanceof WP_Error;}
// ---- fake database -------------------------------------------------
$DB = array('posts'=>array(),'meta'=>array());
function get_post($id){ global $DB; if(!isset($DB['posts'][$id])) return null; return (object)$DB['posts'][$id]; }
function get_post_meta($id,$key='',$single=false){ global $DB; $m=$DB['meta'][$id]??array();
  if($key==='') { $o=array(); foreach($m as $k=>$vals){ $o[$k]=array_map(function($v){return is_array($v)||is_object($v)?serialize($v):$v;}, $vals);} return $o; }
  if(!isset($m[$key])) return $single?'':array();
  return $single ? $m[$key][0] : $m[$key]; }
function update_post_meta($id,$key,$val){ global $DB; $val=wp_unslash($val); $DB['meta'][$id][$key]=array($val); return true; }
function add_post_meta($id,$key,$val){ global $DB; $val=wp_unslash($val); $DB['meta'][$id][$key][]=$val; return 1; }
function delete_post_meta($id,$key){ global $DB; unset($DB['meta'][$id][$key]); return true; }
function wp_update_post($arr,$wp_error=false){ global $DB; $arr=wp_unslash($arr); $id=$arr['ID']; if($GLOBALS['FAIL_UPDATE']??false) return new WP_Error('x','fail'); foreach($arr as $k=>$v) if($k!=='ID') $DB['posts'][$id][$k]=$v; return $id; }
function clean_post_cache($id){}
function get_current_user_id(){return 1;}
// real wp_unslash/wp_slash come from formatting.php

class TWD_AP_Settings { static $s=array('converter_enabled'=>1,'converter_rules'=>''); static function get_settings(){return self::$s;} }
require $plugin.'includes/class-twd-ap-sanitizer.php';
require $plugin.'includes/class-twd-ap-converter.php';
TWD_AP_Settings::$s['converter_rules']=TWD_AP_Converter::default_rules();

$pass=0;$fail=0;
function t($n,$ok,$extra=''){global $pass,$fail; if($ok){$pass++;echo "PASS  $n\n";}else{$fail++;echo "FAIL  $n $extra\n";}}
function mk($id,$content,$data,$extra=array()){ global $DB;
  $DB['posts'][$id]=array('ID'=>$id,'post_type'=>'post','post_status'=>'publish','post_title'=>'Title '.$id,'post_name'=>'slug-'.$id,'post_content'=>$content,'post_excerpt'=>'Excerpt');
  $DB['meta'][$id]=array('_elementor_data'=>array(json_encode($data)),'_elementor_edit_mode'=>array('builder'),'_elementor_version'=>array('3.20.0'),'_elementor_page_settings'=>array(array('hide_title'=>'yes')),'_yoast_wpseo_title'=>array('SEO %%title%%'),'_yoast_wpseo_metadesc'=>array('Meta description here'),'_thumbnail_id'=>array('77'));
  foreach($extra as $k=>$v) $DB['meta'][$id][$k]=array($v); }
function htmlw($html){ return array(array('id'=>'a','elType'=>'section','settings'=>array(),'elements'=>array(array('id'=>'b','elType'=>'column','elements'=>array(array('id'=>'c','elType'=>'widget','widgetType'=>'html','settings'=>array('html'=>$html),'elements'=>array())))))); }

$kcc = <<<'HTML'
<!-- Paste into WordPress editor or HTML widget -->
<div class="kcc-article">
  <p class="kcc-article__intro">
    An intro paragraph, indented the way pasted source often is.
  </p>
  <p class="kcc-body">First body paragraph.</p>
  <p class="kcc-article__subhead">First sub-heading</p>
  <p class="kcc-body">Second body paragraph - with a hyphen.</p>
  <div class="kcc-article__quote">
    <p>A quote line. - Someone</p>
  </div>
  <p class="kcc-article__subhead">Second sub-heading</p>
  <div class="kcc-article__quote kcc-article__quote--coral">
    <p>A coral quote.</p>
  </div>
  <div class="kcc-article__footer">
    <p class="kcc-article__footer-text">Written by A Person, Counsellor.</p>
  </div>
</div>
HTML;
$bushey = <<<'HTML'
<h3>First section</h3>
<p>If you're searching, you might feel unsure. Say "hello" to <em>this</em>.</p>
<h3>Second section</h3>
<p>Read <a href="https://example.com/page/">more here</a>.</p>
<ul>
<li>One point</li>
<li>Another point</li>
</ul>
<h3>Third section</h3>
<p>Email <a href="mailto:someone@example.com">get in touch</a> or <a href="mailto:someone@example.com">someone@example.com</a> - reply within 24 hours.</p>
HTML;

// ---------- parse_rules ----------
$r=TWD_AP_Converter::parse_rules("# c\n\nfoo-bar = H2\nbad line\nx = nonsense\nfoo = p\n  baz__q   =   blockquote  \nUPPER = unwrap");
t('rules: valid parsed, junk ignored', count($r)===4);
t('rules: longest fragment first', $r[0][0]==='baz__q' || strlen($r[0][0])>=strlen($r[1][0]));
t('rules: target lower-cased', in_array(array('foo-bar','h2'),$r,true));

// ---------- extract_content ----------
$f=TWD_AP_Converter::extract_content(array(array('elType'=>'container','settings'=>array(),'elements'=>array(
  array('elType'=>'widget','widgetType'=>'html','settings'=>array('html'=>'<p>A</p>')),
  array('elType'=>'widget','widgetType'=>'spacer','settings'=>array()),
  array('elType'=>'widget','widgetType'=>'text-editor','settings'=>array('editor'=>"Line one\n\nLine two")),
))));
t('extract: html + text-editor collected in order', count($f['html'])===2 && $f['html'][0]==='<p>A</p>');
t('extract: text-editor gets paragraphs', strpos($f['html'][1],'<p>Line one</p>')!==false);
t('extract: spacer ignored, nothing blocked', empty($f['blocked']));
$f=TWD_AP_Converter::extract_content(array(array('elType'=>'section','elements'=>array(array('elType'=>'widget','widgetType'=>'heading','settings'=>array('title'=>'x')),array('elType'=>'widget','widgetType'=>'image','settings'=>array())))));
t('extract: heading and image widgets reported as blocked', isset($f['blocked']['heading']) && isset($f['blocked']['image']));
$f=TWD_AP_Converter::extract_content(array(array('elType'=>'section','settings'=>array('custom_css'=>'selector{color:red}'),'elements'=>array())));
t('extract: custom CSS flagged', $f['custom_css']===true);

// ---------- class-based sample (div wrappers, sub-heading paragraphs, quote boxes) ----------
mk(1,'',htmlw($kcc));
$a=TWD_AP_Converter::analyse(1,true);
t('kcc: analyse ok', is_array($a), is_wp_error($a)?$a->get_error_message():'');
$h=$a['final_html'];
t('kcc: 2 sub-headings became h2', substr_count($h,'<h2>')===2, "got ".substr_count($h,"<h2>"));
t('kcc: sub-heading text kept', strpos($h,'<h2>First sub-heading</h2>')!==false);
t('kcc: 2 quote boxes became blockquote', substr_count($h,'<blockquote>')===2);
t('kcc: quote text inside blockquote', preg_match('/<blockquote>\s*<p>A quote line/',$h)===1);
t('kcc: no divs or classes left', strpos($h,'<div')===false && strpos($h,'class=')===false);
t('kcc: no HTML comment left', strpos($h,'<!--')===false);
t('kcc: author footer text kept', strpos($h,'Written by A Person')!==false);
t('kcc: intro paragraph kept', strpos($h,'An intro paragraph')!==false);
t('kcc: status is safe (no warnings)', $a['status']==='safe', json_encode($a['warnings']));
t('kcc: no text lost (plain text equal)', preg_replace('/\s+/','',strip_tags(html_entity_decode(strip_tags(preg_replace('/<!--.*?-->/s','',$kcc)))))===preg_replace('/\s+/','',strip_tags(html_entity_decode($h))));
t('kcc: no h3 promotion needed (info absent)', !in_array(true, array_map(function($i){return strpos($i,'promoted')!==false;},$a['info'])));

// ---------- plain semantic HTML sample ----------
mk(2,'',htmlw($bushey));
$a=TWD_AP_Converter::analyse(2,true);
$h=$a['final_html'];
t('bushey: safe', $a['status']==='safe', json_encode($a['warnings']));
t('bushey: h3 promoted to h2 (3 of 3)', substr_count($h,'<h2>')===3 && strpos($h,'<h3')===false);
t('bushey: info says promoted', count(array_filter($a['info'],function($i){return strpos($i,'promoted')!==false;}))===1);
t('bushey: em kept', substr_count($h,'<em>')===1);
t('bushey: list kept', substr_count($h,'<li>')===2 && strpos($h,'<ul>')!==false);
t('bushey: https link kept', strpos($h,'<a href="https://example.com/page/">more here</a>')!==false);
t('bushey: mailto links kept (2)', substr_count($h,'href="mailto:someone@example.com"')===2, $h);
t('bushey: apostrophes intact', strpos($h,"you're searching")!==false && strpos($h,'&#8217;')===false && strpos($h,'&amp;#')===false);
t('bushey: no text lost', preg_replace('/\s+/','',strip_tags(html_entity_decode($bushey)))===preg_replace('/\s+/','',strip_tags(html_entity_decode($h))));
$a2=TWD_AP_Converter::analyse(2,false);
t('bushey: promote off leaves h3 alone', substr_count($a2['final_html'],'<h3>')===3 && strpos($a2['final_html'],'<h2')===false);

// ---------- warnings ----------
mk(3,'',htmlw('<h2 style="color:red">Hi</h2><script type="application/ld+json">{"a":1}</script><style>p{color:red}</style><p>Body &mdash; text</p><table><tr><td>cell</td></tr></table><iframe src="x"></iframe>'),array('_wp_page_template'=>'elementor_canvas'));
$a=TWD_AP_Converter::analyse(3,true); $w=implode(' | ',$a['warnings']);
t('warn: status warning', $a['status']==='warning');
t('warn: script flagged', strpos($w,'script block')!==false);
t('warn: style flagged', strpos($w,'style')!==false);
t('warn: inline style flagged', strpos($w,'Inline styling')!==false);
t('warn: table/iframe flagged', strpos($w,'table')!==false && strpos($w,'iframe')!==false);
t('warn: dash flagged and replaced', strpos($w,'dash')!==false && strpos($a['final_html'],"\u{2014}")===false && strpos($a['final_html'],'Body, text')!==false, $a['final_html']);
t('warn: canvas template flagged', strpos($w,'Canvas')!==false);
t('warn: script content not in output', strpos($a['final_html'],'ld+json')===false && strpos($a['final_html'],'color:red')===false);

// ---------- blocked ----------
mk(4,'orig',array(array('elType'=>'section','elements'=>array(array('elType'=>'widget','widgetType'=>'heading','settings'=>array('title'=>'x'))))));
$a=TWD_AP_Converter::analyse(4,true);
t('blocked: other widgets refuses', $a['status']==='blocked');
$c=TWD_AP_Converter::convert(4,true);
t('blocked: convert() returns WP_Error and changes nothing', is_wp_error($c) && get_post(4)->post_content==='orig' && !isset($DB['meta'][4][TWD_AP_Converter::BACKUP_META]) && isset($DB['meta'][4]['_elementor_data']));
$DB['posts'][5]=array('ID'=>5,'post_type'=>'post','post_status'=>'publish','post_title'=>'n','post_content'=>'x'); $DB['meta'][5]=array();
t('blocked: no Elementor data gives WP_Error', is_wp_error(TWD_AP_Converter::analyse(5,true)));
$DB['posts'][6]=array('ID'=>6,'post_type'=>'page','post_status'=>'publish','post_title'=>'p','post_content'=>'x'); $DB['meta'][6]=array('_elementor_data'=>array('[]'));
t('blocked: pages are refused (posts only)', is_wp_error(TWD_AP_Converter::analyse(6,true)));
mk(7,'',array()); $DB['meta'][7]['_elementor_data']=array('not json{');
t('blocked: unreadable data gives WP_Error', is_wp_error(TWD_AP_Converter::analyse(7,true)));

// ---------- convert + undo (full round trip) ----------
mk(10,'ORIGINAL STALE CONTENT',htmlw($kcc),array('_wp_page_template'=>'elementor_header_footer'));
$before_meta=$DB['meta'][10]; $before_post=$DB['posts'][10];
$c=TWD_AP_Converter::convert(10,true);
t('convert: succeeds', is_array($c), is_wp_error($c)?$c->get_error_message():'');
t('convert: post_content replaced with clean html', strpos(get_post(10)->post_content,'<h2>First sub-heading</h2>')!==false);
t('convert: backup saved with original content', ($b=get_post_meta(10,TWD_AP_Converter::BACKUP_META,true)) && $b['post_content']==='ORIGINAL STALE CONTENT');
t('convert: all _elementor_ meta removed', count(array_filter(array_keys($DB['meta'][10]),function($k){return strpos($k,'_elementor_')===0;}))===0);
t('convert: elementor page template reset to default', $DB['meta'][10]['_wp_page_template'][0]==='default');
foreach(array('post_title','post_name','post_excerpt','post_status') as $k) t("convert: $k untouched", $DB['posts'][10][$k]===$before_post[$k]);
foreach(array('_yoast_wpseo_title','_yoast_wpseo_metadesc','_thumbnail_id') as $k) t("convert: $k untouched", $DB['meta'][10][$k]===$before_meta[$k]);
$again=TWD_AP_Converter::convert(10,true);
t('convert: second convert refused while backup exists', is_wp_error($again)||$again['status']==='blocked');
$u=TWD_AP_Converter::undo(10,false);
t('undo: succeeds', $u===true, is_wp_error($u)?$u->get_error_message():'');
t('undo: post_content restored exactly', get_post(10)->post_content==='ORIGINAL STALE CONTENT');
foreach($before_meta as $k=>$v) t("undo: meta $k restored identically", ($DB['meta'][10][$k]??null)===$v, $k);
t('undo: _elementor_data JSON byte-identical', $DB['meta'][10]['_elementor_data'][0]===$before_meta['_elementor_data'][0]);
t('undo: backup removed', !isset($DB['meta'][10][TWD_AP_Converter::BACKUP_META]));
t('undo: with no backup gives WP_Error', is_wp_error(TWD_AP_Converter::undo(10,false)));

// edited after conversion
mk(11,'orig11',htmlw($bushey));
TWD_AP_Converter::convert(11,true);
$DB['posts'][11]['post_content'].="\n<p>Edited later</p>";
$u=TWD_AP_Converter::undo(11,false);
t('undo: refuses without force when edited since', is_wp_error($u) && $u->get_error_code()==='twd_ap_conv_edited' && strpos(get_post(11)->post_content,'Edited later')!==false);
$u=TWD_AP_Converter::undo(11,true);
t('undo: force restores original', $u===true && get_post(11)->post_content==='orig11');

// save failure leaves everything alone
mk(12,'orig12',htmlw($bushey)); $GLOBALS['FAIL_UPDATE']=true;
$c=TWD_AP_Converter::convert(12,true); $GLOBALS['FAIL_UPDATE']=false;
t('failed save: WP_Error, content and meta untouched, backup removed', is_wp_error($c) && get_post(12)->post_content==='orig12' && isset($DB['meta'][12]['_elementor_data']) && !isset($DB['meta'][12][TWD_AP_Converter::BACKUP_META]));

// ---------- custom rules per site ----------
TWD_AP_Settings::$s['converter_rules']="acme-title = h3\nacme-pull = blockquote\nacme-hide = remove\nacme-wrap = unwrap";
mk(13,'',htmlw('<div class="acme-wrap"><p class="acme-title">Heading</p><p class="acme-pull">Pull quote</p><p class="acme-hide">GONE</p><p>Body</p></div>'));
$a=TWD_AP_Converter::analyse(13,false); $h=$a['final_html'];
t('site rules: custom classes mapped', strpos($h,'<h3>Heading</h3>')!==false && strpos($h,'<blockquote>Pull quote</blockquote>')!==false);
t('site rules: remove works, unwrap works', strpos($h,'GONE')===false && strpos($h,'<div')===false && strpos($h,'<p>Body</p>')!==false);
TWD_AP_Settings::$s['converter_rules']='';
mk(14,'',htmlw('<div class="x-sub"><p>Unmapped</p></div><p class="x-sub">Sub</p>'));
$a=TWD_AP_Converter::analyse(14,false);
t('no rules: unmapped classes listed so a rule can be added', in_array('x-sub',$a['dropped_cl'],true));

echo "\n$pass passed, $fail failed\n"; exit($fail?1:0);
