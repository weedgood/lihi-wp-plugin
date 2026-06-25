const {
	copyShortUrl: copyShortUrlFromApi,
	createPassthroughNonce: createPassthroughNonceFromApi,
	createShortUrl,
	errorMessage,
	exceptionMessage,
} = window.LihiButtonApi;
const {
	closeConfirmModal,
	openCreateModal,
	showConfirm,
	showNotice,
} = window.LihiButtonModal;

let lihiBusy = false;
const pendingReverts = new WeakMap();

function getReadyLabel( container ) {
	return container.dataset.lihiAlready === '1'
		? lihiButton.labelReady
		: lihiButton.labelOriginal;
}

function renderButtonLabel( container, btn ) {
	const label = getReadyLabel( container );
	btn.textContent = label;
	btn.setAttribute( 'aria-label', label );
}

function setButtonAlready( container, btn, already ) {
	container.setAttribute( 'data-lihi-already', already ? '1' : '0' );
	renderButtonLabel( container, btn );
	renderEditButton( container );
}

function isMissingShortUrl( data ) {
	return data?.data?.code === 'lihi_missing';
}

function canEditShortUrl() {
	return lihiButton.canEditShortUrl === '1' || lihiButton.canEditShortUrl === true;
}

function prepareButton( btn ) {
	delete btn.dataset.id;
	delete btn.dataset.type;
	delete btn.dataset.lihiAlready;
	btn.dataset.lihi = '';
}

function renderButtonContainer( container ) {
	const existing = container.querySelector( 'button[data-lihi]' );
	if ( existing ) {
		prepareButton( existing );
		renderButtonLabel( container, existing );
		renderEditButton( container );
		return existing;
	}

	const btn = document.createElement( 'button' );

	btn.type = 'button';
	btn.className = 'button button-secondary';
	prepareButton( btn );
	renderButtonLabel( container, btn );

	container.appendChild( btn );
	renderEditButton( container );
	return btn;
}

function renderEditButton( container ) {
	if ( ! canEditShortUrl() || container.dataset.lihiAlready !== '1' ) {
		container.querySelector( 'button[data-lihi-edit]' )?.remove();
		return null;
	}

	const existing = container.querySelector( 'button[data-lihi-edit]' );
	const label = lihiButton.labelEdit || 'Edit';
	if ( existing ) {
		existing.textContent = label;
		existing.setAttribute( 'aria-label', label );
		return existing;
	}

	const btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = 'button button-secondary';
	btn.dataset.lihiEdit = '';
	btn.textContent = label;
	btn.setAttribute( 'aria-label', label );
	container.appendChild( btn );
	return btn;
}

function renderButtonContainers( root ) {
	if ( root.nodeType === Node.ELEMENT_NODE && root.matches?.( '[data-lihi-container]' ) ) {
		renderButtonContainer( root );
		return;
	}

	root.querySelectorAll?.( '[data-lihi-container]' ).forEach( renderButtonContainer );
}

function cancelPendingRevert( container, btn ) {
	const timer = pendingReverts.get( btn );
	if ( ! timer ) return;
	clearTimeout( timer );
	pendingReverts.delete( btn );
	renderButtonLabel( container, btn );
}

async function copyShortUrl( url ) {
	try {
		await navigator.clipboard.writeText( url );
		return true;
	} catch ( error ) {
		window.prompt( lihiButton.copyFallback || 'Copy this short URL:', url );
		return false;
	}
}

function base64UrlEncode( bytes ) {
	let binary = '';
	bytes.forEach( ( byte ) => {
		binary += String.fromCharCode( byte );
	} );

	return window.btoa( binary )
		.replace( /\+/g, '-' )
		.replace( /\//g, '_' )
		.replace( /=+$/g, '' );
}

async function createPassthroughProof( unavailableMessage = '' ) {
	if ( ! window.crypto?.getRandomValues || ! window.crypto?.subtle || ! window.TextEncoder ) {
		throw new Error(
			unavailableMessage ||
			lihiButton.edit?.proofUnavailable ||
			'Your browser does not support secure lihi edit verification.'
		);
	}

	const bytes = new Uint8Array( 32 );
	window.crypto.getRandomValues( bytes );

	const verifier = base64UrlEncode( bytes );
	const digest = await window.crypto.subtle.digest(
		'SHA-256',
		new window.TextEncoder().encode( verifier )
	);

	return {
		challenge: base64UrlEncode( new Uint8Array( digest ) ),
		verifier,
	};
}

function buildPassthroughRedirectUrl( redirectUrl, nonce, verifier ) {
	const separator = redirectUrl.includes( '?' ) ? '&' : '?';
	return redirectUrl + separator +
		'nonce=' + encodeURIComponent( nonce ) +
		'&verifier=' + encodeURIComponent( verifier );
}

async function withLihiBusy( container, btn, callback ) {
	if ( lihiBusy ) return;

	cancelPendingRevert( container, btn );
	const allBtns = document.querySelectorAll( 'button[data-lihi], button[data-lihi-edit]' );

	lihiBusy = true;
	allBtns.forEach( ( b ) => { b.disabled = true; } );
	btn.classList.add( 'lihi-btn-loading' );

	try {
		await callback();
	} finally {
		btn.classList.remove( 'lihi-btn-loading' );
		lihiBusy = false;
		allBtns.forEach( ( b ) => { b.disabled = false; } );
	}
}

async function showCopiedState( container, btn, data ) {
	const shortUrl = data.data?.url;
	if ( ! shortUrl ) {
		await showNotice( errorMessage( data ) );
		return;
	}

	btn.classList.remove( 'lihi-btn-loading' );
	if ( data.data.lihi_already ) {
		setButtonAlready( container, btn, true );
	} else {
		renderButtonLabel( container, btn );
	}

	if ( ! await copyShortUrl( shortUrl ) ) {
		return;
	}

	btn.textContent = lihiButton.labelCopied;

	const timer = setTimeout( () => {
		renderButtonLabel( container, btn );
		pendingReverts.delete( btn );
	}, lihiButton.labelDelay );
	pendingReverts.set( btn, timer );

	await new Promise( ( resolve ) => setTimeout( resolve, lihiButton.resetDelay ) );
}

async function createLihi( container, btn, options = {} ) {
	await withLihiBusy( container, btn, async () => {
		let data;
		try {
			data = await createShortUrl( container, options );
		} catch ( error ) {
			await showNotice( exceptionMessage( error ) );
			return;
		}

		if ( ! data.success ) {
			await showNotice( errorMessage( data ) );
			return;
		}

		await showCopiedState( container, btn, data );
	} );
}

async function openDashboardTarget( container, config = {} ) {
	const target = config.target || '';
	if ( ! target ) {
		throw new Error( 'Invalid lihi dashboard target.' );
	}

	const proof = await createPassthroughProof(
		config.proofUnavailable ||
		'Your browser does not support secure lihi dashboard login.'
	);
	const data = await createPassthroughNonceFromApi(
		container,
		target,
		proof.challenge,
		config
	);

	if ( ! data.success ) {
		throw new Error( errorMessage( data ) );
	}

	const nonce = data.data?.nonce;
	const redirectUrl = data.data?.redirect_url || lihiButton.passthroughRedirectUrl;
	if ( ! nonce || ! redirectUrl ) {
		throw new Error( errorMessage( data ) );
	}

	const link = document.createElement( 'a' );
	link.href = buildPassthroughRedirectUrl( redirectUrl, nonce, proof.verifier );
	link.target = '_blank';
	link.rel = 'noopener noreferrer';
	link.click();
}

async function openCreateOrNoticeExisting( container, btn ) {
	await withLihiBusy( container, btn, async () => {
		let data;
		try {
			data = await copyShortUrlFromApi( container );
		} catch ( error ) {
			await showNotice( exceptionMessage( error ) );
			return;
		}

		if ( data.success ) {
			await showCopiedState( container, btn, data );
			return;
		}

		if ( isMissingShortUrl( data ) ) {
			setButtonAlready( container, btn, false );
			await openCreateModal( container, btn, createLihi, openDashboardTarget );
			return;
		}

		await showNotice( errorMessage( data ) );
	} );
}

async function tryCopyLihi( container, btn ) {
	await withLihiBusy( container, btn, async () => {
		let data;
		try {
			data = await copyShortUrlFromApi( container );
		} catch ( error ) {
			await showNotice( exceptionMessage( error ) );
			return;
		}

		if ( ! data.success ) {
			if ( isMissingShortUrl( data ) ) {
				setButtonAlready( container, btn, false );
				await showNotice( errorMessage( data ), async () => {
					await openCreateModal( container, btn, createLihi, openDashboardTarget );
				} );
				return;
			}

			await showNotice( errorMessage( data ) );
			return;
		}

		await showCopiedState( container, btn, data );
	} );
}

async function editLihi( container, btn ) {
	try {
		await showConfirm(
			lihiButton.edit?.confirmMessage || 'Go to the lihi dashboard to edit this short URL?',
			{
				onConfirm: async () => {
					await withLihiBusy( container, btn, async () => {
						let data;
						let proof;
						try {
							proof = await createPassthroughProof();
							const existing = await copyShortUrlFromApi( container );
							if ( ! existing.success ) {
								data = existing;
							} else if ( ! existing.data?.url ) {
								throw new Error( errorMessage( existing ) );
							} else {
								data = await createPassthroughNonceFromApi(
									container,
									existing.data.url,
									proof.challenge,
									lihiButton.edit || {}
								);
							}
						} catch ( error ) {
							closeConfirmModal();
							await showNotice( exceptionMessage( error ) );
							return;
						}

						if ( ! data.success ) {
							closeConfirmModal();
							if ( isMissingShortUrl( data ) ) {
								setButtonAlready( container, container.querySelector( 'button[data-lihi]' ) || btn, false );
							}
							await showNotice( errorMessage( data ) );
							return;
						}

						const nonce = data.data?.nonce;
						const redirectUrl = data.data?.redirect_url || lihiButton.passthroughRedirectUrl;
						if ( ! nonce || ! redirectUrl ) {
							closeConfirmModal();
							await showNotice( errorMessage( data ) );
							return;
						}

						const link = document.createElement( 'a' );
						link.href = buildPassthroughRedirectUrl( redirectUrl, nonce, proof.verifier );
						link.target = '_blank';
						link.rel = 'noopener noreferrer';
						link.click();
					} );

					return true;
				},
			}
		);
	} catch ( error ) {
		await showNotice( exceptionMessage( error ) );
	}
}

document.addEventListener( 'click', async ( e ) => {
	const container = e.target.closest( '[data-lihi-container]' );
	if ( ! container || lihiBusy ) return;

	const editBtn = e.target.closest( 'button[data-lihi-edit]' );
	if ( editBtn && container.contains( editBtn ) ) {
		e.stopPropagation();
		if ( ! canEditShortUrl() ) return;

		await editLihi( container, editBtn );
		return;
	}

	const btn = e.target.closest( 'button[data-lihi]' );
	if ( ! btn || ! container.contains( btn ) ) return;

	e.stopPropagation();

	if ( container.dataset.lihiAlready === '1' ) {
		await tryCopyLihi( container, btn );
		return;
	}

	await openCreateOrNoticeExisting( container, btn );
} );

function initButtonRendering() {
	renderButtonContainers( document );

	const observer = new MutationObserver( ( mutations ) => {
		mutations.forEach( ( mutation ) => {
			mutation.addedNodes.forEach( ( node ) => {
				if ( node.nodeType !== Node.ELEMENT_NODE ) return;

				renderButtonContainers( node );
			} );
		} );
	} );

	observer.observe( document.body, { childList: true, subtree: true } );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initButtonRendering );
} else {
	initButtonRendering();
}
