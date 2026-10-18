<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes();?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' );?>">
		<meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0" />
		<?php wp_head(); ?>
	</head>

	<body <?php body_class();?>>
<?php wp_body_open();?>
		<header class="global-header-wrapper">
			<div class="global-header-container">
				<div class="global-header">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="header-nav-link global-header-site-name" rel="home"><?php bloginfo( 'name' ); ?></a>
				<?php endif; ?>
				</div>
				<?php if ( has_nav_menu( 'primary' ) ) : ?>
					<nav class="header-nav" aria-label="<?php esc_attr_e( 'Primary Menu', 'yzrh' ); ?>">
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'primary',
								'container'      => false,
								'menu_class'     => 'header-nav-list',
								'depth'          => 2,
								'fallback_cb'    => false,
							)
						);
						?>
					</nav>
				<?php endif; ?>
			</div>
		</header>
