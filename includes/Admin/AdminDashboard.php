<?php
declare(strict_types=1);

namespace WMCyber\BFSG\Remediator\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screen (menu "BFSG Remediator"), dashboard widget and the settings form.
 *
 * Every state on the screen is read from the saved options when the page renders. Until 2026-09-14
 * the screen was in Spanish and showed "CUMPLIMIENTO BFSG 2025 ACTIVO", "WCAG 2.2 AA GARANTIZADO", a
 * simulator result "100% Accesible", fixed widget figures ("< 2ms", "0 CLS", "x / 6" rules counting two
 * that do not exist), counters no current code writes, and a heading toggle no rule of the frontend
 * engine reads.
 */
final class AdminDashboard {
	private static ?AdminDashboard $instance = null;

	/** Settings form fields => option names, in the order the rules run. */
	private const RULE_FIELDS = [
		'wm_bfsg_fix_skip_links' => 'skip_link',
		'wm_bfsg_fix_images'     => 'images_alt',
		'wm_bfsg_fix_labels'     => 'form_labels',
		'wm_bfsg_fix_aria'       => 'nav_landmarks',
	];

	public static function instance(): AdminDashboard {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'save_settings' ] );
		add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
	}

	/**
	 * Register plugin options with WordPress Settings API (including opt-in data deletion on uninstall).
	 */
	public function register_settings(): void {
		$bool_on = [
			'type'              => 'string',
			'sanitize_callback' => static fn( mixed $val ): string => ( '1' === (string) $val || true === $val ) ? '1' : '0',
			'default'           => '1',
		];
		register_setting( 'wm_bfsg_remediator_settings', 'wm_bfsg_remediator_enabled', $bool_on );
		foreach ( array_keys( self::RULE_FIELDS ) as $option ) {
			register_setting( 'wm_bfsg_remediator_settings', $option, $bool_on );
		}
		register_setting(
			'wm_bfsg_remediator_settings',
			'wm_bfsg_remediator_delete_data_on_uninstall',
			[
				'type'              => 'string',
				'sanitize_callback' => static fn( mixed $val ): string => ( '1' === (string) $val || true === $val ) ? '1' : '0',
				'default'           => '0',
			]
		);
	}

	/**
	 * What each rule does, in the words the screen, the widget and the docs use.
	 *
	 * @return array<string, array{title: string, does: string}>
	 */
	public static function rule_descriptions(): array {
		return [
			'lang'          => [
				'title' => __( 'Sprache des Dokuments', 'wm-bfsg-ast-remediator' ),
				'does'  => __( 'Setzt lang am <html>-Element aus der Spracheinstellung von WordPress, wenn es fehlt oder leer ist. Läuft immer.', 'wm-bfsg-ast-remediator' ),
			],
			'skip_link'     => [
				'title' => __( 'Sprunglink zum Hauptbereich', 'wm-bfsg-ast-remediator' ),
				'does'  => __( 'Wenn die Seite ein <main> (oder role="main") und keinen eigenen Sprunglink hat: fügt am Anfang von <body> „Zum Inhalt springen“ ein, das erst bei Fokus sichtbar wird und auf die id des Hauptbereichs zeigt (fehlt sie, wird main-content gesetzt).', 'wm-bfsg-ast-remediator' ),
			],
			'images_alt'    => [
				'title' => __( 'alt-Attribut für Bilder', 'wm-bfsg-ast-remediator' ),
				'does'  => __( 'Gibt jedem img-Element ohne alt ein alt: leer, wenn der Dateipfad icon oder decor enthält, sonst den Dateinamen ohne Endung. Ein Dateiname ist keine Bildbeschreibung.', 'wm-bfsg-ast-remediator' ),
			],
			'form_labels'   => [
				'title' => __( 'aria-label für unbeschriftete Formularfelder', 'wm-bfsg-ast-remediator' ),
				'does'  => __( 'Feldern ohne label, aria-label, aria-labelledby oder title gibt es aria-label aus placeholder, sonst aus name, sonst „Eingabefeld“. Felder innerhalb eines <label> bleiben unverändert.', 'wm-bfsg-ast-remediator' ),
			],
			'nav_landmarks' => [
				'title' => __( 'Namen für mehrere Navigationen', 'wm-bfsg-ast-remediator' ),
				'does'  => __( 'Bei mehr als einem <nav> ohne Namen: aria-label nach der CSS-Klasse (Hauptnavigation, Fußzeilennavigation, Mobilmenü) oder „Navigation 1, 2 …“.', 'wm-bfsg-ast-remediator' ),
			],
		];
	}

	/**
	 * Saved switches: engine, uninstall opt-in, and each rule (document language always on).
	 *
	 * @return array{enabled: bool, delete_data_on_uninstall: bool, available: bool, rules: array<string, bool>}
	 */
	private static function state(): array {
		return [
			'enabled'                  => '1' === (string) get_option( 'wm_bfsg_remediator_enabled', '1' ),
			'delete_data_on_uninstall' => '1' === (string) get_option( 'wm_bfsg_remediator_delete_data_on_uninstall', '0' ),
			'available'                => \WM_BFSG_DOM_Transformer::engine_available(),
			'rules'                    => [ 'lang' => true ] + \WM_BFSG_DOM_Transformer::rules_from_options(),
		];
	}

	public function register_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wm_bfsg_ast_widget',
			__( 'WM BFSG AST Remediator', 'wm-bfsg-ast-remediator' ),
			[ $this, 'render_dashboard_widget' ]
		);
	}

	public function render_dashboard_widget(): void {
		$state  = self::state();
		$active = count( array_filter( $state['rules'] ) );
		$total  = count( $state['rules'] );
		$running = $state['enabled'] && $state['available'];
		?>
		<div class="wm-dash-widget-box">
			<div class="wm-dash-banner <?php echo $running ? 'is-valid' : 'is-warning'; ?>">
				<span class="dashicons <?php echo $running ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
				<div>
					<div class="wm-dash-banner-title">
						<?php
						if ( ! $state['available'] ) {
							esc_html_e( 'Regeln laufen nicht: PHP-Erweiterung dom oder mbstring fehlt', 'wm-bfsg-ast-remediator' );
						} elseif ( $state['enabled'] ) {
							esc_html_e( 'Regeln ändern das HTML der Frontend-Seiten', 'wm-bfsg-ast-remediator' );
						} else {
							esc_html_e( 'Ausgeschaltet: das Frontend bleibt unverändert', 'wm-bfsg-ast-remediator' );
						}
						?>
					</div>
				</div>
			</div>

			<div class="wm-dash-kpi-grid">
				<div class="wm-dash-kpi-card">
					<div class="wm-dash-kpi-val"><?php echo esc_html( sprintf( '%d / %d', $active, $total ) ); ?></div>
					<div class="wm-dash-kpi-lbl"><?php esc_html_e( 'Regeln eingeschaltet', 'wm-bfsg-ast-remediator' ); ?></div>
				</div>
			</div>

			<div class="wm-dash-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wm-bfsg-remediator' ) ); ?>" class="wm-dash-btn">
					<?php esc_html_e( 'Einstellungen und Simulator', 'wm-bfsg-ast-remediator' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Family menu icon: a monochrome hub-and-spoke mark, filled shapes only so the WordPress
	 * svg-painter can recolour it with the admin colour scheme. Placeholder until the family
	 * icon is decided (hackaton plan D12).
	 */
	public static function family_menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><circle cx="10" cy="10" r="2.6" fill="black"/><circle cx="10" cy="3" r="1.8" fill="black"/><circle cx="16.1" cy="13.5" r="1.8" fill="black"/><circle cx="3.9" cy="13.5" r="1.8" fill="black"/><rect x="9.3" y="4.5" width="1.4" height="3.4" fill="black"/><rect x="9.3" y="12.1" width="1.4" height="3.4" fill="black" transform="rotate(-60 10 10)"/><rect x="9.3" y="12.1" width="1.4" height="3.4" fill="black" transform="rotate(60 10 10)"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'WM BFSG Remediator', 'wm-bfsg-ast-remediator' ),
			__( 'BFSG Remediator', 'wm-bfsg-ast-remediator' ),
			'manage_options',
			'wm-bfsg-remediator',
			[ $this, 'render_dashboard' ],
			self::family_menu_icon(),
			81
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'toplevel_page_wm-bfsg-remediator' ) {
			return;
		}

		$root = dirname( __DIR__, 2 ) . '/assets/css/';
		wp_enqueue_style(
			'wm-admin-tokens',
			WM_BFSG_REMEDIATOR_URL . 'assets/css/wm-admin-tokens.css',
			[],
			(string) ( @filemtime( $root . 'wm-admin-tokens.css' ) ?: WM_BFSG_REMEDIATOR_VERSION )
		);
		wp_enqueue_style(
			'wm-admin-ui',
			WM_BFSG_REMEDIATOR_URL . 'assets/css/wm-admin-ui.css',
			[ 'wm-admin-tokens' ],
			(string) ( @filemtime( $root . 'wm-admin-ui.css' ) ?: WM_BFSG_REMEDIATOR_VERSION )
		);
	}

	/**
	 * Saves the kill-switch and the four rule switches the frontend engine reads. Until 2026-09-14
	 * the form saved `wm_bfsg_fix_headings`, which no rule reads, and never offered the skip-link
	 * switch the engine does read.
	 */
	public function save_settings(): void {
		if ( ! isset( $_POST['wm_bfsg_save_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wm_bfsg_save_nonce'] ) ), 'wm_bfsg_save_action' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		update_option( 'wm_bfsg_remediator_enabled', isset( $_POST['wm_bfsg_remediator_enabled'] ) ? '1' : '0' );
		update_option( 'wm_bfsg_remediator_delete_data_on_uninstall', isset( $_POST['wm_bfsg_remediator_delete_data_on_uninstall'] ) ? '1' : '0' );
		foreach ( array_keys( self::RULE_FIELDS ) as $option ) {
			update_option( $option, isset( $_POST[ $option ] ) ? '1' : '0' );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => 'wm-bfsg-remediator', 'tab' => 'rules', 'updated' => 'true' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_dashboard(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
		$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'overview' ) );
		if ( ! in_array( $active_tab, [ 'overview', 'simulator', 'rules', 'limits' ], true ) ) {
			$active_tab = 'overview';
		}
		$state        = self::state();
		$descriptions = self::rule_descriptions();
		$tabs         = [
			'overview'  => __( 'Übersicht', 'wm-bfsg-ast-remediator' ),
			'simulator' => __( 'Simulator', 'wm-bfsg-ast-remediator' ),
			'rules'     => __( 'Regeln', 'wm-bfsg-ast-remediator' ),
			'limits'    => __( 'Was es nicht tut', 'wm-bfsg-ast-remediator' ),
		];
		?>
		<div class="wrap wm-admin">
			<header class="wm-admin-header">
				<div class="wm-header-title">
					<h1><?php esc_html_e( 'WM BFSG AST Remediator', 'wm-bfsg-ast-remediator' ); ?></h1>
					<p class="wm-subtitle"><?php esc_html_e( 'Ändert das HTML jeder Frontend-Seite vor der Auslieferung mit fünf festen Regeln. Es prüft nicht, ob eine Seite barrierefrei ist, und macht keine Website BFSG-konform.', 'wm-bfsg-ast-remediator' ); ?></p>
				</div>
				<div class="wm-header-badges">
					<span class="wm-badge-family" title="<?php esc_attr_e( 'Part of the WM Suite plugin family', 'wm-bfsg-ast-remediator' ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="4" r="2"/><circle cx="19" cy="16" r="2"/><circle cx="5" cy="16" r="2"/><path d="M12 7v2M13.9 13.2l3.4 2M10.1 13.2l-3.4 2"/></svg>
						<?php esc_html_e( 'WM Suite', 'wm-bfsg-ast-remediator' ); ?>
						<span class="wm-badge-family-state"><?php echo defined( 'WM_SUITE_HUB_VERSION' ) ? esc_html__( 'Hub', 'wm-bfsg-ast-remediator' ) : esc_html__( 'Standalone', 'wm-bfsg-ast-remediator' ); ?></span>
					</span>
					<span class="wm-status-pill <?php echo ( $state['enabled'] && $state['available'] ) ? 'wm-status-healthy' : 'wm-status-needs_config'; ?>">
						<?php echo ( $state['enabled'] && $state['available'] ) ? esc_html__( 'Eingeschaltet', 'wm-bfsg-ast-remediator' ) : esc_html__( 'Läuft nicht', 'wm-bfsg-ast-remediator' ); ?>
					</span>
				</div>
			</header>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect flag after a verified save. ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Einstellungen gespeichert. Sie gelten ab dem nächsten Seitenaufruf.', 'wm-bfsg-ast-remediator' ); ?></p></div>
			<?php endif; ?>

			<nav class="wm-nav-tabs" aria-label="<?php esc_attr_e( 'Bereiche', 'wm-bfsg-ast-remediator' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wm-bfsg-remediator', 'tab' => $slug ], admin_url( 'admin.php' ) ) ); ?>" class="wm-tab-link <?php echo $active_tab === $slug ? 'active' : ''; ?>"<?php echo $active_tab === $slug ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( 'overview' === $active_tab ) : ?>
			<section class="wm-card" aria-labelledby="wm-bfsg-overview">
				<h2 id="wm-bfsg-overview"><?php esc_html_e( 'Was gerade läuft', 'wm-bfsg-ast-remediator' ); ?></h2>
				<p class="wm-card-caption"><?php esc_html_e( 'Gelesen aus den gespeicherten Einstellungen beim Aufruf dieser Seite. Das Plugin führt keine Statistik darüber, wie oft eine Regel etwas geändert hat.', 'wm-bfsg-ast-remediator' ); ?></p>
				<table class="wm-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Baustein', 'wm-bfsg-ast-remediator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Zustand', 'wm-bfsg-ast-remediator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Was er tut', 'wm-bfsg-ast-remediator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Engine', 'wm-bfsg-ast-remediator' ); ?></strong></td>
							<td><span class="wm-status-pill <?php echo $state['enabled'] ? 'wm-status-healthy' : 'wm-status-needs_config'; ?>"><?php echo $state['enabled'] ? esc_html__( 'an', 'wm-bfsg-ast-remediator' ) : esc_html__( 'aus', 'wm-bfsg-ast-remediator' ); ?></span></td>
							<td><?php esc_html_e( 'Puffert die Ausgabe jeder Frontend-Seite (nicht Admin, AJAX, Cron, REST) ab template_redirect und wendet die eingeschalteten Regeln auf Dokumente mit <html> an.', 'wm-bfsg-ast-remediator' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'PHP dom und mbstring', 'wm-bfsg-ast-remediator' ); ?></strong></td>
							<td><span class="wm-status-pill <?php echo $state['available'] ? 'wm-status-healthy' : 'wm-status-error'; ?>"><?php echo $state['available'] ? esc_html__( 'vorhanden', 'wm-bfsg-ast-remediator' ) : esc_html__( 'fehlt', 'wm-bfsg-ast-remediator' ); ?></span></td>
							<td><?php esc_html_e( 'Ohne diese Erweiterungen laufen die Regeln nicht und die Seiten bleiben unverändert.', 'wm-bfsg-ast-remediator' ); ?></td>
						</tr>
						<?php foreach ( $state['rules'] as $rule => $enabled ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $descriptions[ $rule ]['title'] ?? $rule ); ?></strong></td>
								<td><span class="wm-status-pill <?php echo $enabled ? 'wm-status-healthy' : 'wm-status-needs_config'; ?>"><?php echo $enabled ? esc_html__( 'an', 'wm-bfsg-ast-remediator' ) : esc_html__( 'aus', 'wm-bfsg-ast-remediator' ); ?></span></td>
								<td><?php echo esc_html( $descriptions[ $rule ]['does'] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>

			<?php elseif ( 'simulator' === $active_tab ) : ?>
			<section class="wm-card" aria-labelledby="wm-bfsg-simulator">
				<h2 id="wm-bfsg-simulator"><?php esc_html_e( 'Simulator', 'wm-bfsg-ast-remediator' ); ?></h2>
				<p class="wm-card-caption"><?php esc_html_e( 'Führt dieselbe Engine mit denselben gespeicherten Regeln aus wie das Frontend. Nur vollständige Dokumente mit <html> werden verarbeitet.', 'wm-bfsg-ast-remediator' ); ?></p>
				<div class="wm-grid-2">
					<div>
						<label for="wm-sim-input"><strong><?php esc_html_e( 'HTML-Dokument', 'wm-bfsg-ast-remediator' ); ?></strong></label>
						<textarea id="wm-sim-input" class="wm-textarea" rows="14"><?php echo esc_textarea( "<!DOCTYPE html>\n<html>\n<head><title>Beispiel</title></head>\n<body>\n  <nav class=\"main-menu\">…</nav>\n  <main id=\"primary\">\n    <h1>Shop</h1>\n    <form>\n      <label>Name <input type=\"text\" name=\"name\"></label>\n      <input type=\"search\" name=\"q\" placeholder=\"Produkt suchen\">\n    </form>\n    <" . "img src=\"/uploads/IMG_2034.jpg\">\n  </main>\n  <footer><nav class=\"footer-links\">…</nav></footer>\n</body>\n</html>" ); ?></textarea>
						<p><button type="button" class="button button-primary" id="wm-sim-run"><?php esc_html_e( 'Regeln anwenden', 'wm-bfsg-ast-remediator' ); ?></button></p>
					</div>
					<div>
						<label for="wm-sim-output"><strong><?php esc_html_e( 'Ergebnis', 'wm-bfsg-ast-remediator' ); ?></strong></label>
						<textarea id="wm-sim-output" class="wm-textarea" rows="14" readonly></textarea>
						<div id="wm-sim-status" role="status" aria-live="polite"></div>
						<ul id="wm-sim-fixes"></ul>
					</div>
				</div>
				<script>
				( function () {
					const run    = document.getElementById( 'wm-sim-run' );
					const input  = document.getElementById( 'wm-sim-input' );
					const output = document.getElementById( 'wm-sim-output' );
					const status = document.getElementById( 'wm-sim-status' );
					const fixes  = document.getElementById( 'wm-sim-fixes' );
					if ( ! run ) { return; }
					run.addEventListener( 'click', function () {
						status.textContent = <?php echo wp_json_encode( __( 'Wird verarbeitet …', 'wm-bfsg-ast-remediator' ) ); ?>;
						fixes.replaceChildren();
						output.value = '';
						fetch( <?php echo wp_json_encode( esc_url_raw( rest_url( 'wm-bfsg/v1/remediate-html' ) ) ); ?>, {
							method: 'POST',
							headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?> },
							body: JSON.stringify( { raw_html: input.value } )
						} )
							.then( function ( res ) { return res.json(); } )
							.then( function ( data ) {
								if ( ! data || ! data.success ) {
									status.textContent = ( data && data.error ) ? data.error : <?php echo wp_json_encode( __( 'Die Anfrage ist fehlgeschlagen.', 'wm-bfsg-ast-remediator' ) ); ?>;
									return;
								}
								output.value = data.data.html;
								status.textContent = data.data.modified
									? data.data.fixed_count + ' ' + <?php echo wp_json_encode( __( 'Regeln haben das Dokument geändert:', 'wm-bfsg-ast-remediator' ) ); ?>
									: <?php echo wp_json_encode( __( 'Keine aktive Regel hat etwas geändert. Das ist keine Aussage über die Barrierefreiheit.', 'wm-bfsg-ast-remediator' ) ); ?>;
								( data.data.fixes || [] ).forEach( function ( code ) {
									const li = document.createElement( 'li' );
									const c  = document.createElement( 'code' );
									c.textContent = code;
									li.appendChild( c );
									fixes.appendChild( li );
								} );
							} )
							.catch( function () {
								status.textContent = <?php echo wp_json_encode( __( 'Verbindungsfehler.', 'wm-bfsg-ast-remediator' ) ); ?>;
							} );
					} );
				} )();
				</script>
			</section>

			<?php elseif ( 'rules' === $active_tab ) : ?>
			<section class="wm-card" aria-labelledby="wm-bfsg-rules">
				<h2 id="wm-bfsg-rules"><?php esc_html_e( 'Regeln', 'wm-bfsg-ast-remediator' ); ?></h2>
				<form method="post" action="">
					<?php wp_nonce_field( 'wm_bfsg_save_action', 'wm_bfsg_save_nonce' ); ?>

					<div class="wm-field-card">
						<label class="wm-toggle-label">
							<input type="checkbox" name="wm_bfsg_remediator_enabled" value="1" <?php checked( $state['enabled'] ); ?> />
							<span class="wm-field-title"><?php esc_html_e( 'Engine einschalten', 'wm-bfsg-ast-remediator' ); ?></span>
						</label>
						<p class="wm-field-desc"><?php esc_html_e( 'Aus: keine Frontend-Seite wird verändert. Die Sprache des Dokuments hat keinen eigenen Schalter und läuft, solange die Engine an ist.', 'wm-bfsg-ast-remediator' ); ?></p>
					</div>

					<?php foreach ( self::RULE_FIELDS as $option => $rule ) : ?>
						<div class="wm-field-card">
							<label class="wm-toggle-label">
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( (bool) ( $state['rules'][ $rule ] ?? true ) ); ?> />
								<span class="wm-field-title"><?php echo esc_html( $descriptions[ $rule ]['title'] ); ?></span>
							</label>
							<p class="wm-field-desc"><?php echo esc_html( $descriptions[ $rule ]['does'] ); ?></p>
						</div>
					<?php endforeach; ?>

					<div class="wm-field-card">
						<label class="wm-toggle-label">
							<input type="checkbox" name="wm_bfsg_remediator_delete_data_on_uninstall" value="1" <?php checked( $state['delete_data_on_uninstall'] ); ?> />
							<span class="wm-field-title"><?php esc_html_e( 'Einstellungen beim Deinstallieren löschen', 'wm-bfsg-ast-remediator' ); ?></span>
						</label>
						<p class="wm-field-desc"><?php esc_html_e( 'Standardmäßig bleiben alle gespeicherten Optionen bei der Deinstallation erhalten. Nur wenn dieses Kontrollkästchen aktiviert ist, entfernt uninstall.php sämtliche Optionen des Plugins.', 'wm-bfsg-ast-remediator' ); ?></p>
					</div>

					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Einstellungen speichern', 'wm-bfsg-ast-remediator' ); ?></button></p>
				</form>
			</section>

			<?php else : ?>
			<section class="wm-card" aria-labelledby="wm-bfsg-limits">
				<h2 id="wm-bfsg-limits"><?php esc_html_e( 'Was das Plugin nicht tut', 'wm-bfsg-ast-remediator' ); ?></h2>
				<ul class="wm-card-body">
					<li><?php esc_html_e( 'Es prüft keine Seite auf Barrierefreiheit und erstellt keinen Bericht; ob eine Website BFSG, BITV 2.0 oder WCAG genügt, beantwortet es nicht.', 'wm-bfsg-ast-remediator' ); ?></li>
					<li><?php esc_html_e( 'Kontraste, Überschriftenhierarchie, Tastaturbedienung, Fokus, Schaltflächen- und Linktexte, Videos, PDFs und Inhalte, die per JavaScript nachgeladen werden, bleiben unberührt.', 'wm-bfsg-ast-remediator' ); ?></li>
					<li><?php esc_html_e( 'Ein aus dem Dateinamen gebildeter alt-Text („IMG 2034“) und ein aria-label aus name oder placeholder sind Notbehelfe, keine Beschreibungen. Die eigentliche Korrektur gehört in Theme und Inhalt.', 'wm-bfsg-ast-remediator' ); ?></li>
					<li><?php esc_html_e( 'Die Änderungen existieren nur im ausgelieferten HTML: Editor, Datenbank und Theme-Dateien bleiben wie sie sind, und Seiten aus einem Seiten-Cache bekommen sie nur, wenn der Cache nach dem Plugin gebaut wurde.', 'wm-bfsg-ast-remediator' ); ?></li>
				</ul>
			</section>
			<?php endif; ?>
		</div>
		<?php
	}
}
