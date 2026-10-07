import React, { useState, useEffect } from 'react';
import './profile_alerts.scss';
import { __ } from '@wordpress/i18n';
import ajax from '../../utils/ajax.js';

/**
 * One labelled on/off switch row.
 *
 * @param {Object}   props
 * @param {string}   props.id       Input id.
 * @param {string}   props.label    Visible label.
 * @param {string}   props.hint     Short explanation under the label.
 * @param {boolean}  props.checked  Current value.
 * @param {boolean}  props.disabled Whether the switch can be changed.
 * @param {Function} props.onChange Called with the new boolean value.
 */
const AlertSwitch = ( { id, label, hint, checked, disabled, onChange } ) => (
	<label className="captlc-pro-alerts__row" htmlFor={ id }>
		<span className="captlc-pro-alerts__text">
			<span className="captlc-pro-alerts__label">{ label }</span>
			<span className="captlc-pro-alerts__hint">{ hint }</span>
		</span>
		<input
			id={ id }
			type="checkbox"
			role="switch"
			className="captlc-pro-alerts__switch"
			checked={ checked }
			disabled={ disabled }
			onChange={ ( e ) => onChange( e.target.checked ) }
		/>
	</label>
);

/**
 * Profile card (free plugin's "profile-alerts" slot): each agent switches
 * the pop-up alerts for new visitor messages on or off for themselves.
 * Saved as user meta by CAPTLC_Pro_Alerts.
 */
const ProfileAlerts = () => {
	const [ prefs, setPrefs ]   = useState( { enabled: true, sound: false } );
	const [ loaded, setLoaded ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ status, setStatus ] = useState( { type: '', msg: '' } );

	useEffect( () => {
		ajax( 'captlc_pro_get_alerts' )
			.then( ( res ) => {
				if ( res?.success ) {
					setPrefs( { enabled: !! res.data.enabled, sound: !! res.data.sound } );
				} else {
					setStatus( { type: 'error', msg: res?.data?.message || __( 'Could not load your alert settings.', 'captain-live-chat-pro' ) } );
				}
			} )
			.catch( () => setStatus( { type: 'error', msg: __( 'Network error.', 'captain-live-chat-pro' ) } ) )
			.finally( () => setLoaded( true ) );
	}, [] );

	const save = ( change ) => {
		const next     = { ...prefs, ...change };
		const previous = prefs;

		setPrefs( next );
		setSaving( true );
		setStatus( { type: '', msg: '' } );

		ajax( 'captlc_pro_save_alerts', { enabled: next.enabled ? '1' : '0', sound: next.sound ? '1' : '0' } )
			.then( ( res ) => {
				if ( res?.success ) {
					setStatus( { type: 'ok', msg: __( 'Saved. Applies from the next page you open.', 'captain-live-chat-pro' ) } );
				} else {
					setPrefs( previous );
					setStatus( { type: 'error', msg: res?.data?.message || __( 'Could not save.', 'captain-live-chat-pro' ) } );
				}
			} )
			.catch( () => {
				setPrefs( previous );
				setStatus( { type: 'error', msg: __( 'Network error - not saved.', 'captain-live-chat-pro' ) } );
			} )
			.finally( () => setSaving( false ) );
	};

	return (
		<div className="captlc-profile__details-card captlc-pro-alerts" data-purpose="message-alerts-card">
			<div>
				<div className="captlc-profile__status-title">
					{ __( 'Pop-up alerts for new messages', 'captain-live-chat-pro' ) }
					<span className="captlc-profile__pro-tag">{ __( 'Pro', 'captain-live-chat-pro' ) }</span>
				</div>
				<p className="captlc-profile__status-desc">
					{ __( 'See a pop-up on any WordPress admin page the moment a visitor writes. This only affects you, not other agents.', 'captain-live-chat-pro' ) }
				</p>
			</div>

			<AlertSwitch
				id="captlc-pro-alerts-enabled"
				label={ __( 'Show pop-up alerts', 'captain-live-chat-pro' ) }
				hint={ __( 'A small pop-up with the visitor name and message, with a button to open the chat.', 'captain-live-chat-pro' ) }
				checked={ prefs.enabled }
				disabled={ ! loaded || saving }
				onChange={ ( value ) => save( { enabled: value } ) }
			/>

			<AlertSwitch
				id="captlc-pro-alerts-sound"
				label={ __( 'Play a short sound', 'captain-live-chat-pro' ) }
				hint={ __( 'Browsers only allow sound after you have clicked somewhere on the page.', 'captain-live-chat-pro' ) }
				checked={ prefs.sound }
				disabled={ ! loaded || saving || ! prefs.enabled }
				onChange={ ( value ) => save( { sound: value } ) }
			/>

			<p
				className={ 'captlc-pro-alerts__status' + ( status.type ? ` is-${ status.type }` : '' ) }
				role={ 'error' === status.type ? 'alert' : 'status' }
				aria-live="polite"
			>
				{ status.msg }
			</p>
		</div>
	);
};

export default ProfileAlerts;
