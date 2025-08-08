<?php
/*
Plugin Name: Granger Client Importer
Description: Import clients/users from CSV or XLSX into WordPress with batch processing, duplicate prevention, and user meta mapping.
Version: 1.1.1
Author: Muhammad Abdullah
Author URI: mailto:callmeabdullahashfaq@gmail.com
*/

if (!defined('ABSPATH')) exit;

class GrangerClientImporter {

    public function __construct() {
        add_action('init', [$this, 'register_role']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_gci_upload_file', [$this, 'ajax_upload_file']);
        add_action('wp_ajax_gci_start_import', [$this, 'ajax_start_import']);
        add_action('wp_ajax_gci_preview_headers', [$this, 'ajax_preview_headers']);
        
        // Add custom user status display
        add_filter('manage_users_columns', [$this, 'add_user_status_column']);
        add_action('manage_users_custom_column', [$this, 'show_user_status_column'], 10, 3);
        add_filter('manage_users_sortable_columns', [$this, 'make_user_status_sortable']);
    }

    public function register_role() {
        if (!get_role('granger_client')) {
            add_role('granger_client', 'Granger Client', [ 'read' => true ]);
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Granger Client Importer',
            'Client Importer',
            'manage_options',
            'granger-client-importer',
            [$this, 'render_admin_page'],
            'dashicons-admin-users',
            31
        );
        add_submenu_page(
            'granger-client-importer',
            'Importer Tools',
            'Tools',
            'manage_options',
            'granger-client-tools',
            [$this, 'render_tools_page']
        );
    }

    public function render_admin_page() {
        $saved_path = get_option('gci_file_path', '');
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">Granger Client Importer <span style="font-size:12px;font-weight:400;color:#666;">by Muhammad Abdullah · <a href="mailto:callmeabdullahashfaq@gmail.com">callmeabdullahashfaq@gmail.com</a></span></h1>
            <p>Import users/clients from CSV or XLSX. Duplicate prevention by Email/Username. Unknown columns are stored as user meta automatically.</p>

            <style>
            /* Force full-width layout for this admin screen */
            #wpcontent, #wpbody-content, .wrap { max-width: 100% !important; width: 100% !important; }
            #wpbody-content { padding-right: 20px; }
            .wrap{max-width:100% !important}
            
            /* Modern UI Enhancements */
            .gci-banner{
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                border-radius: 12px;
                margin: 20px 0;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                border: none;
            }
            .gci-banner h1 { color: white; margin: 0 0 8px 0; font-size: 24px; }
            .gci-banner p { margin: 0; opacity: 0.9; }
            
            .gci-grid{
                display: grid;
                grid-template-columns: 1fr;
                gap: 24px;
                width: 100%;
                margin-top: 20px;
            }
            
            .card.gci-card{
                border: none;
                box-shadow: 0 2px 20px rgba(0,0,0,0.08);
                border-radius: 16px;
                width: 100%;
                box-sizing: border-box;
                background: white;
                transition: all 0.3s ease;
            }
            .card.gci-card:hover {
                box-shadow: 0 4px 25px rgba(0,0,0,0.12);
                transform: translateY(-2px);
            }
            
            .gci-card h2{
                margin: 0 0 20px 0;
                padding: 20px 20px 0;
                font-size: 20px;
                font-weight: 600;
                color: #1d2327;
                border-bottom: 2px solid #f0f2f4;
                padding-bottom: 15px;
            }
            
            .gci-badge{
                background: linear-gradient(45deg, #ff6b6b, #ee5a24);
                color: white;
                border-radius: 20px;
                padding: 4px 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .gci-actions{
                display: flex;
                gap: 12px;
                align-items: center;
                flex-wrap: wrap;
                margin: 10px 0;
            }
            
            .gci-progress{
                width: 100%;
                background: #f8f9fa;
                border: 2px solid #e9ecef;
                border-radius: 12px;
                overflow: hidden;
                margin: 20px 0;
            }
            .gci-progress-fill{
                height: 8px;
                background: linear-gradient(90deg, #667eea, #764ba2);
                width: 0%;
                transition: width 0.3s ease;
                border-radius: 6px;
            }
            
            #gci-log, #gci-added{
                font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.4;
            }
            
            .gci-footer-note{
                color: #6c757d;
                font-size: 13px;
                margin-top: 15px;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #667eea;
            }
            
            .gci-success{
                border-left: 4px solid #28a745;
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                padding: 15px;
                border-radius: 10px;
                color: #155724;
                font-weight: 500;
            }
            
            .gci-error{
                border-left: 4px solid #dc3545;
                background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
                padding: 15px;
                border-radius: 10px;
                color: #721c24;
                font-weight: 500;
            }
            
            .gci-contact{
                border: 2px dashed #dee2e6;
                padding: 20px;
                border-radius: 12px;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                text-align: center;
                margin-top: 20px;
            }
            .gci-contact strong {
                color: #495057;
                font-size: 16px;
                display: block;
                margin-bottom: 8px;
            }
            .gci-contact a {
                color: #667eea;
                text-decoration: none;
                font-weight: 500;
            }
            .gci-contact a:hover {
                text-decoration: underline;
            }
            
            .gci-panels{
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
                margin-top: 20px;
            }
            
            .gci-panel{
                border: 1px solid #e9ecef;
                border-radius: 12px;
                background: #f8f9fa;
                padding: 16px;
                box-sizing: border-box;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            
            .gci-panel h3{
                margin: 0 0 12px 0;
                font-size: 16px;
                font-weight: 600;
                color: #495057;
                padding-bottom: 8px;
                border-bottom: 2px solid #dee2e6;
            }
            
            .gci-scroll{
                max-height: 300px;
                overflow: auto;
                padding: 12px;
                border-radius: 8px;
                background: white;
                border: 1px solid #e9ecef;
                font-size: 12px;
                line-height: 1.5;
            }
            
            .form-table{
                width: 100%;
                margin: 0;
            }
            .form-table th,
            .form-table td{
                padding: 15px 20px;
                vertical-align: top;
                border-bottom: 1px solid #f0f2f4;
            }
            .form-table th {
                width: 200px;
                font-weight: 600;
                color: #495057;
            }
            
            .form-table input[type="file"],
            .form-table input[type="text"],
            .form-table input[type="number"],
            .form-table select {
                border: 2px solid #e9ecef;
                border-radius: 8px;
                padding: 10px 12px;
                font-size: 14px;
                transition: all 0.3s ease;
            }
            .form-table input:focus,
            .form-table select:focus {
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                outline: none;
            }
            
            .button-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                border-radius: 8px;
                padding: 12px 24px;
                font-size: 14px;
                font-weight: 600;
                color: white;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
            }
            .button-primary:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            }
            
            .description {
                color: #6c757d;
                font-size: 13px;
                margin-top: 8px;
                line-height: 1.4;
            }
            
            code {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 4px;
                padding: 4px 8px;
                font-size: 12px;
                color: #495057;
            }
            
            @media (max-width: 1100px){ 
                .gci-grid{grid-template-columns:1fr} 
                .gci-panels{grid-template-columns:1fr} 
            }
            @media (min-width: 1101px){ 
                .gci-grid{grid-template-columns:1fr 1fr} 
            }
            </style>

            <div class="gci-banner">
                <strong>Tips:</strong> CSV loads fastest. Start with a moderate batch (100–300). If you enable reset emails, make sure outgoing mail (SMTP) is configured. For very large files, keep this page open during import.
            </div>

            <div class="gci-grid">
                <div class="card gci-card">
                    <h2>Step 1: Upload CSV/XLSX <span class="gci-badge">Required</span></h2>
                    <form id="gci-upload-form" enctype="multipart/form-data">
                        <table class="form-table">
                            <tr>
                                <th>CSV/XLSX File</th>
                                <td>
                                    <div class="gci-actions">
                                        <input type="file" name="gci_file" accept=".csv,.xlsx" required>
                                    </div>
                                    <p class="description">Select a file to upload automatically. First row must contain headers. Core columns detected by smart aliases (Email, Username/Login, First/Last Name, Display Name, Role, Password).</p>
                                </td>
                            </tr>
                        </table>
                    </form>
                    <div id="gci-upload-status"></div>
                </div>

                <div class="card gci-card" id="gci-import-section" style="display: <?php echo $saved_path ? 'block' : 'none'; ?>;">
                    <h2>Step 2: Configure & Start Import</h2>
                    <form id="gci-import-form">
                        <table class="form-table">
                            <tr>
                                <th>Uploaded File</th>
                                <td>
                                    <code id="gci-file-path"><?php echo esc_html($saved_path ?: 'No file uploaded'); ?></code>
                                    <p class="description"><a href="#" id="gci-preview-headers">Preview headers</a></p>
                                    <div id="gci-headers" style="margin-top:10px;"></div>
                                </td>
                            </tr>
                            <tr>
                                <th>Duplicate Handling</th>
                                <td>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" id="gci-update-existing" checked> Update existing users (match by Email or Username)</label>
                                    <label style="display:block;"><input type="checkbox" id="gci-strict-skip" checked> Strict skip duplicates (no updates if user exists)</label>
                                </td>
                            </tr>
                            <tr>
                                <th>Role</th>
                                <td>
                                    <div class="gci-actions">
                                        <select id="gci-default-role">
                                            <?php foreach (wp_roles()->roles as $role_key => $role) { echo '<option value="'.esc_attr($role_key).'"'.selected($role_key,'subscriber',false).'>'.esc_html($role['name']).'</option>'; } ?>
                                        </select>
                                        <label><input type="checkbox" id="gci-override-role" checked> Override role for existing and new users</label>
                                    </div>
                                    <p class="description">If unchecked, importer will use per-row Role (when present) or leave existing user roles unchanged.</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Passwords</th>
                                <td>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" id="gci-passwords-are-hashed"> Passwords in file are hashed (unknown plaintext)</label>
                                    <label style="display:block;"><input type="checkbox" id="gci-send-reset-email"> Send reset email to newly created users</label>
                                </td>
                            </tr>
                            <tr>
                                <th>Batch Size</th>
                                <td>
                                    <input type="number" id="gci-batch-size" value="200" min="25" max="5000" style="width:100px;">
                                    <p class="description">The importer adapts automatically if the server is busy.</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit"><button class="button button-primary" type="submit">Start Import</button></p>
                    </form>

                    <div id="gci-progress" style="display:none;">
                        <div class="gci-progress"><div id="gci-progress-fill" class="gci-progress-fill"></div></div>
                        <p id="gci-progress-text">Preparing…</p>
                        <div class="gci-panels">
                            <div class="gci-panel">
                                <h3>Added / Updated Users</h3>
                                <div id="gci-added" class="gci-scroll"></div>
                            </div>
                            <div class="gci-panel">
                                <h3>Import Log</h3>
                                <div id="gci-log" class="gci-scroll"></div>
                            </div>
                        </div>
                        <div id="gci-summary" class="gci-footer-note"></div>
                        <div class="gci-contact" style="margin-top:12px;">
                            <strong>Need help or a custom plugin?</strong><br>
                            Contact Muhammad Abdullah — <a href="mailto:callmeabdullahashfaq@gmail.com">callmeabdullahashfaq@gmail.com</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($){
            // Auto-upload on file selection
            $(document).on('change', 'input[name="gci_file"]', function(){
                if(!this.files || !this.files[0]) return;
                var fd = new FormData();
                fd.append('action','gci_upload_file');
                fd.append('nonce','<?php echo wp_create_nonce('gci_upload'); ?>');
                fd.append('gci_file', this.files[0]);
                $.ajax({url: ajaxurl, type:'POST', data: fd, processData:false, contentType:false, success:function(res){
                    if(res.success){
                        $('#gci-upload-status').html('<div class="notice notice-success gci-success"><p>File uploaded successfully.</p></div>');
                        $('#gci-file-path').text(res.data.path);
                        $('#gci-import-section').show();
                        $('html, body').animate({scrollTop: $('#gci-import-section').offset().top - 40}, 300);
                    } else {
                        $('#gci-upload-status').html('<div class="notice notice-error gci-error"><p>'+res.data+'</p></div>');
                    }
                }, error:function(){
                    $('#gci-upload-status').html('<div class="notice notice-error gci-error"><p>Upload failed. Please try again.</p></div>');
                }});
            });

            // Preview headers
            $('#gci-preview-headers').on('click', function(e){
                e.preventDefault();
                $.post(ajaxurl, {action:'gci_preview_headers', nonce:'<?php echo wp_create_nonce('gci_preview'); ?>'}, function(res){
                    if(res.success){
                        $('#gci-headers').html('<strong>Detected Headers:</strong><br>'+ res.data.headers.map(h=>'<code>'+h+'</code>').join(' '));
                    } else {
                        $('#gci-headers').html('<em>'+res.data+'</em>');
                    }
                });
            });

            // Start import
            $('#gci-import-form').on('submit', function(e){
                e.preventDefault();
                $('#gci-progress').show();
                $('#gci-log').empty();
                $('#gci-added').empty();
                $('#gci-summary').empty();
                let offset = 0; let total = 0; let processed = 0; let stopped = false;
                let retryDelay = 1500; // ms, exponential backoff
                let currBatch = parseInt($('#gci-batch-size').val(),10)||200;
                const updateExisting = $('#gci-update-existing').is(':checked') ? 1 : 0;
                const strictSkip = $('#gci-strict-skip').is(':checked') ? 1 : 0;
                const defRole = $('#gci-default-role').val();
                const overrideRole = $('#gci-override-role').is(':checked') ? 1 : 0;
                const hashedPw = $('#gci-passwords-are-hashed').is(':checked') ? 1 : 0;
                const sendReset = $('#gci-send-reset-email').is(':checked') ? 1 : 0;
                const forceSubscriber = 0;
                function pushLine(line){
                    if(/^Created: /i.test(line) || /^Updated: /i.test(line) || /^Skipped \(exists\): /i.test(line)){
                        $('#gci-added').append('<div>'+line+'</div>');
                    } else {
                        $('#gci-log').append('<div>'+line+'</div>');
                    }
                }
                function tick(){
                    if(stopped) return;
                    $.post(ajaxurl, {action:'gci_start_import', nonce:'<?php echo wp_create_nonce('gci_import'); ?>', offset, batch_size: currBatch, update_existing:updateExisting, strict_skip: strictSkip, default_role:defRole, override_role: overrideRole, passwords_hashed: hashedPw, send_reset: sendReset, force_subscriber: forceSubscriber}, function(res){
                        if(res.success){
                            retryDelay = 1500; // reset on success
                            total = res.data.total;
                            processed += res.data.processed;
                            offset += res.data.processed;
                            const pct = total ? Math.round((offset/total)*100) : 0;
                            $('#gci-progress-fill').css('width', pct+'%');
                            $('#gci-progress-text').text('Imported '+offset+' of '+total+' (batch '+currBatch+')');
                            if(res.data.log && res.data.log.length){ res.data.log.forEach(pushLine); }
                            if(offset < total && res.data.processed > 0){ tick(); }
                            else {
                                $('#gci-progress-text').text('Done.');
                                $('#gci-summary').html('<div class="gci-success"><strong>Completed:</strong> '+offset+' of '+total+' rows processed. Users are ready to log in.</div>');
                            }
                        } else {
                            pushLine(res.data);
                        }
                    }).fail(function(){
                        const msg = 'Server busy, retrying in '+Math.round(retryDelay/1000)+'s…';
                        pushLine(msg);
                        setTimeout(function(){
                            if (retryDelay >= 6000 && currBatch > 50) {
                                currBatch = Math.max(50, Math.floor(currBatch/2));
                                pushLine('Reduced batch size to '+currBatch+' to ease server load.');
                            }
                            retryDelay = Math.min(retryDelay * 2, 15000);
                            tick();
                        }, retryDelay);
                    });
                }
                tick();
            });
        });
        </script>
        <?php
    }

    public function render_tools_page() {
        if (isset($_POST['gci_delete_batch']) && check_admin_referer('gci_tools')) {
            $batchId = sanitize_text_field($_POST['gci_batch_id']);
            $deleted = $this->delete_users_by_batch($batchId);
            echo '<div class="updated"><p>Deleted '.intval($deleted).' users from batch '.esc_html($batchId).'.</p></div>';
        }
        echo '<div class="wrap"><h1>Client Importer Tools</h1>';
        echo '<form method="post">';
        wp_nonce_field('gci_tools');
        echo '<p>Remove users created by a specific import batch.</p>';
        echo '<p><label>Batch ID: <input type="text" name="gci_batch_id" placeholder="e.g., 20250808-1"></label></p>';
        echo '<p><input type="submit" class="button" name="gci_delete_batch" value="Delete Users in Batch" onclick="return confirm(\'Are you sure? This cannot be undone.\');"></p>';
        echo '</form></div>';
    }

    public function ajax_upload_file() {
        check_ajax_referer('gci_upload','nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        @ignore_user_abort(true); @set_time_limit(0); @ini_set('memory_limit','1024M');
        if (empty($_FILES['gci_file'])) wp_send_json_error('No file uploaded');
        $file = $_FILES['gci_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv','xlsx'])) wp_send_json_error('Please upload CSV or XLSX');
        $dest = trailingslashit(wp_upload_dir()['basedir']).'gci-import.'. $ext;
        if (!move_uploaded_file($file['tmp_name'], $dest)) wp_send_json_error('Failed to save file');
        update_option('gci_file_path', $dest);
        update_option('gci_file_ext', $ext);
        delete_option('gci_total_rows'); delete_option('gci_headers'); delete_option('gci_batch_id');
        wp_send_json_success(['path'=>$dest]);
    }

    public function ajax_preview_headers() {
        check_ajax_referer('gci_preview','nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        $file = get_option('gci_file_path'); $ext = get_option('gci_file_ext');
        if (!$file || !file_exists($file)) wp_send_json_error('No file uploaded');
        $rows = $ext==='csv' ? $this->read_csv_chunk($file, 1) : $this->read_xlsx_chunk($file, 1);
        if (empty($rows)) wp_send_json_error('Could not read file');
        $headers = array_keys($rows[0]);
        update_option('gci_headers', $headers);
        wp_send_json_success(['headers'=>$headers]);
    }

    public function ajax_start_import() {
        check_ajax_referer('gci_import','nonce');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        @ignore_user_abort(true); @set_time_limit(0); @ini_set('memory_limit','1024M');
        $file = get_option('gci_file_path'); $ext = get_option('gci_file_ext');
        if (!$file || !file_exists($file)) wp_send_json_error('No file uploaded');
        $offset = max(0, intval($_POST['offset'] ?? 0));
        $batch = max(1, intval($_POST['batch_size'] ?? 50));
        $updateExisting = !empty($_POST['update_existing']);
        $strictSkip = !empty($_POST['strict_skip']);
        $defaultRole = sanitize_text_field($_POST['default_role'] ?? 'subscriber');
        $overrideRole = !empty($_POST['override_role']);
        $passwordsHashed = !empty($_POST['passwords_hashed']);
        $sendReset = !empty($_POST['send_reset']);
        $forceSubscriber = !empty($_POST['force_subscriber']); // This parameter is no longer used in the new form, but kept for compatibility

        $total = intval(get_option('gci_total_rows', 0));
        if ($total === 0) { $total = $ext==='csv' ? $this->count_csv_rows($file) : $this->count_xlsx_rows($file); update_option('gci_total_rows', $total); }
        $batchId = get_option('gci_batch_id'); if (!$batchId) { $batchId = date('Ymd-His'); update_option('gci_batch_id', $batchId); }

        $rows = $ext==='csv' ? $this->read_csv_chunk($file, $batch, $offset) : $this->read_xlsx_chunk($file, $batch, $offset);
        $processed = 0; $log = [];
        foreach ($rows as $row) {
            $res = $this->import_single_user($row, $updateExisting && !$strictSkip, $defaultRole, $batchId, $passwordsHashed, $sendReset, $forceSubscriber, $overrideRole);
            $log[] = $res['message']; if ($res['ok']) $processed++;
        }
        wp_send_json_success(['total'=>$total, 'processed'=>$processed, 'log'=>$log]);
    }

    private function normalize_key($k){ return strtolower(trim(preg_replace('/\s+/', ' ', $k))); }
    private function get_by_alias($normalized, $aliases) { foreach ($aliases as $a){ if (isset($normalized[$a]) && $normalized[$a] !== '') return $normalized[$a]; } return ''; }

    private function import_single_user($row, $updateExisting, $defaultRole, $batchId, $passwordsHashed, $sendReset, $forceSubscriber = false, $overrideRole = false) {
        $normalized = []; foreach ($row as $k=>$v) { $normalized[$this->normalize_key($k)] = is_string($v)?trim($v):$v; }
        // Email priority: New Email -> Email -> Old Email
        $email = $this->get_by_alias($normalized, ['new email','email','old email','e-mail','email address','user_email']);
        // Username priority
        $username = $this->get_by_alias($normalized, ['username','user','login','user_login','user name','user-name']);
        if (!$username && $email) { $username = sanitize_user(current(explode('@',$email))); }
        if (!$username) { $username = $this->get_by_alias($normalized, ['old web id']); }
        if (!$username) { $username = $this->get_by_alias($normalized, ['id']); }
        if (!$username) { $username = sanitize_user(($normalized['company'] ?? 'user') . '-' . ($normalized['id'] ?? uniqid('u'))); }

        // Names
        $first = $this->get_by_alias($normalized, ['first name','firstname','given name','cntct_fst','cntct fst']);
        $last  = $this->get_by_alias($normalized, ['last name','lastname','surname','cntct_lst','cntct lst']);
        // URL
        $user_url = $this->get_by_alias($normalized, ['www','website','url','web']);
        // Display name
        $display = $this->get_by_alias($normalized, ['display name','displayname','name','company']);
        if (!$display) { $display = trim(($first.' '.$last)) ?: $username; }

        $role = $this->get_by_alias($normalized, ['role','user role']) ?: $defaultRole;
        if ($forceSubscriber) { $role = 'subscriber'; }
        if ($overrideRole) { $role = $defaultRole; }
        $password = $this->get_by_alias($normalized, ['password','pass','pwd']);

        if (!$email && !$username) { return ['ok'=>false,'message'=>'Skipped: missing Email/Username']; }
        if ($email && !is_email($email)) { return ['ok'=>false,'message'=>'Skipped: invalid email '.$email]; }

        $user = $email ? get_user_by('email', $email) : false; if (!$user && $username) { $user = get_user_by('login', $username); }

        if ($user) {
            $update_data = ['ID'=>$user->ID,'user_email'=>$email?:$user->user_email,'user_login'=>$user->user_login,'first_name'=>$first,'last_name'=>$last,'display_name'=>$display];
            if ($user_url) { $update_data['user_url'] = esc_url_raw($user_url); }
            if ($updateExisting) {
                wp_update_user($update_data);
                if ($overrideRole) { 
                    $u = new WP_User($user->ID); 
                    $u->set_role($defaultRole); 
                }
                elseif ($role && !in_array($role, $user->roles, true)) { 
                    $u = new WP_User($user->ID); 
                    $u->set_role($role); 
                }
                // Approve user
                wp_update_user(['ID' => $user->ID, 'user_status' => 0]);
                delete_user_meta($user->ID, 'wp_user_status');
                return ['ok'=>true, 'message'=>"Updated existing user: $username ($email)"];
            } else {
                return ['ok'=>true,'message'=>'Skipped (exists): '.$user->user_login];
            }
        }

        // New user — ensure login-ready
        if ($passwordsHashed) {
            $temp = wp_generate_password(12, false);
            $insert = ['user_login'=>$username,'user_email'=>$email,'user_pass'=>$temp,'first_name'=>$first,'last_name'=>$last,'display_name'=>$display,'role'=>$role];
            if ($overrideRole) { $insert['role'] = $defaultRole; }
            $user_id = wp_insert_user($insert);
            if (is_wp_error($user_id)) { return ['ok'=>false,'message'=>'Failed to create user: '.$user_id->get_error_message()]; }
            update_user_meta($user_id, 'gci_password_hash', $password);
            // Approve user
            wp_update_user(['ID' => $user_id, 'user_status' => 0]);
            delete_user_meta($user_id, 'wp_user_status');
            if ($sendReset) { $this->send_reset_email($user_id); }
            $this->save_user_meta_bulk($user_id, $normalized, $batchId);
            return ['ok'=>true,'message'=>'Created (hashed): '.$username];
        } else {
            if (!$password) { $password = wp_generate_password(12, false); }
            $insert = ['user_login'=>$username,'user_email'=>$email,'user_pass'=>$password,'first_name'=>$first,'last_name'=>$last,'display_name'=>$display,'role'=>$role];
            if ($overrideRole) { $insert['role'] = $defaultRole; }
            $user_id = wp_insert_user($insert);
            if (is_wp_error($user_id)) { return ['ok'=>false,'message'=>'Failed to create user: '.$user_id->get_error_message()]; }
            // Approve user
            wp_update_user(['ID' => $user_id, 'user_status' => 0]);
            delete_user_meta($user_id, 'wp_user_status');
            if ($sendReset) { $this->send_reset_email($user_id); }
            $this->save_user_meta_bulk($user_id, $normalized, $batchId);
            return ['ok'=>true,'message'=>'Created: '.$username];
        }
    }

    private function send_reset_email($user_id) {
        if (function_exists('wp_send_new_user_notifications')) {
            // Core function (newer WP) can notify admin or user; we notify user.
            wp_send_new_user_notifications($user_id, 'user');
        } else {
            // Fallback to classic function if present
            if (function_exists('wp_new_user_notification')) {
                wp_new_user_notification($user_id, null, 'user');
            }
        }
    }

    private function save_user_meta_bulk($user_id, $normalized, $batchId) {
        $reserved = ['email','e-mail','email address','user_email','username','user','login','user_login','user name','user-name','first name','firstname','given name','last name','lastname','surname','display name','displayname','name','role','user role','password','pass','pwd'];
        foreach ($normalized as $k=>$v) {
            if (in_array($k, $reserved, true)) continue;
            if ($v === '' || $v === null) continue;
            $meta_key = 'gci_'.sanitize_key(str_replace(' ', '_', $k));
            update_user_meta($user_id, $meta_key, $v);
        }
        update_user_meta($user_id, 'gci_import_batch', $batchId);
    }

    private function read_csv_chunk($path, $limit = 50, $offset = 0) {
        $out = []; if (!file_exists($path)) return $out; if (($fh = fopen($path, 'r')) === false) return $out;
        $headers = fgetcsv($fh); if ($headers === false) { fclose($fh); return $out; }
        $row = 0; $end = $offset + $limit;
        while (($data = fgetcsv($fh)) !== false) { if ($row < $offset) { $row++; continue; } if ($row >= $end) break; $out[] = $this->combine_headers($headers, $data); $row++; }
        fclose($fh); return $out;
    }
    private function count_csv_rows($path) { $cnt = 0; if (!file_exists($path)) return 0; $fh = fopen($path, 'r'); if (!$fh) return 0; $headers=fgetcsv($fh); while(fgetcsv($fh)!==false){$cnt++;} fclose($fh); return $cnt; }
    private function combine_headers($headers, $row) { $assoc = []; foreach ($headers as $i=>$h) { $assoc[$h] = $row[$i] ?? ''; } return $assoc; }

    private function read_xlsx_chunk($path, $limit = 50, $offset = 0) { $all = $this->read_xlsx_all($path); if (empty($all)) return []; return array_slice($all, $offset, $limit); }
    private function count_xlsx_rows($path) { $all = $this->read_xlsx_all($path); return count($all); }
    private function read_xlsx_all($xlsx_path) {
        if (!class_exists('ZipArchive')) return [];
        $zip = new ZipArchive(); if ($zip->open($xlsx_path)!==true) return [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml'); $sharedStrings = [];
        if ($ssXml) { $ss=simplexml_load_string($ssXml); if ($ss && isset($ss->si)) foreach($ss->si as $si){ $t=''; if (isset($si->t)){$t=(string)$si->t;} elseif(isset($si->r)){ foreach($si->r as $r){ $t.=(string)$r->t; } } $sharedStrings[]=$t; } }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml'); if (!$sheetXml){ $zip->close(); return []; }
        $sheet = simplexml_load_string($sheetXml); if (!$sheet){ $zip->close(); return []; }
        $rows = [];
        foreach ($sheet->sheetData->row as $row) { $cells = []; foreach ($row->c as $c) { $r=(string)$c['r']; $col=preg_replace('/\d+/', '', $r); $val=''; if (isset($c->v)) { if ((string)$c['t']==='s'){ $idx=intval($c->v); $val=$sharedStrings[$idx]??''; } else { $val=(string)$c->v; } } $cells[$col]=$val; } ksort($cells); $rows[] = array_values($cells); }
        $zip->close(); if (empty($rows)) return [];
        $headers=[]; foreach ($rows[0] as $h){ $headers[] = trim((string)$h); }
        $data=[]; for ($i=1;$i<count($rows);$i++){ $r=$rows[$i]; if (count(array_filter($r,fn($v)=>$v!==''))===0) continue; $assoc=[]; foreach($headers as $idx=>$k){ $assoc[$k]=$r[$idx]??''; } $data[]=$assoc; }
        return $data;
    }

    private function delete_users_by_batch($batchId) {
        $args = [ 'meta_key' => 'gci_import_batch', 'meta_value' => $batchId, 'number' => 9999, 'fields' => 'ID' ];
        $query = new WP_User_Query($args); $deleted = 0; foreach ($query->get_results() as $uid) { require_once ABSPATH.'wp-admin/includes/user.php'; if (wp_delete_user($uid)) { $deleted++; } } return $deleted;
    }

    // Frontend shortcode to show contact banner
    public function register_contact_shortcode() {
        add_shortcode('gci_contact_banner', function($atts){
            $atts = shortcode_atts([
                'text' => 'Contact me for developing any kind of WordPress plugin.',
                'email' => 'callmeabdullahashfaq@gmail.com',
                'name' => 'Muhammad Abdullah'
            ], $atts);
            ob_start(); ?>
            <div class="gci-contact-banner" style="border:1px solid #e2e8f0;background:#f8fafc;border-radius:12px;padding:16px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div style="font-size:16px;color:#1f2937;">
                    <strong><?php echo esc_html($atts['text']); ?></strong>
                </div>
                <div style="white-space:nowrap;">
                    <a href="mailto:<?php echo esc_attr($atts['email']); ?>" style="background:#2271b1;color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none;">Email <?php echo esc_html($atts['name']); ?></a>
                </div>
            </div>
            <?php return ob_get_clean();
        });
    }
}

// init plugin
add_action('init', function(){
    if (class_exists('GrangerClientImporter')) {
        global $grangerClientImporterInstance;
        if (!isset($grangerClientImporterInstance)) {
            $grangerClientImporterInstance = new GrangerClientImporter();
        }
        $grangerClientImporterInstance->register_contact_shortcode();
    }
});
