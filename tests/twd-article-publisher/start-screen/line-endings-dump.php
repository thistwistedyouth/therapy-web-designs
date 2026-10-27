<?php
// Prints {"plain": ..., "tidy": ...} for the browser test: the same made-up article html as it is stored and as
// TWD_AP_Article_Style::tidy_lines() makes it for the page. Real class, nothing else.
define( 'ABSPATH', '/' );
function add_action() {}
function add_filter() {}
require dirname( __DIR__, 3 ) . '/plugins/twd-article-publisher/includes/class-twd-ap-article-style.php';
$sentences = array(
	'We can be so tired of it all, and to be so sure of it is an odd thing to do if we go on in a way that is not us.',
	'It is a long road to an end that we do not see, and so we go by it as we can, one of us at a time.',
	'In a way, to be at ease is to be in it, so do not be too hard on us if we do not know it yet.',
	'Many of us feel we have to do it all on our own, and so we go on as if we do not need an ear or a hand.',
	'There is a way to be with it that is not so hard, if we are at peace with an old part of us.',
);
$paras = '';
for ( $i = 0; $i < 6; $i++ ) {
	$paras .= '<p>' . $sentences[ $i % 5 ] . ' ' . $sentences[ ( $i + 2 ) % 5 ] . '</p>';
}
$plain = '<h2>Why are we so tired of it all and so hard on us?</h2>' . $paras . '<pre>so we go on to it as is</pre>';
echo json_encode( array( 'plain' => $plain, 'tidy' => TWD_AP_Article_Style::tidy_lines( $plain ) ) );
