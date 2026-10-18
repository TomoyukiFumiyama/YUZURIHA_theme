<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
    <footer class="footer-wrapper">
      <div class="footer-container">
        <div class="footer-logo-wrapper">
          <?php if ( has_custom_logo() ) : ?>
            <?php the_custom_logo(); ?>
          <?php else : ?>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="footer-logo-link" rel="home"><?php bloginfo( 'name' ); ?></a>
          <?php endif; ?>
        </div>
        <?php if ( has_nav_menu( 'footer' ) ) : ?>
          <nav class="footer-nav" aria-label="<?php esc_attr_e( 'Footer Menu', 'yzrh' ); ?>">
            <?php
            wp_nav_menu(
              array(
                'theme_location' => 'footer',
                'container'      => false,
                'menu_class'     => 'footer-nav-list',
                'depth'          => 1,
                'fallback_cb'    => false,
              )
            );
            ?>
          </nav>
        <?php endif; ?>

        <?php yzrh_output_sns_icons(); ?>

        <small class="copy-right">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></small>
      </div>
    </footer>

    <?php wp_footer(); ?>
  </body>
</html>
