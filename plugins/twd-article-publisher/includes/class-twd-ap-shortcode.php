<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TWD_AP_Shortcode {

	private static $instance = null;
	private static $styles_printed = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'twd_articles', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'category' => '',
				'tag'      => '',
				'count'    => 6,
				'columns'  => 3,
			),
			$atts,
			'twd_articles'
		);

		$columns = max( 1, min( 4, (int) $atts['columns'] ) );

		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $atts['count'] ),
			'no_found_rows'  => true,
		);

		if ( '' !== $atts['category'] ) {
			$query_args['category_name'] = sanitize_title( $atts['category'] );
		}
		if ( '' !== $atts['tag'] ) {
			$query_args['tag'] = sanitize_title( $atts['tag'] );
		}

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return '<p class="twd-ap-articles-empty">' . esc_html__( 'No articles to show yet.', 'twd-article-publisher' ) . '</p>';
		}

		ob_start();

		$this->print_styles_once();
		?>
		<div class="twd-ap-articles-grid" style="--twd-ap-articles-columns: <?php echo esc_attr( $columns ); ?>;">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<a class="twd-ap-article-card" href="<?php echo esc_url( get_permalink() ); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<span class="twd-ap-article-thumb" style="background-image: url('<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'medium_large' ) ); ?>');"></span>
					<?php endif; ?>
					<span class="twd-ap-article-body">
						<span class="twd-ap-article-date"><?php echo esc_html( get_the_date() ); ?></span>
						<span class="twd-ap-article-title"><?php echo esc_html( get_the_title() ); ?></span>
						<span class="twd-ap-article-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></span>
						<span class="twd-ap-article-readmore"><?php esc_html_e( 'Read more', 'twd-article-publisher' ); ?></span>
					</span>
				</a>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function print_styles_once() {
		if ( self::$styles_printed ) {
			return;
		}
		self::$styles_printed = true;
		?>
		<style>
			.twd-ap-articles-grid {
				display: grid !important;
				grid-template-columns: repeat(var(--twd-ap-articles-columns, 3), 1fr) !important;
				gap: 24px !important;
				margin: 0 !important;
				padding: 0 !important;
			}
			.twd-ap-article-card {
				display: flex !important;
				flex-direction: column !important;
				background: #fff !important;
				border: 1px solid #e5e7eb !important;
				border-radius: 12px !important;
				overflow: hidden !important;
				text-decoration: none !important;
				box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06) !important;
				transition: transform 0.2s ease, box-shadow 0.2s ease !important;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
			}
			.twd-ap-article-card:hover {
				transform: translateY(-3px) !important;
				box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12) !important;
			}
			.twd-ap-article-thumb {
				display: block !important;
				width: 100% !important;
				height: 170px !important;
				background-color: #f3f4f6 !important;
				background-size: cover !important;
				background-position: center !important;
			}
			.twd-ap-article-body {
				display: flex !important;
				flex-direction: column !important;
				gap: 6px !important;
				padding: 18px !important;
			}
			.twd-ap-article-date {
				font-size: 12px !important;
				color: #6b7280 !important;
				text-transform: uppercase !important;
				letter-spacing: 0.05em !important;
			}
			.twd-ap-article-title {
				font-size: 17px !important;
				font-weight: 700 !important;
				color: #1f2937 !important;
				line-height: 1.35 !important;
			}
			.twd-ap-article-excerpt {
				font-size: 14px !important;
				color: #4b5563 !important;
				line-height: 1.5 !important;
			}
			.twd-ap-article-readmore {
				margin-top: 6px !important;
				font-size: 13px !important;
				font-weight: 700 !important;
				color: #2563eb !important;
			}
			@media (max-width: 900px) {
				.twd-ap-articles-grid {
					grid-template-columns: repeat(2, 1fr) !important;
				}
			}
			@media (max-width: 600px) {
				.twd-ap-articles-grid {
					grid-template-columns: 1fr !important;
				}
			}
		</style>
		<?php
	}
}
