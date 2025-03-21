jQuery(document).ready(function($) {
    // Add new directory row.
    $('#add_row').on('click', function(){
        var index = $('#urm_settings_table tbody tr').length;
        var row = '<tr>' +
            '<td><select name="urm_settings['+index+'][directory]">' + urm_vars.directoryOptions + '</select></td>' +
            '<td><input type="number" name="urm_settings['+index+'][days_to_keep]" value="" min="1" /></td>' +
            '<td><input type="text" name="urm_settings['+index+'][exclude_filenames]" value="" /></td>' +
            '<td><button class="remove_row button" type="button">Remove</button></td>' +
            '</tr>';
        $('#urm_settings_table tbody').append(row);
    });
    
    // Remove a row.
    $('#urm_settings_table').on('click', '.remove_row', function(){
        $(this).closest('tr').remove();
    });
    
    // Modal handling for "View Details" buttons.
    $('.urm-view-detail').on('click', function(){
        var logId = $(this).data('log-id');
        var detailHtml = $('#urm-log-detail-' + logId).html();
        $('#urm-modal-body').html(detailHtml);
        $('#urm-modal').fadeIn();
    });
    
    // Close modal when clicking the close button.
    $('.urm-modal-close').on('click', function(){
        $('#urm-modal').fadeOut();
    });
    
    // Close modal when clicking outside the modal content.
    $(window).on('click', function(e) {
        if ($(e.target).is('#urm-modal')) {
            $('#urm-modal').fadeOut();
        }
    });
});
