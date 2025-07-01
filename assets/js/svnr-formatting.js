jQuery(document).ready(function($) {
    // SVNR formatting for field ID 53 and any field with svnr-field class
    $(document).on('input', '#field_1_53 input, .svnr-field input', function() {
        var $input = $(this);
        var value = $input.val().replace(/\D/g, ''); // Remove all non-digits
        
        // Limit to 10 digits
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        
        // Format as XX XXXX XX XX
        var formatted = '';
        for (var i = 0; i < value.length; i++) {
            if (i === 2 || i === 6 || i === 8) {
                formatted += ' ';
            }
            formatted += value[i];
        }
        
        $input.val(formatted);
    });
    
    // Add visual feedback for SVNR validation
    $(document).on('blur', '#field_1_53 input, .svnr-field input', function() {
        var $input = $(this);
        var value = $input.val().replace(/\D/g, '');
        var $field = $input.closest('.gfield');
        
        // Remove previous validation classes
        $field.removeClass('svnr-valid svnr-invalid');
        
        if (value.length === 0) {
            return; // Let Gravity Forms handle required validation
        }
        
        if (value.length === 10 && validateSVNRChecksum(value)) {
            $field.addClass('svnr-valid');
        } else {
            $field.addClass('svnr-invalid');
        }
    });
    
    // SVNR checksum validation function
    function validateSVNRChecksum(svnr) {
        if (svnr.length !== 10) {
            return false;
        }
        
        var digits = svnr.split('').map(Number);
        var weights = [3, 7, 9, 5, 8, 4, 2, 1, 6];
        var sum = 0;
        
        for (var i = 0; i < 9; i++) {
            sum += digits[i] * weights[i];
        }
        
        var checkDigit = (sum % 11) === 10 ? 0 : sum % 11;
        
        return checkDigit === digits[9];
    }
    
    // Add CSS for visual feedback
    if (!$('#rt-employee-svnr-styles').length) {
        $('<style id="rt-employee-svnr-styles">')
            .text(`
                .svnr-valid {
                    border-left: 4px solid #46b450 !important;
                }
                .svnr-invalid {
                    border-left: 4px solid #dc3232 !important;
                }
                .svnr-valid .ginput_container input {
                    border-color: #46b450;
                    box-shadow: 0 0 2px rgba(70, 180, 80, 0.3);
                }
                .svnr-invalid .ginput_container input {
                    border-color: #dc3232;
                    box-shadow: 0 0 2px rgba(220, 50, 50, 0.3);
                }
            `)
            .appendTo('head');
    }
    
    // Phone number formatting (optional enhancement)
    $(document).on('input', '#field_3_10 input', function() {
        var $input = $(this);
        var value = $input.val().replace(/\D/g, '');
        
        if (value.startsWith('43')) {
            // Austrian format: +43 XXX XXXXXXX
            var formatted = '+43';
            if (value.length > 2) {
                formatted += ' ' + value.substring(2, 5);
            }
            if (value.length > 5) {
                formatted += ' ' + value.substring(5);
            }
            $input.val(formatted);
        }
    });
    
    // Real-time form validation feedback
    $(document).on('change', '.gform_wrapper input, .gform_wrapper select, .gform_wrapper textarea', function() {
        var $input = $(this);
        var $field = $input.closest('.gfield');
        
        // Remove any previous custom validation classes
        $field.removeClass('rt-field-valid rt-field-invalid');
        
        if ($input.val().length > 0) {
            $field.addClass('rt-field-valid');
        }
    });
    
    // Add loading state during form submission
    $(document).on('submit', '.gform_wrapper form', function() {
        var $form = $(this);
        var $submitButton = $form.find('input[type="submit"]');
        
        $submitButton.prop('disabled', true);
        $submitButton.val('Wird gesendet...');
        
        // Add loading spinner
        if (!$form.find('.rt-loading-spinner').length) {
            $submitButton.after('<span class="rt-loading-spinner">⏳</span>');
        }
    });
});