/**
 * Captain Live Chat Pro - Live Visitor Tracker
 * Lightweight vanilla JS tracker (no jQuery).
 */
(function () {
	'use strict';

	if ( typeof window === 'undefined' || typeof document === 'undefined' ) {
		return;
	}

	var data = window.captlcLiveTrackerData;
	if ( ! data || ! data.ajaxUrl ) {
		return;
	}

	function generateUuid() {
		var cryptoObj = window.crypto || window.msCrypto;
		var bytes = null;
		if ( cryptoObj && cryptoObj.getRandomValues ) {
			bytes = new Uint8Array( 16 );
			cryptoObj.getRandomValues( bytes );
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function ( c, i ) {
			var r = bytes ? bytes[ i % 16 ] & 15 : ( Math.random() * 16 ) | 0;
			var v = 'x' === c ? r : ( r & 0x3 ) | 0x8;
			return v.toString( 16 );
		} );
	}

	function isValidUuid( str ) {
		return typeof str === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test( str );
	}

	// Retrieve or generate visitor UUID.
	var STORAGE_KEY = 'captlc_visitor_id';
	function getVisitorId() {
		try {
			var vid = localStorage.getItem( STORAGE_KEY );
			if ( ! vid || ! isValidUuid( vid ) ) {
				var match = document.cookie.match( new RegExp( '(?:^|; )' + STORAGE_KEY + '=([^;]*)' ) );
				if ( match && match[1] ) {
					var candidate = decodeURIComponent( match[1] );
					if ( isValidUuid( candidate ) ) {
						vid = candidate;
					}
				}
			}
			if ( ! vid || ! isValidUuid( vid ) ) {
				vid = generateUuid();
				localStorage.setItem( STORAGE_KEY, vid );
			}
			return vid;
		} catch ( e ) {
			return generateUuid();
		}
	}

	var visitorId = getVisitorId();
	var pingInterval = data.pingInterval || 30000;
	var lastPingTime = 0;
	var pingTimer = null;

	function detectDevice() {
		var w = window.innerWidth || ( screen && screen.width ) || 1200;
		if ( w < 640 ) return 'mobile';
		if ( w < 1024 ) return 'tablet';
		return 'desktop';
	}

	function sendPing() {
		if ( document.hidden ) {
			return;
		}

		var now = Date.now();
		lastPingTime = now;

		var params = new URLSearchParams();
		params.append( 'action', 'captlc_live_visitor_ping' );
		params.append( 'nonce', data.nonce );
		params.append( 'visitor_id', visitorId );
		params.append( 'url', window.location.href );
		params.append( 'title', document.title || '' );
		params.append( 'referrer', document.referrer || '' );
		params.append( 'device', detectDevice() );

		fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: params.toString(),
		} )
		.then( function ( res ) { return res.json(); } )
		.then( function ( res ) {
			if ( res && res.success && res.data && res.data.invite ) {
				handleAgentInvite( res.data.invite );
			}
		} )
		.catch( function () {
			// Fail silently on network errors.
		} );
	}

	function handleAgentInvite( invite ) {
		// Store invite so widget picks it up immediately.
		try {
			sessionStorage.setItem( 'captlc_pending_invite', JSON.stringify( invite ) );
		} catch ( e ) {}

		// Dispatch custom DOM event.
		var event;
		if ( typeof CustomEvent === 'function' ) {
			event = new CustomEvent( 'captlc:agent_invite', { detail: invite } );
		} else {
			event = document.createEvent( 'CustomEvent' );
			event.initCustomEvent( 'captlc:agent_invite', true, true, invite );
		}
		window.dispatchEvent( event );
	}

	function onVisibilityChange() {
		if ( ! document.hidden ) {
			// If visible and more than ping interval has elapsed, ping now.
			if ( Date.now() - lastPingTime >= pingInterval ) {
				sendPing();
			}
			startPingTimer();
		} else {
			stopPingTimer();
		}
	}

	function startPingTimer() {
		stopPingTimer();
		pingTimer = setInterval( function () {
			if ( ! document.hidden ) {
				sendPing();
			}
		}, pingInterval );
	}

	function stopPingTimer() {
		if ( pingTimer ) {
			clearInterval( pingTimer );
			pingTimer = null;
		}
	}

	function sendLeaveBeacon() {
		if ( ! navigator.sendBeacon ) {
			return;
		}
		var fd = new FormData();
		fd.append( 'action', 'captlc_live_visitor_leave' );
		fd.append( 'nonce', data.nonce );
		fd.append( 'visitor_id', visitorId );
		navigator.sendBeacon( data.ajaxUrl, fd );
	}

	// Initialize tracking.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			sendPing();
			startPingTimer();
		} );
	} else {
		sendPing();
		startPingTimer();
	}

	document.addEventListener( 'visibilitychange', onVisibilityChange );
	window.addEventListener( 'beforeunload', sendLeaveBeacon );
	window.addEventListener( 'unload', sendLeaveBeacon );
})();
