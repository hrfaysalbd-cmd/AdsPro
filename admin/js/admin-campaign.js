jQuery(document).ready(function($) {
    'use strict';

    // --- Type-based field toggling ---
    const $typeSelect = $('#adcp_type');
    const $overlayFields = $('.adcp-type-overlay-show');
    const $embedFields = $('.adcp-type-embed-show');
    
    function toggleTypeOptions() {
        const type = $typeSelect.val();
        
        if (type === 'embed') {
            // SHOW embed-specific fields (Shortcode)
            $embedFields.show();
            // Enable its inputs
            $embedFields.find('input, textarea, select, button').prop('disabled', false);

            // HIDE overlay-specific fields (Targeting, Device, Frequency Capping)
            $overlayFields.hide();
            // --- FIX: Disable inputs so they don't submit ---
            $overlayFields.find('input, textarea, select').prop('disabled', true);
            
        } else {
            // HIDE embed-specific fields (Shortcode)
            $embedFields.hide();
            // --- FIX: Disable its inputs ---
            $embedFields.find('input, textarea, select, button').prop('disabled', true);

            // SHOW overlay-specific fields (Targeting, Device, Frequency Capping)
            $overlayFields.show();
            // Enable their inputs
            $overlayFields.find('input, textarea, select').prop('disabled', false);
        }
    }
    
    // Call on load and on change
    $typeSelect.on('change', toggleTypeOptions).trigger('change');
    

    // --- Media Uploader ---
    var mediaUploader;

    $('#adcp_upload_creative_button').on('click', function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Campaign Creative',
            button: {
                text: 'Use this Creative'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // Set the URL to the input field
            $('#adcp_creative_url').val(attachment.url);
            
            // Set the MIME type
            $('#adcp_creative_type').val(attachment.mime);

            // Show a preview
            var $preview = $('#adcp_creative_preview');
            $preview.empty();

            if (attachment.type === 'image') {
                $preview.html('<img src="' + attachment.url + '" style="max-width:100%; height:auto; max-height: 200px;">');
            } else if (attachment.type === 'video') {
                 $preview.html('<video controls src="' + attachment.url + '" style="max-width:100%; height:auto; max-height: 200px;"></video>');
            } else {
                 $preview.html('<p>File selected: <code>' + attachment.url + '</code></p>');
            }
        });

        mediaUploader.open();
    });

    // --- FIX: Toggle Creative Type ---
    $('input[name="creative_source"]').on('change', function() {
        if (this.value === 'upload') {
            $('#adcp-creative-upload-wrapper').show();
            $('#adcp-creative-html-wrapper').hide();
        } else {
            $('#adcp-creative-upload-wrapper').hide();
            $('#adcp-creative-html-wrapper').show();
        }
    });
    
    // --- FIX: Trigger change ONLY on the *checked* radio button on page load ---
    // This fixes the bug where the HTML box was showing by default
    $('input[name="creative_source"]:checked').trigger('change');
    
    
    // --- UPDATED: Placement "Select All" ---
    // This jQuery finds the "Select All" checkbox in the new meta box
    // and applies its checked state to all other checkboxes in that box.
    $('#adcp_placement_select_all').on('change', function() {
        // Find all checkboxes with the class 'adcp-placement-checkbox'
        const $checkboxes = $(this).closest('.postbox').find('input.adcp-placement-checkbox');
        $checkboxes.prop('checked', $(this).prop('checked'));
    });


    // --- PREVIEW FUNCTIONALITY ---
    
    // Inject styles for the preview modal
    const previewStyles = `
        <style id="adcp-preview-styles">
            #adcp-live-preview-wrapper {
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.6); z-index: 999998;
                display: flex; align-items: center; justify-content: center;
                flex-direction: column;
            }
            #adcp-preview-controls {
                background: #fff; padding: 5px; border-radius: 6px 6px 0 0;
                border-bottom: 1px solid #ccc;
            }
            #adcp-preview-controls button {
                padding: 4px 12px; margin: 0; border: 1px solid #ccc;
                background: #f9f9f9; cursor: pointer;
            }
            #adcp-preview-controls button.active {
                background: #0073aa; color: #fff; border-color: #0073aa;
            }
            #adcp-preview-container {
                background: #e9e9e9; padding: 20px;
                border: 1px solid #fff; overflow: hidden;
                transition: all 0.3s ease-in-out;
            }
            #adcp-preview-iframe {
                border: 2px solid #333;
                background: #fff;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                transition: all 0.3s ease-in-out;
            }
            #adcp-preview-container.desktop #adcp-preview-iframe {
                width: 100%; height: 600px; max-width: 1000px;
            }
            #adcp-preview-container.tablet #adcp-preview-iframe {
                width: 768px; height: 1024px; max-height: 80vh;
            }
            #adcp-preview-container.mobile #adcp-preview-iframe {
                width: 375px; height: 667px; max-height: 80vh;
            }
        </style>
    `;
    $('head').append(previewStyles);

    $('#adcp_preview_ad').on('click', function() {
        // 1. Remove existing preview
        $('#adcp-live-preview-wrapper').remove();

        // 2. Get form values
        const creative_source = $('input[name="creative_source"]:checked').val();
        let creative_html = '';
        let creative_url = '';
        let creative_type = '';
        let headline = '';
        let subtext = '';
        let cta_label = '';
        let cta_url = '';

        if (creative_source === 'html') {
            creative_html = $('textarea[name="config[creative_html]"]').val();
        } else {
            creative_url = $('input[name="config[creative_url]"]').val();
            creative_type = $('input[name="config[creative_type]"]').val();
            headline = $('input[name="config[headline]"]').val();
            subtext = $('textarea[name="config[subtext]"]').val();
            cta_label = $('input[name="config[cta_label]"]').val();
            cta_url = $('input[name="config[cta_url]"]').val();
        }

        // 3. Build a fake campaign object
        const c = {
            id: 'preview',
            type: $('#adcp_type').val(), // 'popup', 'slide', 'scroll', or 'embed'
            config: { headline, subtext, cta_label, cta_url },
            creative: { url: creative_url, type: creative_type, html: creative_html }
        };

        // 4. Build the ad element HTML
        const adHTML = buildElementHTML(c);
        
        // 5. Create the preview modal structure
        const $wrapper = $(`
            <div id="adcp-live-preview-wrapper">
                <div id="adcp-preview-controls">
                    <button class="active" data-mode="desktop">Desktop</button>
                    <button data-mode="tablet">Tablet</button>
                    <button data-mode="mobile">Mobile</button>
                </div>
                <div id="adcp-preview-container" class="desktop">
                    <iframe id="adcp-preview-iframe"></iframe>
                </div>
            </div>
        `);
        
        // 6. Append to body and inject content into iframe
        $('body').append($wrapper);
        const iframeDoc = $('#adcp-preview-iframe')[0].contentWindow.document;
        
        iframeDoc.open();
        iframeDoc.write(`
            <html>
                <head>
                    <link rel="stylesheet" href="${adcpCampaign.public_css_url}" type="text/css">
                    <style>
                        body { 
                            margin: 0; 
                            padding: 20px; /* Add padding to body */
                            background: #f0f0f0; /* Show a background */
                            font-family: Arial, sans-serif;
                        }
                        /* Ensure ad is visible immediately for preview */
                        .adcp-wrapper, .adcp-embed-wrapper {
                            opacity: 1 !important;
                            transform: none !important;
                            position: relative !important;
                            top: 0 !important; 
                            left: 0 !important;
                        }
                        /* Special rules for overlay types in preview */
                        .adcp-type-popup { 
                            top: 50% !important; 
                            left: 50% !important; 
                            transform: translate(-50%, -50%) !important; 
                            position: absolute !important; 
                        }
                        .adcp-type-slide { 
                            bottom: 20px !important; 
                            right: 20px !important; 
                            top: auto !important; 
                            left: auto !important; 
                            position: absolute !important; 
                        }
                        .adcp-type-scroll { 
                            top: 0 !important; 
                            left: 0 !important; 
                            right: 0 !important; 
                            position: absolute !important; 
                        }
                        /* Let the embed card style itself, just center it */
                        .adcp-type-embed { 
                            margin: 20px auto !important; 
                        }
                    </style>
                </head>
                <body>
                    ${adHTML}
                </body>
            </html>
        `);
        iframeDoc.close();
        
        // 7. Add event handlers for modal
        $wrapper.on('click', function(e) {
            if (e.target === this) {
                $(this).remove();
            }
        });

        $wrapper.find('#adcp-preview-controls button').on('click', function() {
            const mode = $(this).data('mode');
            $wrapper.find('#adcp-preview-controls button').removeClass('active');
            $(this).addClass('active');
            $wrapper.find('#adcp-preview-container').removeClass('desktop tablet mobile').addClass(mode);
        });

        // 8. Make ad visible
        setTimeout(() => {
            $(iframeDoc.body).find('.adcp-wrapper, .adcp-embed-wrapper').addClass('adcp-visible');
        }, 50);
    });

    /**
     * buildElementHTML
     * (Adapted from public/js/tracker.js - returns string)
     */
    function buildElementHTML(c) {
        const wrapperClass = (c.type === 'embed') ? 'adcp-embed-wrapper' : 'adcp-wrapper';
        const elClass = `${wrapperClass} adcp-type-${c.type}`;
        
        let innerHTML = '';
        if (c.type !== 'embed') {
            innerHTML = '<button class="adcp-close" aria-label="Close Ad" onclick="event.preventDefault(); alert(\'Close button clicked.\');">&times;</button>';
        }

        innerHTML += '<div class="adcp-content-wrap">';

        if (c.creative.html) {
            if (c.type === 'embed') {
                 innerHTML += `<div class="adcp-creative-html">${c.creative.html}</div>`;
            } else {
                innerHTML += c.creative.html;
            }
        } else {
            if (c.creative.url) {
                let creative_html = '';
                if (c.creative.type.startsWith('image/')) {
                    creative_html = `<img src="${c.creative.url}" alt="${c.config.headline}" class="adcp-creative-img">`;
                    
                    if (c.config.cta_url) {
                        innerHTML += `<a href="${c.config.cta_url}" target="_blank" class="adcp-creative-img-link" onclick="event.preventDefault();">${creative_html}</a>`;
                    } else {
                        innerHTML += creative_html;
                    }

                } else if (c.creative.type.startsWith('video/')) {
                    innerHTML += `<video controls autoplay muted src="${c.creative.url}" class="adcp-creative-video"></video>`;
                }
            }
            if (c.config.headline) {
                innerHTML += `<h3 class="adcp-headline">${c.config.headline}</h3>`;
            }
            if (c.config.subtext) {
                innerHTML += `<p class="adcp-subtext">${c.config.subtext}</p>`;
            }
            if (c.config.cta_label && c.config.cta_url) {
                innerHTML += `<a href="${c.config.cta_url}" target="_blank" class="adcp-cta" onclick="event.preventDefault(); alert('CTA Click (Preview Mode)');">${c.config.cta_label}</a>`;
            }
        }
        
        innerHTML += '</div>'; // --- END CONTENT WRAPPER ---
        
        return `<div class="${elClass}" data-campaign-id="${c.id}">${innerHTML}</div>`;
    }
    // --- END PREVIEW FUNCTIONALITY ---
});