<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const EBOOK_BUILDER_MENU_SLUG = 'ebook-builder';

add_action( 'admin_menu', 'ebook_builder_register_admin_page' );
add_action( 'admin_enqueue_scripts', 'ebook_builder_enqueue_assets' );

add_action( 'wp_ajax_ebook_builder_get_tree', 'ebook_builder_ajax_get_tree' );
add_action( 'wp_ajax_ebook_builder_get_node', 'ebook_builder_ajax_get_node' );
add_action( 'wp_ajax_ebook_builder_save_node', 'ebook_builder_ajax_save_node' );
add_action( 'wp_ajax_ebook_builder_create_node', 'ebook_builder_ajax_create_node' );
add_action( 'wp_ajax_ebook_builder_create_root', 'ebook_builder_ajax_create_root' );
add_action( 'wp_ajax_ebook_builder_delete_node', 'ebook_builder_ajax_delete_node' );
add_action( 'wp_ajax_ebook_builder_reorder', 'ebook_builder_ajax_reorder' );

function ebook_builder_register_admin_page() {
    add_menu_page(
        __( 'Ebook Builder', 'ebook-gitbook' ),
        __( 'Ebook Builder', 'ebook-gitbook' ),
        'edit_others_posts',
        EBOOK_BUILDER_MENU_SLUG,
        'ebook_builder_render_page',
        'dashicons-layout',
        6
    );
}

function ebook_builder_enqueue_assets( $hook ) {
    if ( 'toplevel_page_' . EBOOK_BUILDER_MENU_SLUG !== $hook ) {
        return;
    }

    $theme_version = wp_get_theme()->get( 'Version' );

    wp_enqueue_style(
        'ebook-builder',
        get_template_directory_uri() . '/assets/css/ebook-builder.css',
        [],
        $theme_version
    );

    wp_enqueue_editor();
    wp_enqueue_media();
    wp_enqueue_script( 'jquery-ui-sortable' );
    wp_enqueue_script(
        'ebook-builder',
        get_template_directory_uri() . '/assets/js/ebook-builder.js',
        [ 'jquery', 'jquery-ui-sortable' ],
        $theme_version,
        true
    );

    $current_root = isset( $_GET['root'] ) ? absint( $_GET['root'] ) : 0;
    $focus_post   = isset( $_GET['focus'] ) ? absint( $_GET['focus'] ) : 0;

    wp_localize_script( 'ebook-builder', 'ebookBuilderData', [
        'nonce'       => wp_create_nonce( 'ebook_builder_nonce' ),
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'roots'       => ebook_builder_get_root_posts(),
        'currentRoot' => $current_root,
        'focusPost'   => $focus_post,
        'strings'     => [
            'selectRoot'        => __( 'Hãy chọn một ebook ở bên trên để bắt đầu.', 'ebook-gitbook' ),
            'emptyTree'         => __( 'Ebook này chưa có trang nào.', 'ebook-gitbook' ),
            'promptTitle'       => __( 'Nhập tiêu đề cho trang mới', 'ebook-gitbook' ),
            'creationError'     => __( 'Bạn cần nhập tiêu đề trang.', 'ebook-gitbook' ),
            'confirmDelete'     => __( 'Bạn chắc chắn xoá trang này?', 'ebook-gitbook' ),
            'cannotDeleteRoot'  => __( 'Không thể xoá ebook gốc.', 'ebook-gitbook' ),
            'unsaved'           => __( 'Hãy chọn và lưu trang hiện tại trước.', 'ebook-gitbook' ),
            'newRootPrompt'     => __( 'Tên ebook gốc mới là gì?', 'ebook-gitbook' ),
        ],
    ] );
}

function ebook_builder_render_page() {
    ?>
    <div class="wrap ebook-builder-wrap">
        <h1><?php esc_html_e( 'Trình soạn thảo Ebook', 'ebook-gitbook' ); ?></h1>
        <p class="description"><?php esc_html_e( 'Quản lý cấu trúc ebook, nội dung chương và meta chỉ với một nơi.', 'ebook-gitbook' ); ?></p>

        <div class="ebook-builder-toolbar">
            <label for="ebook-builder-root" class="screen-reader-text"><?php esc_html_e( 'Chọn ebook', 'ebook-gitbook' ); ?></label>
            <select id="ebook-builder-root" class="regular-text">
                <option value=""><?php esc_html_e( '— Chọn ebook —', 'ebook-gitbook' ); ?></option>
            </select>
            <button type="button" class="button" id="ebook-builder-refresh">
                <?php esc_html_e( 'Tải lại', 'ebook-gitbook' ); ?>
            </button>
            <button type="button" class="button button-primary" id="ebook-builder-create-root">
                <?php esc_html_e( 'Tạo ebook mới', 'ebook-gitbook' ); ?>
            </button>
        </div>

        <div id="ebook-builder-notices"></div>

        <div class="ebook-builder-layout">
            <aside class="ebook-builder-sidebar">
                <div class="ebook-builder-create">
                    <h3><?php esc_html_e( 'Tạo trang mới', 'ebook-gitbook' ); ?></h3>
                    <p class="description"><?php esc_html_e( 'Trang sẽ được thêm vào ebook đang chọn.', 'ebook-gitbook' ); ?></p>
                    <p>
                        <label for="ebook-builder-new-title"><?php esc_html_e( 'Tiêu đề', 'ebook-gitbook' ); ?></label>
                        <input type="text" id="ebook-builder-new-title" class="widefat" placeholder="<?php esc_attr_e( 'VD: Giới thiệu', 'ebook-gitbook' ); ?>">
                    </p>
                    <p>
                        <label for="ebook-builder-new-parent"><?php esc_html_e( 'Thuộc chương', 'ebook-gitbook' ); ?></label>
                        <select id="ebook-builder-new-parent" class="widefat"></select>
                    </p>
                    <p>
                        <label for="ebook-builder-new-status"><?php esc_html_e( 'Trạng thái', 'ebook-gitbook' ); ?></label>
                        <select id="ebook-builder-new-status" class="widefat">
                            <option value="draft"><?php esc_html_e( 'Nháp', 'ebook-gitbook' ); ?></option>
                            <option value="publish"><?php esc_html_e( 'Xuất bản', 'ebook-gitbook' ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Chờ duyệt', 'ebook-gitbook' ); ?></option>
                        </select>
                    </p>
                    <button type="button" class="button button-secondary button-small" id="ebook-builder-create-page">
                        <?php esc_html_e( 'Thêm trang', 'ebook-gitbook' ); ?>
                    </button>
                </div>

                <div class="ebook-builder-reorder" id="ebook-builder-reorder-hint">
                    <p><?php esc_html_e( 'Kéo & thả để sắp xếp. Nhấn "Lưu thứ tự" khi hoàn tất.', 'ebook-gitbook' ); ?></p>
                    <button type="button" class="button button-primary button-small" id="ebook-builder-save-order" disabled>
                        <?php esc_html_e( 'Lưu thứ tự', 'ebook-gitbook' ); ?>
                    </button>
                </div>

                <div class="ebook-builder-tree" id="ebook-builder-tree">
                    <p class="placeholder"><?php esc_html_e( 'Chưa chọn ebook.', 'ebook-gitbook' ); ?></p>
                </div>
            </aside>

            <section class="ebook-builder-editor">
                <form id="ebook-builder-form">
                    <input type="hidden" id="ebook-builder-root-id" name="root_id" value="">
                    <input type="hidden" id="ebook-builder-post-id" name="post_id" value="">

                    <div id="ebook-builder-selection" class="ebook-builder-selection">
                        <p class="selection-placeholder"><?php esc_html_e( 'Chưa chọn trang nào.', 'ebook-gitbook' ); ?></p>
                    </div>

                    <div class="builder-quick-actions">
                        <button type="button" class="button" id="ebook-builder-add-sibling" disabled><?php esc_html_e( 'Thêm cùng cấp', 'ebook-gitbook' ); ?></button>
                        <button type="button" class="button" id="ebook-builder-add-child" disabled><?php esc_html_e( 'Thêm chương con', 'ebook-gitbook' ); ?></button>
                        <button type="button" class="button" id="ebook-builder-preview" disabled><?php esc_html_e( 'Xem trên site', 'ebook-gitbook' ); ?></button>
                        <button type="button" class="button button-link-delete" id="ebook-builder-delete" disabled><?php esc_html_e( 'Xoá trang', 'ebook-gitbook' ); ?></button>
                    </div>

                    <div class="builder-grid">
                        <p class="builder-field">
                            <label for="ebook-builder-title"><?php esc_html_e( 'Tiêu đề trang', 'ebook-gitbook' ); ?></label>
                            <input type="text" class="widefat" id="ebook-builder-title" required>
                        </p>
                        <p class="builder-field">
                            <label for="ebook-builder-status"><?php esc_html_e( 'Trạng thái', 'ebook-gitbook' ); ?></label>
                            <select id="ebook-builder-status" class="widefat">
                                <option value="draft"><?php esc_html_e( 'Nháp', 'ebook-gitbook' ); ?></option>
                                <option value="publish"><?php esc_html_e( 'Xuất bản', 'ebook-gitbook' ); ?></option>
                                <option value="pending"><?php esc_html_e( 'Chờ duyệt', 'ebook-gitbook' ); ?></option>
                            </select>
                        </p>
                        <p class="builder-field">
                            <label for="ebook-builder-parent"><?php esc_html_e( 'Thuộc chương', 'ebook-gitbook' ); ?></label>
                            <select id="ebook-builder-parent" class="widefat"></select>
                        </p>
                    </div>

                    <p class="builder-field">
                        <label for="ebook-builder-excerpt"><?php esc_html_e( 'Tóm tắt ngắn', 'ebook-gitbook' ); ?></label>
                        <textarea id="ebook-builder-excerpt" class="widefat" rows="3"></textarea>
                    </p>

                    <p class="builder-field">
                        <label><?php esc_html_e( 'Nội dung trang', 'ebook-gitbook' ); ?></label>
                        <?php
                        wp_editor(
                            '',
                            'ebook_builder_editor',
                            [
                                'textarea_name' => 'ebook_builder_editor',
                                'textarea_rows' => 18,
                                'tinymce'       => true,
                                'media_buttons' => true,
                                'quicktags'     => true,
                            ]
                        );
                        ?>
                    </p>

                    <div class="builder-actions">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Lưu thay đổi', 'ebook-gitbook' ); ?></button>
                        <button type="button" class="button" id="ebook-builder-refresh"><?php esc_html_e( 'Huỷ thay đổi', 'ebook-gitbook' ); ?></button>
                    </div>
                </form>
            </section>
        </div>
    </div>
    <?php
}

function ebook_builder_get_root_posts() {
    $roots = get_posts( [
        'post_type'      => 'ebook',
        'post_parent'    => 0,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => [ 'draft', 'publish', 'pending', 'future', 'private' ],
    ] );

    return array_map(
        function ( WP_Post $post ) {
            return [
                'id'     => $post->ID,
                'title'  => ebook_get_plain_title( $post ),
                'status' => $post->post_status,
            ];
        },
        $roots
    );
}

function ebook_builder_check_permissions() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => __( 'Bạn không có quyền thực hiện thao tác này.', 'ebook-gitbook' ) ], 403 );
    }
}

function ebook_builder_verify_nonce() {
    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ebook_builder_nonce' ) ) {
        wp_send_json_error( [ 'message' => __( 'Token bảo mật không hợp lệ.', 'ebook-gitbook' ) ], 401 );
    }
}

function ebook_builder_get_post( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || 'ebook' !== $post->post_type ) {
        wp_send_json_error( [ 'message' => __( 'Ebook không tồn tại.', 'ebook-gitbook' ) ], 404 );
    }
    return $post;
}

function ebook_builder_ajax_get_tree() {
    ebook_builder_verify_nonce();
    ebook_builder_check_permissions();

    $root_id = isset( $_POST['root_id'] ) ? absint( $_POST['root_id'] ) : 0;
    if ( ! $root_id ) {
        wp_send_json_error( [ 'message' => __( 'Thiếu ID ebook gốc.', 'ebook-gitbook' ) ], 400 );
    }

    $root = ebook_builder_get_post( $root_id );
    if ( 0 !== (int) $root->post_parent ) {
        wp_send_json_error( [ 'message' => __( 'Ebook này không phải gốc.', 'ebook-gitbook' ) ], 400 );
    }

    wp_send_json_success( [
        'tree' => ebook_builder_format_tree( $root ),
    ] );
}

function ebook_builder_format_tree( WP_Post $post ) {
    $children = get_posts( [
        'post_type'      => 'ebook',
        'post_parent'    => $post->ID,
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'post_status'    => [ 'draft', 'publish', 'pending', 'future', 'private' ],
    ] );

    return [
        'id'       => $post->ID,
        'title'    => ebook_get_plain_title( $post ),
        'status'   => $post->post_status,
        'is_root'  => 0 === (int) $post->post_parent,
        'children' => array_map( 'ebook_builder_format_tree', $children ),
    ];
}

function ebook_builder_ajax_get_node() {
    ebook_builder_verify_nonce();
    ebook_builder_check_permissions();

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( [ 'message' => __( 'Thiếu ID trang.', 'ebook-gitbook' ) ], 400 );
    }

    $post = ebook_builder_get_post( $post_id );

    wp_send_json_success( [
        'post' => [
            'ID'      => $post->ID,
            'title'   => ebook_get_plain_title( $post ),
            'status'  => $post->post_status,
            'excerpt' => $post->post_excerpt,
            'content' => $post->post_content,
            'parent'  => (int) $post->post_parent,
            'link'    => get_permalink( $post ),
            'is_root' => 0 === (int) $post->post_parent,
        ],
    ] );
}

function ebook_builder_ajax_save_node() {
    ebook_builder_verify_nonce();
    ebook_builder_check_permissions();

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( [ 'message' => __( 'Thiếu ID trang.', 'ebook-gitbook' ) ], 400 );
    }

    $post          = ebook_builder_get_post( $post_id );
    $is_root       = 0 === (int) $post->post_parent;
    $parent_id     = isset( $_POST['post_parent'] ) ? absint( $_POST['post_parent'] ) : $post->post_parent;
    $status        = isset( $_POST['post_status'] ) ? sanitize_key( $_POST['post_status'] ) : $post->post_status;
    $title         = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : $post->post_title;
    $excerpt       = isset( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : $post->post_excerpt;
    $content       = isset( $_POST['post_content'] ) ? wp_kses_post( $_POST['post_content'] ) : $post->post_content;

    if ( $is_root ) {
        $parent_id = 0;
    }

    $update = [
        'ID'           => $post_id,
        'post_title'   => $title,
        'post_status'  => $status,
        'post_parent'  => $parent_id,
        'post_excerpt' => $excerpt,
        'post_content' => $content,
    ];

    wp_update_post( $update );

    wp_send_json_success( [ 'message' => __( 'Đã lưu trang.', 'ebook-gitbook' ) ] );
}

function ebook_builder_ajax_create_node() {
    ebook_builder_verify_nonce();
    ebook_builder_check_permissions();

    $root_id    = isset( $_POST['root_id'] ) ? absint( $_POST['root_id'] ) : 0;
    $parent_id  = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;
    $title      = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
    $status     = isset( $_POST['post_status'] ) ? sanitize_key( $_POST['post_status'] ) : 'draft';

    if ( ! $root_id || empty( $title ) ) {
        wp_send_json_error( [ 'message' => __( 'Thiếu thông tin tạo trang.', 'ebook-gitbook' ) ], 400 );
    }

    $root = ebook_builder_get_post( $root_id );
    if ( 0 !== (int) $root->post_parent ) {
        wp_send_json_error( [ 'message' => __( 'ID gốc không hợp lệ.', 'ebook-gitbook' ) ], 400 );
    }

    if ( ! $parent_id ) {
        $parent_id = $root_id;
    }

    $parent_root = ebook_get_root_post_id( $parent_id );
    if ( $parent_root !== $root_id ) {
        wp_send_json_error( [ 'message' => __( 'Trang cha không thuộc ebook này.', 'ebook-gitbook' ) ], 400 );
    }

    $siblings = get_posts( [
        'post_type'      => 'ebook',
        'post_parent'    => $parent_id,
        'posts_per_page' => 1,
        'orderby'        => 'menu_order',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ] );
    $menu_order = $siblings ? ( (int) get_post_field( 'menu_order', $siblings[0] ) + 1 ) : 0;

    $post_id = wp_insert_post( [
        'post_type'   => 'ebook',
        'post_title'  => $title,
        'post_status' => $status,
        'post_parent' => $parent_id,
        'menu_order'  => $menu_order,
    ] );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => $post_id->get_error_message() ], 500 );
    }

    wp_send_json_success( [
        'message' => __( 'Đã tạo trang mới.', 'ebook-gitbook' ),
        'post_id' => $post_id,
    ] );
}

function ebook_builder_ajax_create_root() {
    ebook_builder_verify_nonce();
    ebook_builder_check_permissions();

    $title = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
    if ( ! $title ) {
        wp_send_json_error( [ 'message' => __( 'Vui lòng nhập tiêu đề ebook.', 'ebook-gitbook' ) ], 400 );
    }

    $post_id = wp_insert_post( [
        'post_type'   => 'ebook',
        'post_title'  => $title,
        'post_status' => 'draft',
        'menu_order'  => 0,
    ] );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => $post_id->get_error_message() ], 500 );
    }

    wp_send_json_success( [
        'message' => __( 'Đã tạo ebook mới.', 'ebook-gitbook' ),
        'post_id' => $post_id,
    ] );
}

function ebook_builder_ajax_delete_node() {
    ebook_builder_verify_nonce();
    ebook_builder_check_permissions();

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( [ 'message' => __( 'Thiếu ID trang.', 'ebook-gitbook' ) ], 400 );
    }

    $post = ebook_builder_get_post( $post_id );
    if ( 0 === (int) $post->post_parent ) {
        wp_send_json_error( [ 'message' => __( 'Không thể xoá ebook gốc.', 'ebook-gitbook' ) ], 400 );
    }

    wp_delete_post( $post_id, true );

    wp_send_json_success( [ 'message' => __( 'Đã xoá trang.', 'ebook-gitbook' ) ] );
}

function ebook_builder_ajax_reorder() {
    ebook_builder_verify_nonce();
    ebook_builder_check_permissions();

    $root_id = isset( $_POST['root_id'] ) ? absint( $_POST['root_id'] ) : 0;
    $payload = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : '';

    if ( ! $root_id || ! $payload ) {
        wp_send_json_error( [ 'message' => __( 'Thiếu dữ liệu sắp xếp.', 'ebook-gitbook' ) ], 400 );
    }

    $tree = json_decode( $payload, true );
    if ( ! is_array( $tree ) || empty( $tree ) ) {
        wp_send_json_error( [ 'message' => __( 'Dữ liệu không hợp lệ.', 'ebook-gitbook' ) ], 400 );
    }

    $root_node = $tree[0];
    if ( (int) ( $root_node['id'] ?? 0 ) !== $root_id ) {
        wp_send_json_error( [ 'message' => __( 'Ebook gốc không khớp.', 'ebook-gitbook' ) ], 400 );
    }

    ebook_builder_apply_order( $root_node['children'] ?? [], $root_id );

    wp_send_json_success( [ 'message' => __( 'Đã lưu thứ tự.', 'ebook-gitbook' ) ] );
}

function ebook_builder_apply_order( array $nodes, $parent_id ) {
    foreach ( $nodes as $index => $node ) {
        $post_id = isset( $node['id'] ) ? absint( $node['id'] ) : 0;
        if ( ! $post_id ) {
            continue;
        }

        wp_update_post( [
            'ID'         => $post_id,
            'post_parent'=> $parent_id,
            'menu_order' => $index,
        ] );

        if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
            ebook_builder_apply_order( $node['children'], $post_id );
        }
    }
}

