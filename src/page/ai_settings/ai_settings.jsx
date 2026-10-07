import React, { useState, useEffect, useRef } from 'react';
import './ai_settings.scss';
import { __, sprintf } from '@wordpress/i18n';
import ajax, { parseResponse } from '../../utils/ajax.js';

// ── Provider definitions ──────────────────────────────────────────────────
const PROVIDERS = [
	{
		id:          'groq',
		name:        'Groq',
		badge:       __( 'Free', 'captain-live-chat-pro' ),
		badgeType:   'free',
		placeholder: 'gsk-...',
		models:      [ 'openai/gpt-oss-120b', 'openai/gpt-oss-20b', 'qwen/qwen3.6-27b' ],
		freeLink:    'https://console.groq.com/keys',
	},
	{
		id:          'gemini',
		name:        'Google Gemini',
		badge:       __( 'Free tier', 'captain-live-chat-pro' ),
		badgeType:   'free',
		placeholder: 'AIza...',
		models:      [ 'gemini-3.5-flash', 'gemini-3.1-flash-lite' ],
		freeLink:    'https://aistudio.google.com/app/apikey',
	},
	{
		id:          'openai',
		name:        'OpenAI',
		badge:       __( 'Paid', 'captain-live-chat-pro' ),
		badgeType:   'paid',
		placeholder: 'sk-...',
		models:      [ 'gpt-4o-mini', 'gpt-5.4-mini', 'gpt-5.5' ],
		freeLink:    'https://platform.openai.com/api-keys',
	},
	{
		id:          'anthropic',
		name:        'Anthropic Claude',
		badge:       __( 'Paid', 'captain-live-chat-pro' ),
		badgeType:   'paid',
		placeholder: 'sk-ant-...',
		models:      [ 'claude-haiku-4-5-20251001', 'claude-sonnet-5-5', 'claude-opus-5-5' ],
		freeLink:    'https://console.anthropic.com/settings/keys',
	},
	{
		id:          'openrouter',
		name:        'OpenRouter',
		badge:       __( 'Free models', 'captain-live-chat-pro' ),
		badgeType:   'free',
		placeholder: 'sk-or-...',
		models:      [ 'meta-llama/llama-3.3-70b-instruct:free' ],
		freeLink:    'https://openrouter.ai/keys',
	},
];

// Models the providers have shut down. A saved one is swapped for the
// provider's first listed model instead of being kept as a "custom" name.
const RETIRED_MODELS = [
	'gemini-2.0-flash',
	'gemini-2.0-flash-001',
	'gemini-1.5-flash',
	'gemini-1.5-pro',
	'gemini-1.5-flash-8b',
	'gpt-4-turbo',
	'claude-sonnet-4-6',
	'claude-opus-4-6',
];

// <select> value for "type your own model name".
const CUSTOM_MODEL = '__custom__';

// Same rule as CAPTLC_AI::is_valid_model() on the server.
const MODEL_PATTERN = /^[A-Za-z0-9._:/-]{1,100}$/;

const EyeIcon = ( { off } ) => off ? (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="14" height="14">
		<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-10-8-10-8a18.36 18.36 0 0 1 4.22-5.94M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
		<line x1="1" y1="1" x2="23" y2="23"/>
	</svg>
) : (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="14" height="14">
		<path d="M1 12s3-8 11-8 11 8 11 8-3 8-11 8-11-8-11-8z"/>
		<circle cx="12" cy="12" r="3"/>
	</svg>
);

const ChevronIcon = ( { open } ) => (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="14" height="14" style={ { transform: open ? 'rotate(180deg)' : 'none', transition: 'transform .15s ease' } }>
		<polyline points="6 9 12 15 18 9"/>
	</svg>
);

const InfoIcon = () => (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="13" height="13">
		<circle cx="12" cy="12" r="10"/>
		<line x1="12" y1="16" x2="12" y2="11"/>
		<line x1="12" y1="8" x2="12.01" y2="8"/>
	</svg>
);

const LockIcon = () => (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="15" height="15">
		<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
		<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
	</svg>
);

// ── Field label with an inline "i" tooltip for extra context ─────────────
const LabelWithInfo = ( { children, tip, htmlFor } ) => (
	<label className="captlc-field__label captlc-field__label--with-info" htmlFor={ htmlFor }>
		{ children }
		<span className="captlc-field__info" tabIndex="0" title={ tip }>
			<InfoIcon />
		</span>
	</label>
);

// ── Enabled / Disabled segmented toggle ───────────────────────────────────
const SegmentedToggle = ( { checked, onChange } ) => (
	<div className="captlc-segmented-toggle" role="group">
		<button
			type="button"
			className={ `captlc-segmented-toggle__btn${ checked ? ' is-active' : '' }` }
			onClick={ () => onChange( true ) }
		>{ __( 'Enabled', 'captain-live-chat-pro' ) }</button>
		<button
			type="button"
			className={ `captlc-segmented-toggle__btn${ ! checked ? ' is-active' : '' }` }
			onClick={ () => onChange( false ) }
		>{ __( 'Disabled', 'captain-live-chat-pro' ) }</button>
	</div>
);

// ── Single provider row (collapsed header + expandable body) ─────────────
const ProviderRow = ( { provider, savedKey, savedKeyPreview, savedModel, isConnected, isUnreadable, isActive, expanded, onToggle, onSetActive, onSave, onTest, onRemove } ) => {
	const [ key, setKey ]               = useState( savedKey || '' );
	// A retired model falls back to the first listed one; any other saved
	// model that is not in the list is a custom model name the admin typed.
	const initialModel                  = savedModel && ! RETIRED_MODELS.includes( savedModel ) ? savedModel : provider.models[ 0 ];
	const [ model, setModel ]           = useState( initialModel );
	const [ customModel, setCustomModel ] = useState( ! provider.models.includes( initialModel ) );
	const [ show, setShow ]             = useState( false );
	const [ saving, setSaving ]         = useState( false );
	const [ testing, setTesting ]       = useState( false );
	const [ testResult, setTestResult ] = useState( null );
	const [ saveResult, setSaveResult ] = useState( null );
	const modelValid                    = MODEL_PATTERN.test( model.trim() );

	const handleSave = ( e ) => {
		e.stopPropagation();
		if ( ! modelValid ) {
			setSaveResult( { ok: false, msg: __( 'Enter a valid model name.', 'captain-live-chat-pro' ) } );
			return;
		}
		setSaving( true );
		setSaveResult( null );
		onSave( provider.id, key.trim(), model.trim() )
			.then( ( res ) => {
				setSaveResult( res?.success
					? { ok: true, msg: __( '✓ Saved', 'captain-live-chat-pro' ) }
					: { ok: false, msg: res?.data?.message || __( 'Could not save', 'captain-live-chat-pro' ) }
				);
			} )
			.catch( () => setSaveResult( { ok: false, msg: __( 'Network error - not saved', 'captain-live-chat-pro' ) } ) )
			.finally( () => {
				setSaving( false );
				setTimeout( () => setSaveResult( null ), 3500 );
			} );
	};

	const handleTest = ( e ) => {
		e.stopPropagation();
		// With no key typed in, the server tests the key that is already saved.
		if ( ! key.trim() && ! isConnected ) return;
		if ( ! modelValid ) {
			setTestResult( { ok: false, msg: __( 'Enter a valid model name.', 'captain-live-chat-pro' ) } );
			return;
		}
		setTesting( true );
		setTestResult( null );
		onTest( provider.id, key.trim(), model.trim() )
			.then( ( res ) => {
				setTestResult( res?.success
					? { ok: true,  msg: __( 'Connected', 'captain-live-chat-pro' ) }
					: { ok: false, msg: res?.data?.message || __( 'Connection failed', 'captain-live-chat-pro' ) }
				);
			} )
			.catch( () => setTestResult( { ok: false, msg: __( 'Network error', 'captain-live-chat-pro' ) } ) )
			.finally( () => setTesting( false ) );
	};

	const handleRemove = ( e ) => {
		e.stopPropagation();
		if ( ! window.confirm( __( 'Remove the saved API key for this provider?', 'captain-live-chat-pro' ) ) ) return;
		setSaving( true );
		setSaveResult( null );
		onRemove( provider.id )
			.then( () => {
				setKey( '' );
				setSaveResult( { ok: true, msg: __( 'Key removed', 'captain-live-chat-pro' ) } );
			} )
			.catch( () => setSaveResult( { ok: false, msg: __( 'Network error', 'captain-live-chat-pro' ) } ) )
			.finally( () => {
				setSaving( false );
				setTimeout( () => setSaveResult( null ), 3500 );
			} );
	};

	return (
		<div className={ `captlc-ai-row${ expanded ? ' is-expanded' : '' }${ isActive ? ' is-active-provider' : '' }` }>
			<button type="button" className="captlc-ai-row__head" onClick={ onToggle }>
				<span className={ `captlc-ai-row__dot${ isConnected ? ' is-connected' : '' }` } title={ isConnected ? __( 'Connected', 'captain-live-chat-pro' ) : __( 'Not connected', 'captain-live-chat-pro' ) } />
				<span className="captlc-ai-row__name">{ provider.name }</span>
				<span className={ `captlc-ai-row__badge captlc-ai-row__badge--${ provider.badgeType }` }>{ provider.badge }</span>
				{ isActive && <span className="captlc-ai-row__active-tag">{ __( 'Active', 'captain-live-chat-pro' ) }</span> }
				<span className="captlc-ai-row__spacer" />
				<span className="captlc-ai-row__status">
					{ isUnreadable
						? __( 'Key unreadable', 'captain-live-chat-pro' )
						: ( isConnected ? __( 'Connected', 'captain-live-chat-pro' ) : __( 'Not connected', 'captain-live-chat-pro' ) ) }
				</span>
				<ChevronIcon open={ expanded } />
			</button>

			{ expanded && (
				<div className="captlc-ai-row__body">
					{ isUnreadable && (
						<div className="captlc-notice captlc-notice--error" role="alert">
							{ __( 'The saved key for this provider can no longer be read (the site security keys may have changed). Please enter the key again and save.', 'captain-live-chat-pro' ) }
						</div>
					) }
					<div className="captlc-ai-row__field-grid">
						<div className="captlc-ai-row__field">
							<label className="captlc-field__label" htmlFor={ `captlc-ai-key-${ provider.id }` }>{ __( 'API Key', 'captain-live-chat-pro' ) }</label>
							<div className="captlc-ai-row__key-input">
								<input
									id={ `captlc-ai-key-${ provider.id }` }
									type={ show ? 'text' : 'password' }
									className="captlc-input-field"
									placeholder={ isConnected && ! key ? ( savedKeyPreview || __( 'Saved - enter a new key to replace it', 'captain-live-chat-pro' ) ) : provider.placeholder }
									value={ key }
									onChange={ ( e ) => setKey( e.target.value ) }
								/>
								{ /* Nothing to reveal when the field is empty - the masked
								   preview shown as a placeholder (e.g. gsk_••••••••PPMG) is
								   never the real key and toggling type=text/password on an
								   empty input has no visible effect, which read as "broken". */ }
								{ key && (
									<button type="button" className="captlc-ai-row__show-btn" onClick={ () => setShow( ( v ) => ! v ) } title={ show ? __( 'Hide', 'captain-live-chat-pro' ) : __( 'Show', 'captain-live-chat-pro' ) }>
										<EyeIcon off={ show } />
									</button>
								) }
							</div>
						</div>

						<div className="captlc-ai-row__field captlc-ai-row__field--model">
							<label className="captlc-field__label" htmlFor={ `captlc-ai-model-${ provider.id }` }>{ __( 'Model', 'captain-live-chat-pro' ) }</label>
							<select
								id={ `captlc-ai-model-${ provider.id }` }
								className="captlc-select"
								value={ customModel ? CUSTOM_MODEL : model }
								onChange={ ( e ) => {
									if ( CUSTOM_MODEL === e.target.value ) {
										setCustomModel( true );
									} else {
										setCustomModel( false );
										setModel( e.target.value );
									}
								} }
							>
								{ provider.models.map( ( m ) => <option key={ m } value={ m }>{ m }</option> ) }
								<option value={ CUSTOM_MODEL }>{ __( 'Other (type a model name)…', 'captain-live-chat-pro' ) }</option>
							</select>
							{ customModel && (
								<input
									type="text"
									className="captlc-input-field captlc-ai-row__custom-model"
									value={ model }
									maxLength={ 100 }
									placeholder={ __( 'model-name', 'captain-live-chat-pro' ) }
									aria-label={ __( 'Model name', 'captain-live-chat-pro' ) }
									aria-invalid={ ! modelValid }
									onChange={ ( e ) => setModel( e.target.value ) }
								/>
							) }
						</div>
					</div>

					<div className="captlc-ai-row__footer">
						<div className="captlc-ai-row__footer-left">
							<a href={ provider.freeLink } target="_blank" rel="noopener noreferrer" className="captlc-ai-row__free-link">
								{ __( 'Get free API key', 'captain-live-chat-pro' ) } <span className="captlc-dir-arrow" aria-hidden="true">→</span>
							</a>
							{ testResult && (
								<span className={ `captlc-ai-row__test-result${ testResult.ok ? ' is-ok' : ' is-fail' }` }>
									{ testResult.msg }
								</span>
							) }
							{ saveResult && (
								<span className={ `captlc-ai-row__test-result${ saveResult.ok ? ' is-ok' : ' is-fail' }` }>
									{ saveResult.msg }
								</span>
							) }
						</div>
						<div className="captlc-ai-row__footer-right">
							{ ! isActive && (
								<button type="button" className="captlc-secondary-button captlc-ai-row__small-btn" onClick={ ( e ) => { e.stopPropagation(); onSetActive( provider.id ); } }>
									{ __( 'Set as active', 'captain-live-chat-pro' ) }
								</button>
							) }
							{ isConnected && (
								<button type="button" className="captlc-secondary-button captlc-ai-row__small-btn captlc-ai-row__remove-btn" onClick={ handleRemove } disabled={ saving }>
									{ __( 'Remove key', 'captain-live-chat-pro' ) }
								</button>
							) }
							<button type="button" className="captlc-secondary-button captlc-ai-row__small-btn" onClick={ handleTest } disabled={ testing || ( ! key.trim() && ! isConnected ) }>
								{ testing ? __( 'Testing…', 'captain-live-chat-pro' ) : __( 'Test', 'captain-live-chat-pro' ) }
							</button>
							<button type="button" className="captlc-primary-button captlc-ai-row__small-btn" onClick={ handleSave } disabled={ saving }>
								{ saving ? __( 'Saving…', 'captain-live-chat-pro' ) : __( 'Save', 'captain-live-chat-pro' ) }
							</button>
						</div>
					</div>
				</div>
			) }
		</div>
	);
};

// ── Knowledge Base ─────────────────────────────────────────────────────────
const KnowledgeIcon = ( { type } ) => type === 'url' ? (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="14" height="14">
		<circle cx="12" cy="12" r="10"/>
		<line x1="2" y1="12" x2="22" y2="12"/>
		<path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
	</svg>
) : (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="14" height="14">
		<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
		<polyline points="14 2 14 8 20 8"/>
	</svg>
);

const TrashIcon = () => (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="14" height="14">
		<polyline points="3 6 5 6 21 6"/>
		<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
	</svg>
);

const ViewIcon = () => (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="14" height="14">
		<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
		<circle cx="12" cy="12" r="3"/>
	</svg>
);

const CloseIcon = () => (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="16" height="16">
		<line x1="18" y1="6" x2="6" y2="18"/>
		<line x1="6" y1="6" x2="18" y2="18"/>
	</svg>
);

const WarnIcon = () => (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="14" height="14">
		<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
		<line x1="12" y1="9" x2="12" y2="13"/>
		<line x1="12" y1="17" x2="12.01" y2="17"/>
	</svg>
);

const RefreshIcon = ( { spinning } ) => (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="14" height="14"
		className={ spinning ? 'captlc-spin' : '' }>
		<polyline points="23 4 23 10 17 10"/>
		<polyline points="1 20 1 14 7 14"/>
		<path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
	</svg>
);

/**
 * Below this many extracted characters, a source is very likely to have
 * given the AI little or nothing useful - e.g. a JavaScript-rendered page
 * where the server only returned an empty shell. Purely a UI hint for the
 * admin; doesn't affect what's actually sent to the AI.
 */
const LOW_YIELD_THRESHOLD = 300;

const KnowledgeTextModal = ( { entry, onClose } ) => (
	<div className="captlc-ai-kb-modal__overlay" onClick={ onClose }>
		<div className="captlc-ai-kb-modal" onClick={ ( e ) => e.stopPropagation() }>
			<div className="captlc-ai-kb-modal__head">
				<div className="captlc-ai-kb-modal__head-text">
					<h4 className="captlc-ai-kb-modal__title">{ entry.title }</h4>
					{ entry.type === 'url' && (
						<a href={ entry.source } target="_blank" rel="noreferrer" className="captlc-ai-kb-modal__source">{ entry.source }</a>
					) }
				</div>
				<button type="button" className="captlc-ai-kb-modal__close" onClick={ onClose } title={ __( 'Close', 'captain-live-chat-pro' ) }>
					<CloseIcon />
				</button>
			</div>
			<div className="captlc-ai-kb-modal__meta">
				{ sprintf(
					/* translators: %s: number of characters extracted from this source. */
					__( '%s characters, split into searchable chunks - for each visitor question the AI is sent only the most relevant chunks from this text.', 'captain-live-chat-pro' ),
					entry.char_count.toLocaleString()
				) }
			</div>
			{ entry.content_truncated && (
				<div className="captlc-ai-kb-modal__meta">
					{ __( 'Only the first part of the text is shown here. The AI still searches the whole source.', 'captain-live-chat-pro' ) }
				</div>
			) }
			<div className="captlc-ai-kb-modal__body">
				{ entry.content
					? entry.content
					: __( '(No text was extracted from this source.)', 'captain-live-chat-pro' ) }
			</div>
		</div>
	</div>
);

const KnowledgeBaseSection = () => {
	const [ entries, setEntries ]       = useState( [] );
	const [ loading, setLoading ]       = useState( true );
	const [ urlInput, setUrlInput ]     = useState( '' );
	const [ addingUrl, setAddingUrl ]   = useState( false );
	const [ uploading, setUploading ]   = useState( false );
	const [ error, setError ]           = useState( '' );
	const [ viewingEntry, setViewingEntry ] = useState( null );
	const [ refreshingId, setRefreshingId ] = useState( '' );
	const [ refreshError, setRefreshError ] = useState( { id: '', message: '' } );
	const fileInputRef = useRef( null );

	useEffect( () => {
		ajax( 'captlc_get_knowledge' )
			.then( ( res ) => {
				if ( res?.success ) {
					setEntries( res.data.entries || [] );
				} else {
					setError( res?.data?.message || __( 'Could not load the knowledge base.', 'captain-live-chat-pro' ) );
				}
			} )
			.catch( () => setError( __( 'Network error.', 'captain-live-chat-pro' ) ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const handleAddUrl = () => {
		if ( ! urlInput.trim() || addingUrl ) return;
		setAddingUrl( true );
		setError( '' );
		ajax( 'captlc_add_knowledge_url', { url: urlInput.trim() } )
			.then( ( res ) => {
				if ( res?.success ) {
					setEntries( ( prev ) => [ ...prev, res.data.entry ] );
					setUrlInput( '' );
				} else {
					setError( res?.data?.message || __( 'Could not add this link.', 'captain-live-chat-pro' ) );
				}
			} )
			.catch( () => setError( __( 'Network error.', 'captain-live-chat-pro' ) ) )
			.finally( () => setAddingUrl( false ) );
	};

	const handleFilePick = ( e ) => {
		const file = e.target.files && e.target.files[ 0 ];
		if ( ! file ) return;
		setUploading( true );
		setError( '' );

		const body = new FormData();
		body.append( 'action', 'captlc_upload_knowledge_file' );
		body.append( 'nonce', captlc_data.nonce );
		body.append( 'captlc_knowledge_file', file );

		fetch( captlc_data.ajax_url, { method: 'POST', credentials: 'same-origin', body } )
			.then( parseResponse )
			.then( ( res ) => {
				if ( res?.success ) {
					setEntries( ( prev ) => [ ...prev, res.data.entry ] );
				} else {
					setError( res?.data?.message || __( 'Could not process this file.', 'captain-live-chat-pro' ) );
				}
			} )
			.catch( () => setError( __( 'Network error.', 'captain-live-chat-pro' ) ) )
			.finally( () => {
				setUploading( false );
				if ( fileInputRef.current ) fileInputRef.current.value = '';
			} );
	};

	const handleDelete = ( id ) => {
		const previous = entries;
		setError( '' );
		setEntries( ( prev ) => prev.filter( ( e ) => e.id !== id ) );
		ajax( 'captlc_delete_knowledge', { id } )
			.then( ( res ) => {
				if ( ! res?.success ) {
					setEntries( previous );
					setError( res?.data?.message || __( 'Could not remove this source.', 'captain-live-chat-pro' ) );
				}
			} )
			.catch( () => {
				setEntries( previous );
				setError( __( 'Network error - the source was not removed.', 'captain-live-chat-pro' ) );
			} );
	};

	const handleRefresh = ( id ) => {
		if ( refreshingId ) return;
		setRefreshingId( id );
		setRefreshError( { id: '', message: '' } );
		ajax( 'captlc_refresh_knowledge_url', { id } )
			.then( ( res ) => {
				if ( res?.success ) {
					setEntries( ( prev ) => prev.map( ( e ) => ( e.id === id ? res.data.entry : e ) ) );
					setViewingEntry( ( prev ) => ( prev && prev.id === id ? res.data.entry : prev ) );
				} else {
					setRefreshError( { id, message: res?.data?.message || __( 'Could not refresh this link.', 'captain-live-chat-pro' ) } );
				}
			} )
			.catch( () => setRefreshError( { id, message: __( 'Network error.', 'captain-live-chat-pro' ) } ) )
			.finally( () => setRefreshingId( '' ) );
	};

	return (
		<div className="captlc-ai-kb-section">
			<div className="captlc-ai-kb-section__head">
				<h3 className="captlc-ai-kb-section__title">{ __( 'Knowledge Base', 'captain-live-chat-pro' ) }</h3>
				<p className="captlc-card__desc">
					{ __( 'Add documents or website links. The AI reads them and uses the content to answer visitor questions.', 'captain-live-chat-pro' ) }
				</p>
				<p className="captlc-field__hint">
					{ __( 'Anything you add here can be repeated to visitors in AI answers, so do not add private or confidential material. Text-based PDFs and .txt files work best; PDFs with custom fonts (common for Hindi, Gujarati and other non-Latin text) and scanned PDFs cannot be read - use a .txt file for those.', 'captain-live-chat-pro' ) }
				</p>
			</div>

			{ error && (
				<div className="captlc-notice captlc-notice--error captlc-ai-kb__notice">{ error }</div>
			) }

			<div className="captlc-ai-kb__add-row">
				<input
					type="url"
					className="captlc-input-field"
					placeholder={ __( 'https://yoursite.com/faq', 'captain-live-chat-pro' ) }
					value={ urlInput }
					onChange={ ( e ) => setUrlInput( e.target.value ) }
					onKeyDown={ ( e ) => { if ( 'Enter' === e.key ) handleAddUrl(); } }
				/>
				<button type="button" className="captlc-secondary-button captlc-ai-row__small-btn" onClick={ handleAddUrl } disabled={ addingUrl || ! urlInput.trim() }>
					{ addingUrl ? __( 'Adding…', 'captain-live-chat-pro' ) : __( 'Add Link', 'captain-live-chat-pro' ) }
				</button>

				<span className="captlc-ai-kb__or">{ __( 'or', 'captain-live-chat-pro' ) }</span>

				<input
					type="file"
					ref={ fileInputRef }
					accept=".pdf,.txt,application/pdf,text/plain"
					onChange={ handleFilePick }
					tabIndex={ -1 }
					aria-hidden="true"
					style={ { display: 'none' } }
					id="captlc-kb-file"
				/>
				<button
					type="button"
					className="captlc-secondary-button captlc-ai-row__small-btn captlc-ai-kb__upload-btn"
					onClick={ () => fileInputRef.current?.click() }
					disabled={ uploading }
				>
					{ uploading ? __( 'Reading…', 'captain-live-chat-pro' ) : __( 'Upload PDF / .txt', 'captain-live-chat-pro' ) }
				</button>
			</div>

			{ ! loading && entries.length > 0 && (
				<div className="captlc-ai-kb__list">
					{ entries.map( ( entry ) => {
						const isLowYield  = entry.char_count < LOW_YIELD_THRESHOLD;
						const isRefreshing = refreshingId === entry.id;
						const hasRefreshError = refreshError.id === entry.id;

						return (
							<div key={ entry.id } className="captlc-ai-kb__item">
								<span className="captlc-ai-kb__item-icon"><KnowledgeIcon type={ entry.type } /></span>
								<div className="captlc-ai-kb__item-body">
									<span className="captlc-ai-kb__item-title">{ entry.title }</span>
									<span className="captlc-ai-kb__item-meta">
										{ entry.type === 'url' && `${ entry.source } · ` }
										{ isLowYield && <WarnIcon /> }{ ' ' }
										{ sprintf(
											/* translators: %s: number of characters extracted from this source. */
											__( '%s characters extracted', 'captain-live-chat-pro' ),
											entry.char_count.toLocaleString()
										) }
										{ entry.chunk_count > 0 && (
											/* translators: %d: number of searchable chunks the source was split into. */
											` · ${ sprintf( __( '%d searchable chunks', 'captain-live-chat-pro' ), entry.chunk_count ) }`
										) }
										{ entry.type === 'url' && entry.updated_at && (
											/* translators: %s: date/time this link was last fetched. */
											` · ${ sprintf( __( 'last fetched %s', 'captain-live-chat-pro' ), entry.updated_at ) }`
										) }
									</span>
									{ isLowYield && (
										<span className="captlc-ai-kb__item-warning">
											{ __( 'Very little text was found - if this is a JavaScript-heavy site, the AI may only have seen an empty page.', 'captain-live-chat-pro' ) }
										</span>
									) }
									{ hasRefreshError && (
										<span className="captlc-ai-kb__item-warning">{ refreshError.message }</span>
									) }
								</div>
								{ entry.type === 'url' && (
									<button
										type="button"
										className="captlc-ai-kb__preview-btn"
										onClick={ () => handleRefresh( entry.id ) }
										disabled={ isRefreshing }
										title={ __( 'Re-fetch this link\'s current content', 'captain-live-chat-pro' ) }
									>
										<RefreshIcon spinning={ isRefreshing } />
									</button>
								) }
								<button
									type="button"
									className="captlc-ai-kb__preview-btn"
									onClick={ () => setViewingEntry( entry ) }
									title={ __( 'View full extracted text', 'captain-live-chat-pro' ) }
								>
									<ViewIcon />
								</button>
								<button type="button" className="captlc-ai-kb__delete-btn" onClick={ () => handleDelete( entry.id ) } title={ __( 'Remove', 'captain-live-chat-pro' ) }>
									<TrashIcon />
								</button>
							</div>
						);
					} ) }
				</div>
			) }

			{ ! loading && entries.length === 0 && (
				<p className="captlc-ai-kb__empty">{ __( 'No sources added yet.', 'captain-live-chat-pro' ) }</p>
			) }

			{ viewingEntry && (
				<KnowledgeTextModal entry={ viewingEntry } onClose={ () => setViewingEntry( null ) } />
			) }
		</div>
	);
};

// ── Main AI Settings page ─────────────────────────────────────────────────
const AiSettings = () => {
	const [ providerData, setProviderData ]     = useState( {} );
	const [ autoReply, setAutoReply ]           = useState( false );
	const [ activeProvider, setActiveProvider ] = useState( 'groq' );
	const [ systemPrompt, setSystemPrompt ]     = useState( '' );
	// Matches CAPTLC_AI::DEFAULT_DAILY_LIMIT: a public widget calling a paid
	// API should never default to unlimited.
	const [ dailyLimit, setDailyLimit ]         = useState( 200 );
	// Last saved general settings. "Set as active" re-sends these, not
	// unsaved edits that are still in the form.
	const savedGeneral                          = useRef( { autoReply: false, systemPrompt: '', dailyLimit: 200 } );
	const [ usageToday, setUsageToday ]         = useState( 0 );
	const [ lastError, setLastError ]           = useState( null );
	const [ loading, setLoading ]               = useState( true );
	const [ notice, setNotice ]                 = useState( null );
	const [ savingGeneral, setSavingGeneral ]   = useState( false );
	const [ expandedId, setExpandedId ]         = useState( null );

	useEffect( () => {
		ajax( 'captlc_get_ai_settings' ).then( ( res ) => {
			if ( res?.success ) {
				const limit = typeof res.data.daily_limit === 'number' ? res.data.daily_limit : 200;

				setProviderData( res.data.providers || {} );
				setAutoReply( !! res.data.auto_reply_enabled );
				setActiveProvider( res.data.active_provider || 'groq' );
				setSystemPrompt( res.data.system_prompt || '' );
				setDailyLimit( limit );
				savedGeneral.current = {
					autoReply: !! res.data.auto_reply_enabled,
					systemPrompt: res.data.system_prompt || '',
					dailyLimit: limit,
				};
				setUsageToday( res.data.usage_today || 0 );
				setLastError( res.data.last_error || null );
				setExpandedId( res.data.active_provider || 'groq' );
			} else {
				setNotice( { msg: res?.data?.message || __( 'Could not load AI settings.', 'captain-live-chat-pro' ), type: 'error' } );
			}
		} ).catch( () => setNotice( { msg: __( 'Network error.', 'captain-live-chat-pro' ), type: 'error' } ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const showNotice = ( msg, type = 'success' ) => {
		setNotice( { msg, type } );
		setTimeout( () => setNotice( null ), 3500 );
	};

	const handleSaveProvider = ( providerId, key, model ) => {
		return ajax( 'captlc_save_ai_provider', { provider: providerId, api_key: key, model } )
			.then( ( res ) => {
				if ( res?.success ) {
					// Trust the backend's view of connected/key_preview rather than
					// guessing from the (possibly blank) key we just sent - a blank
					// key with an existing saved key means "still connected", not
					// "disconnected".
					setProviderData( ( prev ) => ( {
						...prev,
						[ providerId ]: {
							model,
							connected:      !! res.data?.connected,
							key_preview:    res.data?.key_preview || '',
							key_unreadable: false,
						},
					} ) );
				}
				return res;
			} );
	};

	const handleRemoveProvider = ( providerId ) => {
		return ajax( 'captlc_save_ai_provider', { provider: providerId, remove: '1' } )
			.then( ( res ) => {
				if ( res?.success ) {
					setProviderData( ( prev ) => ( {
						...prev,
						[ providerId ]: { model: '', connected: false, key_preview: '', key_unreadable: false },
					} ) );
				}
				return res;
			} );
	};

	const handleTestProvider = ( providerId, key, model ) => {
		return ajax( 'captlc_test_ai_provider', { provider: providerId, api_key: key, model } );
	};

	const handleSetActive = ( providerId ) => {
		const previous = activeProvider;
		setActiveProvider( providerId );
		ajax( 'captlc_save_ai_general', {
			auto_reply_enabled: savedGeneral.current.autoReply ? '1' : '0',
			active_provider:    providerId,
			system_prompt:      savedGeneral.current.systemPrompt,
			daily_limit:        savedGeneral.current.dailyLimit,
		} ).then( ( res ) => {
			if ( ! res?.success ) {
				setActiveProvider( previous );
				showNotice( res?.data?.message || __( 'Could not change the active provider.', 'captain-live-chat-pro' ), 'error' );
			} else if ( providerData[ providerId ]?.connected ) {
				showNotice( __( 'Active provider updated.', 'captain-live-chat-pro' ) );
			} else {
				showNotice( __( 'Active provider updated, but it has no working key yet. Add a key or visitors will only get the offline message.', 'captain-live-chat-pro' ), 'error' );
			}
		} ).catch( () => {
			setActiveProvider( previous );
			showNotice( __( 'Network error.', 'captain-live-chat-pro' ), 'error' );
		} );
	};

	const handleSaveGeneral = () => {
		setSavingGeneral( true );
		ajax( 'captlc_save_ai_general', {
			auto_reply_enabled: autoReply ? '1' : '0',
			active_provider:    activeProvider,
			system_prompt:      systemPrompt,
			daily_limit:        dailyLimit,
		} ).then( ( res ) => {
			if ( res?.success ) {
				savedGeneral.current = { autoReply, systemPrompt, dailyLimit };
				showNotice( __( 'Settings saved.', 'captain-live-chat-pro' ) );
			} else showNotice( res?.data?.message || __( 'Save failed.', 'captain-live-chat-pro' ), 'error' );
		} ).catch( () => showNotice( __( 'Network error.', 'captain-live-chat-pro' ), 'error' ) )
		.finally( () => setSavingGeneral( false ) );
	};

	if ( loading ) return <div className="captlc-ai-loading">{ __( 'Loading…', 'captain-live-chat-pro' ) }</div>;

	return (
		<div className="captlc-ai-settings">
			<div className="captlc-main__header captlc-ai-settings__header">
				<div>
					<h1 className="captlc-main__title">{ __( 'AI Auto-Reply', 'captain-live-chat-pro' ) }</h1>
					<p className="captlc-main__subtitle">{ __( 'Answer visitors automatically when every agent is offline.', 'captain-live-chat-pro' ) }</p>
				</div>
			</div>

			{ notice && (
				<div className={ `captlc-notice captlc-notice--${ notice.type }` }>{ notice.msg }</div>
			) }

			{ lastError && (
				<div className="captlc-notice captlc-notice--error" role="alert">
					{ sprintf(
						/* translators: %s: the AI provider's error message */
						__( 'AI auto-reply failed recently: %s - visitors got the offline-message fallback instead. Check your API key and provider status.', 'captain-live-chat-pro' ),
						lastError.message
					) }
					<button
						type="button"
						className="captlc-notice__close"
						onClick={ () => setLastError( null ) }
						aria-label={ __( 'Dismiss', 'captain-live-chat-pro' ) }
					>✕</button>
				</div>
			) }

			{ /* ── General settings - compact ── */ }
			<div className="captlc-card captlc-ai-general">
				<div className="captlc-ai-general__head">
					<span className="captlc-ai-general__head-label">{ __( 'Enable AI auto-reply when all agents are offline', 'captain-live-chat-pro' ) }</span>
					<SegmentedToggle checked={ autoReply } onChange={ ( val ) => setAutoReply( val ) } />
				</div>

				<div className={ `captlc-ai-general__body${ ! autoReply ? ' is-disabled' : '' }` }>
					{ ! autoReply && (
						<div className="captlc-ai-general__overlay">
							<span className="captlc-ai-general__overlay-badge">
								<LockIcon />
								{ __( 'AI Auto-Reply is currently inactive', 'captain-live-chat-pro' ) }
							</span>
						</div>
					) }

					<div className="captlc-ai-general__fields">
						<div className="captlc-field captlc-ai-general__prompt-field">
							<LabelWithInfo htmlFor="captlc-ai-system-prompt" tip={ __( 'Optional - add extra instructions specific to your business (tone, topics to focus on, what to avoid). A baseline persona is already applied automatically, so the assistant never claims to be ChatGPT/another AI provider and won\'t reply with everything as a giant table.', 'captain-live-chat-pro' ) }>
								{ __( 'System Prompt', 'captain-live-chat-pro' ) }
							</LabelWithInfo>
							<textarea
								id="captlc-ai-system-prompt"
								className="captlc-textarea"
								rows="3"
								placeholder={ __( 'e.g. Focus on pricing and shipping questions. Recommend booking a call for anything about custom orders.', 'captain-live-chat-pro' ) }
								value={ systemPrompt }
								maxLength={ 4000 }
								onChange={ ( e ) => setSystemPrompt( e.target.value ) }
							/>
							<p className="captlc-field__hint">
								{ sprintf(
									/* translators: %d: characters used of the 4000 limit */
									__( '%d / 4000 characters. This text is sent with every visitor message, so keep it focused.', 'captain-live-chat-pro' ),
									systemPrompt.length
								) }
							</p>
						</div>

						<div className="captlc-field captlc-ai-general__limit-field">
							<div className="captlc-ai-general__limit-row">
								<LabelWithInfo htmlFor="captlc-ai-daily-limit" tip={ __( 'Set a number to cap how many AI replies go out per day (protects against a traffic spike or bots running up your API bill). Once reached, visitors get the offline-message fallback until it resets at midnight. 0 means unlimited, which is not recommended on a public website.', 'captain-live-chat-pro' ) }>
									{ __( 'Daily reply limit', 'captain-live-chat-pro' ) }
								</LabelWithInfo>
								<input
									id="captlc-ai-daily-limit"
									type="number"
									min="0"
									className="captlc-input-field captlc-ai-general__limit-input"
									value={ dailyLimit }
									onChange={ ( e ) => setDailyLimit( Math.max( 0, parseInt( e.target.value, 10 ) || 0 ) ) }
								/>
							</div>
							{ 0 === Number( dailyLimit ) && (
								<p className="captlc-field__hint captlc-ai-general__limit-hint" role="alert">
									{ __( 'Unlimited: every visitor message can cost you API money. A limit such as 200 per day is safer.', 'captain-live-chat-pro' ) }
								</p>
							) }
							{ Number( dailyLimit ) > 0 && (
								<p className="captlc-field__hint captlc-ai-general__limit-hint">
									{ sprintf(
										/* translators: 1: replies sent today, 2: configured daily limit */
										__( 'Sent %1$d of %2$d today.', 'captain-live-chat-pro' ),
										usageToday,
										dailyLimit
									) }
								</p>
							) }
						</div>

						<div className="captlc-ai-general__kb-wrap">
							<KnowledgeBaseSection />
						</div>
					</div>

					<div className="captlc-ai-general__divider" />

					<div className="captlc-form-actions">
						<button
							type="button"
							className="captlc-primary-button captlc-ai-row__small-btn"
							onClick={ handleSaveGeneral }
							disabled={ savingGeneral }
						>{ savingGeneral ? __( 'Saving…', 'captain-live-chat-pro' ) : __( 'Save Settings', 'captain-live-chat-pro' ) }</button>
					</div>
				</div>
			</div>

			{ /* ── Providers - compact accordion list ── */ }
			<div className="captlc-card captlc-ai-providers">
				<div className="captlc-ai-providers__head">
					<h2 className="captlc-card__title">{ __( 'Providers', 'captain-live-chat-pro' ) }</h2>
					<p className="captlc-card__desc">{ __( 'Click a provider to add its key. Groq and Gemini are free, no card needed.', 'captain-live-chat-pro' ) }</p>
				</div>

				<div className="captlc-ai-providers__list">
					{ PROVIDERS.map( ( provider ) => {
						const saved = providerData[ provider.id ] || {};
						return (
							<ProviderRow
								key={ provider.id }
								provider={ provider }
								savedKey={ saved.key || '' }
								savedKeyPreview={ saved.key_preview || '' }
								savedModel={ saved.model || provider.models[ 0 ] }
								isConnected={ !! saved.connected }
								isUnreadable={ !! saved.key_unreadable }
								isActive={ activeProvider === provider.id }
								expanded={ expandedId === provider.id }
								onToggle={ () => setExpandedId( ( id ) => id === provider.id ? null : provider.id ) }
								onSetActive={ handleSetActive }
								onSave={ handleSaveProvider }
								onTest={ handleTestProvider }
								onRemove={ handleRemoveProvider }
							/>
						);
					} ) }
				</div>
			</div>
		</div>
	);
};

export default AiSettings;
