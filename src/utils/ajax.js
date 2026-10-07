import { __ } from '@wordpress/i18n';

/**
 * Reads an admin-ajax response. WordPress answers "-1" (HTTP 403) instead of
 * JSON when the login or the nonce has expired; that becomes a normal error
 * result with a readable message instead of a JSON parse failure.
 *
 * @param {Response} res Fetch response.
 * @return {Promise<Object>} Parsed result ({ success, data }).
 */
export const parseResponse = ( res ) =>
	res.text().then( ( text ) => {
		try {
			return JSON.parse( text );
		} catch ( err ) {
			return {
				success: false,
				data: {
					message: 403 === res.status || '-1' === text.trim()
						? __( 'Your session expired. Please reload the page and try again.', 'captain-live-chat-pro' )
						: __( 'Unexpected response from the server.', 'captain-live-chat-pro' ),
				},
			};
		}
	} );

/**
 * POSTs one admin-ajax action with the shared Captain Live Chat nonce.
 *
 * @param {string} action Ajax action name.
 * @param {Object} data   Extra fields.
 * @return {Promise<Object>} Parsed result.
 */
const ajax = ( action, data = {} ) => {
	const body = new URLSearchParams( { action, nonce: captlc_data.nonce, ...data } );

	return fetch( captlc_data.ajax_url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: body.toString(),
	} ).then( parseResponse );
};

export default ajax;
