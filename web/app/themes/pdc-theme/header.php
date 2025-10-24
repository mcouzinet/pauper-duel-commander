<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="bg-gray-800 text-white shadow-lg">
    <nav class="container mx-auto px-4 py-6">
        <div class="flex justify-between items-center">
            <div class="text-2xl font-bold">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="hover:text-gray-300">
                    <?php bloginfo('name'); ?>
                </a>
            </div>

            <?php
            if (has_nav_menu('primary')) {
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'container' => 'div',
                    'container_class' => 'hidden md:block',
                    'menu_class' => 'flex space-x-6',
                ]);
            }
            ?>
        </div>
    </nav>
</header>
