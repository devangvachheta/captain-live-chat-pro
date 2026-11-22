import React, { useState, useEffect, useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import ajax from '../../utils/ajax.js';
import './live_visitors.scss';

const LockIcon = () => (
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" width="15" height="15">
		<rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
		<path d="M7 11V7a5 5 0 0 1 10 0v4" />
	</svg>
);

const SegmentedToggle = ( { checked, onChange, disabled } ) => (
	<div className="captlc-segmented-toggle" role="group">
		<button
			type="button"
			disabled={ disabled }
			className={ `captlc-segmented-toggle__btn${ checked ? ' is-active' : '' }` }
			onClick={ () => onChange( true ) }
		>
			{ __( 'Enabled', 'captain-live-chat-pro' ) }
		</button>
		<button
			type="button"
			disabled={ disabled }
			className={ `captlc-segmented-toggle__btn${ ! checked ? ' is-active' : '' }` }
			onClick={ () => onChange( false ) }
		>
			{ __( 'Disabled', 'captain-live-chat-pro' ) }
		</button>
	</div>
);

const DEMO_VISITORS = [
	{ id: 'd1', visitor_id: 'demo-1', visitor_name: 'Visitor #8491', current_url: '/checkout', page_title: 'Checkout - Store', device: 'desktop', browser: 'Chrome', os: 'Windows 11', time_on_site: 300, page_count: 5, is_chatting: false },
	{ id: 'd2', visitor_id: 'demo-2', visitor_name: 'Sarah M.', current_url: '/pricing', page_title: 'Plans & Pricing', device: 'mobile', browser: 'Safari', os: 'iOS', time_on_site: 65, page_count: 2, is_chatting: true },
	{ id: 'd3', visitor_id: 'demo-3', visitor_name: 'Visitor #2014', current_url: '/product/leather-jacket', page_title: 'Vintage Leather Jacket', device: 'desktop', browser: 'Edge', os: 'macOS', time_on_site: 540, page_count: 7, is_chatting: false },
	{ id: 'd4', visitor_id: 'demo-4', visitor_name: 'Visitor #9931', current_url: '/contact', page_title: 'Contact Us', device: 'tablet', browser: 'Firefox', os: 'Android', time_on_site: 45, page_count: 1, is_chatting: false },
];

const formatDuration = ( secs ) => {
	if ( ! secs || secs <= 0 ) return '< 1m';
	if ( secs < 60 ) return `${ secs }s`;
	if ( secs < 3600 ) return `${ Math.floor( secs / 60 ) }m`;
	const hrs = Math.floor( secs / 3600 );
	const mins = Math.floor( ( secs % 3600 ) / 60 );
	return `${ hrs }h ${ mins }m`;
};

const LiveVisitors = () => {
	const [ isEnabled, setIsEnabled ] = useState( () => {
		return !! ( window?.captlc_data?.settings?.live_visitors_enabled );
	} );
	const [ toggling, setToggling ] = useState( false );
	const [ visitors, setVisitors ] = useState( [] );
	const [ stats, setStats ] = useState( { online: 0, chatting: 0, browsing: 0 } );
	const [ loading, setLoading ] = useState( true );
	const [ search, setSearch ] = useState( '' );
	const [ filterDevice, setFilterDevice ] = useState( '' );
	const [ filterStatus, setFilterStatus ] = useState( '' );
	const [ selectedVisitor, setSelectedVisitor ] = useState( null );
	const [ startChatModalOpen, setStartChatModalOpen ] = useState( false );
	const [ targetVisitor, setTargetVisitor ] = useState( null );
	const [ initialMessage, setInitialMessage ] = useState( __( 'Hello! How can I help you today?', 'captain-live-chat-pro' ) );
	const [ initiating, setInitiating ] = useState( false );
	const [ toast, setToast ] = useState( null );

	const showToast = ( msg, type = 'error' ) => {
		setToast( { msg, type } );
		setTimeout( () => setToast( null ), 3500 );
	};

	const handleToggleLiveVisitors = ( targetState ) => {
		if ( toggling ) return;
		const nextState = typeof targetState === 'boolean' ? targetState : ! isEnabled;
		setToggling( true );
		ajax( 'captlc_toggle_live_visitors', {
			enabled: nextState ? 'true' : 'false',
		} )
			.then( ( res ) => {
				if ( res && res.success ) {
					setIsEnabled( nextState );
					if ( window?.captlc_data?.settings ) {
						window.captlc_data.settings.live_visitors_enabled = nextState;
					}
					showToast(
						res.data?.message || ( nextState ? __( 'Live visitor tracking enabled.', 'captain-live-chat-pro' ) : __( 'Live visitor tracking disabled.', 'captain-live-chat-pro' ) ),
						'success'
					);
					if ( nextState ) {
						fetchVisitors();
					}
				} else {
					showToast( res?.data?.message || __( 'Could not update tracking setting.', 'captain-live-chat-pro' ) );
				}
			} )
			.catch( () => showToast( __( 'Network error while updating setting.', 'captain-live-chat-pro' ) ) )
			.finally( () => setToggling( false ) );
	};

	const fetchVisitors = useCallback( () => {
		ajax( 'captlc_get_live_visitors', {
			search: search.trim(),
			device: filterDevice,
			status: filterStatus,
		} )
			.then( ( res ) => {
				if ( res && res.success && res.data ) {
					if ( typeof res.data.enabled !== 'undefined' ) {
						setIsEnabled( !! res.data.enabled );
					}
					setVisitors( res.data.visitors || [] );
					setStats( {
						online: res.data.total_online || 0,
						chatting: res.data.total_chatting || 0,
						browsing: res.data.total_browsing || 0,
					} );
				}
			} )
			.catch( () => {} )
			.finally( () => setLoading( false ) );
	}, [ search, filterDevice, filterStatus ] );

	// Poll visitors every 5s while page is visible AND feature is enabled
	useEffect( () => {
		fetchVisitors();

		let interval = null;
		const startPolling = () => {
			if ( interval ) clearInterval( interval );
			if ( ! isEnabled ) return;
			interval = setInterval( () => {
				if ( ! document.hidden ) {
					fetchVisitors();
				}
			}, 5000 );
		};

		startPolling();

		const onVisibility = () => {
			if ( ! document.hidden && isEnabled ) {
				fetchVisitors();
			}
		};

		document.addEventListener( 'visibilitychange', onVisibility );
		return () => {
			if ( interval ) clearInterval( interval );
			document.removeEventListener( 'visibilitychange', onVisibility );
		};
	}, [ fetchVisitors, isEnabled ] );

	const handleOpenStartChat = ( visitor, e ) => {
		if ( e ) e.stopPropagation();
		setTargetVisitor( visitor );
		setInitialMessage( __( 'Hello! How can I help you today?', 'captain-live-chat-pro' ) );
		setStartChatModalOpen( true );
	};

	const handleStartChatSubmit = () => {
		if ( ! targetVisitor || initiating ) return;
		if ( ! initialMessage.trim() ) {
			showToast( __( 'Please type an opening message.', 'captain-live-chat-pro' ) );
			return;
		}

		setInitiating( true );
		ajax( 'captlc_start_visitor_chat', {
			visitor_id: targetVisitor.visitor_id,
			message: initialMessage.trim(),
		} )
			.then( ( res ) => {
				if ( res && res.success && res.data?.thread_id ) {
					showToast( res.data.message || __( 'Chat initiated!', 'captain-live-chat-pro' ), 'success' );
					setStartChatModalOpen( false );
					window.location.hash = `#/inbox?thread=${ encodeURIComponent( res.data.thread_id ) }`;
				} else {
					showToast( res?.data?.message || __( 'Could not initiate chat.', 'captain-live-chat-pro' ) );
				}
			} )
			.catch( () => showToast( __( 'Network error while initiating chat.', 'captain-live-chat-pro' ) ) )
			.finally( () => setInitiating( false ) );
	};

	const navigateToHistory = ( query ) => {
		window.location.hash = `#/history?search=${ encodeURIComponent( query ) }`;
	};

	const navigateToThread = ( threadId ) => {
		window.location.hash = `#/inbox?thread=${ encodeURIComponent( threadId ) }`;
	};

	const displayedVisitors = ! isEnabled ? DEMO_VISITORS : visitors;
	const displayedStats = ! isEnabled ? { online: 4, browsing: 3, chatting: 1 } : stats;

	return (
		<div className="captlc-live-visitors-page">
			{ toast && (
				<div className={ `captlc-toast captlc-toast--${ toast.type }` }>
					{ toast.msg }
				</div>
			) }

			{ /* Header */ }
			<header className="captlc-live-header">
				<div className="captlc-live-header__left">
					<h1 className="captlc-live-header__title">
						{ __( 'Live Visitors', 'captain-live-chat-pro' ) }
					</h1>
					<p className="captlc-live-header__subtitle">
						{ __( 'See who is currently browsing your site in real-time, view their journey, and initiate proactive chats.', 'captain-live-chat-pro' ) }
					</p>
				</div>
				<div className="captlc-live-header__actions">
					<SegmentedToggle
						checked={ isEnabled }
						onChange={ handleToggleLiveVisitors }
						disabled={ toggling }
					/>

					<button
						type="button"
						className="captlc-btn-refresh"
						onClick={ fetchVisitors }
						disabled={ ! isEnabled }
						title={ __( 'Refresh visitors', 'captain-live-chat-pro' ) }
					>
						<svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" width="16" height="16">
							<path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
						</svg>
						<span>{ __( 'Refresh', 'captain-live-chat-pro' ) }</span>
					</button>
				</div>
			</header>

			{ /* Main Body - blurred when disabled + centered overlay badge */ }
			<div className={ `captlc-live-body${ ! isEnabled ? ' is-disabled' : '' }` }>
				{ ! isEnabled && (
					<div className="captlc-live-overlay">
						<span
							className="captlc-live-overlay-badge"
							role="button"
							tabIndex="0"
							onClick={ () => handleToggleLiveVisitors( true ) }
							onKeyDown={ ( e ) => {
								if ( e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar' ) {
									e.preventDefault();
									handleToggleLiveVisitors( true );
								}
							} }
							title={ __( 'Click to enable Live Visitor Tracking', 'captain-live-chat-pro' ) }
						>
							<LockIcon />
							{ __( 'Live Visitor Tracking is currently inactive', 'captain-live-chat-pro' ) }
						</span>
					</div>
				) }

				<div className="captlc-live-content-wrap">

			{ /* Metric Counters */ }
			<div className="captlc-live-stats-grid">
					<div className="captlc-live-stat-card">
						<div className="captlc-live-stat-card__indicator">
							<span className="captlc-live-pulse-dot" />
							<span className="captlc-live-stat-card__label">{ __( 'Online Right Now', 'captain-live-chat-pro' ) }</span>
						</div>
						<div className="captlc-live-stat-card__value">{ displayedStats.online }</div>
						<div className="captlc-live-stat-card__footer">{ __( 'Pinging within 60s', 'captain-live-chat-pro' ) }</div>
					</div>

					<div className="captlc-live-stat-card">
						<div className="captlc-live-stat-card__indicator">
							<span className="captlc-live-stat-card__label">{ __( 'Browsing Pages', 'captain-live-chat-pro' ) }</span>
						</div>
						<div className="captlc-live-stat-card__value captlc-live-stat-card__value--blue">
							{ displayedStats.browsing }
						</div>
						<div className="captlc-live-stat-card__footer">{ __( 'Potential leads', 'captain-live-chat-pro' ) }</div>
					</div>

					<div className="captlc-live-stat-card">
						<div className="captlc-live-stat-card__indicator">
							<span className="captlc-live-stat-card__label">{ __( 'Active In Chat', 'captain-live-chat-pro' ) }</span>
						</div>
						<div className="captlc-live-stat-card__value captlc-live-stat-card__value--green">
							{ displayedStats.chatting }
						</div>
						<div className="captlc-live-stat-card__footer">{ __( 'Conversations open', 'captain-live-chat-pro' ) }</div>
					</div>
			</div>

			{ /* Filter and Search Toolbar */ }
			<div className="captlc-live-toolbar">
				<div className="captlc-live-search">
					<svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" width="16" height="16">
						<circle cx="11" cy="11" r="8" />
						<line x1="21" y1="21" x2="16.65" y2="16.65" />
					</svg>
					<input
						type="text"
						value={ search }
						onChange={ ( e ) => setSearch( e.target.value ) }
						placeholder={ __( 'Search URL, page title, or visitor...', 'captain-live-chat-pro' ) }
					/>
				</div>

				<div className="captlc-live-filters">
					<select
						value={ filterDevice }
						onChange={ ( e ) => setFilterDevice( e.target.value ) }
						className="captlc-live-select"
					>
						<option value="">{ __( 'All Devices', 'captain-live-chat-pro' ) }</option>
						<option value="desktop">{ __( 'Desktop', 'captain-live-chat-pro' ) }</option>
						<option value="mobile">{ __( 'Mobile', 'captain-live-chat-pro' ) }</option>
						<option value="tablet">{ __( 'Tablet', 'captain-live-chat-pro' ) }</option>
					</select>

					<select
						value={ filterStatus }
						onChange={ ( e ) => setFilterStatus( e.target.value ) }
						className="captlc-live-select"
					>
						<option value="">{ __( 'All Statuses', 'captain-live-chat-pro' ) }</option>
						<option value="browsing">{ __( 'Browsing Only', 'captain-live-chat-pro' ) }</option>
						<option value="chatting">{ __( 'In Active Chat', 'captain-live-chat-pro' ) }</option>
					</select>
				</div>
			</div>

			{ /* Table of Visitors */ }
			<div className="captlc-live-table-container">
				<table className="captlc-live-table">
					<thead>
						<tr>
							<th>{ __( 'Visitor', 'captain-live-chat-pro' ) }</th>
							<th>{ __( 'Current Page', 'captain-live-chat-pro' ) }</th>
							<th>{ __( 'Device / OS', 'captain-live-chat-pro' ) }</th>
							<th>{ __( 'Time on Site', 'captain-live-chat-pro' ) }</th>
							<th>{ __( 'Pages', 'captain-live-chat-pro' ) }</th>
							<th>{ __( 'Status', 'captain-live-chat-pro' ) }</th>
							<th style={ { textAlign: 'right' } }>{ __( 'Actions', 'captain-live-chat-pro' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ isEnabled && loading ? (
							<tr>
								<td colSpan="7" className="captlc-live-empty">
									{ __( 'Loading real-time visitors...', 'captain-live-chat-pro' ) }
								</td>
							</tr>
						) : displayedVisitors.length === 0 ? (
							<tr>
								<td colSpan="7" className="captlc-live-empty">
									<div className="captlc-live-empty-state">
										<p>{ __( 'No active visitors online right now.', 'captain-live-chat-pro' ) }</p>
										<span>{ __( 'Visitors actively browsing your site will appear here in real-time.', 'captain-live-chat-pro' ) }</span>
									</div>
								</td>
							</tr>
						) : (
							displayedVisitors.map( ( v ) => (
								<tr
									key={ v.id }
									onClick={ () => isEnabled && setSelectedVisitor( v ) }
									className={ selectedVisitor?.id === v.id ? 'is-selected' : '' }
								>
									<td>
										<div className="captlc-live-visitor-cell">
											<div className="captlc-live-avatar">
												{ v.visitor_name.charAt( 0 ).toUpperCase() }
											</div>
											<div>
												<div className="captlc-live-name">{ v.visitor_name }</div>
												<span className="captlc-live-meta">
													{ v.country ? `${ v.country } - ` : '' }{ v.visitor_email || v.visitor_id.substring( 0, 10 ) }
												</span>
											</div>
										</div>
									</td>
									<td>
										<div className="captlc-live-page-title" title={ v.page_title }>{ v.page_title }</div>
										<a
											href={ v.current_url }
											target="_blank"
											rel="noopener noreferrer"
											className="captlc-live-page-url"
											onClick={ ( e ) => e.stopPropagation() }
										>
											{ v.current_url.replace( /^https?:\/\/[^/]+/, '' ) || '/' }
										</a>
									</td>
									<td>
										<span className="captlc-live-device-badge">
											{ v.device === 'mobile' ? '📱' : v.device === 'tablet' ? '📟' : '💻' } { v.browser } / { v.os }
										</span>
									</td>
									<td>{ formatDuration( v.time_on_site ) }</td>
									<td>{ v.page_count }</td>
									<td>
										<span className={ `captlc-live-status-pill captlc-live-status-pill--${ v.is_chatting ? 'chatting' : 'browsing' }` }>
											{ v.is_chatting ? __( 'In Chat', 'captain-live-chat-pro' ) : __( 'Browsing', 'captain-live-chat-pro' ) }
										</span>
									</td>
									<td style={ { textAlign: 'right' } }>
										<button
											type="button"
											className="captlc-btn-history-table"
											onClick={ ( e ) => {
												e.stopPropagation();
												const query = v.visitor_email || v.visitor_name || v.visitor_id;
												navigateToHistory( query );
											} }
											title={ __( 'View visitor conversation history', 'captain-live-chat-pro' ) }
										>
											<svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
												<path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
											</svg>
											<span>{ __( 'History', 'captain-live-chat-pro' ) }</span>
										</button>
										{ v.is_chatting ? (
											<button
												type="button"
												className="captlc-btn-open-chat-table"
												onClick={ ( e ) => {
													e.stopPropagation();
													navigateToThread( v.thread_id );
												} }
											>
												{ __( 'Open Chat', 'captain-live-chat-pro' ) }
											</button>
										) : (
											<button
												type="button"
												className="captlc-btn-start-chat-table"
												onClick={ ( e ) => handleOpenStartChat( v, e ) }
											>
												{ __( 'Start Chat', 'captain-live-chat-pro' ) }
											</button>
										) }
									</td>
								</tr>
							) )
						) }
					</tbody>
				</table>
			</div>
				</div>
			</div>

			{ /* Drawer / Details Panel */ }
			{ selectedVisitor && (
				<div className="captlc-live-drawer-backdrop" onClick={ () => setSelectedVisitor( null ) }>
					<div className="captlc-live-drawer" onClick={ ( e ) => e.stopPropagation() }>
						<header className="captlc-live-drawer__header">
							<h3>{ __( 'Visitor Journey', 'captain-live-chat-pro' ) }</h3>
							<button type="button" className="captlc-live-drawer__close" onClick={ () => setSelectedVisitor( null ) }>✕</button>
						</header>

						<div className="captlc-live-drawer__body">
							<div className="captlc-live-drawer__hero">
								<div className="captlc-live-drawer__avatar">
									{ selectedVisitor.visitor_name.charAt( 0 ).toUpperCase() }
								</div>
								<h4>{ selectedVisitor.visitor_name }</h4>
								<span className="captlc-live-drawer__sub">{ selectedVisitor.visitor_email || selectedVisitor.visitor_id }</span>
							</div>

							<div className="captlc-live-drawer__field">
								<label>{ __( 'Current Page', 'captain-live-chat-pro' ) }</label>
								<div className="captlc-live-drawer__val">{ selectedVisitor.page_title }</div>
								<a href={ selectedVisitor.current_url } target="_blank" rel="noopener noreferrer" className="captlc-live-drawer__link">
									{ selectedVisitor.current_url }
								</a>
							</div>

							<div className="captlc-live-drawer__row">
								<div className="captlc-live-drawer__field">
									<label>{ __( 'Time on Site', 'captain-live-chat-pro' ) }</label>
									<div className="captlc-live-drawer__val">{ formatDuration( selectedVisitor.time_on_site ) }</div>
								</div>
								<div className="captlc-live-drawer__field">
									<label>{ __( 'Pages Visited', 'captain-live-chat-pro' ) }</label>
									<div className="captlc-live-drawer__val">{ selectedVisitor.page_count }</div>
								</div>
							</div>

							<div className="captlc-live-drawer__row">
								<div className="captlc-live-drawer__field">
									<label>{ __( 'Device', 'captain-live-chat-pro' ) }</label>
									<div className="captlc-live-drawer__val">{ selectedVisitor.device }</div>
								</div>
								<div className="captlc-live-drawer__field">
									<label>{ __( 'Browser & OS', 'captain-live-chat-pro' ) }</label>
									<div className="captlc-live-drawer__val">{ selectedVisitor.browser } on { selectedVisitor.os }</div>
								</div>
							</div>

							{ selectedVisitor.referrer && (
								<div className="captlc-live-drawer__field">
									<label>{ __( 'Referrer', 'captain-live-chat-pro' ) }</label>
									<div className="captlc-live-drawer__val">{ selectedVisitor.referrer }</div>
								</div>
							) }
						</div>

						<footer className="captlc-live-drawer__footer">
							<button
								type="button"
								className="captlc-live-drawer__btn-history"
								onClick={ () => {
									const query = selectedVisitor.visitor_email || selectedVisitor.visitor_name || selectedVisitor.visitor_id;
									navigateToHistory( query );
								} }
							>
								<svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
									<path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
								</svg>
								<span>{ __( 'View Past Conversation History', 'captain-live-chat-pro' ) }</span>
							</button>
							{ selectedVisitor.is_chatting ? (
								<button
									type="button"
									className="captlc-btn-primary captlc-btn--full"
									onClick={ () => navigateToThread( selectedVisitor.thread_id ) }
								>
									{ __( 'Open Active Conversation', 'captain-live-chat-pro' ) }
								</button>
							) : (
								<button
									type="button"
									className="captlc-btn-primary captlc-btn--full"
									onClick={ ( e ) => handleOpenStartChat( selectedVisitor, e ) }
								>
									{ __( 'Initiate Live Chat', 'captain-live-chat-pro' ) }
								</button>
							) }
						</footer>
					</div>
				</div>
			) }

			{ /* Start Chat Modal */ }
			{ startChatModalOpen && targetVisitor && (
				<div className="captlc-pro-modal-backdrop" onClick={ () => setStartChatModalOpen( false ) }>
					<div className="captlc-pro-modal captlc-start-chat-modal" role="dialog" aria-modal="true" onClick={ ( e ) => e.stopPropagation() }>
						<button type="button" className="captlc-pro-modal__close" onClick={ () => setStartChatModalOpen( false ) }>✕</button>
						<h3 className="captlc-pro-modal__title">{ __( 'Start Chat with Visitor', 'captain-live-chat-pro' ) }</h3>
						<p className="captlc-pro-modal__desc">
							{ __( 'Reach out directly to this visitor while they are browsing.', 'captain-live-chat-pro' ) }
						</p>

						<div className="captlc-start-chat-visitor-summary">
							<div><strong>{ targetVisitor.visitor_name }</strong> ({ targetVisitor.device } - { targetVisitor.browser })</div>
							<div className="captlc-start-chat-target-page">{ targetVisitor.page_title }</div>
						</div>

						<div className="captlc-quick-greeting-chips">
							<span className="captlc-quick-greeting-chips__label">{ __( 'QUICK TEMPLATES', 'captain-live-chat-pro' ) }</span>
							<div className="captlc-chips-row">
								<button
									type="button"
									onClick={ () => setInitialMessage( __( 'Hi! Do you have any questions about this page?', 'captain-live-chat-pro' ) ) }
								>
									{ __( 'General Help', 'captain-live-chat-pro' ) }
								</button>
								<button
									type="button"
									onClick={ () => setInitialMessage( __( 'Need help picking the right plan or option?', 'captain-live-chat-pro' ) ) }
								>
									{ __( 'Pricing / Plans', 'captain-live-chat-pro' ) }
								</button>
								<button
									type="button"
									onClick={ () => setInitialMessage( __( 'Hi! Let me know if you run into any issues during checkout.', 'captain-live-chat-pro' ) ) }
								>
									{ __( 'Checkout Assist', 'captain-live-chat-pro' ) }
								</button>
							</div>
						</div>

						<div className="captlc-start-chat-field">
							<label>{ __( 'Opening Message', 'captain-live-chat-pro' ) }</label>
							<textarea
								value={ initialMessage }
								onChange={ ( e ) => setInitialMessage( e.target.value ) }
								rows={ 3 }
								placeholder={ __( 'Type your opening message...', 'captain-live-chat-pro' ) }
							/>
						</div>

						<div className="captlc-start-chat-actions">
							<button
								type="button"
								className="captlc-btn-secondary"
								onClick={ () => setStartChatModalOpen( false ) }
							>
								{ __( 'Cancel', 'captain-live-chat-pro' ) }
							</button>
							<button
								type="button"
								className="captlc-btn-primary"
								onClick={ handleStartChatSubmit }
								disabled={ initiating }
							>
								{ initiating ? __( 'Starting...', 'captain-live-chat-pro' ) : __( 'Start Chat Now', 'captain-live-chat-pro' ) }
							</button>
						</div>
					</div>
				</div>
			) }
		</div>
	);
};

export default LiveVisitors;
