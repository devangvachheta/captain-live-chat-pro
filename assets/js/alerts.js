/**
 * Captain Live Chat Pro - pop-up alerts for new visitor messages.
 *
 * Listens to the `captlc:unread` event published by the free plugin (menu
 * badge script on every admin screen, dashboard app on its own screen) and
 * shows a toast for each conversation that received a new message.
 */
( function () {
	'use strict';

	var cfg = window.captlcProAlerts;

	if ( ! cfg ) {
		return;
	}

	var STORE_KEY = 'captlc_alert_last_id';
	var MAX_VISIBLE = 3;
	var SHOW_MS = 8000;

	// Highest message id that was already announced (or existed when the
	// agent first opened an admin page). Kept in localStorage so a page
	// reload or a second open tab does not repeat the same pop-up.
	var last = null;
	var container = null;

	function readStored() {
		try {
			var raw = window.localStorage.getItem( STORE_KEY );
			return null === raw ? null : parseInt( raw, 10 ) || 0;
		} catch ( e ) {
			return null;
		}
	}

	function writeStored( id ) {
		try {
			window.localStorage.setItem( STORE_KEY, String( id ) );
		} catch ( e ) {
			// Storage blocked: alerts still work, just without cross-tab memory.
		}
	}

	function format( template, value ) {
		return String( template ).replace( /%[sd]/, String( value ) );
	}

	function getContainer() {
		if ( container && document.body.contains( container ) ) {
			return container;
		}

		container = document.createElement( 'div' );
		container.className = 'captlc-alerts';
		container.setAttribute( 'role', 'region' );
		container.setAttribute( 'aria-label', cfg.i18n.region );
		document.body.appendChild( container );

		return container;
	}

	function beep() {
		try {
			var Ctx = window.AudioContext || window.webkitAudioContext;

			if ( ! Ctx ) {
				return;
			}

			var ctx = new Ctx();
			var osc = ctx.createOscillator();
			var gain = ctx.createGain();

			osc.type = 'sine';
			osc.frequency.value = 880;
			gain.gain.setValueAtTime( 0.0001, ctx.currentTime );
			gain.gain.exponentialRampToValueAtTime( 0.15, ctx.currentTime + 0.02 );
			gain.gain.exponentialRampToValueAtTime( 0.0001, ctx.currentTime + 0.35 );
			osc.connect( gain );
			gain.connect( ctx.destination );
			osc.start();
			osc.stop( ctx.currentTime + 0.4 );
			setTimeout( function () {
				ctx.close();
			}, 600 );
		} catch ( e ) {
			// Browsers block sound until the page has had a click; ignore.
		}
	}

	function dismiss( toast ) {
		if ( toast && toast.parentNode ) {
			toast.parentNode.removeChild( toast );
		}
	}

	function show( title, text, href ) {
		var box = getContainer();

		// Keep the stack short: drop the oldest when too many.
		while ( box.children.length >= MAX_VISIBLE ) {
			dismiss( box.firstChild );
		}

		var toast = document.createElement( 'div' );
		toast.className = 'captlc-alert';
		toast.setAttribute( 'role', 'status' );

		var body = document.createElement( 'div' );
		body.className = 'captlc-alert__body';

		var heading = document.createElement( 'p' );
		heading.className = 'captlc-alert__title';
		heading.textContent = title;
		body.appendChild( heading );

		if ( text ) {
			var para = document.createElement( 'p' );
			para.className = 'captlc-alert__text';
			para.textContent = text;
			body.appendChild( para );
		}

		var link = document.createElement( 'a' );
		link.className = 'captlc-alert__open';
		link.href = href;
		link.textContent = cfg.i18n.open;
		link.addEventListener( 'click', function () {
			dismiss( toast );
		} );
		body.appendChild( link );

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'captlc-alert__close';
		close.setAttribute( 'aria-label', cfg.i18n.dismiss );
		close.textContent = '\u00d7';
		close.addEventListener( 'click', function () {
			dismiss( toast );
		} );

		toast.appendChild( body );
		toast.appendChild( close );
		box.appendChild( toast );

		var timer = setTimeout( function () {
			dismiss( toast );
		}, SHOW_MS );

		// Do not vanish while the agent is reading or about to click.
		toast.addEventListener( 'mouseenter', function () {
			clearTimeout( timer );
		} );
		toast.addEventListener( 'mouseleave', function () {
			timer = setTimeout( function () {
				dismiss( toast );
			}, 3000 );
		} );
	}

	function chatUrl( threadId ) {
		return cfg.inboxUrl + '#/inbox?thread=' + encodeURIComponent( threadId );
	}

	window.addEventListener( 'captlc:unread', function ( event ) {
		var data = ( event && event.detail ) || {};
		var latest = Array.isArray( data.latest ) ? data.latest : [];
		var maxId = 'number' === typeof data.max_id ? data.max_id : 0;

		// Another tab may already have announced newer messages.
		var stored = readStored();

		if ( null !== stored && ( null === last || stored > last ) ) {
			last = stored;
		}

		// First update ever: remember what is already waiting, do not pop up
		// for old messages the agent can see in the badge.
		if ( null === last ) {
			last = maxId;
			writeStored( last );
			return;
		}

		var activeThread = parseInt( window.captlcActiveThreadId, 10 ) || 0;

		var fresh = latest
			.filter( function ( m ) {
				return m && m.id > last && m.thread_id !== activeThread;
			} )
			.sort( function ( a, b ) {
				return a.id - b.id;
			} );

		if ( maxId > last ) {
			last = maxId;
			writeStored( last );
		}

		if ( 0 === fresh.length ) {
			return;
		}

		fresh.slice( 0, MAX_VISIBLE ).forEach( function ( m ) {
			show( format( cfg.i18n.from, m.name ), m.preview, chatUrl( m.thread_id ) );
		} );

		if ( fresh.length > MAX_VISIBLE ) {
			show( format( cfg.i18n.more, fresh.length - MAX_VISIBLE ), '', cfg.inboxUrl );
		}

		if ( cfg.sound ) {
			beep();
		}
	} );
}() );
