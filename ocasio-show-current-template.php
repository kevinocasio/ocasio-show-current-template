<?php
/**
 * Plugin Name: Ocasio Show Current Template
 * Plugin URI:  https://kevinocasio.com/wordpress-plugins/ocasio-show-current-template/
 * Description: Displays the active theme template filename and included template parts in the WordPress admin bar for administrators.
 * Version:     1.0.0
 * Author:      Kevin Ocasio
 * Author URI:  https://kevinocasio.com/
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ocasio-show-current-template
 */

if (!defined('ABSPATH')) {
	exit;
}

define('OCASIO_SCT_VERSION', '1.0.0');
define('OCASIO_SCT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OCASIO_SCT_PLUGIN_URL', plugin_dir_url(__FILE__));

// -------------------------------------------------------------------------
// 1. CORE LOGIC: TEMPLATE CAPTURE & ADMIN BAR DISPLAY
// -------------------------------------------------------------------------

global $ocasio_sct_current_template;
$ocasio_sct_current_template = '';

/**
 * Capture the winning theme template
 */
function ocasio_sct_capture_template($template) {
	global $ocasio_sct_current_template;
	$ocasio_sct_current_template = $template;
	return $template;
}
add_filter('template_include', 'ocasio_sct_capture_template', 9999);

/**
 * Render Active Template Details in Admin Bar
 */
function ocasio_sct_admin_bar_render() {
	global $wp_admin_bar, $ocasio_sct_current_template;

	$enabled = get_option('ocasio_sct_enabled', null);
	if ($enabled === null) {
		$enabled = get_option('ko_sct_enabled', 1);
	}

	if ((int) $enabled !== 1) {
		return;
	}

	if (!current_user_can('manage_options') || is_admin() || empty($ocasio_sct_current_template)) {
		return;
	}

	$theme_dir       = wp_normalize_path(get_template_directory());
	$child_theme_dir = wp_normalize_path(get_stylesheet_directory());
	$template_path   = wp_normalize_path($ocasio_sct_current_template);

	// Make path relative to theme or child theme
	$rel_path = str_replace(array($child_theme_dir . '/', $theme_dir . '/'), '', $template_path);
	$filename = basename($template_path);

	// 1. Main Admin Bar Node
	$wp_admin_bar->add_node(array(
		'id'    => 'ocasio-current-template',
		'title' => '<span class="ab-icon dashicons dashicons-layout" style="top:2px;"></span> Template: <strong style="color:#ffffff;">' . esc_html($filename) . '</strong>',
		'href'  => admin_url('admin.php?page=ocasio-plugins-main'),
		'meta'  => array(
			'title' => 'Main template: ' . esc_attr($rel_path),
		),
	));

	// 2. Subnode: Relative File Path
	$wp_admin_bar->add_node(array(
		'id'     => 'ocasio-template-path',
		'parent' => 'ocasio-current-template',
		'title'  => '<span style="color:#94a3b8;">Path:</span> <code>' . esc_html($rel_path) . '</code>',
		'href'   => false,
	));

	// 3. Subnodes: Included Template Parts
	$included_files = get_included_files();
	$theme_parts    = array();

	foreach ($included_files as $file) {
		$norm = wp_normalize_path($file);
		if ($norm === $template_path) {
			continue;
		}

		if (strpos($norm, $child_theme_dir) === 0 || strpos($norm, $theme_dir) === 0) {
			$part_rel = str_replace(array($child_theme_dir . '/', $theme_dir . '/'), '', $norm);
			// Exclude functions.php and core theme files
			if ($part_rel !== 'functions.php' && substr($part_rel, -4) === '.php') {
				$theme_parts[] = $part_rel;
			}
		}
	}

	if (!empty($theme_parts)) {
		$wp_admin_bar->add_node(array(
			'id'     => 'ocasio-template-parts-header',
			'parent' => 'ocasio-current-template',
			'title'  => '<span style="color:#94a3b8;font-weight:700;">Template Parts (' . count($theme_parts) . '):</span>',
			'href'   => false,
		));

		foreach (array_unique($theme_parts) as $idx => $part) {
			$wp_admin_bar->add_node(array(
				'id'     => 'ocasio-template-part-' . $idx,
				'parent' => 'ocasio-current-template',
				'title'  => '&bull; <code>' . esc_html($part) . '</code>',
				'href'   => false,
			));
		}
	}
}
add_action('wp_before_admin_bar_render', 'ocasio_sct_admin_bar_render');

// -------------------------------------------------------------------------
// 2. ADMIN MENU & SETTINGS
// -------------------------------------------------------------------------

/**
 * Register Admin Menu (Position 65 with SVG Icon)
 */
function ocasio_sct_register_menu() {
	if (empty($GLOBALS['admin_page_hooks']['ocasio-plugins-main'])) {
		$icon_url = plugins_url('assets/favicon.svg', __FILE__);

		add_menu_page(
			'Ocasio Plugins',
			'Ocasio Plugins',
			'manage_options',
			'ocasio-plugins-main',
			'ocasio_plugins_suite_dashboard_html',
			$icon_url,
			65
		);

		add_submenu_page(
			'ocasio-plugins-main',
			'Ocasio Plugins Suite',
			'Dashboard',
			'manage_options',
			'ocasio-plugins-main',
			'ocasio_plugins_suite_dashboard_html'
		);
	}
}
add_action('admin_menu', 'ocasio_sct_register_menu');

/**
 * Settings Link on Plugins Screen
 */
function ocasio_sct_action_links($links) {
	$dashboard_link = '<a href="' . esc_url(admin_url('admin.php?page=ocasio-plugins-main')) . '">Dashboard</a>';
	array_unshift($links, $dashboard_link);
	return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ocasio_sct_action_links');

/**
 * Enqueue Admin Assets
 */
function ocasio_sct_admin_assets($hook) {
	wp_add_inline_style('common', '#adminmenu .toplevel_page_ocasio-plugins-main .wp-menu-image img { width: 20px !important; height: 20px !important; padding: 5px 0 0 0 !important; opacity: 1 !important; }');

	if (strpos($hook, 'ocasio-plugins') !== false || strpos($hook, 'ko-plugins') !== false) {
		wp_enqueue_style(
			'ocasio-plugins-admin-css',
			plugins_url('assets/admin.css', __FILE__),
			array(),
			OCASIO_SCT_VERSION
		);
		wp_enqueue_script(
			'ocasio-plugins-admin-js',
			plugins_url('assets/admin.js', __FILE__),
			array('jquery'),
			OCASIO_SCT_VERSION,
			true
		);
		wp_localize_script(
			'ocasio-plugins-admin-js',
			'ocasio_admin_data',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('ocasio_suite_toggle_nonce'),
			)
		);
	}
}
add_action('admin_enqueue_scripts', 'ocasio_sct_admin_assets');

/**
 * Register Settings
 */
function ocasio_sct_init_settings() {
	register_setting('ocasio_sct_settings_group', 'ocasio_sct_enabled', array(
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'default'           => 1,
	));
}
add_action('admin_init', 'ocasio_sct_init_settings');

/**
 * Activation: Migrate / Set Defaults
 */
function ocasio_sct_activate() {
	if (get_option('ocasio_sct_enabled') === false) {
		$old_val = get_option('ko_sct_enabled', 1);
		update_option('ocasio_sct_enabled', (int) $old_val);
	}
}
register_activation_hook(__FILE__, 'ocasio_sct_activate');

// -------------------------------------------------------------------------
// 3. MASTER OCASIO PLUGINS SUITE DASHBOARD CALLBACK (17-PLUGIN GRID)
// -------------------------------------------------------------------------

if (!function_exists('ocasio_plugins_suite_dashboard_html')) {
	function ocasio_plugins_suite_dashboard_html() {
		$all_plugins = array(
			'ocasio-admin-bar-hider' => array(
				'title'         => 'Admin Bar Hider',
				'desc'          => 'Hides the front-end WordPress admin bar for all users with a single toggle.',
				'file'          => 'ocasio-admin-bar-hider/ocasio-admin-bar-hider.php',
				'fallback_file' => 'ko-admin-bar-hider/ko-admin-bar-hider.php',
				'opt_toggle'    => 'ocasio_abh_enabled',
				'fallback_opt'  => 'ko_abh_enabled',
				'has_options'   => false,
			),
			'ocasio-admin-username-changer' => array(
				'title'         => 'Admin Username Changer',
				'desc'          => 'Safely changes the primary administrator username directly without touching phpMyAdmin.',
				'file'          => 'ocasio-admin-username-changer/ocasio-admin-username-changer.php',
				'fallback_file' => 'ko-admin-username-changer/ko-admin-username-changer.php',
				'fallback_slug' => 'ko-admin-username-changer',
				'opt_toggle'    => null,
				'has_options'   => true,
			),
			'ocasio-auto-copyright-year' => array(
				'title'         => 'Auto Copyright Year',
				'desc'          => 'Displays the current year, symbol, or translated text via simple shortcodes.',
				'file'          => 'ocasio-auto-copyright-year/ocasio-auto-copyright-year.php',
				'fallback_file' => 'ko-auto-copyright-year/ko-auto-copyright-year.php',
				'fallback_slug' => 'ko-auto-copyright-year',
				'opt_toggle'    => null,
				'has_options'   => true,
			),
			'ocasio-clean-image-filenames' => array(
				'title'         => 'Clean Image Filenames',
				'desc'          => 'Sanitizes uploaded media filenames into clean, lowercase, URL-friendly slugs.',
				'file'          => 'ocasio-clean-image-filenames/ocasio-clean-image-filenames.php',
				'fallback_file' => 'ko-clean-image-filenames/ko-clean-image-filenames.php',
				'opt_toggle'    => 'ocasio_cif_enabled',
				'fallback_opt'  => 'ko_cif_enabled',
				'has_options'   => false,
			),
			'ocasio-comment-link-remover' => array(
				'title'         => 'Comment Link Remover',
				'desc'          => 'Strips hyperlinked website URLs from author comments to eliminate backlink spam.',
				'file'          => 'ocasio-comment-link-remover/ocasio-comment-link-remover.php',
				'fallback_file' => 'ko-comment-link-remover/ko-comment-link-remover.php',
				'opt_toggle'    => 'ocasio_clr_enabled',
				'fallback_opt'  => 'ko_clr_enabled',
				'has_options'   => false,
			),
			'ocasio-disable-comments-globally' => array(
				'title'         => 'Disable Comments Globally',
				'desc'          => 'Closes comments and trackbacks across the entire site, posts, and media.',
				'file'          => 'ocasio-disable-comments-globally/ocasio-disable-comments-globally.php',
				'fallback_file' => 'ko-disable-comments-globally/ko-disable-comments-globally.php',
				'opt_toggle'    => 'ocasio_dcg_enabled',
				'fallback_opt'  => 'ko_dcg_enabled',
				'has_options'   => false,
			),
			'ocasio-disable-emojis' => array(
				'title'         => 'Disable Emojis',
				'desc'          => 'Removes WordPress core emoji scripts, styles, and DNS prefetch requests to boost page speed.',
				'file'          => 'ocasio-disable-emojis/ocasio-disable-emojis.php',
				'fallback_file' => 'ko-disable-emojis/ko-disable-emojis.php',
				'opt_toggle'    => 'ocasio_de_enabled',
				'fallback_opt'  => 'ko_de_enabled',
				'has_options'   => false,
			),
			'ocasio-disable-gutenberg' => array(
				'title'         => 'Disable Gutenberg',
				'desc'          => 'Restores the Classic Editor and removes block library CSS for a cleaner authoring workflow.',
				'file'          => 'ocasio-disable-gutenberg/ocasio-disable-gutenberg.php',
				'fallback_file' => 'ko-disable-gutenberg/ko-disable-gutenberg.php',
				'opt_toggle'    => 'ocasio_dg_enabled',
				'fallback_opt'  => 'ko_dg_enabled',
				'has_options'   => false,
			),
			'ocasio-disable-xml-rpc' => array(
				'title'         => 'Disable XML-RPC',
				'desc'          => 'Blocks XML-RPC API access to protect your site against brute-force attacks.',
				'file'          => 'ocasio-disable-xml-rpc/ocasio-disable-xml-rpc.php',
				'fallback_file' => 'ko-disable-xml-rpc/ko-disable-xml-rpc.php',
				'opt_toggle'    => 'ocasio_dxml_enabled',
				'fallback_opt'  => 'ko_dxml_enabled',
				'has_options'   => false,
			),
			'ocasio-duplicate-post-button' => array(
				'title'         => 'Duplicate Post Button',
				'desc'          => 'Adds a one-click Clone action to duplicate any post or page into a new draft.',
				'file'          => 'ocasio-duplicate-post-button/ocasio-duplicate-post-button.php',
				'fallback_file' => 'ko-duplicate-post-button/ko-duplicate-post-button.php',
				'fallback_slug' => 'ko-duplicate-post-button',
				'opt_toggle'    => null,
				'has_options'   => true,
			),
			'ocasio-estimated-reading-time' => array(
				'title'         => 'Estimated Reading Time',
				'desc'          => 'Calculates and displays article read time above post content automatically.',
				'file'          => 'ocasio-estimated-reading-time/ocasio-estimated-reading-time.php',
				'fallback_file' => 'ko-estimated-reading-time/ko-estimated-reading-time.php',
				'opt_toggle'    => 'ocasio_ert_enabled',
				'fallback_opt'  => 'ko_ert_enabled',
				'has_options'   => false,
			),
			'ocasio-external-links-new-tab' => array(
				'title'         => 'External Links New Tab',
				'desc'          => 'Forces external links to open in a new tab with target="_blank" and rel="noopener".',
				'file'          => 'ocasio-external-links-new-tab/ocasio-external-links-new-tab.php',
				'fallback_file' => 'ko-external-links-new-tab/ko-external-links-new-tab.php',
				'opt_toggle'    => 'ocasio_elnt_enabled',
				'fallback_opt'  => 'ko_elnt_enabled',
				'has_options'   => false,
			),
			'ocasio-hide-version' => array(
				'title'         => 'Hide Version',
				'desc'          => 'Removes WordPress version generator tags and script query strings for security.',
				'file'          => 'ocasio-hide-version/ocasio-hide-version.php',
				'fallback_file' => 'ko-hide-version/ko-hide-version.php',
				'opt_toggle'    => 'ocasio_hv_enabled',
				'fallback_opt'  => 'ko_hv_enabled',
				'has_options'   => false,
			),
			'ocasio-limit-login-attempts' => array(
				'title'         => 'Limit Login Attempts',
				'desc'          => 'Throttles repeated failed login attempts by IP address to block brute-force attacks.',
				'file'          => 'ocasio-limit-login-attempts/ocasio-limit-login-attempts.php',
				'fallback_file' => 'ko-limit-login-attempts/ko-limit-login-attempts.php',
				'fallback_slug' => 'ko-limit-login-attempts',
				'opt_toggle'    => null,
				'has_options'   => true,
			),
			'ocasio-show-current-template' => array(
				'title'         => 'Show Current Template',
				'desc'          => 'Displays active template hierarchy filename in the admin bar for developers.',
				'file'          => 'ocasio-show-current-template/ocasio-show-current-template.php',
				'fallback_file' => 'ko-show-current-template/ko-show-current-template.php',
				'opt_toggle'    => 'ocasio_sct_enabled',
				'fallback_opt'  => 'ko_sct_enabled',
				'has_options'   => false,
			),
			'ocasio-301-redirect-manager' => array(
				'title'         => '301 Redirect Manager',
				'desc'          => 'Manages 301 permanent redirects and fixes broken links cleanly inside WordPress.',
				'file'          => 'ocasio-301-redirect-manager/ocasio-301-redirect-manager.php',
				'fallback_file' => 'ko-simple-301-redirects/ko-simple-301-redirects.php',
				'fallback_slug' => 'ko-simple-301-redirects',
				'opt_toggle'    => null,
				'has_options'   => true,
			),
			'ocasio-simple-maintenance-mode' => array(
				'title'         => 'Simple Maintenance Mode',
				'desc'          => 'Displays a clean splash page to visitors while admins work on the site.',
				'file'          => 'ocasio-simple-maintenance-mode/ocasio-simple-maintenance-mode.php',
				'fallback_file' => 'ko-simple-maintenance-mode/ko-simple-maintenance-mode.php',
				'fallback_slug' => 'ko-simple-maintenance-mode',
				'opt_toggle'    => null,
				'has_options'   => true,
			),
		);

		if (!function_exists('is_plugin_active')) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins = get_plugins();
		$active_count      = 0;

		foreach ($all_plugins as $slug => $data) {
			$active_file = $data['file'];
			if (!is_plugin_active($active_file) && !empty($data['fallback_file']) && is_plugin_active($data['fallback_file'])) {
				$active_file = $data['fallback_file'];
			}
			if (is_plugin_active($active_file)) {
				$active_count++;
			}
		}

		$author_url = 'https://kevinocasio.com/';
		$hub_url    = 'https://kevinocasio.com/wordpress-plugins/';
		?>
		<div class="wrap ko-dash-wrap">
			<div class="ko-dash-hero">
				<div class="ko-dash-hero-left">
					<h1>
						<a href="<?php echo esc_url($hub_url); ?>" target="_blank" rel="noopener noreferrer" class="ko-dash-logo">
							<span class="ko-logo-ocasio">OCASIO</span>
							<span class="ko-logo-suite">PLUGINS SUITE</span>
						</a>
					</h1>
				</div>
				<div class="ko-dash-hero-right">
					<span class="ko-dash-count-pill"><?php echo esc_html($active_count); ?> of 17 Active</span>
				</div>
			</div>

			<div class="ko-dash-grid">
				<?php
				foreach ($all_plugins as $slug => $data):
					$active_file = $data['file'];
					if (!is_plugin_active($active_file) && !empty($data['fallback_file']) && is_plugin_active($data['fallback_file'])) {
						$active_file = $data['fallback_file'];
					}
					$is_installed = isset($installed_plugins[$active_file]) || isset($installed_plugins[$data['file']]) || (!empty($data['fallback_file']) && isset($installed_plugins[$data['fallback_file']]));
					$is_active    = is_plugin_active($active_file);
					$is_fallback  = ($active_file !== $data['file'] && !empty($data['fallback_file']));
					$page_slug    = ($is_fallback && !empty($data['fallback_slug'])) ? $data['fallback_slug'] : $slug;
					$settings_url = admin_url('admin.php?page=' . $page_slug);
					$activate_url = wp_nonce_url(admin_url('plugins.php?action=activate&plugin=' . urlencode($data['file'])), 'activate-plugin_' . $data['file']);
					?>
					<div class="ko-dash-card">
						<div class="ko-dash-card-header">
							<h3 class="ko-dash-card-title"><?php echo esc_html($data['title']); ?></h3>
							<?php if ($is_active): ?>
								<?php if (!empty($data['opt_toggle'])):
									$opt_key      = (!empty($data['fallback_opt']) && get_option($data['opt_toggle'], null) === null) ? $data['fallback_opt'] : $data['opt_toggle'];
									$toggle_state = (int) get_option($opt_key, 1);
									$b_class      = ($toggle_state === 1) ? 'badge-active' : 'badge-paused';
									$b_label      = ($toggle_state === 1) ? 'Active' : 'Paused';
									?>
									<span class="ko-dash-badge <?php echo esc_attr($b_class); ?>" id="badge-<?php echo esc_attr($slug); ?>"><?php echo esc_html($b_label); ?></span>
								<?php else: ?>
									<span class="ko-dash-badge badge-active">Active</span>
								<?php endif; ?>
							<?php elseif ($is_installed): ?>
								<span class="ko-dash-badge badge-inactive">Inactive</span>
							<?php else: ?>
								<span class="ko-dash-badge badge-available">Available</span>
							<?php endif; ?>
						</div>

						<p class="ko-dash-card-desc"><?php echo esc_html($data['desc']); ?></p>

						<div class="ko-dash-card-footer">
							<?php if ($is_active): ?>
								<?php if ($data['has_options']): ?>
									<a href="<?php echo esc_url($settings_url); ?>" class="ko-dash-btn-primary">Manage Settings</a>
								<?php elseif (!empty($data['opt_toggle'])):
									$opt_key    = (!empty($data['fallback_opt']) && get_option($data['opt_toggle'], null) === null) ? $data['fallback_opt'] : $data['opt_toggle'];
									$toggle_val = (int) get_option($opt_key, 1);
									?>
									<div class="ko-dash-card-toggle-row">
										<span class="ko-dash-toggle-label">Active on Site</span>
										<div class="ko-dash-toggle-action">
											<span class="ko-dash-saved-pill" id="saved-<?php echo esc_attr($slug); ?>" style="display:none;">Saved</span>
											<label class="ko-switch">
												<input type="checkbox"
													class="ko-ajax-toggle"
													data-slug="<?php echo esc_attr($slug); ?>"
													data-option="<?php echo esc_attr($opt_key); ?>"
													value="1" <?php checked($toggle_val, 1); ?>>
												<span class="ko-slider"></span>
											</label>
										</div>
									</div>
								<?php else: ?>
									<span class="ko-dash-badge badge-active">Active</span>
								<?php endif; ?>
							<?php elseif ($is_installed): ?>
								<a href="<?php echo esc_url($activate_url); ?>" class="ko-dash-btn-activate">Activate</a>
							<?php else: ?>
								<a href="<?php echo esc_url($hub_url); ?>" target="_blank" rel="noopener noreferrer" class="ko-dash-btn-outline">Learn More</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="ko-dash-global-footer">
				<p>Built with pride by <a href="<?php echo esc_url($author_url); ?>" target="_blank" rel="noopener noreferrer">Kevin Ocasio</a>. Explore all <a href="<?php echo esc_url($hub_url); ?>" target="_blank" rel="noopener noreferrer">17 lightweight WordPress tools</a>.</p>
			</div>
		</div>
		<?php
	}
}

// -------------------------------------------------------------------------
// 5. MASTER AJAX HANDLER FOR DASHBOARD GRID IN-CARD TOGGLES
// -------------------------------------------------------------------------

if (!function_exists('ocasio_suite_save_toggle_ajax_callback')) {
	function ocasio_suite_save_toggle_ajax_callback() {
		check_ajax_referer('ocasio_suite_toggle_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized', 403);
		}

		$option_name  = isset($_POST['option_name']) ? sanitize_key($_POST['option_name']) : '';
		$option_value = isset($_POST['option_value']) ? absint($_POST['option_value']) : 0;

		$allowed_options = array(
			'ko_abh_enabled',
			'ko_cif_enabled',
			'ko_clr_enabled',
			'ko_dcg_enabled',
			'ko_de_enabled',
			'ko_dg_enabled',
			'ko_dxml_enabled',
			'ko_elnt_enabled',
			'ko_ert_enabled',
			'ko_hv_enabled',
			'ko_sct_enabled',
			'ocasio_abh_enabled',
			'ocasio_cif_enabled',
			'ocasio_clr_enabled',
			'ocasio_dcg_enabled',
			'ocasio_de_enabled',
			'ocasio_dg_enabled',
			'ocasio_dxml_enabled',
			'ocasio_elnt_enabled',
			'ocasio_ert_enabled',
			'ocasio_hv_enabled',
			'ocasio_sct_enabled',
		);

		if (in_array($option_name, $allowed_options, true)) {
			update_option($option_name, $option_value);
			wp_send_json_success();
		}

		wp_send_json_error('Invalid option key');
	}
	add_action('wp_ajax_ocasio_suite_save_toggle', 'ocasio_suite_save_toggle_ajax_callback');
}
