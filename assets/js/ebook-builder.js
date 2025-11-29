(function ($) {
    const dataset = window.ebookBuilderData || {};
    dataset.roots = dataset.roots || [];

    const state = {
        rootId: dataset.currentRoot || null,
        tree: null,
        selectedId: null,
        currentLink: null,
        descendants: [],
        pendingFocus: dataset.focusPost || null,
        isSorting: false,
        pendingOrder: null,
    };

    const selectors = {
        rootSelect: $('#ebook-builder-root'),
        tree: $('#ebook-builder-tree'),
        form: $('#ebook-builder-form'),
        title: $('#ebook-builder-title'),
        status: $('#ebook-builder-status'),
        parent: $('#ebook-builder-parent'),
        excerpt: $('#ebook-builder-excerpt'),
        metaStatus: $('#ebook-builder-meta-status'),
        metaVersion: $('#ebook-builder-meta-version'),
        metaDownload: $('#ebook-builder-meta-download'),
        rootInput: $('#ebook-builder-root-id'),
        postInput: $('#ebook-builder-post-id'),
        notices: $('#ebook-builder-notices'),
        selection: $('#ebook-builder-selection'),
        addSiblingBtn: $('#ebook-builder-add-sibling'),
        addChildBtn: $('#ebook-builder-add-child'),
        deleteBtn: $('#ebook-builder-delete'),
        newTitle: $('#ebook-builder-new-title'),
        newParent: $('#ebook-builder-new-parent'),
        newStatus: $('#ebook-builder-new-status'),
        createPageBtn: $('#ebook-builder-create-page'),
        createRootBtn: $('#ebook-builder-create-root'),
        refreshBtn: $('#ebook-builder-refresh'),
        previewBtn: $('#ebook-builder-preview'),
        saveOrderBtn: $('#ebook-builder-save-order'),
        reorderHint: $('#ebook-builder-reorder-hint'),
    };

    function init() {
        renderRootOptions(state.rootId);
        bindEvents();

        if (state.rootId) {
            selectors.rootSelect.val(state.rootId);
            loadTree(state.rootId);
        } else if (dataset.roots.length) {
            selectors.rootSelect.val(dataset.roots[0].id);
            loadTree(dataset.roots[0].id);
        }
    }

    function renderRootOptions(selectedId) {
        selectors.rootSelect.empty().append(
            $('<option value=""></option>').text('— Chọn ebook —')
        );
        dataset.roots.forEach((root) => {
            selectors.rootSelect.append(
                $('<option></option>')
                    .val(root.id)
                    .text(root.title || `Ebook #${root.id}`)
            );
        });

        if (selectedId) {
            selectors.rootSelect.val(selectedId);
        }
    }

    function bindEvents() {
        selectors.rootSelect.on('change', function () {
            const value = $(this).val();
            if (!value) {
                clearTree();
                return;
            }
            loadTree(value);
        });

        selectors.refreshBtn.on('click', function () {
            if (state.rootId) {
                loadTree(state.rootId);
            }
        });

        selectors.createRootBtn.on('click', function () {
            const title = (window.prompt(dataset.strings.newRootPrompt) || '').trim();
            if (!title.length) {
                return;
            }
            apiRequest('ebook_builder_create_root', {
                post_title: title,
                post_status: 'draft',
            })
                .then((res) => {
                    dataset.roots.push({ id: res.post_id, title, status: 'draft' });
                    renderRootOptions(res.post_id);
                    loadTree(res.post_id);
                    showNotice(res.message, 'success');
                })
                .fail(showError);
        });

        selectors.createPageBtn.on('click', createPageFromForm);

        selectors.addSiblingBtn.on('click', function () {
            if (!state.selectedId) {
                return alert(dataset.strings.unsaved);
            }
            const parentId = parseInt(selectors.parent.val() || state.rootId, 10);
            promptCreate(parentId);
        });

        selectors.addChildBtn.on('click', function () {
            if (!state.selectedId) {
                return alert(dataset.strings.unsaved);
            }
            promptCreate(state.selectedId);
        });

        selectors.deleteBtn.on('click', function () {
            if (!state.selectedId) {
                return;
            }
            if (state.selectedId === state.rootId) {
                return alert(dataset.strings.cannotDeleteRoot);
            }
            if (confirm(dataset.strings.confirmDelete)) {
                deleteNode(state.selectedId);
            }
        });

        selectors.previewBtn.on('click', function () {
            if (state.currentLink) {
                window.open(state.currentLink, '_blank');
            }
        });

        selectors.form.on('submit', function (e) {
            e.preventDefault();
            if (!state.selectedId) {
                return;
            }
            saveNode();
        });

        selectors.saveOrderBtn.on('click', () => {
            if (!state.pendingOrder || !state.rootId) {
                return;
            }
            apiRequest('ebook_builder_reorder', {
                root_id: state.rootId,
                order: JSON.stringify(state.pendingOrder),
            })
                .then((res) => {
                    showNotice(res.message, 'success');
                    state.pendingOrder = null;
                    setReorderDirty(false);
                    loadTree(state.rootId);
                })
                .fail(showError);
        });
    }

    function apiRequest(action, payload = {}) {
        payload.action = action;
        payload.nonce = dataset.nonce;

        return $.post({
            url: dataset.ajaxUrl,
            data: payload,
        }).then((response) => {
            if (!response || !response.success) {
                const message = (response && response.data && response.data.message) || 'Có lỗi xảy ra.';
                return $.Deferred().reject(message);
            }
            return response.data;
        });
    }

    function loadTree(rootId) {
        state.rootId = parseInt(rootId, 10);
        state.selectedId = null;
        state.tree = null;
        state.descendants = [];
        state.pendingOrder = null;
        selectors.rootInput.val(state.rootId);
        selectors.postInput.val('');
        selectors.tree.addClass('is-loading').html('<p>Đang tải cấu trúc...</p>');
        resetSelectionInfo();
        setQuickActionsEnabled(false);
        setCreateFormEnabled(false);
        setReorderDirty(false);

        const request = apiRequest('ebook_builder_get_tree', { root_id: state.rootId });

        request
            .then((data) => {
                state.tree = data.tree;
                renderTree();
                updateParentOptions();
                setCreateFormEnabled(true);

                let targetId = state.tree.id;
                if (state.pendingFocus && findNode(state.tree, state.pendingFocus)) {
                    targetId = state.pendingFocus;
                    state.pendingFocus = null;
                }
                selectNode(targetId);
                initDragAndDrop();
            })
            .fail(showError)
            .always(() => selectors.tree.removeClass('is-loading'));

        return request;
    }

    function clearTree() {
        state.tree = null;
        state.selectedId = null;
        state.descendants = [];
        selectors.tree.html(`<p class="placeholder">${dataset.strings.selectRoot}</p>`);
        resetForm();
        resetSelectionInfo();
        setQuickActionsEnabled(false);
        setCreateFormEnabled(false);
        setReorderDirty(false);
        selectors.rootInput.val('');
    }

    function renderTree() {
        selectors.tree.empty();
        if (!state.tree) {
            selectors.tree.html(`<p class="placeholder">${dataset.strings.emptyTree}</p>`);
            return;
        }

        const $list = $('<ul class="ebook-tree-sortable"></ul>');
        $list.append(renderNode(state.tree));
        selectors.tree.append($list);
    }

    function renderNode(node) {
        const $li = $('<li></li>').attr('data-id', node.id);
        const $item = $('<div class="ebook-builder-node"></div>')
            .toggleClass('is-active', node.id === state.selectedId);

        const label = node.title || '(Chưa có tiêu đề)';
        const $title = $('<span class="ebook-builder-node__title"></span>').text(label);
        const $badge = $('<span class="ebook-builder-node__badge"></span>').text(node.status);
        $item.append($title, $badge);
        $li.append($item);

        $item.on('click', (e) => {
            e.stopPropagation();
            selectNode(node.id);
        });

        if (node.children && node.children.length) {
            const $children = $('<ul class="ebook-tree-sortable"></ul>');
            node.children.forEach((child) => {
                $children.append(renderNode(child));
            });
            $li.append($children);
        }
        return $li;
    }

    function initDragAndDrop() {
        selectors.tree.find('.ebook-tree-sortable').sortable({
            items: '> li',
            connectWith: '.ebook-tree-sortable',
            placeholder: 'ebook-tree-placeholder',
            tolerance: 'pointer',
            start: () => setReorderDirty(true,
 true),
            stop: () => {
                state.pendingOrder = mapTreeOrder();
                setReorderDirty(true);
            },
        }).disableSelection();
    }

    function mapTreeOrder() {
        const mapList = (element, parentId) => {
            const result = [];
            element.children('li').each(function () {
                const $li = $(this);
                const id = parseInt($li.attr('data-id'), 10);
                if (!id) {
                    return;
                }
                const children = $li.children('ul');
                result.push({
                    id,
                    parent: parentId,
                    children: children.length ? mapList(children, id) : [],
                });
            });
            return result;
        };

        return mapList(selectors.tree.find('> ul'), state.rootId);
    }

    function selectNode(postId) {
        state.selectedId = parseInt(postId, 10);
        selectors.tree.find('.ebook-builder-node').removeClass('is-active');
        selectors.tree.find(`[data-id="${state.selectedId}"] > .ebook-builder-node`).addClass('is-active');

        apiRequest('ebook_builder_get_node', { post_id: state.selectedId })
            .then((data) => {
                fillForm(data);
                setSelectionInfo(data.post);
                setQuickActionsEnabled(true, data.post.is_root);
                selectors.newParent.val(data.post.ID);
            })
            .fail(showError);
    }

    function resetForm() {
        selectors.form.find('input[type="text"], input[type="url"], textarea').val('');
        selectors.status.val('draft');
        selectors.parent.empty();
        setEditorContent('');
        state.currentLink = null;
        selectors.previewBtn.prop('disabled', true);
    }

    function fillForm(response) {
        const { post, meta } = response;
        selectors.postInput.val(post.ID);
        selectors.title.val(post.title);
        selectors.status.val(post.status);
        selectors.excerpt.val(post.excerpt);
        state.currentLink = post.link;
        selectors.previewBtn.prop('disabled', !state.currentLink);

        setEditorContent(post.content || '');
        state.descendants = collectDescendants(state.selectedId);
        updateParentOptions();

        const parentValue = post.is_root ? state.rootId : (post.parent || state.rootId);
        selectors.parent.val(parentValue).prop('disabled', post.is_root);

        toggleMetaFields(post.is_root);

        selectors.metaStatus.val(meta._ebook_status || 'draft').prop('disabled', !post.is_root);
        selectors.metaVersion.val(meta._ebook_version || '1.0').prop('disabled', !post.is_root);
        selectors.metaDownload.val(meta._ebook_download || '').prop('disabled', !post.is_root);
    }

    function toggleMetaFields(isRoot) {
        const disabled = !isRoot;
        selectors.metaStatus.prop('disabled', disabled);
        selectors.metaVersion.prop('disabled', disabled);
        selectors.metaDownload.prop('disabled', disabled);
    }

    function updateParentOptions() {
        selectors.parent.empty();
        selectors.newParent.empty();

        if (!state.tree) {
            selectors.parent.prop('disabled', true);
            selectors.newParent.prop('disabled', true);
            return;
        }

        const options = buildParentOptions(state.tree);
        const blocked = new Set([state.selectedId, ...state.descendants]);

        options.forEach(({ id, label }) => {
            selectors.newParent.append(
                $('<option></option>').val(id).text(label)
            );

            selectors.parent.append(
                $('<option></option>')
                    .val(id)
                    .text(label)
                    .prop('disabled', blocked.has(id))
            );
        });

        const defaultValue = state.selectedId || state.rootId || (options[0] && options[0].id);
        if (defaultValue) {
            selectors.newParent.val(defaultValue);
        }
    }

    function buildParentOptions(node, depth = 0, result = []) {
        if (!node) {
            return result;
        }
        const title = node.title || (depth === 0 ? 'Ebook gốc' : '(Chưa có tiêu đề)');
        const prefix = depth === 0 ? '' : `${'— '.repeat(depth)}`;
        result.push({
            id: node.id,
            label: depth === 0 ? `${title} (Cấp 1)` : `${prefix}${title}`,
        });

        if (node.children) {
            node.children.forEach((child) => buildParentOptions(child, depth + 1, result));
        }

        return result;
    }

    function setSelectionInfo(post) {
        const context = post.is_root ? 'Ebook gốc' : 'Trang đang chỉnh';
        const $wrapper = $('<div></div>');
        $wrapper.append(
            $('<span></span>').text(context),
            $('<strong></strong>').text(post.title || '(Chưa có tiêu đề)'),
            $('<p class="selection-meta"></p>').text(`Trạng thái: ${post.status}`)
        );
        selectors.selection.html($wrapper);
    }

    function resetSelectionInfo() {
        selectors.selection.html('<p class="selection-placeholder">Chưa chọn trang nào.</p>');
    }

    function setQuickActionsEnabled(enabled, isRoot = false) {
        selectors.addSiblingBtn.prop('disabled', !enabled);
        selectors.addChildBtn.prop('disabled', !enabled);
        selectors.deleteBtn.prop('disabled', !enabled || isRoot);
        selectors.previewBtn.prop('disabled', !enabled || !state.currentLink);
    }

    function setCreateFormEnabled(enabled) {
        selectors.newTitle.prop('disabled', !enabled);
        selectors.newParent.prop('disabled', !enabled);
        selectors.newStatus.prop('disabled', !enabled);
        selectors.createPageBtn.prop('disabled', !enabled);
        if (!enabled) {
            selectors.newTitle.val('');
            selectors.newParent.empty();
        }
    }

    function setReorderDirty(dirty) {
        state.isSorting = dirty;
        selectors.saveOrderBtn.prop('disabled', !dirty);
        selectors.reorderHint.toggleClass('is-active', dirty);
    }

    function createPageFromForm() {
        if (!state.rootId) {
            return;
        }
        const title = selectors.newTitle.val().trim();
        const parent = parseInt(selectors.newParent.val() || state.rootId, 10);
        const status = selectors.newStatus.val();

        if (!title.length) {
            selectors.newTitle.focus();
            showNotice(dataset.strings.creationError, 'error');
            return;
        }

        apiRequest('ebook_builder_create_node', {
            root_id: state.rootId,
            parent_id: parent,
            post_title: title,
            post_status: status,
        })
            .then((res) => {
                selectors.newTitle.val('');
                loadTree(state.rootId).then(() => selectNode(res.post_id));
                showNotice(res.message, 'success');
            })
            .fail(showError);
    }

    function getEditor() {
        if (window.tinymce) {
            const editor = tinymce.get('ebook_builder_editor');
            if (editor) {
                return editor;
            }
        }
        return null;
    }

    function getEditorContent() {
        const editor = getEditor();
        if (editor) {
            editor.save();
            return editor.getContent();
        }
        return $('#ebook_builder_editor').val();
    }

    function setEditorContent(content) {
        const editor = getEditor();
        if (editor) {
            editor.setContent(content || '');
        } else {
            $('#ebook_builder_editor').val(content || '');
        }
    }

    function getFormData() {
        const parentDisabled = selectors.parent.prop('disabled');
        return {
            post_id: selectors.postInput.val(),
            root_id: selectors.rootInput.val(),
            post_title: selectors.title.val(),
            post_status: selectors.status.val(),
            post_parent: parentDisabled ? 0 : selectors.parent.val(),
            post_excerpt: selectors.excerpt.val(),
            post_content: getEditorContent(),
            _ebook_status: selectors.metaStatus.val(),
            _ebook_version: selectors.metaVersion.val(),
            _ebook_download: selectors.metaDownload.val(),
        };
    }

    function saveNode() {
        const data = getFormData();
        apiRequest('ebook_builder_save_node', data)
            .then((res) => {
                showNotice(res.message || 'Đã lưu.', 'success');
                const targetId = state.selectedId;
                loadTree(state.rootId).then(() => selectNode(targetId));
            })
            .fail(showError);
    }

    function promptCreate(parentId) {
        const title = (window.prompt(dataset.strings.promptTitle) || '').trim();
        if (!title.length) {
            return;
        }
        apiRequest('ebook_builder_create_node', {
            root_id: state.rootId,
            parent_id: parentId,
            post_title: title,
            post_status: selectors.newStatus.val(),
        })
            .then((res) => {
                showNotice(res.message, 'success');
                loadTree(state.rootId).then(() => selectNode(res.post_id));
            })
            .fail(showError);
    }

    function deleteNode(postId) {
        apiRequest('ebook_builder_delete_node', { post_id: postId })
            .then((res) => {
                showNotice(res.message, 'success');
                loadTree(state.rootId);
            })
            .fail(showError);
    }

    function buildDescendants(node, acc = []) {
        if (!node || !node.children) {
            return acc;
        }
        node.children.forEach((child) => {
            acc.push(child.id);
            buildDescendants(child, acc);
        });
        return acc;
    }

    function findNode(node, targetId) {
        if (!node) {
            return null;
        }
        if (node.id === targetId) {
            return node;
        }
        if (node.children) {
            for (const child of node.children) {
                const match = findNode(child, targetId);
                if (match) {
                    return match;
                }
            }
        }
        return null;
    }

    function collectDescendants(targetId) {
        const node = findNode(state.tree, targetId);
        if (!node) {
            return [];
        }
        return buildDescendants(node, []);
    }

    function showNotice(message, type = 'success') {
        selectors.notices.empty();
        const $notice = $('<div class="notice is-dismissible ebook-builder-notice"></div>')
            .addClass(type === 'error' ? 'notice-error' : 'notice-success')
            .append(`<p>${message}</p>`);
        selectors.notices.append($notice);
    }

    function showError(error) {
        const message = typeof error === 'string' ? error : (error && error.message) || 'Đã xảy ra lỗi.';
        showNotice(message, 'error');
    }

    $(document).ready(init);
})(jQuery);
