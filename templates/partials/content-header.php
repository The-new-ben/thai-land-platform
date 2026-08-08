<?php
/**
 * Shared header for managed content pages.
 *
 * @package Thailand_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<a class="thp-skip-link" href="#main-content">דלגו לתוכן</a>
<header class="thp-site-header" data-thp-header>
	<div class="thp-shell thp-header-inner">
		<a class="thp-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Thai-Land.co.il, עמוד הבית">
			<span class="thp-brand-mark" aria-hidden="true">ת</span>
			<span><strong>Thai-Land</strong><small>תאילנד בעברית</small></span>
		</a>
		<nav class="thp-primary-nav" aria-label="ניווט ראשי">
			<a href="<?php echo esc_url( home_url( '/תיירות-בתאילנד/' ) ); ?>">טיולים</a>
			<a class="is-current" href="<?php echo esc_url( home_url( '/נדלן-בתאילנד/' ) ); ?>"<?php echo 'hub' === $route['kind'] ? ' aria-current="page"' : ''; ?>>נדל״ן</a>
			<a href="<?php echo esc_url( home_url( '/עסקים-בתאילנד-סקירה-כללית/' ) ); ?>">עסקים</a>
			<a href="<?php echo esc_url( home_url( '/בנגקוק-תאילנד/' ) ); ?>">בנגקוק</a>
			<a href="<?php echo esc_url( home_url( '/פוקט-או-קו-סמוי/' ) ); ?>">איים</a>
		</nav>
		<form class="thp-header-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
			<label class="thp-sr-only" for="thp-content-search">חיפוש באתר</label>
			<input id="thp-content-search" name="s" type="search" placeholder="מה מחפשים בתאילנד?">
			<button type="submit" aria-label="חיפוש">חיפוש</button>
		</form>
		<button class="thp-menu-toggle" type="button" aria-expanded="false" aria-controls="thp-mobile-nav" aria-label="פתיחת תפריט">
			<span></span><span></span><span></span>
		</button>
	</div>
	<div class="thp-mobile-nav" id="thp-mobile-nav" hidden>
		<div class="thp-mobile-nav-panel" role="dialog" aria-modal="true" aria-label="תפריט ראשי">
			<div class="thp-mobile-nav-head"><strong>תפריט ראשי</strong><button type="button" data-thp-menu-close aria-label="סגירת תפריט">×</button></div>
			<nav aria-label="ניווט בנייד">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">עמוד הבית</a>
				<a href="<?php echo esc_url( home_url( '/תיירות-בתאילנד/' ) ); ?>">טיולים בתאילנד</a>
				<a href="<?php echo esc_url( home_url( '/נדלן-בתאילנד/' ) ); ?>"<?php echo 'hub' === $route['kind'] ? ' aria-current="page"' : ''; ?>>נדל״ן בתאילנד</a>
				<a href="<?php echo esc_url( home_url( '/עסקים-בתאילנד-סקירה-כללית/' ) ); ?>">עסקים בתאילנד</a>
				<a href="<?php echo esc_url( home_url( '/בנגקוק-תאילנד/' ) ); ?>">בנגקוק</a>
				<a href="<?php echo esc_url( home_url( '/פוקט-או-קו-סמוי/' ) ); ?>">פוקט או קוסמוי</a>
			</nav>
		</div>
		<div class="thp-mobile-nav-backdrop" data-thp-menu-close aria-hidden="true"></div>
	</div>
</header>
