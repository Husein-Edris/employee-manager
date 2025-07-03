/**
 * RT Employee Manager Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize admin functionality
        initFormEnhancements();
        hideUnnecessaryElements();
    });

    /**
     * Initialize form enhancements
     */
    function initFormEnhancements() {
        // Format SVNR field automatically
        $('input[name="acf[field_svnr]"]').on('input', function() {
            var value = $(this).val().replace(/\D/g, '');
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            $(this).val(value);
        });

        // Auto-update post title when name fields change
        $('input[name="acf[field_vorname]"], input[name="acf[field_nachname]"]').on('blur', function() {
            var vorname = $('input[name="acf[field_vorname]"]').val();
            var nachname = $('input[name="acf[field_nachname]"]').val();
            
            if (vorname && nachname) {
                var newTitle = vorname + ' ' + nachname;
                $('#title').val(newTitle);
            }
        });
    }

    /**
     * Hide unnecessary elements for kunden users
     */
    function hideUnnecessaryElements() {
        // Hide file upload metabox for employee posts
        if ($('#post').attr('data-post-type') === 'angestellte') {
            $('#postimagediv').hide();
            $('#media-buttons').hide();
            
            // Remove visual editor tabs
            $('#wp-content-wrap .wp-editor-tabs').hide();
            
            // Hide content editor for employee posts (we use ACF fields)
            $('#postdivrich').hide();
            
            // Hide excerpt
            $('#postexcerpt').hide();
            
            // Hide comments
            $('#commentstatusdiv').hide();
            
            // Hide trackbacks
            $('#trackbacksdiv').hide();
            
            // Hide custom fields (we use ACF)
            $('#postcustom').hide();
            
            // Hide page attributes
            $('#pageparentdiv').hide();
        }
    }

})(jQuery);