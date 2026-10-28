<?php
// Prints {"plain": ..., "tidy": ...} for the browser test: the same made-up article html as it is stored and as
// TWD_AP_Article_Style::tidy_lines() makes it for the page. Real class, nothing else.
define( 'ABSPATH', '/' );
function add_action() {}
function add_filter() {}
require dirname( __DIR__, 3 ) . '/plugins/twd-article-publisher/includes/class-twd-ap-article-style.php';
// Dense with short words, but with no run of more than five in a row (the tidy step glues five deep; a
// longer run is only checked for not overflowing, in the "chain" paragraph below).
$sentences = array(
	'We can be so tired of it all, and to be so sure of it is an odd thing to try if we go on in this way, and not at all as we do.',
	'It is a long road to an end that we do not see, and so we go by it, as we can, one of us at a time.',
	'In a way, to be at ease is to be in it, so do not be too hard on us if we do not know it yet.',
	'Many of us feel we have to do it all on our own, and we go on, as if we had no need of an ear or a hand.',
	'There is a way to be with it that is not so hard, if we are at peace with an old part of us.',
);
$paras = '';
for ( $i = 0; $i < 6; $i++ ) {
	$paras .= '<p>' . $sentences[ $i % 5 ] . ' ' . $sentences[ ( $i + 2 ) % 5 ] . '</p>';
}
// As WordPress leaves them after wptexturize: apostrophes are entities. And a run of short words, which
// must never become one block too wide for a narrow column.
$paras .= '<p>It&#8217;s a day when we don&#8217;t do it, and I&#8217;m so tired of it that we&#8217;re so sure they&#8217;ve gone, as it&#8217;s ok to do so.</p>';
$paras .= '<p class="chain">We go on as if it is ok to do so as we do it, and so we go on as if it is so, so we do it as we go on to be at it in an odd way.</p>';
$plain = '<h2>Why are we so tired of it all and so hard on us?</h2>' . $paras . '<pre>so we go on to it as is</pre>';
echo json_encode( array( 'plain' => $plain, 'tidy' => TWD_AP_Article_Style::tidy_lines( $plain ) ) );
