( function () {
	function errorMessage( data ) {
		if ( typeof data?.data === 'string' ) return data.data;
		if ( typeof data?.data?.message === 'string' ) return data.data.message;
		return 'Request failed.';
	}

	function exceptionMessage( error ) {
		return error?.message || 'Request failed.';
	}

	async function postAjax( params ) {
		const res = await fetch( lihiButton.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( params ),
		} );
		return await res.json();
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
		return await postAjax( itemPayload( container, lihiButton.optionsAction ) );
	}

	async function createShortUrl( container, options = {} ) {
		return await postAjax( createPayload( container, options ) );
	}

	async function copyShortUrl( container ) {
		return await postAjax( itemPayload( container, lihiButton.copyAction ) );
	}

	window.LihiButtonApi = Object.freeze( {
		copyShortUrl,
		createShortUrl,
		errorMessage,
		exceptionMessage,
		loadUrlOptions,
	} );
}() );
