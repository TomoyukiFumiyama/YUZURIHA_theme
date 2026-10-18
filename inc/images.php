<?php
/** Image configuration and extension points. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/*
 * Register project-specific image sizes here, hooked to `after_setup_theme`:
 *
 *     add_action( 'after_setup_theme', function () {
 *         add_image_size( 'project-card', 720, 480, true );
 *     } );
 *
 * Only add sizes that a template actually requests; every size is generated
 * for each upload.
 */
