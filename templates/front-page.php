<?php
/**
 * Theme-independent Thailand Platform homepage document.
 *
 * @package Thailand_Platform
 */

use Thailand_Platform\Homepage\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$platform_body = Renderer::body_markup();

if ( '' === $platform_body ) {
	return;
}
?>
<!doctype html>
<html <?php language_attributes(); ?> class="thailand-platform-document">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#0d514c">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php
	if ( function_exists( 'wp_body_open' ) ) {
		wp_body_open();
	}

	?>
	<div class="thp-home">
		<?php
		// The markup is immutable, reviewed plugin source rather than user input.
		echo $platform_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>
	<?php

	wp_footer();
	?>
</body>
</html>
