<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'bg-slate-50 text-slate-900' ); ?>>
<header class="border-b border-slate-200 bg-white/80 backdrop-blur sticky top-0 z-40">
    <div class="max-w-6xl mx-auto flex items-center justify-between px-6 py-3">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="font-semibold tracking-tight">Ebook Library</a>
        <?php wp_nav_menu( [
            'theme_location' => 'ebook-header',
            'container'      => false,
            'fallback_cb'    => false,
            'menu_class'     => 'flex gap-4 text-sm font-medium',
        ] ); ?>
    </div>
</header>
<main id="app" class="min-h-screen">
