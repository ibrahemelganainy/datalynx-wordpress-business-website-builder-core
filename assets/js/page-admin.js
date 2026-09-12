jQuery(function ($) {

    'use strict';

    let currentSectionId = null;
    let currentSectionType = null;
    let currentSectionSchema = null;


    /**
     * ---------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------
     */

    function getPageId() {

        return $('#post_ID').val() || '';
    }


    function getAjaxConfig() {

        if (
            typeof BBPageAdmin === 'undefined'
            || !BBPageAdmin.ajaxUrl
            || !BBPageAdmin.nonce
        ) {

            return null;
        }

        return BBPageAdmin;
    }


    function getEditorDocumentContext() {

        const editor =
            $('#bb-section-editor');

        if (editor.length) {
            return editor;
        }

        return $(document);
    }


    function escapeHtml(value) {

        if (
            value === null
            || typeof value === 'undefined'
        ) {

            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }


    function escapeAttribute(value) {

        return escapeHtml(value);
    }


    function normalizeValue(value, fallback) {

        if (
            value === null
            || typeof value === 'undefined'
        ) {

            return (
                typeof fallback !== 'undefined'
                    ? fallback
                    : ''
            );
        }

        return value;
    }


    function isObject(value) {

        return (
            value !== null
            && typeof value === 'object'
            && !Array.isArray(value)
        );
    }


    function getFieldValue(
        container,
        fieldKey
    ) {

        const field =
            container.find(
                '[data-bb-field-key="' +
                escapeSelector(fieldKey) +
                '"]'
            ).first();

        if (!field.length) {
            return '';
        }

        const type =
            field.data('bb-field-type');

        if (type === 'checkbox') {

            return field.is(':checked');
        }

        if (type === 'repeater') {

            return collectRepeaterValue(
                field
            );
        }

        return field.val();
    }


    function escapeSelector(value) {

        if (
            typeof CSS !== 'undefined'
            && typeof CSS.escape === 'function'
        ) {

            return CSS.escape(
                String(value)
            );
        }

        return String(value)
            .replace(
                /([!"#$%&'()*+,.\/:;<=>?@\[\]^`{|}~\\])/g,
                '\\$1'
            );
    }


    /**
     * ---------------------------------------------------------
     * Add Section
     * ---------------------------------------------------------
     */

    $(document).on(
        'click',
        '.bb-add-section',
        function (event) {

            event.preventDefault();

            const button = $(this);

            const type =
                button.data('section-type');

            const pageId =
                getPageId();

            if (!type) {
                return;
            }

            if (!pageId) {

                alert(
                    'Please save the page first.'
                );

                return;
            }

            if (
                button.hasClass(
                    'is-loading'
                )
            ) {

                return;
            }

            const ajax =
                getAjaxConfig();

            if (!ajax) {

                alert(
                    'Business Builder AJAX configuration is missing.'
                );

                return;
            }

            button.addClass(
                'is-loading'
            );

            button.prop(
                'disabled',
                true
            );

            $.ajax({

                url: ajax.ajaxUrl,

                type: 'POST',

                dataType: 'json',

                data: {

                    action:
                        'bb_add_section',

                    nonce:
                        ajax.nonce,

                    page_id:
                        pageId,

                    type:
                        type
                },

                success: function (response) {

                    if (
                        !response
                        || !response.success
                    ) {

                        const message =
                            response
                            && response.data
                            && response.data.message
                                ? response.data.message
                                : 'Could not add section.';

                        alert(message);

                        return;
                    }

                    window.location.reload();
                },

                error: function (xhr) {

                    let message =
                        'An unexpected error occurred.';

                    if (
                        xhr.responseJSON
                        && xhr.responseJSON.data
                        && xhr.responseJSON.data.message
                    ) {

                        message =
                            xhr.responseJSON.data.message;
                    }

                    alert(message);
                },

                complete: function () {

                    button.removeClass(
                        'is-loading'
                    );

                    button.prop(
                        'disabled',
                        false
                    );
                }
            });
        }
    );


    /**
     * ---------------------------------------------------------
     * Delete Section
     * ---------------------------------------------------------
     */

    $(document).on(
        'click',
        '.bb-delete-section',
        function (event) {

            event.preventDefault();

            const button = $(this);

            const sectionId =
                button.data('section-id');

            const pageId =
                getPageId();

            if (
                !sectionId
                || !pageId
            ) {

                return;
            }

            const confirmed =
                window.confirm(
                    'Are you sure you want to delete this section?'
                );

            if (!confirmed) {
                return;
            }

            const ajax =
                getAjaxConfig();

            if (!ajax) {

                alert(
                    'Business Builder AJAX configuration is missing.'
                );

                return;
            }

            button.prop(
                'disabled',
                true
            );

            $.ajax({

                url: ajax.ajaxUrl,

                type: 'POST',

                dataType: 'json',

                data: {

                    action:
                        'bb_delete_section',

                    nonce:
                        ajax.nonce,

                    page_id:
                        pageId,

                    section_id:
                        sectionId
                },

                success: function (response) {

                    if (
                        !response
                        || !response.success
                    ) {

                        const message =
                            response
                            && response.data
                            && response.data.message
                                ? response.data.message
                                : 'Could not delete section.';

                        alert(message);

                        return;
                    }

                    window.location.reload();
                },

                error: function (xhr) {

                    let message =
                        'An unexpected error occurred.';

                    if (
                        xhr.responseJSON
                        && xhr.responseJSON.data
                        && xhr.responseJSON.data.message
                    ) {

                        message =
                            xhr.responseJSON.data.message;
                    }

                    alert(message);
                },

                complete: function () {

                    button.prop(
                        'disabled',
                        false
                    );
                }
            });
        }
    );


    /**
     * ---------------------------------------------------------
     * Section Reordering (Drag & Drop)
     * ---------------------------------------------------------
     */

    $(document).on(
        'dragstart',
        '.bb-section-item',
        function (event) {

            const item =
                $(this);

            const sectionId =
                item.data(
                    'section-id'
                );

            if (!sectionId) {
                return;
            }

            item.addClass(
                'is-dragging'
            );

            window.bbDraggedSectionId =
                sectionId;

            const dataTransfer =
                event.originalEvent
                && event.originalEvent.dataTransfer
                    ? event.originalEvent.dataTransfer
                    : null;

            if (dataTransfer) {
                dataTransfer.effectAllowed =
                    'move';
                dataTransfer.setData(
                    'text/plain',
                    sectionId
                );
            }
        }
    );

    $(document).on(
        'dragend',
        '.bb-section-item',
        function () {

            $(this).removeClass(
                'is-dragging'
            );

            $(this)
                .closest(
                    '.bb-section-list'
                )
                .find(
                    '.bb-section-item'
                )
                .removeClass(
                    'is-drop-target'
                );

            window.bbDraggedSectionId =
                null;
        }
    );

    $(document).on(
        'dragover',
        '.bb-section-item',
        function (event) {

            if (
                !window.bbDraggedSectionId
                || window.bbDraggedSectionId ===
                    $(this).data(
                        'section-id'
                    )
            ) {
                return;
            }

            event.preventDefault();

            $(this).addClass(
                'is-drop-target'
            );
        }
    );

    $(document).on(
        'dragleave',
        '.bb-section-item',
        function () {

            $(this).removeClass(
                'is-drop-target'
            );
        }
    );

    $(document).on(
        'drop',
        '.bb-section-item',
        function (event) {

            event.preventDefault();

            const target =
                $(this);

            const targetId =
                target.data(
                    'section-id'
                );

            const draggedId =
                window.bbDraggedSectionId;

            if (!draggedId || !targetId || draggedId === targetId) {
                return;
            }

            const list =
                target.closest(
                    '.bb-section-list'
                );

            const items =
                list.find(
                    '.bb-section-item'
                );

            const draggedItem =
                items.filter(
                    '[data-section-id="' +
                    escapeSelector(draggedId) +
                    '"]'
                ).first();

            if (!draggedItem.length) {
                return;
            }

            const draggedIndex =
                items.index(
                    draggedItem
                );

            const targetIndex =
                items.index(
                    target
                );

            if (draggedIndex < targetIndex) {
                target.after(
                    draggedItem
                );
            } else {
                target.before(
                    draggedItem
                );
            }

            const newOrder =
                list.find(
                    '.bb-section-item'
                ).index(
                    draggedItem
                ) + 1;

            list.find(
                '.bb-section-item'
            ).each(
                function (index) {

                    const orderBox =
                        $(this).find(
                            '.bb-section-item-order'
                        );

                    if (orderBox.length) {
                        orderBox.text(
                            index + 1
                        );
                    }
                }
            );

            const ajax =
                getAjaxConfig();

            if (!ajax) {
                alert(
                    'Business Builder AJAX configuration is missing.'
                );
                return;
            }

            $.ajax({

                url: ajax.ajaxUrl,

                type: 'POST',

                dataType: 'json',

                data: {

                    action:
                        'bb_move_section',

                    nonce:
                        ajax.nonce,

                    page_id:
                        getPageId(),

                    section_id:
                        draggedId,

                    new_order:
                        newOrder
                },

                success: function (response) {

                    if (
                        !response
                        || !response.success
                    ) {

                        const message =
                            response
                            && response.data
                            && response.data.message
                                ? response.data.message
                                : 'Could not reorder section.';

                        alert(message);
                        window.location.reload();
                    }
                },

                error: function (xhr) {

                    let message =
                        'An unexpected error occurred.';

                    if (
                        xhr.responseJSON
                        && xhr.responseJSON.data
                        && xhr.responseJSON.data.message
                    ) {

                        message =
                            xhr.responseJSON.data.message;
                    }

                    alert(message);
                    window.location.reload();
                }
            });
        }
    );

    /**
     * ---------------------------------------------------------
     * Open Section Editor
     * ---------------------------------------------------------
     */

    $(document).on(
        'click',
        '.bb-duplicate-section',
        function (event) {

            event.preventDefault();

            const button = $(this);

            const sectionId =
                button.data('section-id');

            const pageId =
                getPageId();

            if (
                !sectionId
                || !pageId
            ) {
                return;
            }

            const ajax =
                getAjaxConfig();

            if (!ajax) {

                alert(
                    'Business Builder AJAX configuration is missing.'
                );

                return;
            }

            button.prop(
                'disabled',
                true
            );

            $.ajax({

                url: ajax.ajaxUrl,

                type: 'POST',

                dataType: 'json',

                data: {

                    action:
                        'bb_duplicate_section',

                    nonce:
                        ajax.nonce,

                    page_id:
                        pageId,

                    section_id:
                        sectionId
                },

                success: function (response) {

                    if (
                        !response
                        || !response.success
                    ) {

                        const message =
                            response
                            && response.data
                            && response.data.message
                                ? response.data.message
                                : 'Could not duplicate section.';

                        alert(message);

                        return;
                    }

                    window.location.reload();
                },

                error: function (xhr) {

                    let message =
                        'An unexpected error occurred.';

                    if (
                        xhr.responseJSON
                        && xhr.responseJSON.data
                        && xhr.responseJSON.data.message
                    ) {

                        message =
                            xhr.responseJSON.data.message;
                    }

                    alert(message);
                },

                complete: function () {

                    button.prop(
                        'disabled',
                        false
                    );
                }
            });
        }
    );

    function initSliderControls() {

        $('.bb-slider').each(function () {

            const slider = $(this);
            const slides = slider.find('.bb-slider-slide');

            if (!slides.length) {
                return;
            }

            let currentIndex = 0;
            let autoplayTimer = null;
            const autoplayEnabled =
                slider.data('autoplay') === true
                || slider.data('autoplay') === '1'
                || slider.data('autoplay') === 1;

            function showSlide(index) {

                const total = slides.length;

                if (!total) {
                    return;
                }

                currentIndex = (index + total) % total;

                slides.removeClass('is-active');
                slides.eq(currentIndex).addClass('is-active');

                slider.find('.bb-slider-dot')
                    .removeClass('is-active')
                    .attr('aria-pressed', 'false');

                const activeDot =
                    slider.find('.bb-slider-dot').eq(currentIndex);

                if (activeDot.length) {
                    activeDot.addClass('is-active')
                        .attr('aria-pressed', 'true');
                }
            }

            function startAutoplay() {

                if (!autoplayEnabled || slides.length < 2) {
                    return;
                }

                clearInterval(autoplayTimer);
                autoplayTimer = setInterval(function () {
                    showSlide(currentIndex + 1);
                }, 5000);
            }

            function stopAutoplay() {
                clearInterval(autoplayTimer);
                autoplayTimer = null;
            }

            slider.find('.bb-slider-prev').on('click', function () {
                showSlide(currentIndex - 1);
                startAutoplay();
            });

            slider.find('.bb-slider-next').on('click', function () {
                showSlide(currentIndex + 1);
                startAutoplay();
            });

            slider.find('.bb-slider-dot').on('click', function () {
                const index = Number($(this).data('slide-index'));
                showSlide(index);
                startAutoplay();
            });

            slider.on('mouseenter focusin', stopAutoplay)
                .on('mouseleave focusout', startAutoplay);

            showSlide(0);
            startAutoplay();
        });
    }

    let activePreviewPopup = null;

    $(document).on(
        'click',
        '.bb-live-preview-button',
        function (event) {

            event.preventDefault();

            const previewUrl =
                $(this).data('preview-url');

            if (!previewUrl) {
                return;
            }

            const finalUrl =
                previewUrl.includes('?')
                    ? previewUrl + '&bb_preview=1'
                    : previewUrl + '?bb_preview=1';

            if (
                activePreviewPopup
                && !activePreviewPopup.closed
            ) {
                activePreviewPopup.location.href = finalUrl;
                activePreviewPopup.focus();
                return;
            }

            const popup = window.open(
                finalUrl,
                '_blank',
                'width=1400,height=920,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes'
            );

            if (!popup) {
                const modal =
                    $('#bb-live-preview-modal');

                const iframe =
                    $('#bb-live-preview-iframe');

                iframe.attr(
                    'src',
                    finalUrl
                );

                modal.show();
                return;
            }

            activePreviewPopup = popup;
        }
    );

    $(document).on(
        'click',
        '.bb-close-live-preview, .bb-live-preview-overlay',
        function () {

            if (
                activePreviewPopup
                && !activePreviewPopup.closed
            ) {
                activePreviewPopup.close();
                activePreviewPopup = null;
                return;
            }

            $('#bb-live-preview-modal').hide();
            $('#bb-live-preview-iframe').attr('src', '');
        }
    );

    $(document).ready(function () {
        initSliderControls();
    });

    $(document).on(
        'click',
        '.bb-edit-section',
        function (event) {

            event.preventDefault();

            const button = $(this);

            currentSectionId =
                button.data('section-id');

            currentSectionType =
                button.data('section-type');

            const pageId =
                getPageId();

            if (
                !currentSectionId
                || !currentSectionType
                || !pageId
            ) {

                return;
            }

            openSectionEditor(
                pageId,
                currentSectionId,
                currentSectionType
            );
        }
    );


    /**
     * ---------------------------------------------------------
     * Open Editor
     * ---------------------------------------------------------
     */

    function openSectionEditor(
        pageId,
        sectionId,
        sectionType
    ) {

        const editor =
            $('#bb-section-editor');

        const body =
            $('#bb-section-editor-body');

        editor.show();
        body.html('<p>Loading...</p>');

        const ajax =
            getAjaxConfig();

        if (!ajax) {

            alert(
                'Business Builder AJAX configuration is missing.'
            );

            return;
        }

        $.ajax({

            url: ajax.ajaxUrl,

            type: 'POST',

            dataType: 'json',

            data: {

                action:
                    'bb_get_section',

                nonce:
                    ajax.nonce,

                page_id:
                    pageId,

                section_id:
                    sectionId
            },

            success: function (response) {

                if (
                    !response
                    || !response.success
                ) {

                    body.html(
                        '<p>Could not load section.</p>'
                    );

                    return;
                }

                const section =
                    response.data
                    && response.data.section
                        ? response.data.section
                        : null;

                const schema =
                    response.data
                    && response.data.schema
                        ? response.data.schema
                        : {
                            content: {},
                            settings: {}
                        };

                if (!section) {

                    body.html(
                        '<p>Section data is missing.</p>'
                    );

                    return;
                }

                currentSectionSchema =
                    schema;

                renderSectionEditor(
                    section,
                    schema
                );
            },

            error: function (xhr) {

                let message =
                    'An unexpected error occurred.';

                if (
                    xhr.responseJSON
                    && xhr.responseJSON.data
                    && xhr.responseJSON.data.message
                ) {

                    message =
                        xhr.responseJSON.data.message;
                }

                body.html(
                    '<p>' +
                    escapeHtml(message) +
                    '</p>'
                );
            }
        });
    }


    /**
     * ---------------------------------------------------------
     * Render Section Editor
     * ---------------------------------------------------------
     */

    function renderSectionEditor(
        section,
        schema
    ) {

        const root =
            getEditorDocumentContext();

        const body =
            root.find('#bb-section-editor-body');

        const title =
            root.find('#bb-section-editor-title');

        const type =
            section.type || '';

        const settings =
            isObject(section.settings)
                ? section.settings
                : {};

        const content =
            isObject(section.content)
                ? section.content
                : {};

        const contentSchema =
            isObject(schema.content)
                ? schema.content
                : {};

        const settingsSchema =
            isObject(schema.settings)
                ? schema.settings
                : {};

        title.text(
            'Edit Section: ' + type
        );

        let html = '';

        html +=
            '<input ' +
            'type="hidden" ' +
            'id="bb-editor-section-type" ' +
            'value="' +
            escapeAttribute(type) +
            '">' ;


        /**
         * Content
         */
        if (
            Object.keys(contentSchema).length
        ) {

            html +=
                '<div class="bb-section-editor-group">' +
                    '<h3 class="bb-section-editor-group-title">' +
                        'Content' +
                    '</h3>';

            html +=
                renderSchemaFields(
                    contentSchema,
                    content,
                    'content'
                );

            html +=
                '</div>';
        }


        /**
         * Settings
         */
        if (
            Object.keys(settingsSchema).length
        ) {

            html +=
                '<div class="bb-section-editor-group">' +
                    '<h3 class="bb-section-editor-group-title">' +
                        'Settings' +
                    '</h3>';

            html +=
                renderSchemaFields(
                    settingsSchema,
                    settings,
                    'settings'
                );

            html +=
                '</div>';
        }


        if (
            !Object.keys(contentSchema).length
            && !Object.keys(settingsSchema).length
        ) {

            html +=
                '<p>' +
                    'This section has no editable fields.' +
                '</p>';
        }

        body.html(html);

        initializeDynamicFields(
            body
        );

        renderEditorPreview(
            content,
            settings,
            type
        );

        body.on(
            'input change',
            '[data-bb-field-key]',
            function () {
                renderEditorPreview(
                    collectGroupValues(
                        body,
                        'content'
                    ),
                    collectGroupValues(
                        body,
                        'settings'
                    ),
                    type
                );
            }
        );
    }

    function renderEditorPreview(
        content,
        settings,
        sectionType
    ) {

        const previewContainer =
            getEditorDocumentContext().find('#bb-section-editor-preview');

        if (!previewContainer.length) {
            return;
        }

        const normalizedType =
            String(sectionType || 'Section')
                .replace(/[_-]+/g, ' ')
                .replace(/\b\w/g, function (letter) {
                    return letter.toUpperCase();
                });

        const title =
            content.title
            || content.heading
            || content.name
            || settings.title
            || settings.heading
            || currentSectionType
            || 'Section';

        const description =
            content.description
            || settings.description
            || 'Your content will appear here as you edit.';

        const ctaText =
            content.button_text
            || content.cta_text
            || settings.button_text
            || settings.cta_text
            || 'Get Started';

        let items = [];

        if (Array.isArray(content.items)) {
            items = content.items;
        } else if (Array.isArray(content.slides)) {
            items = content.slides;
        } else if (Array.isArray(content.features)) {
            items = content.features;
        }

        const firstItems = items.slice(0, 3);

        let html =
            '<div class="bb-section-editor-preview-card">';

        html +=
            '<div class="bb-preview-topbar">' +
            '<span class="bb-preview-badge">' +
            escapeHtml(normalizedType) +
            '</span>' +
            '<span class="bb-preview-pill">Live</span>' +
            '</div>';

        html +=
            '<div class="bb-preview-hero">';

        const imageValue =
            content.image
            || content.background_image
            || settings.image
            || settings.background_image;

        if (imageValue) {
            html +=
                '<div class="bb-preview-visual">' +
                '<span class="bb-preview-visual-badge">Image</span>' +
                '</div>';
        } else {
            html +=
                '<div class="bb-preview-visual is-empty">' +
                '<span class="bb-preview-visual-badge">Preview</span>' +
                '</div>';
        }

        html +=
            '<h4>' +
            escapeHtml(String(title)) +
            '</h4>';

        if (description) {
            html +=
                '<p class="bb-preview-description">' +
                escapeHtml(String(description).slice(0, 170)) +
                '</p>';
        }

        html +=
            '</div>';

        if (firstItems.length) {
            html += '<div class="bb-preview-grid">';

            firstItems.forEach(function (item) {

                if (!item || typeof item !== 'object') {
                    return;
                }

                const itemTitle =
                    item.title
                    || item.label
                    || item.question
                    || item.eyebrow
                    || 'Item';

                const topicText =
                    item.description
                    || item.subtitle
                    || item.summary
                    || item.value
                    || '';

                html +=
                    '<div class="bb-preview-mini-card">' +
                    '<span class="bb-preview-mini-kicker"></span>' +
                    '<strong>' +
                    escapeHtml(String(itemTitle)) +
                    '</strong>' +
                    (topicText ? '<small>' + escapeHtml(String(topicText).slice(0, 55)) + '</small>' : '') +
                    '</div>';
            });

            html += '</div>';
        }

        html +=
            '<div class="bb-preview-actions">' +
            '<button type="button" class="bb-preview-cta">' +
            escapeHtml(String(ctaText)) +
            '</button>' +
            '</div>';

        html += '</div>';

        previewContainer.find(
            '.bb-section-editor-preview-body'
        ).html(html);
    }


    /**
     * ---------------------------------------------------------
     * Render Schema Fields
     * ---------------------------------------------------------
     */

    function renderSchemaFields(
        schema,
        values,
        group
    ) {

        let html = '';

        Object.keys(schema).forEach(
            function (fieldKey) {

                const field =
                    normalizeFieldSchema(
                        fieldKey,
                        schema[fieldKey]
                    );

                const value =
                    normalizeValue(
                        values[fieldKey],
                        field.default
                    );

                html +=
                    renderField(
                        fieldKey,
                        field,
                        value,
                        group
                    );
            }
        );

        return html;
    }


    /**
     * Normalize a field definition.
     */
    function normalizeFieldSchema(
        fieldKey,
        field
    ) {

        if (
            !isObject(field)
        ) {

            return {

                type:
                    'text',

                label:
                    prettifyFieldName(
                        fieldKey
                    ),

                description:
                    '',

                default:
                    '',

                placeholder:
                    '',

                options:
                    {},

                required:
                    false,

                min:
                    null,

                max:
                    null,

                step:
                    null,

                rows:
                    4,

                multiple:
                    false,

                fields:
                    {}
            };
        }

        const normalizedFields = {};

        if (isObject(field.fields)) {

            Object.keys(field.fields).forEach(
                function (nestedKey) {

                    normalizedFields[nestedKey] =
                        normalizeFieldSchema(
                            nestedKey,
                            field.fields[nestedKey]
                        );
                }
            );
        }

        return {

            type:
                field.type
                || 'text',

            label:
                field.label
                || prettifyFieldName(
                    fieldKey
                ),

            description:
                field.description
                || '',

            default:
                typeof field.default !== 'undefined'
                    ? field.default
                    : '',

            placeholder:
                field.placeholder
                || '',

            options:
                isObject(field.options)
                    ? field.options
                    : {},

            required:
                Boolean(
                    field.required
                ),

            min:
                typeof field.min !== 'undefined'
                    ? field.min
                    : null,

            max:
                typeof field.max !== 'undefined'
                    ? field.max
                    : null,

            step:
                typeof field.step !== 'undefined'
                    ? field.step
                    : null,

            rows:
                field.rows
                || 4,

            multiple:
                Boolean(
                    field.multiple
                ),

            fields:
                normalizedFields
        };
    }


    /**
     * ---------------------------------------------------------
     * Render Individual Field
     * ---------------------------------------------------------
     */

    function renderField(
        fieldKey,
        field,
        value,
        group
    ) {

        const fieldId =
            'bb-editor-' +
            group +
            '-' +
            fieldKey;

        const type =
            String(
                field.type || 'text'
            ).toLowerCase();

        let html =
            '<div ' +
            'class="bb-section-editor-field" ' +
            'data-bb-field-wrapper="' +
            escapeAttribute(fieldKey) +
            '">';

        html +=
            '<label ' +
            'for="' +
            escapeAttribute(fieldId) +
            '">' +
            escapeHtml(field.label);

        if (field.required) {

            html +=
                ' <span class="bb-required">*</span>';
        }

        html +=
            '</label>';


        /**
         * Description
         */
        if (field.description) {

            html +=
                '<p class="bb-section-editor-description">' +
                    escapeHtml(
                        field.description
                    ) +
                '</p>';
        }


        /**
         * Field
         */
        switch (type) {

            case 'textarea':

                html +=
                    renderTextareaField(
                        fieldId,
                        fieldKey,
                        field,
                        value,
                        group
                    );

                break;


            case 'url':

                html +=
                    renderTextLikeField(
                        'url',
                        fieldId,
                        fieldKey,
                        field,
                        value,
                        group
                    );

                break;


            case 'email':

                html +=
                    renderTextLikeField(
                        'email',
                        fieldId,
                        fieldKey,
                        field,
                        value,
                        group
                    );

                break;


            case 'number':

                html +=
                    renderNumberField(
                        fieldId,
                        fieldKey,
                        field,
                        value,
                        group
                    );

                break;


            case 'color':

                html +=
                    renderColorField(
                        fieldId,
                        fieldKey,
                        field,
                        value,
                        group
                    );

                break;


            case 'select':

                html +=
                    renderSelectField(
                        fieldId,
                        fieldKey,
                        field,
                        value,
                        group
                    );

                break;


            case 'checkbox':

                html +=
                    renderCheckboxField(
                        fieldId,
                        fieldKey,
                        field,
                        value,
                        group
                    );

                break;


            case 'image':

                html +=
                    renderImageField(
                        fieldId,
                        fieldKey,
                        field,
                        value,
                        group
                    );

                break;


            case 'repeater':

                html +=
                    renderRepeaterField(
                        fieldId,
                        fieldKey,
                        field,
                        value,
                        group
                    );

                break;


            case 'text':

            default:

                html +=
                    renderTextLikeField(
                        'text',
                        fieldId,
                        fieldKey,
                        field,
                        value,
                        group
                    );

                break;
        }


        html +=
            '</div>';

        return html;
    }


    /**
     * ---------------------------------------------------------
     * Text / URL
     * ---------------------------------------------------------
     */

    function renderTextLikeField(
        inputType,
        fieldId,
        fieldKey,
        field,
        value,
        group
    ) {

        let html =
            '<input ' +
            'type="' +
            escapeAttribute(inputType) +
            '" ' +
            'id="' +
            escapeAttribute(fieldId) +
            '" ' +
            'class="widefat" ' +
            'data-bb-field-key="' +
            escapeAttribute(fieldKey) +
            '" ' +
            'data-bb-field-type="' +
            escapeAttribute(field.type) +
            '" ';

        if (field.placeholder) {

            html +=
                'placeholder="' +
                escapeAttribute(
                    field.placeholder
                ) +
                '" ';
        }

        if (field.required) {

            html +=
                'required ';
        }

        html +=
            'value="' +
            escapeAttribute(
                value
            ) +
            '">';

        return html;
    }


    /**
     * ---------------------------------------------------------
     * Textarea
     * ---------------------------------------------------------
     */

    function renderTextareaField(
        fieldId,
        fieldKey,
        field,
        value,
        group
    ) {

        let html =
            '<textarea ' +
            'id="' +
            escapeAttribute(fieldId) +
            '" ' +
            'class="widefat" ' +
            'rows="' +
            escapeAttribute(
                field.rows
            ) +
            '" ' +
            'data-bb-field-key="' +
            escapeAttribute(fieldKey) +
            '" ' +
            'data-bb-field-type="textarea" ';

        if (field.placeholder) {

            html +=
                'placeholder="' +
                escapeAttribute(
                    field.placeholder
                ) +
                '" ';
        }

        if (field.required) {

            html +=
                'required ';
        }

        html +=
            '>' +
            escapeHtml(
                value
            ) +
            '</textarea>';

        return html;
    }


    /**
     * ---------------------------------------------------------
     * Number
     * ---------------------------------------------------------
     */

    function renderNumberField(
        fieldId,
        fieldKey,
        field,
        value,
        group
    ) {

        let html =
            '<input ' +
            'type="number" ' +
            'id="' +
            escapeAttribute(fieldId) +
            '" ' +
            'class="widefat" ' +
            'data-bb-field-key="' +
            escapeAttribute(fieldKey) +
            '" ' +
            'data-bb-field-type="number" ';

        if (
            field.min !== null
            && field.min !== ''
        ) {

            html +=
                'min="' +
                escapeAttribute(
                    field.min
                ) +
                '" ';
        }

        if (
            field.max !== null
            && field.max !== ''
        ) {

            html +=
                'max="' +
                escapeAttribute(
                    field.max
                ) +
                '" ';
        }

        if (
            field.step !== null
            && field.step !== ''
        ) {

            html +=
                'step="' +
                escapeAttribute(
                    field.step
                ) +
                '" ';
        }

        if (field.required) {

            html +=
                'required ';
        }

        html +=
            'value="' +
            escapeAttribute(
                value
            ) +
            '">';

        return html;
    }


    /**
     * ---------------------------------------------------------
     * Color
     * ---------------------------------------------------------
     */

    function renderColorField(
        fieldId,
        fieldKey,
        field,
        value,
        group
    ) {

        let html =
            '<input ' +
            'type="color" ' +
            'id="' +
            escapeAttribute(fieldId) +
            '" ' +
            'class="bb-color-field" ' +
            'data-bb-field-key="' +
            escapeAttribute(fieldKey) +
            '" ' +
            'data-bb-field-type="color" ';

        const color =
            isValidColor(
                value
            )
                ? value
                : '#000000';

        html +=
            'value="' +
            escapeAttribute(
                color
            ) +
            '">';

        return html;
    }


    /**
     * ---------------------------------------------------------
     * Select
     * ---------------------------------------------------------
     */

    function renderSelectField(
        fieldId,
        fieldKey,
        field,
        value,
        group
    ) {

        let html =
            '<select ' +
            'id="' +
            escapeAttribute(fieldId) +
            '" ' +
            'class="widefat" ' +
            'data-bb-field-key="' +
            escapeAttribute(fieldKey) +
            '" ' +
            'data-bb-field-type="select" ';

        if (field.multiple) {

            html +=
                'multiple ';
        }

        html +=
            '>';

        Object.keys(
            field.options
        ).forEach(
            function (optionValue) {

                const optionLabel =
                    field.options[
                        optionValue
                    ];

                let selected = false;

                if (
                    Array.isArray(value)
                ) {

                    selected =
                        value.indexOf(
                            optionValue
                        ) !== -1;

                } else {

                    selected =
                        String(value)
                        === String(optionValue);
                }

                html +=
                    '<option ' +
                    'value="' +
                    escapeAttribute(
                        optionValue
                    ) +
                    '"' +
                    (
                        selected
                            ? ' selected'
                            : ''
                    ) +
                    '>' +
                    escapeHtml(
                        optionLabel
                    ) +
                    '</option>';
            }
        );

        html +=
            '</select>';

        return html;
    }


    /**
     * ---------------------------------------------------------
     * Checkbox
     * ---------------------------------------------------------
     */

    function renderCheckboxField(
        fieldId,
        fieldKey,
        field,
        value,
        group
    ) {

        const checked =
            Boolean(
                value
            );

        let html =
            '<label ' +
            'class="bb-checkbox-field" ' +
            'for="' +
            escapeAttribute(fieldId) +
            '">';

        html +=
            '<input ' +
            'type="checkbox" ' +
            'id="' +
            escapeAttribute(fieldId) +
            '" ' +
            'data-bb-field-key="' +
            escapeAttribute(fieldKey) +
            '" ' +
            'data-bb-field-type="checkbox" ' +
            (
                checked
                    ? 'checked '
                    : ''
            ) +
            '>';

        html +=
            '<span>' +
                escapeHtml(
                    field.label
                ) +
            '</span>';

        html +=
            '</label>';

        /*
         * The normal field label is hidden for checkboxes
         * because the checkbox itself already contains it.
         */
        return html;
    }


    /**
     * ---------------------------------------------------------
     * Image (WordPress Media Library)
     * ---------------------------------------------------------
     *
     * The value is always the attachment ID, kept in a
     * hidden input — collectGroupValues() / collectRepeaterValue()
     * and the PHP sanitizer already expect exactly that, so
     * nothing about the data contract changes here.
     *
     * renderImageFieldMarkup() is the single shared component:
     * it only needs the already-built hidden input HTML plus
     * whether a value is currently set, and renders the same
     * preview + Select/Change + Remove UI either way. This is
     * what lets the exact same "Image" experience be reused
     * for a top-level field (renderImageField) and for a field
     * nested inside a repeater (renderRepeaterImageField),
     * with one shared set of click handlers driving both.
     */
    function renderImageFieldMarkup(
        hiddenInputHtml,
        hasValue
    ) {

        let html =
            '<div class="bb-image-uploader-wrapper" ' +
            'style="background: #f9f9f9; padding: 15px; ' +
            'border: 1px solid #ddd; border-radius: 4px; ' +
            'margin-bottom: 10px;">';

        html += hiddenInputHtml;

        html +=
            '<div class="bb-image-preview-container" ' +
            'style="margin-bottom: 10px;">';

        html +=
            '<img src="" class="bb-preview-img" ' +
            'style="max-width: 100%; height: auto; ' +
            'max-height: 150px; border: 1px solid #ccc; ' +
            'padding: 3px; background: #fff;' +
            (
                hasValue
                    ? ''
                    : ' display: none;'
            ) +
            '" />';

        html += '</div>';

        html += '<div class="bb-image-actions">';

        html +=
            '<button type="button" ' +
            'class="button button-secondary bb-select-image-btn">' +
            (
                hasValue
                    ? 'Change Image'
                    : 'Select Image'
            ) +
            '</button> ';

        html +=
            '<button type="button" ' +
            'class="button button-link bb-remove-image-btn" ' +
            'style="color: #a00; text-decoration: none;' +
            (
                hasValue
                    ? ''
                    : ' display:none;'
            ) +
            '">' +
            'Remove Image' +
            '</button>';

        html += '</div>';

        html += '</div>';

        return html;
    }


    /**
     * Top-level image field. Same shape as before
     * (`.bb-image-id`, `data-bb-field-key`, `data-bb-field-type`)
     * so collectGroupValues() and validateEditor() keep working
     * unchanged.
     */
    function renderImageField(
        fieldId,
        fieldKey,
        field,
        value,
        group
    ) {

        const hasValue = !!value;

        const hiddenInputHtml =
            '<input type="hidden" ' +
            'id="' + escapeAttribute(fieldId) + '" ' +
            'class="bb-image-id widefat" ' +
            'data-bb-field-key="' + escapeAttribute(fieldKey) + '" ' +
            'data-bb-field-type="image" ' +
            'value="' + escapeAttribute(value || '') + '">';

        return renderImageFieldMarkup(
            hiddenInputHtml,
            hasValue
        );
    }


    /**
     * Image field nested inside a repeater item. Carries
     * `.bb-repeater-field` (so collectRepeaterValue() picks
     * it up like any other repeater sub-field) plus
     * `.bb-image-id` (so the shared select/remove handlers
     * below can find it the same way they find a top-level
     * one).
     */
    function renderRepeaterImageField(
        fieldKey,
        value
    ) {

        const hasValue = !!value;

        const hiddenInputHtml =
            '<input type="hidden" ' +
            'class="widefat bb-repeater-field bb-image-id" ' +
            'data-repeater-field-key="' + escapeAttribute(fieldKey) + '" ' +
            'data-repeater-field-type="image" ' +
            'value="' + escapeAttribute(value || '') + '">';

        return renderImageFieldMarkup(
            hiddenInputHtml,
            hasValue
        );
    }


    /**
     * ---------------------------------------------------------
     * Repeater
     * ---------------------------------------------------------
     *
     * Supports both:
     *
     * 1. Array of objects:
     *
     * [
     *     {
     *         title: "...",
     *         description: "..."
     *     }
     * ]
     *
     * 2. Array of scalar values:
     *
     * [
     *     "One",
     *     "Two"
     * ]
     *
     * If the schema contains `fields`, each item is rendered
     * as a small dynamic repeater item.
     */
    function renderRepeaterField(
        fieldId,
        fieldKey,
        field,
        value,
        group,
        isNested = false
    ) {

        let items = [];

        if (Array.isArray(value)) {

            items = value;

        } else if (
            typeof value === 'string'
            && value.trim() !== ''
        ) {

            try {

                const decoded =
                    JSON.parse(value);

                if (Array.isArray(decoded)) {

                    items = decoded;
                }

            } catch (error) {

                items = [];
            }
        }


        const nestedClass =
            isNested
                ? ' bb-repeater-field bb-repeater-nested'
                : '';

        const nestedDataAttributes =
            isNested
                ? 'data-repeater-field-key="' +
                    escapeAttribute(fieldKey) +
                  '" ' +
                  'data-repeater-field-type="repeater" '
                : '';

        let html =
            '<div ' +
            'class="bb-repeater' + nestedClass + '" ' +
            (
                isNested
                    ? ''
                    : 'id="' + escapeAttribute(fieldId) + '" '
            ) +
            'data-bb-field-key="' +
            escapeAttribute(fieldKey) +
            '" ' +
            'data-bb-field-type="repeater" ' +
            nestedDataAttributes +
            'data-bb-repeater-fields=\'' +
            escapeAttribute(
                JSON.stringify(
                    field.fields || {}
                )
            ) +
            '\'>';


        html +=
            '<div class="bb-repeater-items">';


        if (items.length) {

            items.forEach(
                function (item, index) {

                    html +=
                        renderRepeaterItem(
                            field,
                            item,
                            index
                        );
                }
            );

        } else {

            html +=
                renderRepeaterItem(
                    field,
                    {},
                    0
                );
        }


        html +=
            '</div>';


        html +=
            '<button ' +
            'type="button" ' +
            'class="button bb-repeater-add">' +
            '+ Add Item' +
            '</button>';


        html +=
            '</div>';

        return html;
    }


    /**
     * Render one repeater item.
     */
    function renderRepeaterItem(
        field,
        item,
        index
    ) {

        let html =
            '<div ' +
            'class="bb-repeater-item" ' +
            'data-repeater-index="' +
            escapeAttribute(index) +
            '">';


        if (
            isObject(field.fields)
            && Object.keys(
                field.fields
            ).length
        ) {

            Object.keys(
                field.fields
            ).forEach(
                function (subKey) {

                    const subField =
                        normalizeFieldSchema(
                            subKey,
                            field.fields[
                                subKey
                            ]
                        );

                    const subValue =
                        isObject(item)
                        && typeof item[subKey] !== 'undefined'
                            ? item[subKey]
                            : subField.default;

                    html +=
                        '<div class="bb-repeater-subfield">';

                    html +=
                        '<label>' +
                            escapeHtml(
                                subField.label
                            ) +
                        '</label>';

                    html +=
                        renderInlineRepeaterField(
                            subKey,
                            subField,
                            subValue
                        );

                    html +=
                        '</div>';
                }
            );

        } else {

            const scalarValue =
                !isObject(item)
                    ? item
                    : '';

            html +=
                '<input ' +
                'type="text" ' +
                'class="widefat bb-repeater-scalar" ' +
                'value="' +
                escapeAttribute(
                    scalarValue
                ) +
                '" ' +
                'placeholder="Item value">';

        }


        html +=
            '<button ' +
            'type="button" ' +
            'class="button-link-delete bb-repeater-remove">' +
            'Remove' +
            '</button>';

        html +=
            '</div>';

        return html;
    }


    /**
     * Render field inside repeater.
     */
    function renderInlineRepeaterField(
        fieldKey,
        field,
        value
    ) {

        const type =
            String(
                field.type || 'text'
            ).toLowerCase();

        let html = '';


        switch (type) {

            case 'textarea':

                html +=
                    '<textarea ' +
                    'class="widefat bb-repeater-field" ' +
                    'data-repeater-field-key="' +
                    escapeAttribute(
                        fieldKey
                    ) +
                    '" ' +
                    'data-repeater-field-type="textarea" ' +
                    'rows="' +
                    escapeAttribute(
                        field.rows || 3
                    ) +
                    '">' +
                    escapeHtml(
                        value
                    ) +
                    '</textarea>';

                break;


            case 'number':

                html +=
                    '<input ' +
                    'type="number" ' +
                    'class="widefat bb-repeater-field" ' +
                    'data-repeater-field-key="' +
                    escapeAttribute(
                        fieldKey
                    ) +
                    '" ' +
                    'data-repeater-field-type="number" ' +
                    'value="' +
                    escapeAttribute(
                        value
                    ) +
                    '">';

                break;


            case 'url':

                html +=
                    '<input ' +
                    'type="url" ' +
                    'class="widefat bb-repeater-field" ' +
                    'data-repeater-field-key="' +
                    escapeAttribute(
                        fieldKey
                    ) +
                    '" ' +
                    'data-repeater-field-type="url" ' +
                    'value="' +
                    escapeAttribute(
                        value
                    ) +
                    '">';

                break;


            case 'checkbox':

                html +=
                    '<label>';

                html +=
                    '<input ' +
                    'type="checkbox" ' +
                    'class="bb-repeater-field" ' +
                    'data-repeater-field-key="' +
                    escapeAttribute(
                        fieldKey
                    ) +
                    '" ' +
                    'data-repeater-field-type="checkbox" ' +
                    (
                        value
                            ? 'checked'
                            : ''
                    ) +
                    '>';

                html +=
                    '</label>';

                break;


            case 'select':

                html +=
                    '<select ' +
                    'class="widefat bb-repeater-field" ' +
                    'data-repeater-field-key="' +
                    escapeAttribute(
                        fieldKey
                    ) +
                    '" ' +
                    'data-repeater-field-type="select">';

                Object.keys(
                    field.options || {}
                ).forEach(
                    function (optionValue) {

                        const selected =
                            String(value)
                            === String(optionValue);

                        html +=
                            '<option ' +
                            'value="' +
                            escapeAttribute(
                                optionValue
                            ) +
                            '"' +
                            (
                                selected
                                    ? ' selected'
                                    : ''
                            ) +
                            '>' +
                            escapeHtml(
                                field.options[
                                    optionValue
                                ]
                            ) +
                            '</option>';
                    }
                );

                html +=
                    '</select>';

                break;


            case 'image':

                html +=
                    renderRepeaterImageField(
                        fieldKey,
                        value
                    );

                break;


            case 'repeater':

                html +=
                    renderRepeaterField(
                        'bb-repeater-field-' +
                            escapeAttribute(fieldKey),
                        fieldKey,
                        field,
                        value,
                        null,
                        true
                    );

                break;


            default:

                html +=
                    '<input ' +
                    'type="text" ' +
                    'class="widefat bb-repeater-field" ' +
                    'data-repeater-field-key="' +
                    escapeAttribute(
                        fieldKey
                    ) +
                    '" ' +
                    'data-repeater-field-type="text" ' +
                    'value="' +
                    escapeAttribute(
                        value
                    ) +
                    '">';

                break;
        }


        return html;
    }


    /**
     * ---------------------------------------------------------
     * Image Field Behavior (Media Library)
     * ---------------------------------------------------------
     *
     * One set of handlers for every `.bb-image-uploader-wrapper`
     * on the page, whether it belongs to a top-level field or
     * to a field nested inside a repeater item — both render
     * the exact same wrapper markup (see renderImageFieldMarkup),
     * so `closest('.bb-image-uploader-wrapper')` finds the right
     * one regardless of context.
     */

    $(document).on(
        'click',
        '.bb-select-image-btn',
        function (event) {

            event.preventDefault();

            const button =
                $(this);

            const wrapper =
                button.closest(
                    '.bb-image-uploader-wrapper'
                );

            const hiddenInput =
                wrapper.find(
                    '.bb-image-id'
                );

            const previewImg =
                wrapper.find(
                    '.bb-preview-img'
                );

            const removeBtn =
                wrapper.find(
                    '.bb-remove-image-btn'
                );

            if (
                typeof wp === 'undefined'
                || !wp.media
            ) {

                alert(
                    'The WordPress Media Library is not available on this screen.'
                );

                return;
            }

            const customUploader =
                wp.media({

                    title:
                        'Select Image',

                    button: {
                        text:
                            'Select this Image'
                    },

                    multiple:
                        false
                });

            customUploader.on(
                'select',
                function () {

                    const attachment =
                        customUploader
                            .state()
                            .get('selection')
                            .first()
                            .toJSON();

                    hiddenInput
                        .val(
                            attachment.id
                        )
                        .trigger(
                            'change'
                        );

                    const imgUrl =
                        (
                            attachment.sizes
                            && attachment.sizes.thumbnail
                        )
                            ? attachment.sizes.thumbnail.url
                            : attachment.url;

                    previewImg
                        .attr(
                            'src',
                            imgUrl
                        )
                        .show();

                    /*
                     * FIX: this used to be set in Arabic
                     * ("تغيير الصورة") here while the initial
                     * render-time label was in English
                     * ("Change Image"), so the button's text
                     * language flipped after the first
                     * selection. Kept consistent in English
                     * to match the rest of the builder's UI.
                     */
                    button.text(
                        'Change Image'
                    );

                    removeBtn.show();
                }
            );

            customUploader.open();
        }
    );


    $(document).on(
        'click',
        '.bb-remove-image-btn',
        function (event) {

            event.preventDefault();

            const button =
                $(this);

            const wrapper =
                button.closest(
                    '.bb-image-uploader-wrapper'
                );

            const hiddenInput =
                wrapper.find(
                    '.bb-image-id'
                );

            const previewImg =
                wrapper.find(
                    '.bb-preview-img'
                );

            const selectBtn =
                wrapper.find(
                    '.bb-select-image-btn'
                );

            hiddenInput
                .val('')
                .trigger('change');

            previewImg
                .attr('src', '')
                .hide();

            selectBtn.text(
                'Select Image'
            );

            button.hide();
        }
    );


    /**
     * Resolve preview thumbnails for every image field
     * already carrying a value inside the given container —
     * scoped to that container (not the whole document), and
     * called explicitly at the moments the markup actually
     * changes (initial editor render, repeater item added).
     * `.is-initialized` still guards against re-fetching the
     * same wrapper twice.
     */
    function initializeImageFields(
        container
    ) {

        container
            .find(
                '.bb-image-uploader-wrapper:not(.is-initialized)'
            )
            .each(
                function () {

                    const wrapper =
                        $(this);

                    const hiddenInput =
                        wrapper.find(
                            '.bb-image-id'
                        );

                    const attachmentId =
                        hiddenInput.val();

                    if (!attachmentId) {
                        return;
                    }

                    wrapper.addClass(
                        'is-initialized'
                    );

                    const previewImg =
                        wrapper.find(
                            '.bb-preview-img'
                        );

                    const selectBtn =
                        wrapper.find(
                            '.bb-select-image-btn'
                        );

                    const removeBtn =
                        wrapper.find(
                            '.bb-remove-image-btn'
                        );

                    if (
                        typeof wp === 'undefined'
                        || !wp.media
                    ) {

                        return;
                    }

                    const attachment =
                        wp.media.attachment(
                            attachmentId
                        );

                    attachment.fetch()
                        .then(
                            function () {

                                const sizes =
                                    attachment.get(
                                        'sizes'
                                    );

                                const imgUrl =
                                    (
                                        sizes
                                        && sizes.thumbnail
                                    )
                                        ? sizes.thumbnail.url
                                        : attachment.get(
                                            'url'
                                        );

                                if (imgUrl) {

                                    previewImg
                                        .attr(
                                            'src',
                                            imgUrl
                                        )
                                        .show();

                                    selectBtn.text(
                                        'Change Image'
                                    );

                                    removeBtn.show();
                                }
                            }
                        )
                        .fail(
                            function () {

                                /*
                                 * Attachment could not be
                                 * fetched (e.g. deleted from
                                 * the Media Library). Allow a
                                 * retry later instead of
                                 * silently leaving a broken
                                 * preview permanently marked
                                 * as initialized.
                                 */
                                wrapper.removeClass(
                                    'is-initialized'
                                );
                            }
                        );
                }
            );
    }


    /**
     * ---------------------------------------------------------
     * Initialize Dynamic Fields
     * ---------------------------------------------------------
     */

    function initializeDynamicFields(
        container
    ) {

        container
            .find('.bb-color-field')
            .each(
                function () {

                    const input =
                        $(this);

                    if (
                        !isValidColor(
                            input.val()
                        )
                    ) {

                        input.val(
                            '#000000'
                        );
                    }
                }
            );

        initializeImageFields(
            container
        );
    }


    /**
     * ---------------------------------------------------------
     * Repeater Add
     * ---------------------------------------------------------
     */

    $(document).on(
        'click',
        '.bb-repeater-add',
        function (event) {

            event.preventDefault();

            const button =
                $(this);

            const repeater =
                button.closest(
                    '.bb-repeater'
                );

            const fields =
                parseRepeaterFields(
                    repeater
                );

            const items =
                repeater.find(
                    '.bb-repeater-items'
                );

            const index =
                items.children(
                    '.bb-repeater-item'
                ).length;

            const item =
                {};

            if (
                Object.keys(fields).length
            ) {

                Object.keys(fields).forEach(
                    function (key) {

                        item[key] =
                            fields[key].default;
                    }
                );
            }

            /*
             * FIX: wrap the rendered HTML with $() so we get
             * a jQuery reference to the newly added item and
             * can scope initializeImageFields() to it — new
             * items normally start with no image value, but
             * this keeps behavior correct if a repeater's
             * sub-field ever ships with a non-empty default.
             */
            const $newItem =
                $(
                    renderRepeaterItem(
                        {
                            fields:
                                fields
                        },
                        item,
                        index
                    )
                );

            items.append(
                $newItem
            );

            initializeImageFields(
                $newItem
            );
        }
    );


    /**
     * ---------------------------------------------------------
     * Repeater Remove
     * ---------------------------------------------------------
     */

    $(document).on(
        'click',
        '.bb-repeater-remove',
        function (event) {

            event.preventDefault();

            const button =
                $(this);

            const repeater =
                button.closest(
                    '.bb-repeater'
                );

            const items =
                repeater.find(
                    '.bb-repeater-items'
                );

            button
                .closest(
                    '.bb-repeater-item'
                )
                .remove();

            /*
             * Keep at least one item in the editor.
             */
            if (
                !items.children(
                    '.bb-repeater-item'
                ).length
            ) {

                const fields =
                    parseRepeaterFields(
                        repeater
                    );

                items.append(
                    renderRepeaterItem(
                        {
                            fields:
                                fields
                        },
                        {},
                        0
                    )
                );
            }
        }
    );


    /**
     * Parse repeater fields.
     */
    function parseRepeaterFields(
        repeater
    ) {

        const raw =
            repeater.attr(
                'data-bb-repeater-fields'
            );

        if (!raw) {

            return {};
        }

        try {

            const decoded =
                JSON.parse(
                    raw
                );

            return isObject(decoded)
                ? decoded
                : {};

        } catch (error) {

            return {};
        }
    }


    /**
     * Collect repeater values.
     */
    function collectRepeaterValue(
        repeater
    ) {

        const result =
            [];

        repeater
            .find(
                '.bb-repeater-items > .bb-repeater-item'
            )
            .each(
                function () {

                    const item =
                        $(this);

                    const fields =
                        item.find(
                            '.bb-repeater-field'
                        )
                        .filter(
                            function () {

                                const field =
                                    $(this);

                                const closestItem =
                                    field.closest(
                                        '.bb-repeater-item'
                                    );

                                return !closestItem.length
                                    || closestItem.is(
                                        item
                                    );
                            }
                        );

                    if (!fields.length) {

                        const scalar =
                            item.find(
                                '.bb-repeater-scalar'
                            ).val();

                        result.push(
                            scalar || ''
                        );

                        return;
                    }

                    const data =
                        {};

                    fields.each(
                        function () {

                            const field =
                                $(this);

                            const key =
                                field.data(
                                    'repeater-field-key'
                                );

                            if (!key) {
                                return;
                            }

                            const type =
                                field.data(
                                    'repeater-field-type'
                                );

                            if (
                                type ===
                                'checkbox'
                            ) {

                                data[key] =
                                    field.is(
                                        ':checked'
                                    );

                            } else if (
                                type ===
                                'repeater'
                            ) {

                                data[key] =
                                    collectRepeaterValue(
                                        field
                                    );

                            } else {

                                /*
                                 * Covers 'image' too: the
                                 * hidden input's .val() is
                                 * the attachment ID string,
                                 * exactly what PHP's
                                 * sanitize_single_value()
                                 * expects for an image field
                                 * (it runs absint() on it).
                                 */
                                data[key] =
                                    field.val();
                            }
                        }
                    );

                    result.push(
                        data
                    );
                }
            );

        return result;
    }


    /**
     * ---------------------------------------------------------
     * Save Section
     * ---------------------------------------------------------
     */

    function saveActiveSectionFromPopup(popupDocument) {

        const body =
            popupDocument
            && popupDocument.find
                ? popupDocument.find('#bb-section-editor-body')
                : getEditorDocumentContext().find('#bb-section-editor-body');

        saveSectionFromBody(body);
    }

    function saveSectionFromBody(body) {

        const pageId =
            getPageId();

        if (
            !pageId
            || !currentSectionId
        ) {

            return;
        }

        const ajax =
            getAjaxConfig();

        if (!ajax) {

            alert(
                'Business Builder AJAX configuration is missing.'
            );

            return;
        }

        const button =
            getEditorDocumentContext().find('#bb-save-section');

        if (!button.length) {
            return;
        }

        const validation =
            validateEditor(
                body
            );

        if (!validation.valid) {

            alert(
                validation.message
            );

            if (
                validation.element
            ) {

                validation.element
                    .focus();
            }

            return;
        }

        const content =
            collectGroupValues(
                body,
                'content'
            );

        const settings =
            collectGroupValues(
                body,
                'settings'
            );

        renderEditorPreview(
            content,
            settings,
            currentSectionType
        );

        button.prop(
            'disabled',
            true
        );

        button.addClass(
            'is-loading'
        );

        $.ajax({

            url: ajax.ajaxUrl,

            type: 'POST',

            dataType: 'json',

            data: {

                action:
                    'bb_update_section',

                nonce:
                    ajax.nonce,

                page_id:
                    pageId,

                section_id:
                    currentSectionId,

                settings:
                    JSON.stringify(
                        settings
                    ),

                content:
                    JSON.stringify(
                        content
                    )
            },

            success: function (response) {

                if (
                    !response
                    || !response.success
                ) {

                    const message =
                        response
                        && response.data
                        && response.data.message
                            ? response.data.message
                            : 'Could not save section.';

                    alert(message);

                    return;
                }

                closeSectionEditor();

                window.location.reload();
            },

            error: function (xhr) {

                let message =
                    'An unexpected error occurred.';

                if (
                    xhr.responseJSON
                    && xhr.responseJSON.data
                    && xhr.responseJSON.data.message
                ) {

                    message =
                        xhr.responseJSON.data.message;
                }

                alert(message);
            },

            complete: function () {

                button.prop(
                    'disabled',
                    false
                );

                button.removeClass(
                    'is-loading'
                );
            }
        });
    }

    $(document).on(
        'click',
        '#bb-save-section',
        function (event) {

            event.preventDefault();

            saveSectionFromBody(
                getEditorDocumentContext().find('#bb-section-editor-body')
            );
        }
    );


    /**
     * ---------------------------------------------------------
     * Collect Group Values
     * ---------------------------------------------------------
     */

    function collectGroupValues(
        body,
        group
    ) {

        const result =
            {};

        body
            .find(
                '[data-bb-field-key]'
            )
            .each(
                function () {

                    const field =
                        $(this);

                    const wrapper =
                        field.closest(
                            '[data-bb-field-wrapper]'
                        );

                    /*
                     * Determine whether the field belongs
                     * to the requested group.
                     */
                    const id =
                        field.attr(
                            'id'
                        ) || '';

                    if (
                        id.indexOf(
                            'bb-editor-' +
                            group +
                            '-'
                        ) !== 0
                    ) {

                        /*
                         * Repeater fields do not have
                         * the group prefix on their
                         * internal controls, so ignore
                         * them here.
                         */
                        return;
                    }

                    const key =
                        field.data(
                            'bb-field-key'
                        );

                    if (!key) {
                        return;
                    }

                    const type =
                        field.data(
                            'bb-field-type'
                        );

                    if (
                        type === 'checkbox'
                    ) {

                        result[key] =
                            field.is(
                                ':checked'
                            );

                    } else if (
                        type === 'repeater'
                    ) {

                        result[key] =
                            collectRepeaterValue(
                                field
                            );

                    } else if (
                        type === 'number'
                    ) {

                        const value =
                            field.val();

                        if (
                            value === ''
                            || value === null
                        ) {

                            result[key] =
                                '';

                        } else if (
                            String(value)
                                .indexOf('.') !== -1
                        ) {

                            result[key] =
                                parseFloat(
                                    value
                                );

                        } else {

                            result[key] =
                                parseInt(
                                    value,
                                    10
                                );
                        }

                    } else if (
                        type === 'select'
                        && field.prop(
                            'multiple'
                        )
                    ) {

                        /*
                         * FIX (matching PHP): read multi-select
                         * values explicitly as an array so the
                         * backend receives a real array instead
                         * of a single scalar via .val().
                         */
                        result[key] =
                            field.val() || [];

                    } else {

                        result[key] =
                            field.val();
                    }
                }
            );

        return result;
    }


    /**
     * Validate a repeater item against its schema, including
     * nested repeater fields recursively.
     */
    function validateRepeaterItemSchema(
        fieldsSchema,
        item,
        labelPrefix
    ) {

        if (!isObject(fieldsSchema)) {
            return {
                valid: true,
                message: '',
                element: null
            };
        }

        if (!isObject(item)) {
            return {
                valid: true,
                message: '',
                element: null
            };
        }

        for (
            const key in fieldsSchema
        ) {

            if (
                !Object.prototype.hasOwnProperty.call(
                    fieldsSchema,
                    key
                )
            ) {
                continue;
            }

            const field = normalizeFieldSchema(
                key,
                fieldsSchema[key]
            );

            const value = item[key];

            if (
                field.required
                && (
                    value === ''
                    || value === null
                    || typeof value === 'undefined'
                    || (
                        Array.isArray(value)
                        && value.length === 0
                    )
                )
                && field.type !== 'checkbox'
            ) {
                return {
                    valid: false,
                    message: labelPrefix + ' > ' + field.label + ' is required.',
                    element: null
                };
            }

            if (
                field.type === 'number'
                && value !== ''
                && value !== null
                && typeof value !== 'undefined'
            ) {

                const numericValue = parseFloat(value);

                if (
                    field.min !== null
                    && !isNaN(numericValue)
                    && numericValue < parseFloat(field.min)
                ) {
                    return {
                        valid: false,
                        message: labelPrefix + ' > ' + field.label + ' must be at least ' + field.min + '.',
                        element: null
                    };
                }

                if (
                    field.max !== null
                    && !isNaN(numericValue)
                    && numericValue > parseFloat(field.max)
                ) {
                    return {
                        valid: false,
                        message: labelPrefix + ' > ' + field.label + ' must be at most ' + field.max + '.',
                        element: null
                    };
                }
            }

            if (
                field.type === 'repeater'
                && Array.isArray(value)
            ) {

                for (
                    let index = 0;
                    index < value.length;
                    index++
                ) {

                    const nestedValidation = validateRepeaterItemSchema(
                        field.fields || {},
                        value[index],
                        labelPrefix + ' > ' + field.label + ' item ' + (index + 1)
                    );

                    if (!nestedValidation.valid) {
                        return nestedValidation;
                    }
                }
            }
        }

        return {
            valid: true,
            message: '',
            element: null
        };
    }

    function validateEditor(
        body
    ) {

        let result = {

            valid:
                true,

            message:
                '',

            element:
                null
        };


        if (
            !currentSectionSchema
        ) {

            return result;
        }


        const groups =
            [
                'content',
                'settings'
            ];


        for (
            let groupIndex = 0;
            groupIndex < groups.length;
            groupIndex++
        ) {

            const group =
                groups[groupIndex];

            const schema =
                currentSectionSchema[
                    group
                ] || {};


            for (
                const fieldKey
                in schema
            ) {

                if (
                    !Object.prototype
                        .hasOwnProperty
                        .call(
                            schema,
                            fieldKey
                        )
                ) {

                    continue;
                }


                const field =
                    normalizeFieldSchema(
                        fieldKey,
                        schema[fieldKey]
                    );


                const selector =
                    '[data-bb-field-key="' +
                    escapeSelector(
                        fieldKey
                    ) +
                    '"]';


                const element =
                    body
                        .find(
                            selector
                        )
                        .filter(
                            function () {

                                const id =
                                    $(this)
                                        .attr(
                                            'id'
                                        ) || '';

                                return (
                                    id.indexOf(
                                        'bb-editor-' +
                                        group +
                                        '-'
                                    ) === 0
                                );
                            }
                        )
                        .first();


                if (!element.length) {

                    continue;
                }


                /*
                 * Required
                 */
                if (
                    field.required
                ) {

                    let value =
                        '';

                    const type =
                        element.data(
                            'bb-field-type'
                        );

                    if (
                        type ===
                        'checkbox'
                    ) {

                        value =
                            element.is(
                                ':checked'
                            );

                    } else if (
                        type ===
                        'repeater'
                    ) {

                        value =
                            collectRepeaterValue(
                                element
                            );

                    } else {

                        value =
                            element.val();
                    }


                    const empty =
                        Array.isArray(value)
                            ? value.length === 0
                            : (
                                value === ''
                                || value === null
                                || typeof value ===
                                    'undefined'
                            );


                    if (
                        empty
                        && type !==
                            'checkbox'
                    ) {

                        result.valid =
                            false;

                        result.message =
                            field.label +
                            ' is required.';

                        result.element =
                            element;

                        return result;
                    }

                    if (
                        type === 'repeater'
                        && Array.isArray(value)
                    ) {

                        for (
                            let index = 0;
                            index < value.length;
                            index++
                        ) {

                            const nestedValidation = validateRepeaterItemSchema(
                                field.fields || {},
                                value[index],
                                field.label + ' item ' + (index + 1)
                            );

                            if (!nestedValidation.valid) {
                                result.valid = false;
                                result.message = nestedValidation.message;
                                result.element = element;
                                return result;
                            }
                        }
                    }
                }


                /*
                 * Number min / max
                 */
                if (
                    field.type ===
                    'number'
                ) {

                    const rawValue =
                        element.val();

                    if (
                        rawValue !== ''
                    ) {

                        const numericValue =
                            parseFloat(
                                rawValue
                            );


                        if (
                            field.min !== null
                            && !isNaN(
                                numericValue
                            )
                            && numericValue <
                                parseFloat(
                                    field.min
                                )
                        ) {

                            result.valid =
                                false;

                            result.message =
                                field.label +
                                ' must be at least ' +
                                field.min +
                                '.';

                            result.element =
                                element;

                            return result;
                        }


                        if (
                            field.max !== null
                            && !isNaN(
                                numericValue
                            )
                            && numericValue >
                                parseFloat(
                                    field.max
                                )
                        ) {

                            result.valid =
                                false;

                            result.message =
                                field.label +
                                ' must be at most ' +
                                field.max +
                                '.';

                            result.element =
                                element;

                            return result;
                        }
                    }
                }
            }
        }


        return result;
    }


    /**
     * ---------------------------------------------------------
     * Close Editor
     * ---------------------------------------------------------
     */

    $(document).on(
        'click',
        '.bb-close-section-editor, .bb-section-editor-overlay',
        function () {

            closeSectionEditor();
        }
    );


    function closeSectionEditor() {

        $('#bb-section-editor').hide();
        $('#bb-live-preview-modal').hide();

        currentSectionId =
            null;

        currentSectionType =
            null;

        currentSectionSchema =
            null;
    }


    /**
     * ---------------------------------------------------------
     * Utility
     * ---------------------------------------------------------
     */

    function prettifyFieldName(
        fieldKey
    ) {

        return String(
            fieldKey || ''
        )
            .replace(
                /[_-]+/g,
                ' '
            )
            .replace(
                /\b\w/g,
                function (letter) {

                    return letter.toUpperCase();
                }
            );
    }


    function isValidColor(
        value
    ) {

        return /^#[0-9A-Fa-f]{6}$/.test(
            String(value || '')
        );
    }

});


