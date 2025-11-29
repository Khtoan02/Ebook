<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Lấy ID ebook từ URL (nếu đang chỉnh sửa)
$ebook_id = isset( $_GET['ebook_id'] ) ? (int) $_GET['ebook_id'] : 0;
$is_edit = $ebook_id > 0;

// Nếu đang chỉnh sửa, lấy thông tin ebook
$ebook = null;
$ebook_title = '';
$ebook_content = '';
$ebook_structure = [];

if ( $is_edit ) {
    $ebook = get_post( $ebook_id );
    if ( $ebook && $ebook->post_type === 'ebook' ) {
        $ebook_title = $ebook->post_title;
        $ebook_content = $ebook->post_content;
        
        // Lấy danh sách các trang con (child pages)
        $child_pages = get_posts( [
            'post_type'      => 'ebook',
            'post_parent'    => $ebook_id,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ] );
        
        // Lấy cấu trúc chapters từ meta hoặc tạo từ child pages
        $ebook_structure = get_post_meta( $ebook_id, '_ebook_structure', true );
        if ( ! $ebook_structure || empty( $ebook_structure ) ) {
            // Tạo structure từ child pages nếu chưa có
            $ebook_structure = [];
            foreach ( $child_pages as $child ) {
                $ebook_structure[] = [
                    'id'       => 'page_' . $child->ID,
                    'title'    => $child->post_title,
                    'post_id'  => $child->ID,
                    'content'  => $child->post_content,
                    'children' => [],
                ];
            }
        } else {
            // Đồng bộ với child pages thực tế
            foreach ( $ebook_structure as &$item ) {
                if ( isset( $item['post_id'] ) ) {
                    $child_post = get_post( $item['post_id'] );
                    if ( $child_post ) {
                        $item['content'] = $child_post->post_content;
                        $item['title'] = $child_post->post_title;
                    }
                }
            }
        }
    } else {
        wp_die( 'Ebook không tồn tại.' );
    }
}

// Nonce để bảo mật
$nonce = wp_create_nonce( 'ebook_editor_save_' . ( $is_edit ? $ebook_id : 0 ) );

// Set page title cho admin
$admin_page_title = $is_edit && !empty($ebook_title) ? esc_html($ebook_title) : ($is_edit ? 'Chỉnh sửa Ebook' : 'Thêm Ebook mới');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $admin_page_title; ?> - Ebook Editor</title>
    
    <?php wp_head(); ?>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ============================================
           RESET & WORDPRESS ADMIN INTEGRATION
           ============================================ */
        
        /* Reset WordPress admin styles */
        body.wp-admin {
            font-family: 'Inter', sans-serif !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Điều chỉnh layout để có admin menu */
        #wpcontent {
            margin-left: 160px !important;
            padding: 0 !important;
        }
        
        #wpbody-content {
            padding: 0 !important;
        }
        
        /* Ẩn WordPress admin bar nếu có */
        #wpadminbar {
            display: none !important;
        }
        
        /* Ẩn WordPress admin notices */
        .notice,
        .update-nag,
        .error,
        .updated {
            display: none !important;
        }
        
        /* ============================================
           EBOOK EDITOR WRAPPER
           ============================================ */
        .ebook-editor-wrapper {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            position: relative;
            min-height: 100vh;
            height: auto;
            display: flex;
            flex-direction: column;
            width: 100%;
            overflow-x: hidden;
        }
        
        /* Main layout container */
        .ebook-editor-wrapper > .flex {
            flex: 1;
            min-height: 0;
            overflow: visible;
        }
        
        /* Editor Canvas */
        #editor-canvas { 
            outline: none; 
            min-height: 500px;
            height: auto;
            padding-bottom: 300px; 
            cursor: text;
            overflow: visible;
        }
        
        /* Editor paper container */
        #editor-paper {
            min-height: auto !important;
            height: auto !important;
            overflow: visible;
        }
        p:empty:not(:focus)::before { 
            content: "Nhập nội dung..."; 
            color: #cbd5e1; 
            pointer-events: none; 
            float: left; 
            height: 0; 
        }
        
        /* Danh sách & Link trong Editor */
        #editor-canvas ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
        #editor-canvas ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 1rem; }
        #editor-canvas li { margin-bottom: 0.25rem; }
        #editor-canvas a { color: #2563eb; text-decoration: underline; cursor: pointer; }

        /* Block Wrapper */
        .block-wrapper { 
            position: relative; 
            margin: 3.5rem 0 1.5rem 0; 
            padding-top: 40px; /* Tạo không gian cho block-actions */
            border: 1px solid transparent; 
            border-radius: 8px; 
            transition: all 0.2s; 
            user-select: none;
            overflow: visible; /* Đảm bảo block-actions không bị cắt */
        }
        .block-wrapper:hover { 
            border-color: #e2e8f0; 
            background-color: rgba(255,255,255,0.5); 
        }
        
        /* Block Actions */
        .block-actions { 
            position: absolute; 
            top: 0; 
            right: 0; 
            display: none; 
            flex-direction: row; 
            gap: 6px; 
            z-index: 1000 !important; 
            background: white; 
            padding: 6px 8px; 
            border-radius: 6px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            pointer-events: auto !important;
            white-space: nowrap;
        }
        .block-wrapper:hover .block-actions { 
            display: flex !important; 
        }
        
        /* Block action buttons */
        .block-actions button {
            pointer-events: auto !important;
            cursor: pointer !important;
            z-index: 1001 !important;
            flex-shrink: 0;
            min-width: 28px;
            min-height: 28px;
        }
        
        /* Handle block for dragging */
        .handle-block {
            cursor: move !important;
            pointer-events: auto !important;
        }
        
        /* Đảm bảo editor paper không cắt block-actions */
        #editor-paper {
            overflow: visible !important;
        }
        
        /* Đảm bảo editor canvas không cắt block-actions */
        #editor-canvas {
            overflow: visible !important;
        }
        
        /* Nội dung bên trong Block */
        .block-content-editable { outline: none; cursor: text; user-select: text; }
        
        /* Custom Block Styles */
        .callout-info { 
            background: #f0f9ff; 
            border-left: 4px solid #0ea5e9; 
            border-radius: 4px; 
            padding: 16px; 
            display: flex; 
            gap: 12px; 
        }
        .callout-warning { 
            background: #fef2f2; 
            border-left: 4px solid #ef4444; 
            border-radius: 4px; 
            padding: 16px; 
            display: flex; 
            gap: 12px; 
        }
        
        .timeline-step { 
            position: relative; 
            padding-left: 24px; 
            border-left: 2px solid #e2e8f0; 
            margin-left: 8px; 
            padding-bottom: 24px; 
        }
        .timeline-step:last-of-type { 
            border-left-color: transparent; 
            padding-bottom: 0; 
        }
        .timeline-dot { 
            position: absolute; 
            left: -7px; 
            top: 0; 
            width: 12px; 
            height: 12px; 
            border-radius: 50%; 
            background: #fff; 
            border: 2px solid #0ea5e9; 
            box-shadow: 0 0 0 2px white; 
        }
        
        .tab-header { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 4px; 
            background: #f1f5f9; 
            padding: 4px; 
            border-radius: 6px; 
            margin-bottom: 12px; 
            align-items: center; 
        }
        .tab-btn { 
            padding: 6px 12px; 
            font-size: 13px; 
            font-weight: 500; 
            border-radius: 4px; 
            cursor: pointer; 
            border: 1px solid transparent; 
            user-select: none; 
        }
        .tab-btn.active { 
            background: #fff; 
            border-color: #e2e8f0; 
            color: #0284c7; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.05); 
        }
        .tab-content-pane { display: none; }
        .tab-content-pane.active { display: block; }

        .aux-btn { 
            font-size: 12px; 
            color: #64748b; 
            background: #f8fafc; 
            border: 1px dashed #cbd5e1; 
            padding: 4px 8px; 
            border-radius: 4px; 
            cursor: pointer; 
            margin-top: 8px; 
            transition: all 0.2s; 
            display: inline-flex; 
            align-items: center; 
            gap: 4px; 
        }
        .aux-btn:hover { 
            background: #f1f5f9; 
            color: #0284c7; 
            border-color: #0284c7; 
        }
        
        .chapter-item { 
            transition: all 0.2s; 
            border: 1px solid transparent; 
        }
        .chapter-item:hover { background-color: #f1f5f9; }
        .chapter-item.active { 
            background-color: #e0f2fe; 
            color: #0284c7; 
            font-weight: 600; 
        }
        
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { 
            overflow-x: hidden; 
            overflow-y: visible !important; 
        }
        
        /* ============================================
           HEADER STYLES
           ============================================ */
        .ebook-editor-header {
            position: sticky !important;
            top: 0 !important;
            z-index: 50 !important;
            background: white !important;
            border-bottom: 1px solid #e5e7eb !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1) !important;
            height: 64px !important;
            min-height: 64px !important;
            max-height: 64px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding: 0 16px !important;
            margin: 0 !important;
            width: 100% !important;
        }
        
        .ebook-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            min-width: 0;
        }
        
        .ebook-header-back-btn {
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .ebook-header-back-btn:hover {
            background-color: #f3f4f6;
            color: #374151;
        }
        
        .ebook-header-icon {
            width: 32px;
            height: 32px;
            background: #2563eb;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .ebook-header-title {
            font-weight: 700;
            font-size: 18px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 400px;
            display: none;
        }
        @media (min-width: 768px) {
            .ebook-header-title {
                display: block;
            }
        }
        
        .ebook-header-toolbar {
            display: flex;
            align-items: center;
            background: #f3f4f6;
            padding: 4px;
            border-radius: 8px;
            overflow-x: auto;
            flex: 1;
            margin: 0 16px;
            min-width: 0;
            max-width: calc(100% - 300px);
        }
        
        .ebook-header-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        
        .ebook-status-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 14px;
            background: white;
            color: #374151;
            min-width: 120px;
            cursor: pointer;
            outline: none;
        }
        .ebook-status-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .ebook-save-btn {
            background: #2563eb;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s;
            white-space: nowrap;
        }
        .ebook-save-btn:hover {
            background: #1d4ed8;
        }
        .ebook-save-text {
            display: none;
        }
        @media (min-width: 640px) {
            .ebook-save-text {
                display: inline;
            }
        }
        
        /* Toolbar Button Style */
        .toolbar-btn { 
            padding: 0.5rem; 
            border-radius: 0.25rem; 
            color: #4b5563; 
            transition: background-color 0.2s; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            width: 2rem; 
            height: 2rem;
            flex-shrink: 0;
            border: none;
            background: transparent;
            cursor: pointer;
        }
        .toolbar-btn:hover { 
            background-color: white; 
            color: #1f2937; 
        }
        .toolbar-group { 
            display: flex; 
            align-items: center; 
            gap: 2px; 
            padding-right: 0.5rem; 
            margin-right: 0.5rem; 
            border-right: 1px solid #d1d5db;
            flex-shrink: 0;
        }
        .toolbar-group:last-child { 
            border-right: none; 
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .loading-overlay.active {
            display: flex;
        }
        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="wp-admin wp-core-ui admin-color-fresh">
<div class="ebook-editor-wrapper">

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Header -->
    <header class="ebook-editor-header">
        <div class="ebook-header-left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ebook-manager' ) ); ?>" class="ebook-header-back-btn" title="Quay lại">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="ebook-header-icon">
                <i class="fas fa-book"></i>
            </div>
            <h1 id="header-title" class="ebook-header-title"><?php echo $is_edit && !empty($ebook_title) ? esc_html($ebook_title) : ($is_edit ? 'Chỉnh sửa Ebook' : 'Thêm Ebook mới'); ?></h1>
        </div>

        <!-- MAIN TOOLBAR -->
        <div class="ebook-header-toolbar">
            
            <!-- Group 1: Style -->
            <div class="toolbar-group">
                <button onmousedown="event.preventDefault(); formatDoc('bold')" class="toolbar-btn" title="In đậm (Ctrl+B)"><i class="fas fa-bold"></i></button>
                <button onmousedown="event.preventDefault(); formatDoc('italic')" class="toolbar-btn" title="In nghiêng (Ctrl+I)"><i class="fas fa-italic"></i></button>
                <button onmousedown="event.preventDefault(); formatDoc('underline')" class="toolbar-btn" title="Gạch chân (Ctrl+U)"><i class="fas fa-underline"></i></button>
            </div>

            <!-- Group 2: Link & List -->
            <div class="toolbar-group">
                <button onmousedown="event.preventDefault(); openLinkModal()" class="toolbar-btn" title="Chèn Link"><i class="fas fa-link"></i></button>
                <button onmousedown="event.preventDefault(); formatDoc('insertUnorderedList')" class="toolbar-btn" title="Danh sách chấm"><i class="fas fa-list-ul"></i></button>
                <button onmousedown="event.preventDefault(); formatDoc('insertOrderedList')" class="toolbar-btn" title="Danh sách số"><i class="fas fa-list-ol"></i></button>
            </div>

            <!-- Group 3: Alignment -->
            <div class="toolbar-group">
                <button onmousedown="event.preventDefault(); formatDoc('justifyLeft')" class="toolbar-btn" title="Căn trái"><i class="fas fa-align-left"></i></button>
                <button onmousedown="event.preventDefault(); formatDoc('justifyCenter')" class="toolbar-btn" title="Căn giữa"><i class="fas fa-align-center"></i></button>
                <button onmousedown="event.preventDefault(); formatDoc('justifyRight')" class="toolbar-btn" title="Căn phải"><i class="fas fa-align-right"></i></button>
            </div>
            
            <!-- Group 4: Custom Blocks -->
            <div class="flex items-center gap-1">
                <button onmousedown="event.preventDefault(); applyBlock('info')" class="flex items-center gap-1 px-2 py-1.5 hover:bg-white rounded text-sm font-medium text-sky-700 whitespace-nowrap"><i class="fas fa-info-circle"></i> Info</button>
                <button onmousedown="event.preventDefault(); applyBlock('warning')" class="flex items-center gap-1 px-2 py-1.5 hover:bg-white rounded text-sm font-medium text-red-700 whitespace-nowrap"><i class="fas fa-exclamation-triangle"></i> Warn</button>
                <button onmousedown="event.preventDefault(); applyBlock('timeline')" class="flex items-center gap-1 px-2 py-1.5 hover:bg-white rounded text-sm font-medium text-slate-700 whitespace-nowrap"><i class="fas fa-stream"></i> Time</button>
                <button onmousedown="event.preventDefault(); applyBlock('checklist')" class="flex items-center gap-1 px-2 py-1.5 hover:bg-white rounded text-sm font-medium text-slate-700 whitespace-nowrap"><i class="fas fa-check-square"></i> Check</button>
                <button onmousedown="event.preventDefault(); applyBlock('tabs')" class="flex items-center gap-1 px-2 py-1.5 hover:bg-white rounded text-sm font-medium text-slate-700 whitespace-nowrap"><i class="fas fa-columns"></i> Tabs</button>
                <button onmousedown="event.preventDefault(); applyBlock('faq')" class="flex items-center gap-1 px-2 py-1.5 hover:bg-white rounded text-sm font-medium text-slate-700 whitespace-nowrap"><i class="fas fa-question-circle"></i> FAQ</button>
                <button onmousedown="event.preventDefault(); applyBlock('table')" class="flex items-center gap-1 px-2 py-1.5 hover:bg-white rounded text-sm font-medium text-slate-700 whitespace-nowrap"><i class="fas fa-table"></i> Table</button>
                <button onmousedown="event.preventDefault(); openMediaModal()" class="flex items-center gap-1 px-2 py-1.5 hover:bg-white rounded text-sm font-medium text-slate-700 whitespace-nowrap"><i class="fas fa-image"></i> Img</button>
            </div>
        </div>

        <div class="ebook-header-right">
            <!-- Trạng thái xuất bản -->
            <select id="ebook-status" class="ebook-status-select">
                <?php 
                $current_status = $is_edit && $ebook ? $ebook->post_status : 'draft';
                ?>
                <option value="draft" <?php selected( $current_status, 'draft' ); ?>>Bản nháp</option>
                <option value="publish" <?php selected( $current_status, 'publish' ); ?>>Đã xuất bản</option>
                <option value="pending" <?php selected( $current_status, 'pending' ); ?>>Chờ duyệt</option>
                <option value="private" <?php selected( $current_status, 'private' ); ?>>Riêng tư</option>
            </select>
            <button onclick="saveEbook()" class="ebook-save-btn">
                <i class="fas fa-save"></i> 
                <span class="ebook-save-text">Lưu Ebook</span>
            </button>
        </div>
    </header>

    <!-- Main Layout -->
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar Tree -->
        <aside class="w-72 bg-white border-r border-gray-200 flex flex-col flex-shrink-0 z-10 hidden md:flex">
            <!-- Ảnh đại diện -->
            <div class="p-4 border-b border-gray-100 bg-gray-50">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Ảnh đại diện</label>
                <div class="relative">
                    <?php 
                    $thumbnail_id = $is_edit ? get_post_thumbnail_id( $ebook_id ) : 0;
                    $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'medium' ) : '';
                    ?>
                    <div id="ebook-thumbnail-preview" class="w-full aspect-video bg-gray-100 rounded border-2 border-dashed border-gray-300 flex items-center justify-center cursor-pointer hover:border-blue-500 transition-colors" onclick="openThumbnailModal()">
                        <?php if ( $thumbnail_url ) : ?>
                            <img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="Thumbnail" class="w-full h-full object-cover rounded">
                        <?php else : ?>
                            <div class="text-center text-gray-400">
                                <i class="fas fa-image text-3xl mb-2"></i>
                                <p class="text-xs">Click để chọn ảnh</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" id="ebook-thumbnail-id" value="<?php echo esc_attr( $thumbnail_id ); ?>">
                    <?php if ( $thumbnail_url ) : ?>
                        <button onclick="removeThumbnail()" class="mt-2 w-full text-xs text-red-600 hover:text-red-800 py-1">
                            <i class="fas fa-trash"></i> Xóa ảnh
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Cấu trúc</span>
                <button onclick="addPageRoot()" class="text-blue-600 hover:text-blue-800 text-xs font-bold px-2 py-1 hover:bg-blue-50 rounded"><i class="fas fa-plus"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto p-2" id="chapter-list-container">
                <!-- Ebook chính -->
                <div id="main-ebook-item" class="chapter-item flex justify-between items-center py-1.5 px-3 rounded cursor-pointer mb-1 bg-blue-50 border border-blue-200 active" onclick="selectMainEbook()" data-type="main">
                    <div class="flex items-center gap-2 truncate">
                        <i class="fas fa-book text-blue-600 text-xs"></i>
                        <span class="truncate text-sm font-medium title-editable" contenteditable="false"><?php echo esc_html( $ebook_title ?: 'Ebook chính' ); ?></span>
                    </div>
                </div>
                <ul id="chapter-root" class="space-y-1 pb-10 mt-2"></ul>
            </div>
        </aside>

        <!-- Editor -->
        <main class="flex-1 bg-gray-100 p-4 md:p-8 flex justify-center relative overflow-y-auto" id="main-scroll" style="max-height: calc(100vh - 64px); overflow-x: visible;">
            <div class="bg-white w-full max-w-4xl shadow-lg rounded-lg p-8 md:p-12 relative mb-8" id="editor-paper" style="overflow: visible;">
                <h1 id="page-title" contenteditable="true" class="text-3xl md:text-4xl font-extrabold text-slate-800 mb-6 border-none outline-none placeholder:text-gray-300" placeholder="Tiêu đề trang..."><?php echo esc_html( $ebook_title ?: 'Tiêu đề Ebook' ); ?></h1>
                
                <div id="editor-canvas" contenteditable="true" class="text-base leading-relaxed text-slate-600" onmouseup="saveSelection()" onkeyup="saveSelection()" style="min-height: 500px;">
                    <?php if ( $ebook_content ) : ?>
                        <?php echo wp_kses_post( $ebook_content ); ?>
                    <?php else : ?>
                        <p class="mb-4">Chào mừng! Bạn có thể soạn thảo văn bản bình thường tại đây.</p>
                        <p class="mb-4">Thử bôi đen dòng chữ này và bấm nút Link trên thanh công cụ.</p>
                        <p class="mb-4">Sau đó nhập URL vào cửa sổ hiện ra.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- LINK MODAL -->
    <div id="link-modal" class="modal opacity-0 pointer-events-none fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="closeLinkModal()"></div>
        <div class="bg-white w-full max-w-md rounded-lg shadow-xl z-50 overflow-hidden transform transition-all scale-95" id="link-modal-content">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-gray-700">Chèn liên kết</h3>
                <button onclick="closeLinkModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Đường dẫn URL</label>
                <input type="text" id="link-url-input" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500" placeholder="https://example.com" onkeydown="if(event.key==='Enter') insertLinkFromModal()">
                <div class="flex justify-end gap-2 mt-4">
                    <button onclick="closeLinkModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded text-sm font-medium hover:bg-gray-200">Hủy</button>
                    <button onclick="insertLinkFromModal()" class="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700">Chèn</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MEDIA MODAL -->
    <div id="media-modal" class="modal opacity-0 pointer-events-none fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="closeMediaModal()"></div>
        <div class="bg-white w-11/12 max-w-3xl rounded-lg shadow-xl z-50 overflow-hidden flex flex-col max-h-[80vh]">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-lg">Chọn hình ảnh</h3>
                <button onclick="closeMediaModal()" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6 overflow-y-auto grid grid-cols-3 sm:grid-cols-4 gap-4">
                <div onclick="insertImage('https://placehold.co/600x400/e2e8f0/64748b?text=Chart')" class="aspect-video bg-gray-100 rounded border hover:border-blue-500 cursor-pointer flex items-center justify-center"><i class="fas fa-chart-pie text-2xl text-gray-400"></i></div>
                <div onclick="insertImage('https://placehold.co/600x400/f0f9ff/0ea5e9?text=Process')" class="aspect-video bg-gray-100 rounded border hover:border-blue-500 cursor-pointer flex items-center justify-center"><i class="fas fa-cogs text-2xl text-sky-300"></i></div>
            </div>
        </div>
    </div>

    <!-- THUMBNAIL MODAL (WordPress Media Library) -->
    <div id="thumbnail-modal" class="modal opacity-0 pointer-events-none fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="closeThumbnailModal()"></div>
        <div class="bg-white w-11/12 max-w-4xl rounded-lg shadow-xl z-50 overflow-hidden flex flex-col max-h-[80vh]">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-lg">Chọn ảnh đại diện</h3>
                <button onclick="closeThumbnailModal()" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6 overflow-y-auto">
                <p class="text-sm text-gray-600 mb-4">Sử dụng WordPress Media Library để chọn ảnh:</p>
                <button onclick="openWordPressMediaLibrary()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-medium">
                    <i class="fas fa-images"></i> Mở Media Library
                </button>
                <div id="thumbnail-media-library" class="mt-4"></div>
            </div>
        </div>
    </div>

    <script>
        // WordPress AJAX URL và nonce (khai báo global ngay)
        const ebookEditor = {
            ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
            nonce: '<?php echo esc_js( $nonce ); ?>',
            ebookId: <?php echo $ebook_id; ?>,
            isEdit: <?php echo $is_edit ? 'true' : 'false'; ?>
        };

        // Khai báo các biến global
        let savedRange = null;
        let chapters = <?php echo json_encode( $ebook_structure ); ?>;
        if (!chapters || chapters.length === 0) {
            chapters = [];
        }
        let rootEl = null; // Khai báo global để dùng trong saveEbook
        let currentPageId = null; // ID của page đang chỉnh sửa
        let currentPageType = 'main'; // 'main' hoặc 'page'
        let pageContents = {}; // Lưu nội dung của từng page
        
        // Lưu nội dung ebook chính ban đầu
        pageContents['main'] = {
            title: <?php echo json_encode( $ebook_title ?: 'Tiêu đề Ebook' ); ?>,
            content: <?php echo json_encode( $ebook_content ); ?>
        };
        
        // Lưu nội dung các pages
        <?php if ( $is_edit && !empty( $ebook_structure ) ) : ?>
            <?php foreach ( $ebook_structure as $item ) : ?>
                pageContents['<?php echo esc_js( $item['id'] ?? '' ); ?>'] = {
                    title: <?php echo json_encode( $item['title'] ?? '' ); ?>,
                    content: <?php echo json_encode( $item['content'] ?? '' ); ?>,
                    post_id: <?php echo isset( $item['post_id'] ) ? (int) $item['post_id'] : 'null'; ?>
                };
            <?php endforeach; ?>
        <?php endif; ?>
        
        // Đảm bảo script chạy sau khi DOM đã load
        document.addEventListener('DOMContentLoaded', function() {
        
        rootEl = document.getElementById('chapter-root');
        
        if (!rootEl) {
            console.error('Không tìm thấy element #chapter-root');
            return;
        }
        
        function renderTree(items, container) {
            container.innerHTML = '';
            items.forEach(item => {
                // Đảm bảo pageContents có item này
                if (!pageContents[item.id]) {
                    pageContents[item.id] = {
                        title: item.title || 'Trang mới',
                        content: item.content || '',
                        post_id: item.post_id || null
                    };
                } else {
                    // Đồng bộ title từ item vào pageContents
                    if (item.title && item.title !== pageContents[item.id].title) {
                        pageContents[item.id].title = item.title;
                    }
                    if (item.content !== undefined) {
                        pageContents[item.id].content = item.content;
                    }
                    if (item.post_id) {
                        pageContents[item.id].post_id = item.post_id;
                    }
                }
                
                const li = document.createElement('li');
                const displayTitle = pageContents[item.id]?.title || item.title || 'Trang mới';
                li.innerHTML = `
                    <div class="chapter-item flex justify-between items-center py-1.5 px-3 rounded cursor-pointer mb-1" onclick="selectPage(this)" data-id="${item.id}" data-type="page">
                        <div class="flex items-center gap-2 truncate"><i class="fas fa-grip-vertical text-gray-300 handle text-xs"></i><span class="truncate text-sm font-medium title-editable" contenteditable="false">${displayTitle}</span></div>
                        <div class="flex gap-1"><button class="w-5 h-5 text-gray-400 hover:text-blue-600 text-xs" onclick="addChildPage(event,this)"><i class="fas fa-plus"></i></button><button class="w-5 h-5 text-gray-400 hover:text-red-600 text-xs" onclick="deletePage(event,this)"><i class="fas fa-trash"></i></button></div>
                    </div><ul class="pl-4 border-l border-gray-200 ml-3 space-y-1 nested-sortable min-h-[2px]"></ul>`;
                if(item.children && item.children.length>0) renderTree(item.children, li.querySelector('ul'));
                new Sortable(li.querySelector('ul'), { group: 'nested', animation: 150, fallbackOnBody: true });
                container.appendChild(li);
            });
        }
        new Sortable(rootEl, { group: 'nested', animation: 150, handle: '.handle' });
        renderTree(chapters, rootEl);
        
        // Hàm lưu nội dung hiện tại trước khi chuyển page
        function saveCurrentPageContent() {
            if (currentPageId) {
                const title = document.getElementById('page-title').innerText.trim();
                const content = document.getElementById('editor-canvas').innerHTML;
                pageContents[currentPageId] = {
                    title: title,
                    content: content,
                    post_id: pageContents[currentPageId]?.post_id || null
                };
            }
        }
        
        // Hàm load nội dung page
        function loadPageContent(pageId, pageType) {
            const pageTitleEl = document.getElementById('page-title');
            const contentEl = document.getElementById('editor-canvas');
            const headerTitleEl = document.getElementById('header-title');
            
            if (!pageTitleEl || !contentEl) return;
            
            if (pageType === 'main') {
                // Load nội dung ebook chính
                const mainContent = pageContents['main'] || { title: 'Tiêu đề Ebook', content: '' };
                pageTitleEl.innerText = mainContent.title;
                contentEl.innerHTML = mainContent.content || '<p class="mb-4">Chào mừng! Bạn có thể soạn thảo văn bản bình thường tại đây.</p>';
                if (headerTitleEl) {
                    headerTitleEl.innerText = mainContent.title;
                    document.title = mainContent.title + ' - Ebook Editor';
                }
            } else {
                // Load nội dung page
                const pageContent = pageContents[pageId] || { title: 'Trang mới', content: '' };
                pageTitleEl.innerText = pageContent.title;
                contentEl.innerHTML = pageContent.content || '<p class="mb-4">Nội dung trang...</p>';
                if (headerTitleEl) {
                    headerTitleEl.innerText = pageContent.title;
                    document.title = pageContent.title + ' - Ebook Editor';
                }
            }
        }
        
        // Khai báo global các hàm để có thể gọi từ onclick
        window.selectMainEbook = function() {
            // Lưu nội dung page hiện tại
            saveCurrentPageContent();
            
            // Chọn ebook chính
            document.querySelectorAll('.chapter-item').forEach(i=>i.classList.remove('active', 'bg-blue-50', 'border', 'border-blue-200'));
            const mainItem = document.getElementById('main-ebook-item');
            if (mainItem) {
                mainItem.classList.add('active', 'bg-blue-50', 'border', 'border-blue-200');
            }
            
            currentPageId = 'main';
            currentPageType = 'main';
            loadPageContent('main', 'main');
        };
        
        window.selectPage = function(el){ 
            // Lưu nội dung page hiện tại
            saveCurrentPageContent();
            
            // Chọn page mới
            document.querySelectorAll('.chapter-item').forEach(i=>i.classList.remove('active', 'bg-blue-50', 'border', 'border-blue-200'));
            el.classList.add('active');
            
            const pageId = el.getAttribute('data-id');
            const pageType = el.getAttribute('data-type') || 'page';
            
            currentPageId = pageId;
            currentPageType = pageType;
            
            // Load nội dung page
            loadPageContent(pageId, pageType);
        };
        
        window.deletePage = function(e,btn){ 
            e.stopPropagation(); 
            if(confirm('Bạn có chắc chắn muốn xóa trang này? Tất cả nội dung sẽ bị mất.')) {
                const pageId = btn.closest('[data-id]')?.getAttribute('data-id');
                if (pageId) {
                    // Xóa khỏi pageContents
                    if (pageContents[pageId]) {
                        delete pageContents[pageId];
                    }
                    
                    // Xóa khỏi chapters structure
                    function removeFromChapters(items, targetId) {
                        for (let i = items.length - 1; i >= 0; i--) {
                            if (items[i].id === targetId) {
                                items.splice(i, 1);
                                return true;
                            }
                            if (items[i].children && items[i].children.length > 0) {
                                if (removeFromChapters(items[i].children, targetId)) {
                                    return true;
                                }
                            }
                        }
                        return false;
                    }
                    removeFromChapters(chapters, pageId);
                    
                    // Nếu đang chỉnh sửa page này, chuyển về ebook chính
                    if (currentPageId === pageId) {
                        selectMainEbook();
                    }
                }
                btn.closest('li').remove(); 
            }
        };
        
        window.addPageRoot = function() {
            const newId = 'page_' + Date.now();
            chapters.push({ id: String(newId), title: 'Trang mới', children: [] });
            
            // Khởi tạo nội dung trống cho page mới
            pageContents[newId] = {
                title: 'Trang mới',
                content: '',
                post_id: null
            };
            
            renderTree(chapters, rootEl);
        };
        
        function addChildPage(e,btn){ 
            e.stopPropagation(); 
            let ul = btn.closest('li').querySelector('ul'); 
            if (!ul) {
                // Tạo ul nếu chưa có
                ul = document.createElement('ul');
                ul.className = 'pl-4 border-l border-gray-200 ml-3 space-y-1 nested-sortable min-h-[2px]';
                btn.closest('li').appendChild(ul);
                new Sortable(ul, { group: 'nested', animation: 150, fallbackOnBody: true });
            }
            const li=document.createElement('li'); 
            const newId = 'page_' + Date.now();
            
            // Khởi tạo nội dung trống cho page mới
            pageContents[newId] = {
                title: 'Trang mới',
                content: '',
                post_id: null
            };
            
            // Tìm parent page ID để thêm vào chapters structure
            const parentItem = btn.closest('[data-id]');
            const parentId = parentItem ? parentItem.getAttribute('data-id') : null;
            
            // Thêm vào chapters structure nếu cần
            if (parentId && parentId !== 'main') {
                // Tìm trong chapters và thêm children
                function addToChapters(items, targetId, newItem) {
                    for (let item of items) {
                        if (item.id === targetId) {
                            if (!item.children) item.children = [];
                            item.children.push(newItem);
                            return true;
                        }
                        if (item.children && item.children.length > 0) {
                            if (addToChapters(item.children, targetId, newItem)) {
                                return true;
                            }
                        }
                    }
                    return false;
                }
                addToChapters(chapters, parentId, { id: newId, title: 'Trang mới', children: [] });
            } else {
                // Thêm vào root
                chapters.push({ id: newId, title: 'Trang mới', children: [] });
            }
            
            li.innerHTML=`<div class="chapter-item flex justify-between items-center py-1.5 px-3 rounded cursor-pointer mb-1" onclick="selectPage(this)" data-id="${newId}" data-type="page"><div class="flex items-center gap-2"><i class="fas fa-file text-gray-300 text-xs"></i><span class="text-sm title-editable" contenteditable="false">Trang mới</span></div><div class="flex gap-1"><button class="w-5 h-5 text-gray-400 hover:text-blue-600 text-xs" onclick="addChildPage(event,this)"><i class="fas fa-plus"></i></button><button class="w-5 h-5 text-gray-400 hover:text-red-600 text-xs" onclick="deletePage(event,this)"><i class="fas fa-trash"></i></button></div></div><ul class="pl-4 border-l border-gray-200 ml-3 space-y-1 nested-sortable min-h-[2px]"></ul>`; 
            ul.appendChild(li);
            const childUl = li.querySelector('ul');
            if (childUl) {
                new Sortable(childUl, { group: 'nested', animation: 150, fallbackOnBody: true });
            }
        }
        window.addChildPage = addChildPage;

        // --- 2. EDITOR LOGIC ---
        window.saveSelection = function() {
            const sel = window.getSelection();
            if (sel.rangeCount > 0) savedRange = sel.getRangeAt(0);
        };
        
        window.formatDoc = function(cmd, value = null) { 
            document.execCommand(cmd, false, value); 
            const canvas = document.getElementById('editor-canvas');
            if (canvas) canvas.focus(); 
        };

        // --- LINK MODAL LOGIC ---
        const linkModal = document.getElementById('link-modal');
        const linkInput = document.getElementById('link-url-input');

        window.openLinkModal = function() {
            saveSelection();
            if(!savedRange) { 
                alert('Vui lòng bôi đen văn bản cần chèn link'); 
                return; 
            }
            if (linkModal) {
                linkModal.classList.remove('opacity-0', 'pointer-events-none');
                document.body.classList.add('modal-active');
            }
            if (linkInput) {
                linkInput.value = '';
                setTimeout(() => linkInput.focus(), 100);
            }
        };

        window.closeLinkModal = function() {
            if (linkModal) {
                linkModal.classList.add('opacity-0', 'pointer-events-none');
                document.body.classList.remove('modal-active');
            }
        };

        window.insertLinkFromModal = function() {
            if (!linkInput) return;
            const url = linkInput.value.trim();
            if (url) {
                const sel = window.getSelection();
                if (savedRange) {
                    sel.removeAllRanges();
                    sel.addRange(savedRange);
                    formatDoc('createLink', url);
                }
            }
            closeLinkModal();
        };

        // --- KEYDOWN: ENTER & BACKSPACE ---
        const editorCanvasForKeydown = document.getElementById('editor-canvas');
        if (editorCanvasForKeydown) {
            editorCanvasForKeydown.addEventListener('keydown', function(e) {
                const selection = window.getSelection();
                if (!selection.rangeCount) return;
                const range = selection.getRangeAt(0);
                
                let node = range.startContainer;
                if (node.nodeType === 3) node = node.parentNode;
                const checkItem = node.closest('.checklist-item');
                const timelineItem = node.closest('.timeline-step');
                const activeItem = checkItem || timelineItem;

                if (e.key === 'Enter' && activeItem) {
                    const editableField = node.closest('.block-content-editable');
                    if (editableField) {
                        e.preventDefault();
                        
                        let textAfter = "";
                        if (range.startContainer.nodeType === 3) {
                            const textNode = range.startContainer;
                            const fullText = textNode.textContent;
                            const pos = range.startOffset;
                            textAfter = fullText.substring(pos);
                            textNode.textContent = fullText.substring(0, pos);
                        }

                        const temp = document.createElement('div');
                        if (checkItem) temp.innerHTML = createChecklistItemHTML(textAfter);
                        else temp.innerHTML = createTimelineStepHTML(99, textAfter, "Bước tiếp");
                        
                        const newItem = temp.firstElementChild;
                        activeItem.after(newItem);
                        
                        const input = newItem.querySelector('.block-content-editable');
                        if(input) {
                            input.focus();
                            const newRange = document.createRange();
                            newRange.setStart(input, 0); 
                            newRange.collapse(true);
                            selection.removeAllRanges();
                            selection.addRange(newRange);
                        }
                        return;
                    }
                }

                if (e.key === 'Backspace' && activeItem) {
                    const editableField = node.closest('.block-content-editable');
                    if (editableField && range.collapsed && range.startOffset === 0) {
                        const prevItem = activeItem.previousElementSibling;
                        if (prevItem && prevItem.className === activeItem.className) {
                            e.preventDefault();
                            const currentText = editableField.innerText;
                            const prevInput = prevItem.querySelector('.block-content-editable');
                            
                            const oldLen = prevInput.innerText.length;
                            prevInput.innerText += currentText;
                            activeItem.remove();
                            
                            const newRange = document.createRange();
                            if (prevInput.lastChild && prevInput.lastChild.nodeType === 3) {
                                newRange.setStart(prevInput.lastChild, oldLen);
                            } else {
                                newRange.setStart(prevInput, prevInput.childNodes.length);
                            }
                            newRange.collapse(true);
                            selection.removeAllRanges();
                            selection.addRange(newRange);
                            return;
                        }
                    }
                }
            });
        }

        // --- GLOBAL DELETE BLOCK FUNCTION ---
        window.deleteBlock = function(btn) {
            const wrapper = btn.closest('.block-wrapper');
            if (wrapper) wrapper.remove();
        };

        // --- BLOCK APPLIER ---
        window.applyBlock = function(type) {
            const canvas = document.getElementById('editor-canvas');
            let lines = [];
            let isSelectionReplaced = false;

            const sel = window.getSelection();
            if (sel.rangeCount > 0 && !sel.isCollapsed) {
                const range = sel.getRangeAt(0);
                const div = document.createElement('div');
                div.appendChild(range.cloneContents());
                
                let html = div.innerHTML;
                html = html.replace(/<\/p>/gi, '\n');
                html = html.replace(/<\/div>/gi, '\n');
                html = html.replace(/<br\s*\/?>/gi, '\n');
                html = html.replace(/<\/li>/gi, '\n');
                
                const tempText = document.createElement('div');
                tempText.innerHTML = html;
                lines = tempText.innerText.split('\n').map(l => l.trim()).filter(l => l);
                isSelectionReplaced = true;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'block-wrapper group/block';
            wrapper.contentEditable = false;
            wrapper.style.position = 'relative';
            wrapper.style.zIndex = '1';
            
            // Tạo block actions với event listeners đúng cách
            const blockActions = document.createElement('div');
            blockActions.className = 'block-actions';
            blockActions.style.pointerEvents = 'auto';
            blockActions.style.zIndex = '1000';
            
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'w-7 h-7 bg-white border border-gray-200 text-gray-500 rounded hover:text-red-500 hover:bg-red-50 flex items-center justify-center shadow-sm';
            deleteBtn.title = 'Xóa Block';
            deleteBtn.style.pointerEvents = 'auto';
            deleteBtn.style.cursor = 'pointer';
            deleteBtn.innerHTML = '<i class="fas fa-trash text-xs"></i>';
            deleteBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                deleteBlock(this);
            };
            
            const moveBtn = document.createElement('button');
            moveBtn.className = 'w-7 h-7 bg-white border border-gray-200 text-gray-500 rounded hover:text-blue-500 hover:bg-blue-50 cursor-move handle-block flex items-center justify-center shadow-sm';
            moveBtn.title = 'Di chuyển';
            moveBtn.style.pointerEvents = 'auto';
            moveBtn.style.cursor = 'move';
            moveBtn.innerHTML = '<i class="fas fa-grip-vertical text-xs"></i>';
            
            blockActions.appendChild(deleteBtn);
            blockActions.appendChild(moveBtn);
            wrapper.appendChild(blockActions);
            
            const content = document.createElement('div');
            wrapper.appendChild(content);

            switch(type) {
                case 'checklist':
                    let listHTML = `<div class="bg-white border border-gray-200 rounded-lg p-4"><ul class="space-y-2 checklist-container">`;
                    if (lines.length > 0) lines.forEach(l => listHTML += createChecklistItemHTML(l));
                    else listHTML += createChecklistItemHTML('Mục mới');
                    listHTML += `</ul><button class="aux-btn" onclick="addChecklistItem(this)"><i class="fas fa-plus"></i> Thêm mục</button></div>`;
                    content.innerHTML = listHTML;
                    break;
                case 'timeline':
                    let timeHTML = `<div class="timeline-container py-2">`;
                    if (lines.length > 0) lines.forEach((l, i) => timeHTML += createTimelineStepHTML(i+1, l, `Bước ${i+1}`));
                    else timeHTML += createTimelineStepHTML(1, 'Mô tả...', 'Bước 1');
                    timeHTML += `</div><button class="aux-btn" onclick="addTimelineStep(this)"><i class="fas fa-plus"></i> Thêm bước</button>`;
                    content.innerHTML = timeHTML;
                    break;
                case 'info':
                case 'warning':
                    const txt = lines.length ? lines.join('<br>') : 'Nội dung...';
                    const color = type==='info'?'sky':'red';
                    const icon = type==='info'?'info-circle':'exclamation-triangle';
                    const title = type==='info'?'Lưu ý':'Cảnh báo';
                    content.innerHTML = `<div class="callout-${type}"><i class="fas fa-${icon} text-${color}-500 text-xl mt-0.5"></i><div class="flex-1"><h4 class="font-bold text-${color}-800 mb-1 block-content-editable" contenteditable="true">${title}</h4><p class="text-${color}-700 text-sm block-content-editable" contenteditable="true">${txt}</p></div></div>`;
                    break;
                case 'tabs':
                    content.innerHTML = `<div class="border border-gray-200 rounded-lg p-4 bg-white shadow-sm"><div class="tab-header"><div class="tab-btn active block-content-editable" onclick="switchTab(this)" contenteditable="true">Tab 1</div><button class="w-6 h-6 rounded hover:bg-gray-200 text-gray-400 ml-1 text-xs" onclick="addTab(this)"><i class="fas fa-plus"></i></button></div><div class="tab-body"><div class="tab-content-pane active p-2 min-h-[60px] block-content-editable" contenteditable="true">Nội dung...</div></div></div>`;
                    break;
                case 'faq':
                    content.innerHTML = `<div class="faq-container space-y-2">${createFaqHTML('Câu hỏi?', 'Trả lời...')}</div><button class="aux-btn" onclick="addFaq(this)"><i class="fas fa-plus"></i> Thêm</button>`;
                    break;
                case 'table':
                    content.innerHTML = `<div class="overflow-x-auto my-4 border border-gray-200 rounded"><table class="custom-table w-full text-sm"><thead><tr><th class="block-content-editable" contenteditable="true">Cột 1</th><th class="block-content-editable" contenteditable="true">Cột 2</th></tr></thead><tbody><tr><td class="block-content-editable" contenteditable="true">...</td><td class="block-content-editable" contenteditable="true">...</td></tr></tbody></table></div>`;
                    break;
            }

            if (isSelectionReplaced) {
                sel.deleteFromDocument();
                const range = sel.getRangeAt(0);
                range.insertNode(wrapper);
            } else {
                if(savedRange) {
                    savedRange.insertNode(wrapper);
                } else {
                    canvas.appendChild(wrapper);
                }
            }

            const p = document.createElement('p');
            p.innerHTML = '<br>';
            wrapper.after(p);
            
            const newRange = document.createRange();
            newRange.setStart(p, 0);
            newRange.collapse(true);
            sel.removeAllRanges();
            sel.addRange(newRange);
        }

        // --- HTML GENERATORS ---
        function createChecklistItemHTML(text) { return `<li class="flex items-start gap-3 checklist-item"><i class="far fa-square text-gray-400 mt-1 cursor-pointer hover:text-blue-500" onclick="toggleCheck(this)"></i><div class="block-content-editable flex-1 outline-none border-b border-transparent focus:border-gray-100" contenteditable="true">${text}</div><button class="text-gray-300 hover:text-red-400" onclick="this.closest('li').remove()"><i class="fas fa-times"></i></button></li>`; }
        function createTimelineStepHTML(num, text, title) { return `<div class="timeline-step"><div class="timeline-dot"></div><h4 class="font-bold text-slate-800 text-sm uppercase mb-1 block-content-editable outline-none" contenteditable="true">${title}</h4><p class="text-sm text-slate-500 block-content-editable outline-none" contenteditable="true">${text}</p></div>`; }
        function createFaqHTML(q,a) { return `<details class="group border border-gray-200 rounded-lg open:bg-gray-50 bg-white"><summary class="font-medium cursor-pointer list-none flex justify-between items-center text-slate-700 p-4"><span class="block-content-editable" contenteditable="true">${q}</span><i class="fas fa-chevron-down text-xs transition group-open:rotate-180"></i></summary><div class="px-4 pb-4 text-sm text-slate-600 pt-2 border-t border-gray-100 block-content-editable outline-none" contenteditable="true">${a}</div></details>`; }

        // --- ACTIONS ---
        window.addChecklistItem = function(btn) { 
            if (btn && btn.previousElementSibling) {
                btn.previousElementSibling.insertAdjacentHTML('beforeend', createChecklistItemHTML('Mục mới')); 
            }
        };
        
        window.addTimelineStep = function(btn) { 
            if (btn && btn.previousElementSibling) {
                btn.previousElementSibling.insertAdjacentHTML('beforeend', createTimelineStepHTML(99, '...', 'Bước tiếp')); 
            }
        };
        
        window.addFaq = function(btn) { 
            if (btn && btn.previousElementSibling) {
                btn.previousElementSibling.insertAdjacentHTML('beforeend', createFaqHTML('Câu hỏi?', 'Trả lời...')); 
            }
        };
        
        window.toggleCheck = function(i) { 
            if (!i) return;
            i.className = i.classList.contains('fa-square') ? "fas fa-check-square text-blue-500 mt-1 cursor-pointer" : "far fa-square text-gray-400 mt-1 cursor-pointer hover:text-blue-500"; 
            if (i.nextElementSibling) {
                i.nextElementSibling.classList.toggle('line-through'); 
                i.nextElementSibling.classList.toggle('text-gray-400');
            }
        };
        
        window.addTab = function(btn) { 
            if (!btn) return;
            const header = btn.parentElement; 
            const body = header.nextElementSibling; 
            const newBtn = document.createElement('div'); 
            newBtn.className='tab-btn block-content-editable'; 
            newBtn.innerText='New Tab'; 
            newBtn.contentEditable=true; 
            newBtn.onclick=function(){switchTab(this)}; 
            header.insertBefore(newBtn, btn); 
            const newPane = document.createElement('div'); 
            newPane.className='tab-content-pane p-2 min-h-[60px] block-content-editable outline-none'; 
            newPane.contentEditable=true; 
            newPane.innerText='Content...'; 
            body.appendChild(newPane); 
            switchTab(newBtn); 
        };
        
        window.switchTab = function(btn) { 
            if (!btn) return;
            const container = btn.closest('.block-wrapper'); 
            if (!container) return;
            const btns = container.querySelectorAll('.tab-btn'); 
            const panes = container.querySelectorAll('.tab-content-pane'); 
            const idx = Array.from(btns).indexOf(btn); 
            btns.forEach(b=>b.classList.remove('active')); 
            btn.classList.add('active'); 
            panes.forEach(p=>p.classList.remove('active')); 
            if(panes[idx]) panes[idx].classList.add('active'); 
        };

        const mediaModal = document.getElementById('media-modal');
        window.openMediaModal = function() { 
            saveSelection(); 
            if (mediaModal) {
                mediaModal.classList.remove('opacity-0', 'pointer-events-none'); 
                document.body.classList.add('modal-active');
            }
        };
        
        window.closeMediaModal = function() { 
            if (mediaModal) {
                mediaModal.classList.add('opacity-0', 'pointer-events-none'); 
                document.body.classList.remove('modal-active');
            }
        };
        
        window.insertImage = function(url) {
            const wrapper = document.createElement('div'); 
            wrapper.className='block-wrapper group/block my-6 text-center'; 
            wrapper.contentEditable=false;
            wrapper.style.position = 'relative';
            wrapper.style.zIndex = '1';
            
            // Tạo block actions với event listeners đúng cách
            const blockActions = document.createElement('div');
            blockActions.className = 'block-actions';
            blockActions.style.pointerEvents = 'auto';
            blockActions.style.zIndex = '1000';
            
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'w-7 h-7 bg-white border border-gray-200 text-gray-500 rounded hover:text-red-500 hover:bg-red-50 flex items-center justify-center shadow-sm';
            deleteBtn.style.pointerEvents = 'auto';
            deleteBtn.style.cursor = 'pointer';
            deleteBtn.innerHTML = '<i class="fas fa-trash text-xs"></i>';
            deleteBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                deleteBlock(this);
            };
            
            const moveBtn = document.createElement('button');
            moveBtn.className = 'w-7 h-7 bg-white border border-gray-200 text-gray-500 rounded hover:text-blue-500 hover:bg-blue-50 cursor-move handle-block flex items-center justify-center shadow-sm';
            moveBtn.style.pointerEvents = 'auto';
            moveBtn.style.cursor = 'move';
            moveBtn.innerHTML = '<i class="fas fa-grip-vertical text-xs"></i>';
            
            blockActions.appendChild(deleteBtn);
            blockActions.appendChild(moveBtn);
            wrapper.appendChild(blockActions);
            
            const figure = document.createElement('figure');
            figure.className = 'inline-block relative';
            const img = document.createElement('img');
            img.src = url;
            img.className = 'max-w-full h-auto rounded shadow-sm border';
            const figcaption = document.createElement('figcaption');
            figcaption.className = 'text-sm text-gray-500 mt-2 italic block-content-editable outline-none';
            figcaption.contentEditable = 'true';
            figcaption.textContent = 'Caption...';
            figure.appendChild(img);
            figure.appendChild(figcaption);
            wrapper.appendChild(figure);
            
            const canvas = document.getElementById('editor-canvas'); 
            if (canvas) {
                if(savedRange) {
                    savedRange.insertNode(wrapper);
                } else {
                    canvas.appendChild(wrapper);
                }
                const p = document.createElement('p'); 
                p.innerHTML='<br>'; 
                wrapper.after(p);
            }
            closeMediaModal();
        };
        
        const editorCanvas = document.getElementById('editor-canvas');
        if (editorCanvas) {
            new Sortable(editorCanvas, { animation: 150, handle: '.handle-block', draggable: '.block-wrapper', ghostClass: 'opacity-50' });
        }
        
        // Thumbnail functions
        window.openThumbnailModal = function() {
            const thumbnailModal = document.getElementById('thumbnail-modal');
            if (thumbnailModal) {
                thumbnailModal.classList.remove('opacity-0', 'pointer-events-none');
                document.body.classList.add('modal-active');
            }
        };
        
        window.closeThumbnailModal = function() {
            const thumbnailModal = document.getElementById('thumbnail-modal');
            if (thumbnailModal) {
                thumbnailModal.classList.add('opacity-0', 'pointer-events-none');
                document.body.classList.remove('modal-active');
            }
        };
        
        window.openWordPressMediaLibrary = function() {
            // Sử dụng WordPress Media Library
            if (typeof wp !== 'undefined' && wp.media) {
                const frame = wp.media({
                    title: 'Chọn ảnh đại diện',
                    button: {
                        text: 'Chọn ảnh'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    setThumbnail(attachment.id, attachment.url);
                    closeThumbnailModal();
                });
                
                frame.open();
            } else {
                alert('WordPress Media Library chưa sẵn sàng. Vui lòng tải lại trang.');
            }
        };
        
        window.setThumbnail = function(attachmentId, attachmentUrl) {
            const preview = document.getElementById('ebook-thumbnail-preview');
            const input = document.getElementById('ebook-thumbnail-id');
            
            if (preview && input) {
                preview.innerHTML = `<img src="${attachmentUrl}" alt="Thumbnail" class="w-full h-full object-cover rounded">`;
                input.value = attachmentId;
                
                // Thêm nút xóa
                const removeBtn = document.createElement('button');
                removeBtn.className = 'mt-2 w-full text-xs text-red-600 hover:text-red-800 py-1';
                removeBtn.innerHTML = '<i class="fas fa-trash"></i> Xóa ảnh';
                removeBtn.onclick = removeThumbnail;
                
                // Xóa nút xóa cũ nếu có
                const oldBtn = preview.parentElement.querySelector('button');
                if (oldBtn) oldBtn.remove();
                
                preview.parentElement.appendChild(removeBtn);
            }
        };
        
        window.removeThumbnail = function() {
            const preview = document.getElementById('ebook-thumbnail-preview');
            const input = document.getElementById('ebook-thumbnail-id');
            
            if (preview && input) {
                preview.innerHTML = `
                    <div class="text-center text-gray-400">
                        <i class="fas fa-image text-3xl mb-2"></i>
                        <p class="text-xs">Click để chọn ảnh</p>
                    </div>
                `;
                input.value = '0';
                
                // Xóa nút xóa nếu có
                const parent = preview.parentElement;
                if (parent) {
                    const removeBtn = parent.querySelector('button');
                    if (removeBtn && removeBtn.onclick === removeThumbnail) {
                        removeBtn.remove();
                    }
                }
            }
        };
        
        // Cập nhật tiêu đề header khi người dùng chỉnh sửa tiêu đề ebook
        const pageTitleEl = document.getElementById('page-title');
        const headerTitleEl = document.getElementById('header-title');
        if (pageTitleEl && headerTitleEl) {
            // Cập nhật khi người dùng nhập
            pageTitleEl.addEventListener('input', function() {
                const newTitle = this.innerText.trim();
                if (newTitle) {
                    headerTitleEl.innerText = newTitle;
                    // Cập nhật title của trang
                    document.title = newTitle + ' - Ebook Editor';
                    
                    // Cập nhật trong pageContents
                    if (currentPageId) {
                        if (!pageContents[currentPageId]) {
                            pageContents[currentPageId] = { title: newTitle, content: '', post_id: null };
                        } else {
                            pageContents[currentPageId].title = newTitle;
                        }
                    }
                } else {
                    headerTitleEl.innerText = '<?php echo $is_edit ? 'Chỉnh sửa Ebook' : 'Thêm Ebook mới'; ?>';
                    document.title = '<?php echo $is_edit ? 'Chỉnh sửa Ebook' : 'Thêm Ebook mới'; ?> - Ebook Editor';
                }
            });
            
            // Cập nhật khi người dùng blur (rời khỏi field)
            pageTitleEl.addEventListener('blur', function() {
                const newTitle = this.innerText.trim();
                if (newTitle && headerTitleEl) {
                    headerTitleEl.innerText = newTitle;
                    document.title = newTitle + ' - Ebook Editor';
                    
                    // Cập nhật trong pageContents và sidebar
                    if (currentPageId) {
                        if (!pageContents[currentPageId]) {
                            pageContents[currentPageId] = { title: newTitle, content: '', post_id: null };
                        } else {
                            pageContents[currentPageId].title = newTitle;
                        }
                        
                        // Cập nhật title trong sidebar
                        if (currentPageType === 'main') {
                            const mainItem = document.getElementById('main-ebook-item');
                            if (mainItem) {
                                const titleSpan = mainItem.querySelector('.title-editable');
                                if (titleSpan) titleSpan.innerText = newTitle;
                            }
                        } else {
                            const pageItem = document.querySelector(`[data-id="${currentPageId}"]`);
                            if (pageItem) {
                                const titleSpan = pageItem.querySelector('.title-editable');
                                if (titleSpan) titleSpan.innerText = newTitle;
                            }
                        }
                    }
                }
            });
        }
        
        // Lưu nội dung khi editor thay đổi
        const editorCanvasEl = document.getElementById('editor-canvas');
        if (editorCanvasEl) {
            // Lưu khi input (gõ phím)
            editorCanvasEl.addEventListener('input', function() {
                if (currentPageId) {
                    const titleEl = document.getElementById('page-title');
                    if (!pageContents[currentPageId]) {
                        pageContents[currentPageId] = { 
                            title: titleEl ? titleEl.innerText.trim() : '', 
                            content: '', 
                            post_id: null 
                        };
                    }
                    pageContents[currentPageId].content = this.innerHTML;
                    console.log('Đã lưu nội dung cho page:', currentPageId, 'Content length:', this.innerHTML.length);
                }
            });
            
            // Lưu khi blur (rời khỏi editor)
            editorCanvasEl.addEventListener('blur', function() {
                if (currentPageId) {
                    const titleEl = document.getElementById('page-title');
                    if (!pageContents[currentPageId]) {
                        pageContents[currentPageId] = { 
                            title: titleEl ? titleEl.innerText.trim() : '', 
                            content: '', 
                            post_id: null 
                        };
                    }
                    pageContents[currentPageId].content = this.innerHTML;
                    pageContents[currentPageId].title = titleEl ? titleEl.innerText.trim() : pageContents[currentPageId].title;
                }
            });
        }
        
        // Khởi tạo: Chọn ebook chính khi trang load
        currentPageId = 'main';
        currentPageType = 'main';
        const mainItem = document.getElementById('main-ebook-item');
        if (mainItem) {
            mainItem.classList.add('active', 'bg-blue-50', 'border', 'border-blue-200');
        }

        }); // End DOMContentLoaded
        
        // --- SAVE EBOOK FUNCTION (phải khai báo global, ngoài DOMContentLoaded) ---
        window.saveEbook = function() {
            console.log('saveEbook được gọi');
            
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                loadingOverlay.classList.add('active');
            }

            const titleEl = document.getElementById('page-title');
            const contentEl = document.getElementById('editor-canvas');
            
            if (!titleEl) {
                alert('Không tìm thấy tiêu đề!');
                if (loadingOverlay) loadingOverlay.classList.remove('active');
                return;
            }
            
            if (!contentEl) {
                alert('Không tìm thấy nội dung editor!');
                if (loadingOverlay) loadingOverlay.classList.remove('active');
                return;
            }

            // Lưu nội dung page hiện tại trước khi lưu
            if (currentPageId) {
                const title = titleEl.innerText.trim();
                const content = contentEl.innerHTML;
                if (!pageContents[currentPageId]) {
                    pageContents[currentPageId] = {
                        title: title,
                        content: content,
                        post_id: null
                    };
                } else {
                    pageContents[currentPageId].title = title;
                    pageContents[currentPageId].content = content;
                    // Giữ nguyên post_id nếu có
                    if (pageContents[currentPageId].post_id === undefined) {
                        pageContents[currentPageId].post_id = null;
                    }
                }
                console.log('Đã lưu nội dung page hiện tại:', currentPageId, {
                    title: title.substring(0, 50),
                    contentLength: content.length
                });
            }
            
            // Lấy nội dung ebook chính
            const mainContent = pageContents['main'] || { title: '', content: '' };
            const title = mainContent.title.trim();
            const content = mainContent.content;
            const status = document.getElementById('ebook-status') ? document.getElementById('ebook-status').value : 'draft';
            const thumbnailId = document.getElementById('ebook-thumbnail-id') ? document.getElementById('ebook-thumbnail-id').value : '';
            
            if (!title) {
                alert('Vui lòng nhập tiêu đề Ebook!');
                if (loadingOverlay) loadingOverlay.classList.remove('active');
                return;
            }
            
            // Lấy cấu trúc chapters từ DOM và kết hợp với nội dung đã lưu
            function extractChapters(ul) {
                if (!ul) return [];
                const items = [];
                Array.from(ul.children).forEach(li => {
                    const itemDiv = li.querySelector('.chapter-item');
                    if (itemDiv) {
                        const titleEl = itemDiv.querySelector('.title-editable');
                        if (titleEl) {
                            const id = itemDiv.getAttribute('data-id') || 'page_' + Date.now();
                            
                            // Lấy nội dung từ pageContents (ưu tiên) hoặc từ DOM
                            let pageContent = pageContents[id];
                            if (!pageContent) {
                                // Nếu chưa có trong pageContents, tạo mới
                                const title = titleEl.innerText.trim();
                                pageContent = {
                                    title: title,
                                    content: '',
                                    post_id: null
                                };
                                pageContents[id] = pageContent;
                            } else {
                                // Đảm bảo title được cập nhật từ DOM
                                const domTitle = titleEl.innerText.trim();
                                if (domTitle && domTitle !== pageContent.title) {
                                    pageContent.title = domTitle;
                                }
                            }
                            
                            const childrenUl = li.querySelector('ul');
                            let children = [];
                            if (childrenUl && childrenUl.children.length > 0) {
                                children = extractChapters(childrenUl);
                            }
                            
                            items.push({ 
                                id: id, 
                                title: pageContent.title || titleEl.innerText.trim(), 
                                content: pageContent.content || '',
                                post_id: pageContent.post_id || null,
                                children: children 
                            });
                        }
                    }
                });
                return items;
            }
            
            // Lấy rootEl nếu chưa có
            if (!rootEl) {
                rootEl = document.getElementById('chapter-root');
            }
            
            const structure = rootEl ? extractChapters(rootEl) : [];
            
            // Debug: Log structure để kiểm tra
            console.log('Structure sẽ được gửi:', JSON.stringify(structure, null, 2));
            console.log('pageContents hiện tại:', Object.keys(pageContents).map(key => ({
                id: key,
                title: pageContents[key].title?.substring(0, 30),
                contentLength: pageContents[key].content?.length || 0,
                hasPostId: !!pageContents[key].post_id
            })));

            const formData = new FormData();
            formData.append('action', 'ebook_save');
            formData.append('nonce', ebookEditor.nonce);
            formData.append('ebook_id', ebookEditor.ebookId);
            formData.append('title', title);
            formData.append('content', content);
            formData.append('status', status);
            formData.append('thumbnail_id', thumbnailId);
            formData.append('structure', JSON.stringify(structure));

            console.log('Gửi request:', {
                action: 'ebook_save',
                ebook_id: ebookEditor.ebookId,
                title: title.substring(0, 50) + '...',
                ajaxUrl: ebookEditor.ajaxUrl
            });

            fetch(ebookEditor.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('active');
                }
                if (data.success) {
                    const wasNewEbook = !ebookEditor.isEdit;
                    
                    // Cập nhật nonce và ebook_id sau khi lưu thành công
                    if (data.data && data.data.ebook_id) {
                        ebookEditor.ebookId = data.data.ebook_id;
                        ebookEditor.isEdit = true;
                    }
                    
                    console.log('Ebook đã được lưu:', {
                        ebook_id: data.data.ebook_id,
                        title: data.data.post_title,
                        status: data.data.post_status,
                        parent: data.data.post_parent
                    });
                    
                    alert('Đã lưu Ebook thành công! ID: ' + data.data.ebook_id);
                    
                    // Redirect về trang quản lý nếu là ebook mới để xem ngay
                    if (wasNewEbook && data.data && data.data.ebook_id) {
                        setTimeout(() => {
                            window.location.href = '<?php echo esc_js( admin_url( 'admin.php?page=ebook-manager' ) ); ?>';
                        }, 1000);
                    }
                } else {
                    const errorMsg = data.data && data.data.message ? data.data.message : (typeof data.data === 'string' ? data.data : 'Không thể lưu Ebook');
                    console.error('Lỗi từ server:', data);
                    alert('Lỗi: ' + errorMsg);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('active');
                }
                alert('Đã xảy ra lỗi khi lưu Ebook: ' + error.message);
            });
        };
    </script>
<?php wp_footer(); ?>
</body>
</html>
