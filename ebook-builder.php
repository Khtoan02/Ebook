<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate Ebook Builder V11 (Fix Link Modal)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; overflow: hidden; }
        
        /* Editor Canvas */
        #editor-canvas { outline: none; min-height: 80vh; padding-bottom: 300px; cursor: text; }
        p:empty:not(:focus)::before { content: "Nhập nội dung..."; color: #cbd5e1; pointer-events: none; float: left; height: 0; }
        
        /* Danh sách & Link trong Editor */
        #editor-canvas ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
        #editor-canvas ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 1rem; }
        #editor-canvas li { margin-bottom: 0.25rem; }
        #editor-canvas a { color: #2563eb; text-decoration: underline; cursor: pointer; }

        /* Block Wrapper */
        .block-wrapper { position: relative; margin: 2.5rem 0 1.5rem 0; border: 1px solid transparent; border-radius: 8px; transition: all 0.2s; user-select: none; }
        .block-wrapper:hover { border-color: #e2e8f0; background-color: rgba(255,255,255,0.5); }
        
        /* Block Actions */
        .block-actions { 
            position: absolute; top: -30px; right: 0; 
            display: none; flex-direction: row; gap: 6px; z-index: 50; 
            background: white; padding: 4px; border-radius: 4px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .block-wrapper:hover .block-actions { display: flex; }
        
        /* Nội dung bên trong Block */
        .block-content-editable { outline: none; cursor: text; user-select: text; }
        
        /* Custom Block Styles */
        .callout-info { background: #f0f9ff; border-left: 4px solid #0ea5e9; border-radius: 4px; padding: 16px; display: flex; gap: 12px; }
        .callout-warning { background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 4px; padding: 16px; display: flex; gap: 12px; }
        
        .timeline-step { position: relative; padding-left: 24px; border-left: 2px solid #e2e8f0; margin-left: 8px; padding-bottom: 24px; }
        .timeline-step:last-of-type { border-left-color: transparent; padding-bottom: 0; }
        .timeline-dot { position: absolute; left: -7px; top: 0; width: 12px; height: 12px; border-radius: 50%; background: #fff; border: 2px solid #0ea5e9; box-shadow: 0 0 0 2px white; }
        
        .tab-header { display: flex; flex-wrap: wrap; gap: 4px; background: #f1f5f9; padding: 4px; border-radius: 6px; margin-bottom: 12px; align-items: center; }
        .tab-btn { padding: 6px 12px; font-size: 13px; font-weight: 500; border-radius: 4px; cursor: pointer; border: 1px solid transparent; user-select: none; }
        .tab-btn.active { background: #fff; border-color: #e2e8f0; color: #0284c7; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .tab-content-pane { display: none; }
        .tab-content-pane.active { display: block; }

        .aux-btn { font-size: 12px; color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; padding: 4px 8px; border-radius: 4px; cursor: pointer; margin-top: 8px; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px; }
        .aux-btn:hover { background: #f1f5f9; color: #0284c7; border-color: #0284c7; }
        
        .chapter-item { transition: all 0.2s; border: 1px solid transparent; }
        .chapter-item:hover { background-color: #f1f5f9; }
        .chapter-item.active { background-color: #e0f2fe; color: #0284c7; font-weight: 600; }
        
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: visible !important; }
        
        /* Toolbar Button Style */
        .toolbar-btn { padding: 0.5rem; border-radius: 0.25rem; color: #4b5563; transition: background-color 0.2s; display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; }
        .toolbar-btn:hover { background-color: white; color: #1f2937; }
        .toolbar-group { display: flex; align-items: center; gap: 2px; padding-right: 0.5rem; margin-right: 0.5rem; border-right: 1px solid #d1d5db; }
        .toolbar-group:last-child { border-right: none; }
    </style>
</head>
<body class="flex flex-col h-screen text-slate-700">

    <!-- Header -->
    <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-4 z-20 flex-shrink-0 shadow-sm sticky top-0">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white font-bold"><i class="fas fa-book"></i></div>
            <h1 class="font-bold text-lg hidden md:block">Ebook Creator</h1>
        </div>

        <!-- MAIN TOOLBAR -->
        <div class="flex items-center bg-gray-100 p-1 rounded-lg overflow-x-auto max-w-[60vw]">
            
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

        <div class="flex items-center gap-3">
            <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">Xuất bản</button>
        </div>
    </header>

    <!-- Main Layout -->
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar Tree -->
        <aside class="w-72 bg-white border-r border-gray-200 flex flex-col flex-shrink-0 z-10 hidden md:flex">
            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Cấu trúc</span>
                <button onclick="addPageRoot()" class="text-blue-600 hover:text-blue-800 text-xs font-bold px-2 py-1 hover:bg-blue-50 rounded"><i class="fas fa-plus"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto p-2" id="chapter-list-container">
                <ul id="chapter-root" class="space-y-1 pb-10"></ul>
            </div>
        </aside>

        <!-- Editor -->
        <main class="flex-1 overflow-y-auto bg-gray-100 p-4 md:p-8 flex justify-center relative" id="main-scroll">
            <div class="bg-white w-full max-w-4xl min-h-[90vh] shadow-lg rounded-lg p-8 md:p-12 relative" id="editor-paper">
                <h1 id="page-title" contenteditable="true" class="text-3xl md:text-4xl font-extrabold text-slate-800 mb-6 border-none outline-none placeholder:text-gray-300" placeholder="Tiêu đề trang...">Giới thiệu Ebook</h1>
                
                <div id="editor-canvas" contenteditable="true" class="text-base leading-relaxed text-slate-600" onmouseup="saveSelection()" onkeyup="saveSelection()">
                    <p class="mb-4">Chào mừng! Bạn có thể soạn thảo văn bản bình thường tại đây.</p>
                    <p class="mb-4">Thử bôi đen dòng chữ này và bấm nút Link trên thanh công cụ.</p>
                    <p class="mb-4">Sau đó nhập URL vào cửa sổ hiện ra.</p>
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

    <script>
        // --- 1. TREE STRUCTURE (Mock) ---
        let chapters = [ { id: '1', title: 'Chương 1: Tổng quan', children: [] } ];
        const rootEl = document.getElementById('chapter-root');
        
        function renderTree(items, container) {
            container.innerHTML = '';
            items.forEach(item => {
                const li = document.createElement('li');
                li.innerHTML = `
                    <div class="chapter-item flex justify-between items-center py-1.5 px-3 rounded cursor-pointer mb-1 ${item.id==='1'?'active':''}" onclick="selectPage(this)">
                        <div class="flex items-center gap-2 truncate"><i class="fas fa-grip-vertical text-gray-300 handle text-xs"></i><span class="truncate text-sm font-medium title-editable" contenteditable="false">${item.title}</span></div>
                        <div class="flex gap-1"><button class="w-5 h-5 text-gray-400 hover:text-blue-600 text-xs" onclick="addChildPage(event,this)"><i class="fas fa-plus"></i></button><button class="w-5 h-5 text-gray-400 hover:text-red-600 text-xs" onclick="deletePage(event,this)"><i class="fas fa-trash"></i></button></div>
                    </div><ul class="pl-4 border-l border-gray-200 ml-3 space-y-1 nested-sortable min-h-[2px]"></ul>`;
                if(item.children.length>0) renderTree(item.children, li.querySelector('ul'));
                new Sortable(li.querySelector('ul'), { group: 'nested', animation: 150, fallbackOnBody: true });
                container.appendChild(li);
            });
        }
        new Sortable(rootEl, { group: 'nested', animation: 150, handle: '.handle' });
        renderTree(chapters, rootEl);
        function selectPage(el){ document.querySelectorAll('.chapter-item').forEach(i=>i.classList.remove('active')); el.classList.add('active'); document.getElementById('page-title').innerText=el.querySelector('.title-editable').innerText; }
        function addChildPage(e,btn){ e.stopPropagation(); const ul=btn.closest('li').querySelector('ul'); const li=document.createElement('li'); li.innerHTML=`<div class="chapter-item flex justify-between items-center py-1.5 px-3 rounded cursor-pointer mb-1"><div class="flex items-center gap-2"><i class="fas fa-file text-gray-300 text-xs"></i><span class="text-sm">Trang mới</span></div></div>`; ul.appendChild(li); }
        function deletePage(e,btn){ e.stopPropagation(); if(confirm('Xóa?')) btn.closest('li').remove(); }

        // --- 2. EDITOR LOGIC ---
        let savedRange = null;
        function saveSelection() {
            const sel = window.getSelection();
            if (sel.rangeCount > 0) savedRange = sel.getRangeAt(0);
        }
        function formatDoc(cmd, value = null) { 
            document.execCommand(cmd, false, value); 
            document.getElementById('editor-canvas').focus(); 
        }

        // --- LINK MODAL LOGIC ---
        const linkModal = document.getElementById('link-modal');
        const linkInput = document.getElementById('link-url-input');

        function openLinkModal() {
            saveSelection(); // Lưu vùng chọn trước khi mở modal
            if(!savedRange) { alert('Vui lòng bôi đen văn bản cần chèn link'); return; }
            linkModal.classList.remove('opacity-0', 'pointer-events-none');
            document.body.classList.add('modal-active');
            linkInput.value = '';
            setTimeout(() => linkInput.focus(), 100);
        }

        function closeLinkModal() {
            linkModal.classList.add('opacity-0', 'pointer-events-none');
            document.body.classList.remove('modal-active');
        }

        function insertLinkFromModal() {
            const url = linkInput.value.trim();
            if (url) {
                // Restore selection
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedRange);
                // Exec command
                formatDoc('createLink', url);
            }
            closeLinkModal();
        }

        // --- KEYDOWN: ENTER & BACKSPACE ---
        document.getElementById('editor-canvas').addEventListener('keydown', function(e) {
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

        // --- GLOBAL DELETE BLOCK FUNCTION ---
        window.deleteBlock = function(btn) {
            const wrapper = btn.closest('.block-wrapper');
            if (wrapper) wrapper.remove();
        }

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
            wrapper.innerHTML = `<div class="block-actions"><button class="w-7 h-7 bg-white border border-gray-200 text-gray-500 rounded hover:text-red-500 hover:bg-red-50 flex items-center justify-center shadow-sm" onclick="deleteBlock(this)" title="Xóa Block"><i class="fas fa-trash text-xs"></i></button><button class="w-7 h-7 bg-white border border-gray-200 text-gray-500 rounded hover:text-blue-500 hover:bg-blue-50 cursor-move handle-block flex items-center justify-center shadow-sm" title="Di chuyển"><i class="fas fa-grip-vertical text-xs"></i></button></div>`;
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
        function addChecklistItem(btn) { btn.previousElementSibling.insertAdjacentHTML('beforeend', createChecklistItemHTML('Mục mới')); }
        function addTimelineStep(btn) { btn.previousElementSibling.insertAdjacentHTML('beforeend', createTimelineStepHTML(99, '...', 'Bước tiếp')); }
        function addFaq(btn) { btn.previousElementSibling.insertAdjacentHTML('beforeend', createFaqHTML('Câu hỏi?', 'Trả lời...')); }
        function toggleCheck(i) { i.className = i.classList.contains('fa-square') ? "fas fa-check-square text-blue-500 mt-1 cursor-pointer" : "far fa-square text-gray-400 mt-1 cursor-pointer hover:text-blue-500"; i.nextElementSibling.classList.toggle('line-through'); i.nextElementSibling.classList.toggle('text-gray-400'); }
        function addTab(btn) { const header = btn.parentElement; const body = header.nextElementSibling; const newBtn = document.createElement('div'); newBtn.className='tab-btn block-content-editable'; newBtn.innerText='New Tab'; newBtn.contentEditable=true; newBtn.onclick=function(){switchTab(this)}; header.insertBefore(newBtn, btn); const newPane = document.createElement('div'); newPane.className='tab-content-pane p-2 min-h-[60px] block-content-editable outline-none'; newPane.contentEditable=true; newPane.innerText='Content...'; body.appendChild(newPane); switchTab(newBtn); }
        function switchTab(btn) { const container = btn.closest('.block-wrapper'); const btns = container.querySelectorAll('.tab-btn'); const panes = container.querySelectorAll('.tab-content-pane'); const idx = Array.from(btns).indexOf(btn); btns.forEach(b=>b.classList.remove('active')); btn.classList.add('active'); panes.forEach(p=>p.classList.remove('active')); if(panes[idx]) panes[idx].classList.add('active'); }

        const mediaModal = document.getElementById('media-modal');
        function openMediaModal() { saveSelection(); mediaModal.classList.remove('opacity-0', 'pointer-events-none'); document.body.classList.add('modal-active'); }
        function closeMediaModal() { mediaModal.classList.add('opacity-0', 'pointer-events-none'); document.body.classList.remove('modal-active'); }
        function insertImage(url) {
            const wrapper = document.createElement('div'); wrapper.className='block-wrapper group/block my-6 text-center'; wrapper.contentEditable=false;
            wrapper.innerHTML = `<div class="block-actions"><button class="w-7 h-7 bg-white border border-gray-200 text-gray-500 rounded hover:text-red-500 hover:bg-red-50 flex items-center justify-center shadow-sm" onclick="deleteBlock(this)"><i class="fas fa-trash text-xs"></i></button><button class="w-7 h-7 bg-white border border-gray-200 text-gray-500 rounded hover:text-blue-500 hover:bg-blue-50 cursor-move handle-block flex items-center justify-center shadow-sm"><i class="fas fa-grip-vertical text-xs"></i></button></div><figure class="inline-block relative"><img src="${url}" class="max-w-full h-auto rounded shadow-sm border"><figcaption class="text-sm text-gray-500 mt-2 italic block-content-editable outline-none" contenteditable="true">Caption...</figcaption></figure>`;
            const canvas = document.getElementById('editor-canvas'); if(savedRange) savedRange.insertNode(wrapper); else canvas.appendChild(wrapper); const p = document.createElement('p'); p.innerHTML='<br>'; wrapper.after(p); closeMediaModal();
        }
        new Sortable(document.getElementById('editor-canvas'), { animation: 150, handle: '.handle-block', draggable: '.block-wrapper', ghostClass: 'opacity-50' });
    </script>
</body>
</html>