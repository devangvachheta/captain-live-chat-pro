import React, { useState } from 'react';
import './white_label.scss';
import { __ } from '@wordpress/i18n';
import ajax from '../../utils/ajax.js';

const WhiteLabelSettings = () => {
	const initial = ( typeof captlc_data !== 'undefined' && captlc_data?.white_label ) || {};
	const [ name, setName ] = useState( initial.name || '' );
	const [ logoUrl, setLogoUrl ] = useState( initial.logo_url || '' );
	const [ saving, setSaving ] = useState( false );
	const [ saveState, setSaveState ] = useState( '' ); // '', 'saved', 'error'

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
		ajax( 'captlc_pro_save_white_label', { name, logo_url: logoUrl } )
			.then( ( res ) => {
				if ( res?.success ) {
					setSaveState( 'saved' );
					setTimeout( () => setSaveState( '' ), 2500 );
				} else {
					setSaveState( 'error' );
				}
			} )
			.catch( () => setSaveState( 'error' ) )
			.finally( () => setSaving( false ) );
	};

	const handleReset = () => {
		setName( '' );
		setLogoUrl( '' );
		setSaving( true );
		ajax( 'captlc_pro_save_white_label', { name: '', logo_url: '' } )
			.then( ( res ) => {
				setSaveState( res?.success ? 'saved' : 'error' );
				if ( res?.success ) setTimeout( () => setSaveState( '' ), 2500 );
			} )
			.catch( () => setSaveState( 'error' ) )
			.finally( () => setSaving( false ) );
	};

	return (
		<div className="captlc-wl">
			<div className="captlc-wl__header">
				<h1>{ __( 'White Label', 'captain-live-chat-pro' ) }</h1>
				<p>{ __( 'Replace the "Captain Live Chat" name and logo with your own agency branding, everywhere in this admin.', 'captain-live-chat-pro' ) }</p>
			</div>

			<form className="captlc-wl__card" onSubmit={ handleSave }>
				<div className="captlc-wl__field">
					<label htmlFor="captlc-wl-name">{ __( 'Custom Plugin Name', 'captain-live-chat-pro' ) }</label>
					<input
						id="captlc-wl-name"
						type="text"
						placeholder={ __( 'e.g. Acme Agency Chat', 'captain-live-chat-pro' ) }
						value={ name }
						onChange={ ( e ) => setName( e.target.value ) }
					/>
					<p className="captlc-wl__hint">
						{ __( 'Shown in the WordPress admin menu and the plugin sidebar. Leave blank to keep the default name.', 'captain-live-chat-pro' ) }
					</p>
				</div>

				<div className="captlc-wl__field">
					<label>{ __( 'Custom Logo', 'captain-live-chat-pro' ) }</label>
					<div className="captlc-wl__logo-row">
						<button
							type="button"
							className={ `captlc-wl__logo-preview${ logoUrl ? '' : ' is-empty' }` }
							onClick={ handleBrowseLogo }
							title={ __( 'Click to change', 'captain-live-chat-pro' ) }
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
						{ __( 'Recommended: a square SVG or PNG icon. Leave blank to keep the default logo.', 'captain-live-chat-pro' ) }
					</p>
				</div>

				<div className="captlc-wl__actions">
					<button type="button" className="captlc-wl__btn-secondary" onClick={ handleReset } disabled={ saving }>
						{ __( 'Reset to Default', 'captain-live-chat-pro' ) }
					</button>
					<button type="submit" className="captlc-wl__btn-save" disabled={ saving }>
						{ saving ? __( 'Saving…', 'captain-live-chat-pro' ) : __( 'Save Branding', 'captain-live-chat-pro' ) }
					</button>
				</div>

				{ 'saved' === saveState && <p className="captlc-wl__status is-saved">{ __( 'Saved - reload the page to see it everywhere.', 'captain-live-chat-pro' ) }</p> }
				{ 'error' === saveState && <p className="captlc-wl__status is-error">{ __( 'Could not save. Please try again.', 'captain-live-chat-pro' ) }</p> }
			</form>
		</div>
	);
};

export default WhiteLabelSettings;
