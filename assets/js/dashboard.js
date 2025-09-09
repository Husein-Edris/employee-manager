jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize dashboard
    initDashboard();
    
    function initDashboard() {
        handleStatusChanges();
        handleEmployeeActions();
        handleModal();
        initTooltips();
        initRealTimeUpdates();
    }
    
    // Handle status changes
    function handleStatusChanges() {
        $(document).on('change', '.rt-status-select', function() {
            const $select = $(this);
            const employeeId = $select.data('employee-id');
            const newStatus = $select.val();
            const $row = $select.closest('tr');
            
            if (!employeeId || !newStatus) {
                return;
            }
            
            // Show loading state
            $select.prop('disabled', true);
            $row.addClass('rt-loading');
            
            $.ajax({
                url: rtEmployeeDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rt_update_employee_status',
                    employee_id: employeeId,
                    status: newStatus,
                    nonce: rtEmployeeDashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update status badge
                        const $statusBadge = $row.find('.status-badge');
                        $statusBadge.removeClass('status-active status-inactive status-suspended status-terminated');
                        $statusBadge.addClass('status-' + newStatus);
                        $statusBadge.text(getStatusLabel(newStatus));
                        
                        // Update row class
                        $row.removeClass('status-active status-inactive status-suspended status-terminated');
                        $row.addClass('status-' + newStatus);
                        
                        // Update statistics with server data
                        if (response.data.stats) {
                            updateStatisticsWithData(response.data.stats);
                        } else {
                            updateStatistics();
                        }
                        
                        showNotification(response.data.message, 'success');
                    } else {
                        showNotification(response.data.message || rtEmployeeDashboard.strings.error, 'error');
                        // Revert selection
                        $select.val($select.data('original-value'));
                    }
                },
                error: function() {
                    showNotification(rtEmployeeDashboard.strings.error, 'error');
                    $select.val($select.data('original-value'));
                },
                complete: function() {
                    $select.prop('disabled', false);
                    $row.removeClass('rt-loading');
                }
            });
        });
        
        // Store original values
        $('.rt-status-select').each(function() {
            $(this).data('original-value', $(this).val());
        });
    }
    
    // Handle employee actions (edit, delete)
    function handleEmployeeActions() {
        // Edit button
        $(document).on('click', '.rt-btn-edit', function(e) {
            e.preventDefault();
            
            const employeeId = $(this).data('employee-id');
            if (!employeeId) return;
            
            openEditModal(employeeId);
        });
        
        // Delete button
        $(document).on('click', '.rt-btn-delete', function(e) {
            e.preventDefault();
            
            const employeeId = $(this).data('employee-id');
            const employeeName = $(this).data('employee-name');
            
            if (!employeeId) return;
            
            if (confirm(rtEmployeeDashboard.strings.confirmDelete.replace('%s', employeeName))) {
                deleteEmployee(employeeId);
            }
        });
    }
    
    // Open edit modal
    function openEditModal(employeeId) {
        const $modal = $('#rt-employee-edit-modal');
        const $form = $('#rt-employee-edit-form');
        
        // Show loading
        $form.html('<div class="loading">Lädt...</div>');
        $modal.show();
        
        $.ajax({
            url: rtEmployeeDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'rt_get_employee_data',
                employee_id: employeeId,
                nonce: rtEmployeeDashboard.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderEditForm(employeeId, response.data);
                } else {
                    $form.html('<div class="error">Fehler beim Laden der Daten</div>');
                }
            },
            error: function() {
                $form.html('<div class="error">Fehler beim Laden der Daten</div>');
            }
        });
    }
    
    // Render edit form
    function renderEditForm(employeeId, data) {
        const fields = data.fields;
        let formHtml = '<form id="rt-employee-edit-form-inner" data-employee-id="' + employeeId + '">';
        
        // Generate form fields based on ACF fields
        const fieldOrder = ['vorname', 'nachname', 'sozialversicherungsnummer', 'geburtsdatum', 'eintrittsdatum', 'bezeichnung_der_tatigkeit', 'art_des_dienstverhaltnisses', 'arbeitszeit_pro_woche', 'gehaltlohn', 'type', 'status'];
        
        fieldOrder.forEach(function(fieldName) {
            if (fields.hasOwnProperty(fieldName)) {
                formHtml += generateFieldHtml(fieldName, fields[fieldName]);
            }
        });
        
        formHtml += '<div class="form-actions">';
        formHtml += '<button type="submit" class="rt-btn rt-btn-primary">Speichern</button>';
        formHtml += '<button type="button" class="rt-btn rt-btn-secondary rt-modal-cancel">Abbrechen</button>';
        formHtml += '</div>';
        formHtml += '</form>';
        
        $('#rt-employee-edit-form').html(formHtml);
        
        // Handle form submission
        handleEditFormSubmission();
    }
    
    // Generate field HTML
    function generateFieldHtml(fieldName, value) {
        const labels = {
            'vorname': 'Vorname',
            'nachname': 'Nachname',
            'sozialversicherungsnummer': 'Sozialversicherungsnummer',
            'geburtsdatum': 'Geburtsdatum',
            'eintrittsdatum': 'Eintrittsdatum',
            'bezeichnung_der_tatigkeit': 'Bezeichnung der Tätigkeit',
            'art_des_dienstverhaltnisses': 'Art des Dienstverhältnisses',
            'arbeitszeit_pro_woche': 'Arbeitszeit pro Woche',
            'gehaltlohn': 'Gehalt/Lohn',
            'type': 'Brutto/Netto',
            'status': 'Status'
        };
        
        const label = labels[fieldName] || fieldName;
        let inputHtml = '';
        
        switch (fieldName) {
            case 'art_des_dienstverhaltnisses':
                inputHtml = '<select name="' + fieldName + '" class="form-field">' +
                    '<option value="Angestellter"' + (value === 'Angestellter' ? ' selected' : '') + '>Angestellter</option>' +
                    '<option value="Arbeiter/in"' + (value === 'Arbeiter/in' ? ' selected' : '') + '>Arbeiter/in</option>' +
                    '<option value="Lehrling"' + (value === 'Lehrling' ? ' selected' : '') + '>Lehrling</option>' +
                    '</select>';
                break;
            
            case 'type':
                inputHtml = '<select name="' + fieldName + '" class="form-field">' +
                    '<option value="Brutto"' + (value === 'Brutto' ? ' selected' : '') + '>Brutto</option>' +
                    '<option value="Netto"' + (value === 'Netto' ? ' selected' : '') + '>Netto</option>' +
                    '</select>';
                break;
            
            case 'status':
                inputHtml = '<select name="' + fieldName + '" class="form-field">' +
                    '<option value="active"' + (value === 'active' ? ' selected' : '') + '>Aktiv</option>' +
                    '<option value="inactive"' + (value === 'inactive' ? ' selected' : '') + '>Inaktiv</option>' +
                    '<option value="suspended"' + (value === 'suspended' ? ' selected' : '') + '>Gesperrt</option>' +
                    '<option value="terminated"' + (value === 'terminated' ? ' selected' : '') + '>Gekündigt</option>' +
                    '</select>';
                break;
            
            case 'geburtsdatum':
            case 'eintrittsdatum':
                inputHtml = '<input type="date" name="' + fieldName + '" value="' + formatDateForInput(value) + '" class="form-field" />';
                break;
            
            case 'arbeitszeit_pro_woche':
            case 'gehaltlohn':
                inputHtml = '<input type="number" name="' + fieldName + '" value="' + (value || '') + '" class="form-field" step="0.01" />';
                break;
            
            case 'sozialversicherungsnummer':
                inputHtml = '<input type="text" name="' + fieldName + '" value="' + (value || '') + '" class="form-field svnr-input" maxlength="13" />';
                break;
            
            default:
                inputHtml = '<input type="text" name="' + fieldName + '" value="' + (value || '') + '" class="form-field" />';
        }
        
        return '<div class="form-group">' +
            '<label for="' + fieldName + '">' + label + '</label>' +
            inputHtml +
            '</div>';
    }
    
    // Handle edit form submission
    function handleEditFormSubmission() {
        $(document).on('submit', '#rt-employee-edit-form-inner', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const employeeId = $form.data('employee-id');
            const formData = $form.serializeArray();
            
            // Show loading
            $form.find('button').prop('disabled', true);
            $form.addClass('rt-loading');
            
            // Prepare data for WordPress meta update
            const updateData = {
                action: 'rt_update_employee_data',
                employee_id: employeeId,
                nonce: rtEmployeeDashboard.nonce
            };
            
            formData.forEach(function(field) {
                updateData[field.name] = field.value;
            });
            
            $.ajax({
                url: rtEmployeeDashboard.ajaxurl,
                type: 'POST',
                data: updateData,
                success: function(response) {
                    if (response.success) {
                        showNotification('Mitarbeiter erfolgreich aktualisiert', 'success');
                        $('#rt-employee-edit-modal').hide();
                        // Refresh the page or update the table row
                        location.reload();
                    } else {
                        showNotification(response.data.message || 'Fehler beim Speichern', 'error');
                    }
                },
                error: function() {
                    showNotification('Fehler beim Speichern', 'error');
                },
                complete: function() {
                    $form.find('button').prop('disabled', false);
                    $form.removeClass('rt-loading');
                }
            });
        });
    }
    
    // Delete employee
    function deleteEmployee(employeeId) {
        const $row = $('tr[data-employee-id="' + employeeId + '"]');
        
        $row.addClass('rt-loading');
        
        $.ajax({
            url: rtEmployeeDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'rt_delete_employee',
                employee_id: employeeId,
                nonce: rtEmployeeDashboard.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Update statistics with server data
                        if (response.data.stats) {
                            updateStatisticsWithData(response.data.stats);
                        } else {
                            updateStatistics();
                        }
                        
                        // Check if table is empty
                        if ($('.rt-employee-table tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data.message || rtEmployeeDashboard.strings.error, 'error');
                }
            },
            error: function() {
                showNotification(rtEmployeeDashboard.strings.error, 'error');
            },
            complete: function() {
                $row.removeClass('rt-loading');
            }
        });
    }
    
    // Handle modal
    function handleModal() {
        // Close modal
        $(document).on('click', '.rt-modal-close, .rt-modal-cancel', function() {
            $('#rt-employee-edit-modal').hide();
        });
        
        // Close on background click
        $(document).on('click', '.rt-modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
        
        // Close on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.rt-modal').hide();
            }
        });
    }
    
    // Initialize tooltips
    function initTooltips() {
        $('[title]').each(function() {
            $(this).on('mouseenter', function() {
                const title = $(this).attr('title');
                $(this).attr('data-original-title', title).removeAttr('title');
                
                const tooltip = $('<div class="rt-tooltip">' + title + '</div>');
                $('body').append(tooltip);
                
                const offset = $(this).offset();
                tooltip.css({
                    top: offset.top - tooltip.outerHeight() - 5,
                    left: offset.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2)
                });
            }).on('mouseleave', function() {
                $('.rt-tooltip').remove();
                $(this).attr('title', $(this).attr('data-original-title'));
            });
        });
    }
    
    // Real-time updates
    function initRealTimeUpdates() {
        // Update statistics every 30 seconds
        setInterval(updateStatistics, 30000);
        
        // Check for new employees every 60 seconds
        setInterval(checkForNewEmployees, 60000);
    }
    
    // Update statistics
    function updateStatistics() {
        // Count current rows by status
        const stats = {
            total: $('.employee-row').length,
            active: $('.employee-row.status-active').length,
            inactive: $('.employee-row.status-inactive').length,
            terminated: $('.employee-row.status-terminated').length
        };
        
        updateStatisticsWithData(stats);
    }
    
    // Update statistics with provided data
    function updateStatisticsWithData(stats) {
        // Update stat cards
        $('.rt-stat-card').each(function() {
            const $card = $(this);
            const text = $card.find('p').text().toLowerCase();
            
            if (text.includes('gesamt')) {
                $card.find('h3').text(stats.total || 0);
            } else if (text.includes('beschäftigt') || text.includes('aktiv')) {
                $card.find('h3').text(stats.active || 0);
            } else if (text.includes('beurlaubt') || text.includes('inaktiv')) {
                $card.find('h3').text(stats.inactive || 0);
            } else if (text.includes('ausgeschieden') || text.includes('gekündigt') || text.includes('terminated')) {
                $card.find('h3').text(stats.terminated || 0);
            }
        });
        
        // Add animation effect
        $('.rt-stat-card h3').each(function() {
            $(this).addClass('updated').delay(500).queue(function() {
                $(this).removeClass('updated').dequeue();
            });
        });
    }
    
    // Check for new employees
    function checkForNewEmployees() {
        // This would typically make an AJAX call to check for new employees
        // For now, we'll just add a visual indicator if needed
    }
    
    // Show notification
    function showNotification(message, type) {
        // Remove existing notifications
        $('.rt-notification').remove();
        
        const notification = $('<div class="rt-notification rt-notification-' + type + '">' + message + '</div>');
        
        $('body').append(notification);
        
        // Position notification
        notification.css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: 10000,
            padding: '15px 20px',
            borderRadius: '4px',
            color: 'white',
            fontWeight: 'bold',
            maxWidth: '300px',
            boxShadow: '0 4px 8px rgba(0,0,0,0.2)'
        });
        
        if (type === 'success') {
            notification.css('background', '#46b450');
        } else if (type === 'error') {
            notification.css('background', '#dc3232');
        } else {
            notification.css('background', '#0073aa');
        }
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Click to dismiss
        notification.on('click', function() {
            $(this).fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    // Get status label
    function getStatusLabel(status) {
        const labels = {
            'active': 'Aktiv',
            'inactive': 'Inaktiv',
            'suspended': 'Gesperrt',
            'terminated': 'Gekündigt'
        };
        
        return labels[status] || status;
    }
    
    // Format date for input
    function formatDateForInput(dateString) {
        if (!dateString) return '';
        
        // Handle various date formats
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            // Try parsing DD.MM.YYYY format
            const parts = dateString.split('.');
            if (parts.length === 3) {
                const day = parseInt(parts[0]);
                const month = parseInt(parts[1]) - 1; // Month is 0-indexed
                const year = parseInt(parts[2]);
                const newDate = new Date(year, month, day);
                if (!isNaN(newDate.getTime())) {
                    return newDate.toISOString().split('T')[0];
                }
            }
            return '';
        }
        
        return date.toISOString().split('T')[0];
    }
    
    // SVNR formatting for edit form
    $(document).on('input', '.svnr-input', function() {
        const $input = $(this);
        let value = $input.val().replace(/\D/g, '');
        
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        
        let formatted = '';
        for (let i = 0; i < value.length; i++) {
            if (i === 2 || i === 6 || i === 8) {
                formatted += ' ';
            }
            formatted += value[i];
        }
        
        $input.val(formatted);
    });
    
    // Export functionality
    $(document).on('click', '.rt-export-btn', function(e) {
        e.preventDefault();
        
        const format = $(this).data('format') || 'csv';
        const url = new URL(window.location);
        url.searchParams.set('rt_export', format);
        url.searchParams.set('nonce', rtEmployeeDashboard.nonce);
        
        window.open(url.toString(), '_blank');
    });
    
    // Print functionality
    $(document).on('click', '.rt-print-btn', function(e) {
        e.preventDefault();
        window.print();
    });
});