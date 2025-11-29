<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --------------------------------------------------
// Theme setup
// --------------------------------------------------
add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    register_nav_menus( [
        'ebook-header' => __( 'Ebook Header Menu', 'ebook-gitbook' ),
    ] );
} );

// --------------------------------------------------
// Assets
// --------------------------------------------------
add_action( 'wp_enqueue_scripts', function () {
    $theme_version = wp_get_theme()->get( 'Version' );

    // CSS riêng của theme (không phụ thuộc file Tailwind build sẵn)
    wp_enqueue_style( 'ebook-theme', get_template_directory_uri() . '/assets/css/theme.css', [], $theme_version );

    // Font Awesome cho icon toàn site (dùng trong giao diện ebook)
    wp_enqueue_style( 'ebook-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0' );

    // Tailwind CDN (JIT) để mọi class utility trong template ebook đều hoạt động
    wp_enqueue_script( 'ebook-tailwind', 'https://cdn.tailwindcss.com?plugins=typography', [], null, false );

    wp_enqueue_script( 'alpine', 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js', [], null, true );
    wp_enqueue_script( 'htmx', 'https://cdn.jsdelivr.net/npm/htmx.org@1.9.12/dist/htmx.min.js', [], null, true );
    wp_enqueue_script( 'ebook-theme', get_template_directory_uri() . '/assets/js/theme.js', [ 'htmx' ], $theme_version, true );

    wp_localize_script( 'ebook-theme', 'ebookTheme', [
        'tocHeadingSelector' => 'main#ebook-main article',
    ] );
} );

// --------------------------------------------------
// Custom Post Type & Taxonomy
// --------------------------------------------------
add_action( 'init', function () {
    register_post_type( 'ebook', [
        'label'         => __( 'Ebooks', 'ebook-gitbook' ),
        'labels'        => [
            'name'          => __( 'Ebooks', 'ebook-gitbook' ),
            'singular_name' => __( 'Ebook', 'ebook-gitbook' ),
        ],
        'public'        => true,
        'hierarchical'  => true,
        'has_archive'   => true,
        'rewrite'       => [ 'slug' => 'ebooks' ],
        'menu_position' => 5,
        'menu_icon'     => 'dashicons-book-alt',
        'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields' ],
        'show_in_rest'  => true,
    ] );

    register_taxonomy( 'ebook-series', 'ebook', [
        'label'        => __( 'Series', 'ebook-gitbook' ),
        'hierarchical' => true,
        'rewrite'      => [ 'slug' => 'ebook-series' ],
        'show_in_rest' => true,
    ] );
} );

// --------------------------------------------------
// Meta boxes for status, version, files
// --------------------------------------------------
function ebook_register_meta_fields() {
    $fields = [
        'status'   => [ 'default' => 'draft' ],
        'version'  => [ 'default' => '1.0' ],
        'download' => [ 'default' => '' ],
    ];

    foreach ( $fields as $key => $args ) {
        register_post_meta( 'ebook', "_ebook_{$key}", [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
            'default'      => $args['default'],
            'auth_callback'=> function () {
                return current_user_can( 'edit_posts' );
            },
        ] );
    }
}
add_action( 'init', 'ebook_register_meta_fields' );

function ebook_add_meta_box() {
    add_meta_box( 'ebook_meta', __( 'Ebook Details', 'ebook-gitbook' ), 'ebook_render_meta_box', 'ebook', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'ebook_add_meta_box' );

function ebook_render_meta_box( $post ) {
    wp_nonce_field( 'ebook_meta_save', 'ebook_meta_nonce' );
    $status   = get_post_meta( $post->ID, '_ebook_status', true ) ?: 'draft';
    $version  = get_post_meta( $post->ID, '_ebook_version', true ) ?: '1.0';
    $download = get_post_meta( $post->ID, '_ebook_download', true );
    ?>
    <p>
        <label for="ebook_status"><?php _e( 'Trạng thái', 'ebook-gitbook' ); ?></label>
        <select name="ebook_status" id="ebook_status" class="widefat">
            <?php foreach ( [ 'draft' => 'Draft', 'beta' => 'Beta', 'published' => 'Published' ] as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="ebook_version"><?php _e( 'Version', 'ebook-gitbook' ); ?></label>
        <input type="text" name="ebook_version" id="ebook_version" class="widefat" value="<?php echo esc_attr( $version ); ?>" />
    </p>
    <p>
        <label for="ebook_download"><?php _e( 'Link tải PDF/ePub', 'ebook-gitbook' ); ?></label>
        <input type="url" name="ebook_download" id="ebook_download" class="widefat" value="<?php echo esc_url( $download ); ?>" placeholder="https://" />
    </p>
    <?php
}

function ebook_save_meta( $post_id ) {
    if ( ! isset( $_POST['ebook_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ebook_meta_nonce'], 'ebook_meta_save' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $fields = [ 'status', 'version', 'download' ];

    foreach ( $fields as $field ) {
        if ( isset( $_POST[ "ebook_{$field}" ] ) ) {
            update_post_meta( $post_id, "_ebook_{$field}", sanitize_text_field( wp_unslash( $_POST[ "ebook_{$field}" ] ) ) );
        }
    }
}
add_action( 'save_post_ebook', 'ebook_save_meta' );

// --------------------------------------------------
// Admin drag & drop ordering via SortableJS
// --------------------------------------------------
add_filter( 'manage_ebook_posts_columns', function ( $columns ) {
    $columns['menu_order'] = __( 'Thứ tự', 'ebook-gitbook' );
    return $columns;
} );

add_action( 'manage_ebook_posts_custom_column', function ( $column, $post_id ) {
    if ( 'menu_order' === $column ) {
        echo (int) get_post_field( 'menu_order', $post_id );
        echo '<span class="dashicons dashicons-move"></span>';
    }
}, 10, 2 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    global $typenow;
    if ( 'edit.php' === $hook && 'ebook' === $typenow ) {
        wp_enqueue_script( 'sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js', [], null, true );
        wp_enqueue_script( 'ebook-admin-sort', get_template_directory_uri() . '/assets/js/admin-sortable.js', [ 'sortablejs', 'jquery' ], wp_get_theme()->get( 'Version' ), true );
        wp_localize_script( 'ebook-admin-sort', 'ebookSort', [
            'nonce'   => wp_create_nonce( 'ebook_sort_nonce' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ] );
    }
} );

add_action( 'wp_ajax_ebook_reorder', function () {
    check_ajax_referer( 'ebook_sort_nonce', 'nonce' );

    if ( empty( $_POST['order'] ) || ! is_array( $_POST['order'] ) ) {
        wp_send_json_error( [ 'message' => 'Invalid payload' ], 400 );
    }

    foreach ( $_POST['order'] as $index => $post_id ) {
        wp_update_post( [
            'ID'         => (int) $post_id,
            'menu_order' => $index,
        ] );
    }

    wp_send_json_success();
} );

// --------------------------------------------------
// Frontend helpers
// --------------------------------------------------
function ebook_get_root_post_id( $post_id ) {
    $ancestors = get_post_ancestors( $post_id );
    if ( empty( $ancestors ) ) {
        return $post_id;
    }
    return end( $ancestors );
}

function ebook_get_children_tree( $parent_id ) {
    $children = get_posts( [
        'post_type'      => 'ebook',
        'post_parent'    => $parent_id,
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ] );

    $tree = [];
    foreach ( $children as $child ) {
        $tree[] = [
            'post'     => $child,
            'children' => ebook_get_children_tree( $child->ID ),
        ];
    }

    return $tree;
}

/**
 * Trả về danh sách ID các trang ebook theo thứ tự menu (flatten từ cây).
 *
 * @param int $root_id
 * @return int[]
 */
function ebook_get_linear_order( $root_id ) {
    $root_post = get_post( $root_id );
    if ( ! $root_post ) {
        return [];
    }

    $tree = [
        [
            'post'     => $root_post,
            'children' => ebook_get_children_tree( $root_id ),
        ],
    ];

    $ids  = [];
    $walk = function ( $nodes ) use ( &$walk, &$ids ) {
        foreach ( $nodes as $node ) {
            if ( empty( $node['post'] ) ) {
                continue;
            }
            $ids[] = (int) $node['post']->ID;
            if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
                $walk( $node['children'] );
            }
        }
    };

    $walk( $tree );

    return $ids;
}

/**
 * Lấy ID trang trước / sau trong cùng ebook dựa trên cấu trúc menu_order.
 *
 * @param int    $current_id
 * @param string $direction 'prev' hoặc 'next'
 * @return int|false
 */
function ebook_get_adjacent_page_id( $current_id, $direction = 'next' ) {
    $root_id = ebook_get_root_post_id( $current_id );
    $order   = ebook_get_linear_order( $root_id );

    if ( empty( $order ) ) {
        return false;
    }

    $current_id = (int) $current_id;
    $index      = array_search( $current_id, $order, true );

    if ( false === $index ) {
        return false;
    }

    if ( 'prev' === $direction ) {
        return $order[ $index - 1 ] ?? false;
    }

    return $order[ $index + 1 ] ?? false;
}

function ebook_render_navigation_tree( $tree, $current_id ) {
    if ( empty( $tree ) ) {
        return;
    }

    echo '<ul class="ebook-tree">';
    foreach ( $tree as $node ) {
        $post      = $node['post'];
        $is_active = (int) $post->ID === (int) $current_id;
        $is_parent = in_array( $current_id, get_post_ancestors( $current_id ), true );

        $classes = [ 'ebook-tree__item' ];
        if ( $is_active ) {
            $classes[] = 'is-active';
        }
        echo '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">';
        echo '<a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( ebook_get_plain_title( $post ) ) . '</a>';
        if ( ! empty( $node['children'] ) ) {
            ebook_render_navigation_tree( $node['children'], $current_id );
        }
        echo '</li>';
    }
    echo '</ul>';
}

function ebook_get_plain_title( $post ) {
    return wp_specialchars_decode( get_the_title( $post ), ENT_QUOTES );
}

// --------------------------------------------------
// Search scope handling
// --------------------------------------------------
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( $query->is_search() && isset( $_GET['ebook_root'] ) ) {
        $query->set( 'post_type', 'ebook' );
        $query->set( 'post_parent', (int) $_GET['ebook_root'] );
    }
} );

require_once get_template_directory() . '/admin-ebook-builder.php';
