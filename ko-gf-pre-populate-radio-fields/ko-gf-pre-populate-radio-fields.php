<?php
/**
 * Plugin Name: KO – GF Pre-populate Radio Fields
 * Description: Production: Mirrors a parent radio selection into a child radio field (matching labels/values). Includes Settings UI for multiple rules.
 * Version: 1.0.0
 * Author: KO
 */

if ( ! defined('ABSPATH') ) exit;

class KO_GF_Prepopulate_Radio_Fields {
	const OPTION_KEY = 'ko_gf_pprf_rules';
	const CAP        = 'manage_options';
	const SLUG       = 'ko-gf-pre-populate-radio-fields';
	const HANDLE     = 'ko-gf-pprf-frontend';
	const VERSION    = '1.0.0';

	public function __construct() {
		add_action('admin_menu', [$this, 'admin_menu']);
		add_action('admin_init', [$this, 'maybe_save_settings']);

		add_action('wp_enqueue_scripts', [$this, 'register_assets']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
	}

	public function admin_menu() {
		add_options_page(
			'KO – GF Pre-populate Radio Fields',
			'KO – GF Pre-populate Radio Fields',
			self::CAP,
			self::SLUG,
			[$this, 'settings_page']
		);
	}

	public function register_assets() {
		wp_register_script(
			self::HANDLE,
			plugin_dir_url(__FILE__) . 'assets/ko-gf-pprf.js',
			[],
			self::VERSION,
			true
		);
	}

	public function enqueue_assets() {
		if ( is_admin() ) return;

		$rules = $this->get_rules();
		if ( empty($rules) ) return;

		$by_form = [];
		foreach ( $rules as $r ) {
			$fid = isset($r['form_id']) ? (int) $r['form_id'] : 0;
			$pid = isset($r['parent_field_id']) ? (int) $r['parent_field_id'] : 0;
			$cid = isset($r['child_field_id']) ? (int) $r['child_field_id'] : 0;

			if ( ! $fid || ! $pid || ! $cid ) continue;

			$by_form[$fid][] = [
				'form_id'         => $fid,
				'parent_field_id' => $pid,
				'child_field_id'  => $cid,
			];
		}

		if ( empty($by_form) ) return;

		wp_enqueue_script(self::HANDLE);

		// Localized rules object used by frontend JS.
		wp_localize_script(self::HANDLE, 'KO_PPRB', [
			'rulesByForm' => $by_form,
			'debug'       => false,
		]);
	}

	private function get_rules() {
		$rules = get_option(self::OPTION_KEY, []);
		return is_array($rules) ? $rules : [];
	}

	private function save_rules( array $rules ) {
		update_option(self::OPTION_KEY, $rules, false);
	}

	public function maybe_save_settings() {
		if ( ! is_admin() ) return;
		if ( ! isset($_POST['ko_gf_pprf_save']) ) return;

		if ( ! current_user_can(self::CAP) ) {
			wp_die('You do not have permission to do that.');
		}

		check_admin_referer('ko_gf_pprf_save_rules');

		$posted = isset($_POST['ko_gf_pprf_rules']) && is_array($_POST['ko_gf_pprf_rules'])
			? $_POST['ko_gf_pprf_rules']
			: [];

		$clean = [];
		foreach ( $posted as $row ) {
			$form_id   = isset($row['form_id']) ? (int) $row['form_id'] : 0;
			$parent_id = isset($row['parent_field_id']) ? (int) $row['parent_field_id'] : 0;
			$child_id  = isset($row['child_field_id']) ? (int) $row['child_field_id'] : 0;

			if ( ! $form_id || ! $parent_id || ! $child_id ) continue;

			$clean[] = [
				'form_id'         => $form_id,
				'parent_field_id' => $parent_id,
				'child_field_id'  => $child_id,
			];
		}

		$this->save_rules($clean);

		wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'updated' => '1'], admin_url('options-general.php')));
		exit;
	}

	public function settings_page() {
		if ( ! current_user_can(self::CAP) ) {
			wp_die('You do not have permission to view this page.');
		}

		$rules = $this->get_rules();
		?>
		<div class="wrap">
			<h1>KO – GF Pre-populate Radio Fields</h1>

			<?php if ( isset($_GET['updated']) && $_GET['updated'] === '1' ): ?>
				<div class="notice notice-success is-dismissible"><p>Rules saved.</p></div>
			<?php endif; ?>

			<p>
				Add one or more rules. Each rule mirrors the selected value from the <strong>Parent</strong> radio field
				into the <strong>Child</strong> radio field (labels/values must match).
			</p>

			<form method="post">
				<?php wp_nonce_field('ko_gf_pprf_save_rules'); ?>

				<table class="widefat striped" id="ko-gf-pprf-rules-table" style="max-width: 980px;">
					<thead>
						<tr>
							<th style="width: 20%;">Form ID</th>
							<th style="width: 25%;">Parent Field ID</th>
							<th style="width: 25%;">Child Field ID</th>
							<th style="width: 15%;">Remove</th>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( empty($rules) ) {
							$rules = [['form_id'=>'','parent_field_id'=>'','child_field_id'=>'']];
						}
						foreach ( $rules as $i => $r ):
							$form_id   = isset($r['form_id']) ? (int)$r['form_id'] : '';
							$parent_id = isset($r['parent_field_id']) ? (int)$r['parent_field_id'] : '';
							$child_id  = isset($r['child_field_id']) ? (int)$r['child_field_id'] : '';
							?>
							<tr>
								<td><input type="number" min="1" name="ko_gf_pprf_rules[<?php echo esc_attr($i); ?>][form_id]" value="<?php echo esc_attr($form_id); ?>" style="width:100%;"></td>
								<td><input type="number" min="1" name="ko_gf_pprf_rules[<?php echo esc_attr($i); ?>][parent_field_id]" value="<?php echo esc_attr($parent_id); ?>" style="width:100%;"></td>
								<td><input type="number" min="1" name="ko_gf_pprf_rules[<?php echo esc_attr($i); ?>][child_field_id]" value="<?php echo esc_attr($child_id); ?>" style="width:100%;"></td>
								<td><button type="button" class="button ko-gf-pprf-remove">Remove</button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p style="max-width: 980px; display:flex; gap:10px; align-items:center;">
					<button type="button" class="button" id="ko-gf-pprf-add">+ Add Rule</button>
					<button type="submit" class="button button-primary" name="ko_gf_pprf_save" value="1">Save Rules</button>
				</p>
			</form>
		</div>

		<script>
		(function(){
			const tbody = document.querySelector('#ko-gf-pprf-rules-table tbody');
			const addBtn = document.getElementById('ko-gf-pprf-add');

			function nextIndex(){ return tbody.querySelectorAll('tr').length; }

			function rowHtml(i){
				return `
					<tr>
						<td><input type="number" min="1" name="ko_gf_pprf_rules[${i}][form_id]" value="" style="width:100%;"></td>
						<td><input type="number" min="1" name="ko_gf_pprf_rules[${i}][parent_field_id]" value="" style="width:100%;"></td>
						<td><input type="number" min="1" name="ko_gf_pprf_rules[${i}][child_field_id]" value="" style="width:100%;"></td>
						<td><button type="button" class="button ko-gf-pprf-remove">Remove</button></td>
					</tr>
				`;
			}

			addBtn.addEventListener('click', function(){
				tbody.insertAdjacentHTML('beforeend', rowHtml(nextIndex()));
			});

			tbody.addEventListener('click', function(e){
				if(e.target && e.target.classList.contains('ko-gf-pprf-remove')){
					e.target.closest('tr')?.remove();
				}
			});
		})();
		</script>
		<?php
	}
}

new KO_GF_Prepopulate_Radio_Fields();
