/**
 * Proactive Greeting Vanilla JS (Pro)
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var bubble = document.getElementById( 'captlc-proactive-greeting' );
		var container = document.getElementById( 'captlc-chat-buttons' ) || document.getElementById( 'captlc-widget' );
		if ( ! bubble || ! container ) return;

		var config = window.captlcGreetingData || {
			triggerType: 'delay',
			delaySeconds: 5,
			scrollPercentage: 50,
			action: 'bubble_only',
			autoHideSeconds: 0,
			frequency: 'session',
			frequencyDays: 7
		};

		// 1. Frequency checks.
		var isDismissed = false;
		try {
			if ( config.frequency === 'page_load' ) {
				isDismissed = false;
			} else if ( config.frequency === 'session' ) {
				isDismissed = !! sessionStorage.getItem( 'captlc_greeting_dismissed' );
			} else if ( config.frequency === 'days' ) {
				var dismissedTime = localStorage.getItem( 'captlc_greeting_dismissed' );
				if ( dismissedTime ) {
					var diff = Date.now() - parseInt( dismissedTime, 10 );
					var windowMs = ( config.frequencyDays || 7 ) * 86400000;
					if ( diff < windowMs ) isDismissed = true;
				}
			}
		} catch ( e ) {}

		if ( isDismissed ) return;

		var hasShown = false;
		var autoHideTimer = null;

		function showBubble() {
			if ( hasShown ) return;
			hasShown = true;

			bubble.style.display = 'block';

			// Optional Auto-open
			if ( config.action === 'bubble_and_open' ) {
				if ( typeof window.captlcOpenChat === 'function' ) {
					window.captlcOpenChat();
				} else {
					container.classList.add( 'captlc-cb-stack--open' );
				}
			}

			// Auto-hide
			if ( config.autoHideSeconds > 0 ) {
				autoHideTimer = setTimeout( function () {
					dismissBubble( false );
				}, config.autoHideSeconds * 1000 );
			}
		}

		function dismissBubble( recordDismissal ) {
			bubble.style.display = 'none';
			if ( autoHideTimer ) clearTimeout( autoHideTimer );

			if ( recordDismissal !== false && config.frequency !== 'page_load' ) {
				try {
					if ( config.frequency === 'session' ) {
						sessionStorage.setItem( 'captlc_greeting_dismissed', '1' );
					} else {
						localStorage.setItem( 'captlc_greeting_dismissed', String( Date.now() ) );
					}
				} catch ( e ) {}
			}
		}

		// Close button
		var closeBtn = bubble.querySelector( '.captlc-proactive-bubble__close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				dismissBubble( true );
			} );
		}

		// Escape key closes
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && bubble.style.display === 'block' ) {
				dismissBubble( true );
			}
		} );

		// Dismiss when chat opens or another channel button is clicked
		window.addEventListener( 'captlc:widget-open', function () {
			dismissBubble( false );
		} );
		window.addEventListener( 'captlc_chat_button_clicked', function () {
			dismissBubble( false );
		} );

		// Click bubble to open live chat or buttons
		bubble.addEventListener( 'click', function () {
			if ( typeof window.captlcOpenChat === 'function' ) {
				window.captlcOpenChat();
			} else {
				var firstBtn = container.querySelector( '.captlc-cb-btn' );
				if ( firstBtn ) firstBtn.focus();
				container.classList.add( 'captlc-cb-stack--open' );
			}
		} );

		// Triggers
		if ( config.triggerType === 'delay' ) {
			setTimeout( showBubble, ( config.delaySeconds || 5 ) * 1000 );
		} else if ( config.triggerType === 'scroll' ) {
			var onScroll = function () {
				var scrollY = window.pageYOffset || document.documentElement.scrollTop;
				var docH = document.documentElement.scrollHeight - document.documentElement.clientHeight;
				if ( docH <= 0 ) return;
				var pct = ( scrollY / docH ) * 100;
				if ( pct >= ( config.scrollPercentage || 50 ) ) {
					showBubble();
					window.removeEventListener( 'scroll', onScroll );
				}
			};
			window.addEventListener( 'scroll', onScroll, { passive: true } );
		} else if ( config.triggerType === 'exit_intent' ) {
			if ( 'ontouchstart' in window || navigator.maxTouchPoints > 0 ) return;
			var onMouseLeave = function ( e ) {
				if ( e.clientY <= 0 ) {
					showBubble();
					document.removeEventListener( 'mouseleave', onMouseLeave );
				}
			};
			document.addEventListener( 'mouseleave', onMouseLeave );
		}
	} );
} )();
