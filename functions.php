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
// Register Ebook Custom Post Type
// --------------------------------------------------
add_action( 'init', function () {
    // Kiểm tra xem post type đã được đăng ký chưa (có thể từ plugin)
    if ( ! post_type_exists( 'ebook' ) ) {
        register_post_type( 'ebook', [
            'labels'              => [
                'name'               => __( 'Ebooks', 'ebook-gitbook' ),
                'singular_name'      => __( 'Ebook', 'ebook-gitbook' ),
                'add_new'            => __( 'Thêm mới', 'ebook-gitbook' ),
                'add_new_item'       => __( 'Thêm Ebook mới', 'ebook-gitbook' ),
                'edit_item'          => __( 'Chỉnh sửa Ebook', 'ebook-gitbook' ),
                'new_item'           => __( 'Ebook mới', 'ebook-gitbook' ),
                'view_item'          => __( 'Xem Ebook', 'ebook-gitbook' ),
                'search_items'       => __( 'Tìm kiếm Ebook', 'ebook-gitbook' ),
                'not_found'          => __( 'Không tìm thấy Ebook', 'ebook-gitbook' ),
                'not_found_in_trash' => __( 'Không có Ebook trong thùng rác', 'ebook-gitbook' ),
            ],
            'public'              => true,
            'has_archive'         => true,
            'hierarchical'        => true,
            'supports'            => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'menu_icon'           => 'dashicons-book-alt',
            'rewrite'             => [ 'slug' => 'ebook' ],
            'show_in_rest'         => false,
            'show_ui'              => false, // Ẩn menu mặc định, chỉ dùng menu tùy chỉnh
            'show_in_menu'         => false, // Ẩn khỏi menu admin
        ] );
    }
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

// --------------------------------------------------
// Admin: Ebook Manager
// --------------------------------------------------
add_action( 'admin_menu', function () {
    add_menu_page(
        __( 'Quản lý Ebook', 'ebook-gitbook' ),
        __( 'Quản lý Ebook', 'ebook-gitbook' ),
        'manage_options',
        'ebook-manager',
        'ebook_admin_manager_page',
        'dashicons-book-alt',
        30
    );
    
    // Thêm submenu cho trang soạn thảo ebook
    add_submenu_page(
        'ebook-manager',
        __( 'Soạn thảo Ebook', 'ebook-gitbook' ),
        __( 'Soạn thảo Ebook', 'ebook-gitbook' ),
        'manage_options',
        'ebook-editor',
        'ebook_admin_editor_page'
    );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( 'toplevel_page_ebook-manager' !== $hook ) {
        return;
    }
    // Font Awesome cho icon
    wp_enqueue_style( 'ebook-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0' );
    // CSS tùy chỉnh
    wp_enqueue_style( 'ebook-admin-manager', get_template_directory_uri() . '/assets/css/admin-ebook-manager.css', [ 'ebook-fontawesome' ], wp_get_theme()->get( 'Version' ) );
} );

// Enqueue scripts cho trang soạn thảo ebook
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    // Kiểm tra hook cho trang editor
    // Hook có thể là: ebook-manager_page_ebook-editor (submenu) hoặc tương tự
    if ( $hook !== 'ebook-manager_page_ebook-editor' && strpos( $hook, 'ebook-editor' ) === false ) {
        return;
    }
    // WordPress Media Library
    wp_enqueue_media();
} );

// Xử lý xóa Ebook
add_action( 'admin_post_ebook_delete', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Bạn không có quyền thực hiện hành động này.', 'ebook-gitbook' ) );
    }

    check_admin_referer( 'ebook_delete_' . $_GET['ebook_id'] );

    $ebook_id = (int) $_GET['ebook_id'];
    if ( $ebook_id > 0 ) {
        // Xóa ebook và tất cả trang con
        $children = get_posts( [
            'post_type'      => 'ebook',
            'post_parent'    => $ebook_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $children as $child_id ) {
            wp_delete_post( $child_id, true );
        }

        wp_delete_post( $ebook_id, true );
    }

    wp_redirect( admin_url( 'admin.php?page=ebook-manager&deleted=1' ) );
    exit;
} );

function ebook_admin_manager_page() {
    require_once get_template_directory() . '/admin-ebook-manager.php';
}

// Helper function để format thời gian cho admin
function ebook_format_time( $seconds ) {
    if ( $seconds < 60 ) {
        return $seconds . 's';
    } elseif ( $seconds < 3600 ) {
        return floor( $seconds / 60 ) . 'm';
    } else {
        return floor( $seconds / 3600 ) . 'h ' . floor( ( $seconds % 3600 ) / 60 ) . 'm';
    }
}

// --------------------------------------------------
// Admin: Ebook Editor
// --------------------------------------------------
function ebook_admin_editor_page() {
    require_once get_template_directory() . '/admin-ebook-editor.php';
}

// API endpoint để lưu ebook
add_action( 'wp_ajax_ebook_save', function () {
    // Kiểm tra quyền
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Bạn không có quyền thực hiện hành động này.' ] );
    }

    // Kiểm tra nonce
    $ebook_id = isset( $_POST['ebook_id'] ) ? (int) $_POST['ebook_id'] : 0;
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
    
    // Nonce được tạo với ebook_id = 0 khi tạo mới, hoặc ebook_id thực khi chỉnh sửa
    $nonce_action = 'ebook_editor_save_' . $ebook_id;
    if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
        wp_send_json_error( [ 'message' => 'Nonce không hợp lệ. Vui lòng tải lại trang.' ] );
    }

    // Lấy dữ liệu
    $title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
    $content = isset( $_POST['content'] ) ? wp_kses_post( $_POST['content'] ) : '';
    $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'draft';
    $thumbnail_id = isset( $_POST['thumbnail_id'] ) ? (int) $_POST['thumbnail_id'] : 0;
    $structure_raw = isset( $_POST['structure'] ) ? $_POST['structure'] : '';
    
    // Xử lý structure JSON
    $structure = [];
    if ( ! empty( $structure_raw ) ) {
        // Xử lý cả trường hợp có slashes và không có
        $decoded = json_decode( stripslashes( $structure_raw ), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            // Thử lại không có stripslashes
            $decoded = json_decode( $structure_raw, true );
        }
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $structure = $decoded;
        } else {
            // Log lỗi để debug
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Ebook Save: JSON decode error - ' . json_last_error_msg() );
                error_log( 'Ebook Save: Structure raw - ' . substr( $structure_raw, 0, 500 ) );
            }
        }
    }

    if ( empty( $title ) ) {
        wp_send_json_error( [ 'message' => 'Tiêu đề không được để trống.' ] );
    }

    // Chuẩn bị dữ liệu post
    $post_data = [
        'post_title'   => $title,
        'post_content' => $content,
        'post_type'    => 'ebook',
        'post_status'  => $status, // Sử dụng status từ form
        'post_parent'  => 0, // Đảm bảo ebook chính có parent = 0
    ];

    if ( $ebook_id > 0 ) {
        // Cập nhật ebook hiện có
        $post_data['ID'] = $ebook_id;
        // Không thay đổi post_parent khi update (giữ nguyên cấu trúc)
        unset( $post_data['post_parent'] );
        $result = wp_update_post( $post_data, true );
    } else {
        // Tạo ebook mới - đảm bảo post_parent = 0
        $result = wp_insert_post( $post_data, true );
    }

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 
            'message' => $result->get_error_message(),
            'error_code' => $result->get_error_code(),
            'error_data' => $result->get_error_data(),
        ] );
    }

    $ebook_id = (int) $result;
    
    // Kiểm tra xem post đã được tạo/cập nhật thành công chưa
    if ( $ebook_id <= 0 ) {
        wp_send_json_error( [ 'message' => 'Không thể tạo/cập nhật Ebook. ID không hợp lệ.' ] );
    }

    // Lưu cấu trúc chapters vào meta
    if ( ! empty( $structure ) ) {
        update_post_meta( $ebook_id, '_ebook_structure', $structure );
        
        // Tạo/cập nhật các child pages từ structure
        $updated_structure = [];
        foreach ( $structure as $item ) {
            $page_data = [
                'post_title'   => $item['title'] ?? 'Trang mới',
                'post_content' => $item['content'] ?? '',
                'post_type'    => 'ebook',
                'post_parent'  => $ebook_id,
                'post_status'  => $status, // Dùng cùng status với ebook chính
                'menu_order'   => 0,
            ];
            
            if ( isset( $item['post_id'] ) && $item['post_id'] > 0 ) {
                // Cập nhật page hiện có
                $page_data['ID'] = (int) $item['post_id'];
                $update_result = wp_update_post( $page_data, true );
                if ( ! is_wp_error( $update_result ) ) {
                    $item['post_id'] = (int) $item['post_id'];
                }
            } else {
                // Tạo page mới
                $new_page_id = wp_insert_post( $page_data, true );
                if ( ! is_wp_error( $new_page_id ) && $new_page_id > 0 ) {
                    // Cập nhật lại structure với post_id mới
                    $item['post_id'] = $new_page_id;
                }
            }
            
            // Xử lý children nếu có (recursive)
            if ( isset( $item['children'] ) && is_array( $item['children'] ) && ! empty( $item['children'] ) ) {
                // Xử lý children pages - parent phải là parent page ID, không phải ebook_id
                $parent_page_id = isset( $item['post_id'] ) && $item['post_id'] > 0 ? (int) $item['post_id'] : $ebook_id;
                $processed_children = [];
                foreach ( $item['children'] as $child ) {
                    $child_page_data = [
                        'post_title'   => $child['title'] ?? 'Trang mới',
                        'post_content' => $child['content'] ?? '',
                        'post_type'    => 'ebook',
                        'post_parent'  => $parent_page_id, // Children có parent là parent page ID
                        'post_status'  => $status,
                        'menu_order'   => 0,
                    ];
                    
                    if ( isset( $child['post_id'] ) && $child['post_id'] > 0 ) {
                        $child_page_data['ID'] = (int) $child['post_id'];
                        $child_update_result = wp_update_post( $child_page_data, true );
                        if ( ! is_wp_error( $child_update_result ) ) {
                            $child['post_id'] = (int) $child['post_id'];
                        }
                    } else {
                        $new_child_page_id = wp_insert_post( $child_page_data, true );
                        if ( ! is_wp_error( $new_child_page_id ) && $new_child_page_id > 0 ) {
                            $child['post_id'] = $new_child_page_id;
                        }
                    }
                    
                    // Xử lý nested children (recursive)
                    if ( isset( $child['children'] ) && is_array( $child['children'] ) && ! empty( $child['children'] ) ) {
                        $child_parent_id = isset( $child['post_id'] ) && $child['post_id'] > 0 ? (int) $child['post_id'] : $parent_page_id;
                        $nested_children = [];
                        foreach ( $child['children'] as $nested_child ) {
                            $nested_page_data = [
                                'post_title'   => $nested_child['title'] ?? 'Trang mới',
                                'post_content' => $nested_child['content'] ?? '',
                                'post_type'    => 'ebook',
                                'post_parent'  => $child_parent_id,
                                'post_status'  => $status,
                                'menu_order'   => 0,
                            ];
                            
                            if ( isset( $nested_child['post_id'] ) && $nested_child['post_id'] > 0 ) {
                                $nested_page_data['ID'] = (int) $nested_child['post_id'];
                                $nested_update_result = wp_update_post( $nested_page_data, true );
                                if ( ! is_wp_error( $nested_update_result ) ) {
                                    $nested_child['post_id'] = (int) $nested_child['post_id'];
                                }
                            } else {
                                $new_nested_page_id = wp_insert_post( $nested_page_data, true );
                                if ( ! is_wp_error( $new_nested_page_id ) && $new_nested_page_id > 0 ) {
                                    $nested_child['post_id'] = $new_nested_page_id;
                                }
                            }
                            
                            $nested_children[] = $nested_child;
                        }
                        $child['children'] = $nested_children;
                    } else {
                        $child['children'] = [];
                    }
                    
                    $processed_children[] = $child;
                }
                $item['children'] = $processed_children;
            } else {
                $item['children'] = [];
            }
            
            $updated_structure[] = $item;
        }
        
        // Cập nhật lại structure với post_id đã được cập nhật
        update_post_meta( $ebook_id, '_ebook_structure', $updated_structure );
    }

    // Lưu thumbnail (ảnh đại diện)
    if ( $thumbnail_id > 0 ) {
        set_post_thumbnail( $ebook_id, $thumbnail_id );
    } elseif ( $thumbnail_id === 0 && $ebook_id > 0 ) {
        // Xóa thumbnail nếu thumbnail_id = 0
        delete_post_thumbnail( $ebook_id );
    }

    // Verify post was created/updated
    $saved_post = get_post( $ebook_id );
    if ( ! $saved_post || $saved_post->post_type !== 'ebook' ) {
        wp_send_json_error( [ 'message' => 'Ebook đã được lưu nhưng không thể xác minh.' ] );
    }

    wp_send_json_success( [
        'message'  => 'Đã lưu Ebook thành công!',
        'ebook_id' => $ebook_id,
        'data'     => [
            'ebook_id'    => $ebook_id,
            'post_title'   => $saved_post->post_title,
            'post_status'  => $saved_post->post_status,
            'post_parent'  => $saved_post->post_parent,
        ],
    ] );
} );
