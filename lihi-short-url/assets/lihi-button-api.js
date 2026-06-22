( function () {
	const URL_OPTIONS_CACHE_TTL = 60000;
	let urlOptionsCache = null;
	let urlOptionsCacheTime = 0;
	let urlOptionsRequest = null;

	function fallbackMessage() {
		return lihiButton.requestFailed || 'Request failed. Please try again later.';
	}

	function errorMessage( data ) {
		if ( typeof data?.data === 'string' ) return data.data;
		if ( typeof data?.data?.message === 'string' ) return data.data.message;
		return fallbackMessage();
	}

	function exceptionMessage( error ) {
		return error?.message || fallbackMessage();
	}

	function isValidPassthroughChallenge( challenge ) {
		return typeof challenge === 'string' && /^[A-Za-z0-9_-]{43}$/.test( challenge );
	}

	async function parseAjaxResponse( res ) {
		const text = await res.text();

		try {
			const data = JSON.parse( text );
			if ( data && typeof data === 'object' && ! Array.isArray( data ) ) {
				return data;
			}
		} catch {
			// Non-JSON admin-ajax responses should never leak parser details into the modal.
		}

		throw new Error( fallbackMessage() );
	}

	async function postAjax( params ) {
		const body = new URLSearchParams();
		Object.entries( params ).forEach( ( [ key, value ] ) => {
			if ( value === undefined || value === null ) return;
			body.append( key, value );
		} );

		const res = await fetch( lihiButton.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body,
		} );
		return await parseAjaxResponse( res );
	}

	function itemPayload( container, action ) {
		return {
			action,
			nonce: lihiButton.nonce,
			item_id: container.dataset.id,
			type: container.dataset.type,
		};
	}

	function createPayload( container, options = {} ) {
		return {
			...itemPayload( container, lihiButton.createAction ),
			domain: options.domain || '',
			tags: JSON.stringify( options.tags || [] ),
			utm: JSON.stringify( options.utm || {} ),
		};
	}

	async function loadUrlOptions( container ) {
		if ( urlOptionsCache && Date.now() - urlOptionsCacheTime < URL_OPTIONS_CACHE_TTL ) {
			return urlOptionsCache;
		}

		if ( ! urlOptionsRequest ) {
			urlOptionsRequest = postAjax( itemPayload( container, lihiButton.optionsAction ) )
				.then( ( data ) => {
					if ( data.success ) {
						urlOptionsCache = data;
						urlOptionsCacheTime = Date.now();
					}
					return data;
				} )
				.finally( () => {
					urlOptionsRequest = null;
				} );
		}

		return await urlOptionsRequest;
	}

	async function createShortUrl( container, options = {} ) {
		return await postAjax( createPayload( container, options ) );
	}

	async function copyShortUrl( container ) {
		return await postAjax( itemPayload( container, lihiButton.copyAction ) );
	}

	async function editShortUrl( container, challenge ) {
		if ( ! isValidPassthroughChallenge( challenge ) ) {
			throw new Error(
				lihiButton.edit?.invalidProof ||
				'Could not verify browser session. Please refresh the page and try again.'
			);
		}

		return await postAjax( {
			...itemPayload( container, lihiButton.editAction ),
			challenge,
		} );
	}

	window.LihiButtonApi = Object.freeze( {
		copyShortUrl,
		createShortUrl,
		editShortUrl,
		errorMessage,
		exceptionMessage,
		loadUrlOptions,
	} );
}() );
