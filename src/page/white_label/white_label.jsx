import React, { useState } from 'react';
import './white_label.scss';
import { __ } from '@wordpress/i18n';
import ajax from '../../utils/ajax.js';

const SwitchRow = ( { id, label, hint, checked, disabled, onChange } ) => (
	<label className="captlc-wl__switch-row" htmlFor={ id }>
		<div className="captlc-wl__switch-text">
			<span className="captlc-wl__switch-label">{ label }</span>
			{ hint && <span className="captlc-wl__switch-hint">{ hint }</span> }
		</div>
		<input
			id={ id }
			type="checkbox"
			role="switch"
			className="captlc-wl__switch"
			checked={ checked }
			disabled={ disabled }
			onChange={ ( e ) => onChange( e.target.checked ) }
		/>
	</label>
);

const WhiteLabelSettings = () => {
	const initial = ( typeof captlc_data !== 'undefined' && captlc_data?.white_label ) || {};

	// Plugin Identity
	const [ name, setName ]             = useState( initial.name || '' );
	const [ desc, setDesc ]             = useState( initial.desc || '' );
	const [ author, setAuthor ]         = useState( initial.author || '' );
	const [ authorUrl, setAuthorUrl ]   = useState( initial.author_url || '' );
	const [ logoUrl, setLogoUrl ]       = useState( initial.logo_url || '' );

	// Admin Navigation & Sidebar
	const [ hideProducts, setHideProducts ]     = useState( !! initial.hide_products );
	const [ hideDocs, setHideDocs ]             = useState( !! initial.hide_docs );
	const [ customDocsUrl, setCustomDocsUrl ]   = useState( initial.custom_docs_url || '' );
	const [ hideHelp, setHideHelp ]             = useState( !! initial.hide_help );
	const [ customHelpUrl, setCustomHelpUrl ]   = useState( initial.custom_help_url || '' );
	const [ hideVersion, setHideVersion ]       = useState( !! initial.hide_version );

	// Frontend Widget Branding
	const [ showBranding, setShowBranding ]     = useState( !! initial.show_branding );
	const [ brandingText, setBrandingText ]     = useState( initial.branding_text || '' );
	const [ brandingUrl, setBrandingUrl ]       = useState( initial.branding_url || '' );

	// Admin Footer
	const [ adminFooter, setAdminFooter ]       = useState( initial.admin_footer || '' );

	const [ saving, setSaving ]         = useState( false );
	const [ saveState, setSaveState ]   = useState( '' ); // '', 'saved', 'error'

	const handleBrowseLogo = () => {
		if ( window.wp && window.wp.media ) {
			const frame = window.wp.media( {
				title: __( 'Select or Upload Logo', 'captain-live-chat-pro' ),
				button: { text: __( 'Use this logo', 'captain-live-chat-pro' ) },
				multiple: false,
				library: { type: [ 'image', 'image/svg+xml' ] },
			} );
			frame.on( 'select', () => {
				const attachment = frame.state().get( 'selection' ).first().toJSON();
				if ( attachment && attachment.url ) {
					setLogoUrl( attachment.url );
				}
			} );
			frame.open();
		} else {
			const url = window.prompt( __( 'Enter logo image URL:', 'captain-live-chat-pro' ), logoUrl );
			if ( url !== null && url.trim() ) {
				setLogoUrl( url.trim() );
			}
		}
	};

	const handleSave = ( e ) => {
		e.preventDefault();
		setSaving( true );
		setSaveState( '' );

		const payload = {
			name,
			desc,
			author,
			author_url: authorUrl,
			logo_url: logoUrl,
			hide_products: hideProducts ? '1' : '0',
			hide_docs: hideDocs ? '1' : '0',
			custom_docs_url: customDocsUrl,
			hide_help: hideHelp ? '1' : '0',
			custom_help_url: customHelpUrl,
			hide_version: hideVersion ? '1' : '0',
			show_branding: showBranding ? '1' : '0',
			branding_text: brandingText,
			branding_url: brandingUrl,
			admin_footer: adminFooter,
		};

		ajax( 'captlc_pro_save_white_label', payload )
			.then( ( res ) => {
				if ( res?.success ) {
					setSaveState( 'saved' );
					setTimeout( () => setSaveState( '' ), 3000 );
				} else {
					setSaveState( 'error' );
				}
			} )
			.catch( () => setSaveState( 'error' ) )
			.finally( () => setSaving( false ) );
	};

	const handleReset = () => {
		if ( ! window.confirm( __( 'Are you sure you want to reset all white label settings back to default?', 'captain-live-chat-pro' ) ) ) {
			return;
		}

		setName( '' );
		setDesc( '' );
		setAuthor( '' );
		setAuthorUrl( '' );
		setLogoUrl( '' );
		setHideProducts( false );
		setHideDocs( false );
		setCustomDocsUrl( '' );
		setHideHelp( false );
		setCustomHelpUrl( '' );
		setHideVersion( false );
		setShowBranding( false );
		setBrandingText( '' );
		setBrandingUrl( '' );
		setAdminFooter( '' );

		setSaving( true );
		ajax( 'captlc_pro_save_white_label', {
			name: '',
			desc: '',
			author: '',
			author_url: '',
			logo_url: '',
			hide_products: '0',
			hide_docs: '0',
			custom_docs_url: '',
			hide_help: '0',
			custom_help_url: '',
			hide_version: '0',
			show_branding: '0',
			branding_text: '',
			branding_url: '',
			admin_footer: '',
		} )
			.then( ( res ) => {
				setSaveState( res?.success ? 'saved' : 'error' );
				if ( res?.success ) setTimeout( () => setSaveState( '' ), 3000 );
			} )
			.catch( () => setSaveState( 'error' ) )
			.finally( () => setSaving( false ) );
	};

	return (
		<div className="captlc-wl">
			<div className="captlc-wl__header">
				<h1>{ __( 'White Label Settings', 'captain-live-chat-pro' ) }</h1>
				<p>{ __( 'Customize plugin branding, WordPress meta, sidebar navigation, and widget attribution for your agency or clients.', 'captain-live-chat-pro' ) }</p>
			</div>

			<form onSubmit={ handleSave } className="captlc-wl__form">
				{ /* 1. Plugin Identity */ }
				<div className="captlc-wl__card">
					<div className="captlc-wl__card-header">
						<div className="captlc-wl__card-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
						</div>
						<div>
							<h2 className="captlc-wl__card-title">{ __( 'Plugin Identity & WordPress Meta', 'captain-live-chat-pro' ) }</h2>
							<p className="captlc-wl__card-desc">{ __( 'Change how the plugin appears in the WordPress admin menu and the Plugins list.', 'captain-live-chat-pro' ) }</p>
						</div>
					</div>

					<div className="captlc-wl__grid">
						<div className="captlc-wl__field">
							<label htmlFor="captlc-wl-name">{ __( 'Custom Plugin Name', 'captain-live-chat-pro' ) }</label>
							<input
								id="captlc-wl-name"
								type="text"
								placeholder={ __( 'e.g. Acme Live Support', 'captain-live-chat-pro' ) }
								value={ name }
								onChange={ ( e ) => setName( e.target.value ) }
							/>
							<p className="captlc-wl__hint">
								{ __( 'Shown in the WordPress admin menu, plugin header, and sidebar.', 'captain-live-chat-pro' ) }
							</p>
						</div>

						<div className="captlc-wl__field">
							<label htmlFor="captlc-wl-author">{ __( 'Plugin Author / Agency Name', 'captain-live-chat-pro' ) }</label>
							<input
								id="captlc-wl-author"
								type="text"
								placeholder={ __( 'e.g. Acme Digital Solutions', 'captain-live-chat-pro' ) }
								value={ author }
								onChange={ ( e ) => setAuthor( e.target.value ) }
							/>
							<p className="captlc-wl__hint">
								{ __( 'Shown on the WordPress Plugins screen as the author.', 'captain-live-chat-pro' ) }
							</p>
						</div>
					</div>

					<div className="captlc-wl__field">
						<label htmlFor="captlc-wl-author-url">{ __( 'Author / Agency Website URL', 'captain-live-chat-pro' ) }</label>
						<input
							id="captlc-wl-author-url"
							type="url"
							placeholder="https://acmedigital.com"
							value={ authorUrl }
							onChange={ ( e ) => setAuthorUrl( e.target.value ) }
						/>
						<p className="captlc-wl__hint">
							{ __( 'Link associated with the author and plugin details on the Plugins page.', 'captain-live-chat-pro' ) }
						</p>
					</div>

					<div className="captlc-wl__field">
						<label htmlFor="captlc-wl-desc">{ __( 'Plugin Description', 'captain-live-chat-pro' ) }</label>
						<textarea
							id="captlc-wl-desc"
							rows="2"
							placeholder={ __( 'Fast and beautiful real-time live chat tailored for our clients.', 'captain-live-chat-pro' ) }
							value={ desc }
							onChange={ ( e ) => setDesc( e.target.value ) }
						/>
						<p className="captlc-wl__hint">
							{ __( 'Replaces the default plugin description text on the WordPress Plugins screen.', 'captain-live-chat-pro' ) }
						</p>
					</div>

					<div className="captlc-wl__field">
						<label>{ __( 'Custom Brand Logo', 'captain-live-chat-pro' ) }</label>
						<div className="captlc-wl__logo-row">
							<button
								type="button"
								className={ `captlc-wl__logo-preview${ logoUrl ? '' : ' is-empty' }` }
								onClick={ handleBrowseLogo }
								title={ __( 'Click to upload or choose logo', 'captain-live-chat-pro' ) }
							>
								{ logoUrl ? <img src={ logoUrl } alt="" /> : <span>+</span> }
							</button>
							<div className="captlc-wl__logo-actions">
								<button type="button" className="captlc-wl__btn-secondary" onClick={ handleBrowseLogo }>
									{ __( 'Browse Media Library', 'captain-live-chat-pro' ) }
								</button>
								{ logoUrl && (
									<button type="button" className="captlc-wl__btn-text" onClick={ () => setLogoUrl( '' ) }>
										{ __( 'Remove logo', 'captain-live-chat-pro' ) }
									</button>
								) }
							</div>
						</div>
						<input
							type="url"
							className="captlc-wl__url-input"
							placeholder="https://example.com/logo.svg"
							value={ logoUrl }
							onChange={ ( e ) => setLogoUrl( e.target.value ) }
						/>
						<p className="captlc-wl__hint">
							{ __( 'Recommended: square SVG or PNG. Replaces the default logo in the sidebar and chat header.', 'captain-live-chat-pro' ) }
						</p>
					</div>
				</div>

				{ /* 2. Admin Navigation & Sidebar */ }
				<div className="captlc-wl__card">
					<div className="captlc-wl__card-header">
						<div className="captlc-wl__card-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
						</div>
						<div>
							<h2 className="captlc-wl__card-title">{ __( 'Admin Navigation & Sidebar', 'captain-live-chat-pro' ) }</h2>
							<p className="captlc-wl__card-desc">{ __( 'Control visibility of external promotion links, documentation, and help items.', 'captain-live-chat-pro' ) }</p>
						</div>
					</div>

					<div className="captlc-wl__switches">
						<SwitchRow
							id="captlc-wl-hide-products"
							label={ __( 'Hide "Our Products"', 'captain-live-chat-pro' ) }
							hint={ __( 'Removes the Our Products promotion link from both the WordPress submenu and the plugin sidebar.', 'captain-live-chat-pro' ) }
							checked={ hideProducts }
							onChange={ setHideProducts }
						/>

						<SwitchRow
							id="captlc-wl-hide-version"
							label={ __( 'Hide Version Number', 'captain-live-chat-pro' ) }
							hint={ __( 'Removes the "VERSION: 1.1.2" badge from the bottom of the plugin sidebar.', 'captain-live-chat-pro' ) }
							checked={ hideVersion }
							onChange={ setHideVersion }
						/>

						<SwitchRow
							id="captlc-wl-hide-docs"
							label={ __( 'Hide Documentation Link', 'captain-live-chat-pro' ) }
							hint={ __( 'Hides the external documentation link from the plugin sidebar completely.', 'captain-live-chat-pro' ) }
							checked={ hideDocs }
							onChange={ setHideDocs }
						/>

						{ ! hideDocs && (
							<div className="captlc-wl__field captlc-wl__indent">
								<label htmlFor="captlc-wl-docs-url">{ __( 'Custom Documentation URL', 'captain-live-chat-pro' ) }</label>
								<input
									id="captlc-wl-docs-url"
									type="url"
									placeholder="https://docs.acmedigital.com/chat"
									value={ customDocsUrl }
									onChange={ ( e ) => setCustomDocsUrl( e.target.value ) }
								/>
								<p className="captlc-wl__hint">
									{ __( 'Leave empty to keep the default documentation link, or set your own client guides URL.', 'captain-live-chat-pro' ) }
								</p>
							</div>
						) }

						<SwitchRow
							id="captlc-wl-hide-help"
							label={ __( 'Hide Help & FAQ Page', 'captain-live-chat-pro' ) }
							hint={ __( 'Hides the Help & FAQ link from the sidebar and WordPress admin submenu.', 'captain-live-chat-pro' ) }
							checked={ hideHelp }
							onChange={ setHideHelp }
						/>

						{ ! hideHelp && (
							<div className="captlc-wl__field captlc-wl__indent">
								<label htmlFor="captlc-wl-help-url">{ __( 'Custom Help / Support Portal URL', 'captain-live-chat-pro' ) }</label>
								<input
									id="captlc-wl-help-url"
									type="url"
									placeholder="https://support.acmedigital.com"
									value={ customHelpUrl }
									onChange={ ( e ) => setCustomHelpUrl( e.target.value ) }
								/>
								<p className="captlc-wl__hint">
									{ __( 'Redirects the Help navigation link to your custom agency ticket desk or support portal.', 'captain-live-chat-pro' ) }
								</p>
							</div>
						) }
					</div>
				</div>

				{ /* 3. Frontend Chat Widget Branding */ }
				<div className="captlc-wl__card">
					<div className="captlc-wl__card-header">
						<div className="captlc-wl__card-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
						</div>
						<div>
							<h2 className="captlc-wl__card-title">{ __( 'Frontend Widget Branding', 'captain-live-chat-pro' ) }</h2>
							<p className="captlc-wl__card-desc">{ __( 'Customize the "Powered by" branding footer displayed inside the visitor chat widget.', 'captain-live-chat-pro' ) }</p>
						</div>
					</div>

					<div className="captlc-wl__switches">
						<SwitchRow
							id="captlc-wl-show-branding"
							label={ __( 'Show "Powered by" Attribution', 'captain-live-chat-pro' ) }
							hint={ __( 'Toggle whether the branding credit appears at the bottom of the live chat popup.', 'captain-live-chat-pro' ) }
							checked={ showBranding }
							onChange={ setShowBranding }
						/>

						{ showBranding && (
							<div className="captlc-wl__grid captlc-wl__indent">
								<div className="captlc-wl__field">
									<label htmlFor="captlc-wl-branding-text">{ __( 'Branding Text', 'captain-live-chat-pro' ) }</label>
									<input
										id="captlc-wl-branding-text"
										type="text"
										placeholder={ __( 'Powered by Acme Digital', 'captain-live-chat-pro' ) }
										value={ brandingText }
										onChange={ ( e ) => setBrandingText( e.target.value ) }
									/>
									<p className="captlc-wl__hint">
										{ __( 'Text displayed at the base of the chat window.', 'captain-live-chat-pro' ) }
									</p>
								</div>

								<div className="captlc-wl__field">
									<label htmlFor="captlc-wl-branding-url">{ __( 'Branding Link URL', 'captain-live-chat-pro' ) }</label>
									<input
										id="captlc-wl-branding-url"
										type="url"
										placeholder="https://acmedigital.com"
										value={ brandingUrl }
										onChange={ ( e ) => setBrandingUrl( e.target.value ) }
									/>
									<p className="captlc-wl__hint">
										{ __( 'Optional link opened in a new tab when visitors click your branding.', 'captain-live-chat-pro' ) }
									</p>
								</div>
							</div>
						) }
					</div>
				</div>

				{ /* 4. WordPress Admin Footer */ }
				<div className="captlc-wl__card">
					<div className="captlc-wl__card-header">
						<div className="captlc-wl__card-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 17h18"/></svg>
						</div>
						<div>
							<h2 className="captlc-wl__card-title">{ __( 'WordPress Admin Footer', 'captain-live-chat-pro' ) }</h2>
							<p className="captlc-wl__card-desc">{ __( 'Replace the bottom admin footer text on Captain Live Chat screens.', 'captain-live-chat-pro' ) }</p>
						</div>
					</div>

					<div className="captlc-wl__field">
						<label htmlFor="captlc-wl-admin-footer">{ __( 'Custom Admin Footer Text', 'captain-live-chat-pro' ) }</label>
						<input
							id="captlc-wl-admin-footer"
							type="text"
							placeholder={ __( 'Crafted with care by Acme Agency for our clients.', 'captain-live-chat-pro' ) }
							value={ adminFooter }
							onChange={ ( e ) => setAdminFooter( e.target.value ) }
						/>
						<p className="captlc-wl__hint">
							{ __( 'Shown at the very bottom of the page when navigating plugin screens.', 'captain-live-chat-pro' ) }
						</p>
					</div>
				</div>

				{ /* Action buttons */ }
				<div className="captlc-wl__sticky-bar">
					<div className="captlc-wl__status-area">
						{ 'saved' === saveState && (
							<span className="captlc-wl__status is-saved">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
								{ __( 'Settings saved successfully! Reload the page to see changes across the menu.', 'captain-live-chat-pro' ) }
							</span>
						) }
						{ 'error' === saveState && (
							<span className="captlc-wl__status is-error">
								{ __( 'Could not save settings. Please try again.', 'captain-live-chat-pro' ) }
							</span>
						) }
					</div>

					<div className="captlc-wl__actions">
						<button
							type="button"
							className="captlc-wl__btn-secondary"
							onClick={ handleReset }
							disabled={ saving }
						>
							{ __( 'Reset to Defaults', 'captain-live-chat-pro' ) }
						</button>
						<button
							type="submit"
							className="captlc-wl__btn-save"
							disabled={ saving }
						>
							{ saving ? __( 'Saving…', 'captain-live-chat-pro' ) : __( 'Save All Changes', 'captain-live-chat-pro' ) }
						</button>
					</div>
				</div>
			</form>
		</div>
	);
};

export default WhiteLabelSettings;
