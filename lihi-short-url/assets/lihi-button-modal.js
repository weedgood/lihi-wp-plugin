( function () {
	const { errorMessage, exceptionMessage, loadUrlOptions } = window.LihiButtonApi;
	const UTM_KEYS = [ 'source', 'medium', 'campaign', 'term', 'content' ];
	const MODAL_IDS = Object.freeze( {
		create: 'lihi-create-modal',
		createTitle: 'lihi-modal-title',
		domain: 'lihi-modal-domain',
		tagInput: 'lihi-modal-tag-input',
		tagAdd: 'lihi-modal-tag-add',
		tags: 'lihi-modal-tags',
		submit: 'lihi-modal-submit',
		notice: 'lihi-notice-modal',
		noticeTitle: 'lihi-notice-title',
		noticeMessage: 'lihi-notice-message',
		noticeConfirm: 'lihi-notice-confirm',
		confirm: 'lihi-confirm-modal',
		confirmTitle: 'lihi-confirm-title',
		confirmMessage: 'lihi-confirm-message',
		confirmCancel: 'lihi-confirm-cancel',
		confirmConfirm: 'lihi-confirm-confirm',
	} );
	const UTM_INPUT_IDS = Object.freeze( {
		source: 'lihi-utm-source',
		medium: 'lihi-utm-medium',
		campaign: 'lihi-utm-campaign',
		term: 'lihi-utm-term',
		content: 'lihi-utm-content',
	} );
	const modalHideTimers = new WeakMap();
	let activeModalTarget = null;
	let createHandler = null;

	function idSelector( id ) {
		return '#' + id;
	}

	function showModalElement( modal ) {
		const timer = modalHideTimers.get( modal );
		if ( timer ) {
			clearTimeout( timer );
			modalHideTimers.delete( modal );
		}

		modal.hidden = false;
		requestAnimationFrame( () => {
			modal.classList.add( 'is-visible' );
		} );
	}

	function hideModalElement( modal ) {
		modal.classList.remove( 'is-visible' );
		const timer = setTimeout( () => {
			if ( ! modal.classList.contains( 'is-visible' ) ) {
				modal.hidden = true;
			}
			modalHideTimers.delete( modal );
		}, lihiButton.modal?.fadeDelay || 180 );
		modalHideTimers.set( modal, timer );
	}

	function labelledControl( labelText, control ) {
		const label = document.createElement( 'label' );
		const span = document.createElement( 'span' );

		label.className = 'lihi-field';
		span.textContent = labelText;
		label.append( span, control );

		return label;
	}

	function labelledBlock( labelText, block ) {
		const wrapper = document.createElement( 'div' );
		const span = document.createElement( 'span' );

		wrapper.className = 'lihi-field';
		span.textContent = labelText;
		wrapper.append( span, block );

		return wrapper;
	}

	function textInput( id ) {
		const input = document.createElement( 'input' );
		input.id = id;
		input.type = 'text';
		return input;
	}

	function defaultTagsForContainer( container ) {
		return [ 'wordpress', lihiButton.siteHost, container.dataset.type ].filter( Boolean );
	}

	function ensureNoticeModal() {
		let modal = document.getElementById( MODAL_IDS.notice );
		if ( modal ) return modal;

		modal = document.createElement( 'div' );
		modal.id = MODAL_IDS.notice;
		modal.className = 'lihi-modal lihi-notice-modal';
		modal.hidden = true;

		const backdrop = document.createElement( 'div' );
		backdrop.className = 'lihi-modal__backdrop';

		const panel = document.createElement( 'div' );
		panel.className = 'lihi-modal__panel';
		panel.setAttribute( 'role', 'alertdialog' );
		panel.setAttribute( 'aria-modal', 'true' );
		panel.setAttribute( 'aria-labelledby', MODAL_IDS.noticeTitle );
		panel.setAttribute( 'aria-describedby', MODAL_IDS.noticeMessage );

		const header = document.createElement( 'div' );
		header.className = 'lihi-modal__header';

		const title = document.createElement( 'h2' );
		title.id = MODAL_IDS.noticeTitle;
		title.textContent = lihiButton.notice?.title || 'lihi';

		const body = document.createElement( 'div' );
		body.className = 'lihi-modal__body';

		const message = document.createElement( 'p' );
		message.id = MODAL_IDS.noticeMessage;
		message.className = 'lihi-notice-modal__message';
		body.appendChild( message );

		const footer = document.createElement( 'div' );
		footer.className = 'lihi-modal__footer';

		const confirm = document.createElement( 'button' );
		confirm.type = 'button';
		confirm.className = 'button button-primary';
		confirm.id = MODAL_IDS.noticeConfirm;
		confirm.textContent = lihiButton.notice?.confirm || 'OK';

		header.appendChild( title );
		footer.appendChild( confirm );
		panel.append( header, body, footer );
		modal.append( backdrop, panel );
		document.body.appendChild( modal );

		return modal;
	}

	function showNotice( message, onConfirm = null ) {
		const modal = ensureNoticeModal();
		const confirm = modal.querySelector( idSelector( MODAL_IDS.noticeConfirm ) );
		confirm.disabled = false;
		confirm.classList.remove( 'lihi-btn-loading' );
		confirm.textContent = lihiButton.notice?.confirm || 'OK';
		modal.querySelector( idSelector( MODAL_IDS.noticeMessage ) ).textContent = message;
		showModalElement( modal );
		confirm.focus();

		return new Promise( ( resolve ) => {
			confirm.onclick = async () => {
				hideModalElement( modal );
				try {
					if ( typeof onConfirm === 'function' ) {
						await onConfirm();
					}
				} catch ( error ) {
					await showNotice( exceptionMessage( error ) );
				} finally {
					resolve();
				}
			};
		} );
	}

	function ensureConfirmModal() {
		let modal = document.getElementById( MODAL_IDS.confirm );
		if ( modal ) return modal;

		modal = document.createElement( 'div' );
		modal.id = MODAL_IDS.confirm;
		modal.className = 'lihi-modal lihi-confirm-modal';
		modal.hidden = true;

		const backdrop = document.createElement( 'div' );
		backdrop.className = 'lihi-modal__backdrop';

		const panel = document.createElement( 'div' );
		panel.className = 'lihi-modal__panel';
		panel.setAttribute( 'role', 'alertdialog' );
		panel.setAttribute( 'aria-modal', 'true' );
		panel.setAttribute( 'aria-labelledby', MODAL_IDS.confirmTitle );
		panel.setAttribute( 'aria-describedby', MODAL_IDS.confirmMessage );

		const header = document.createElement( 'div' );
		header.className = 'lihi-modal__header';

		const title = document.createElement( 'h2' );
		title.id = MODAL_IDS.confirmTitle;
		title.textContent = lihiButton.notice?.title || 'lihi';

		const body = document.createElement( 'div' );
		body.className = 'lihi-modal__body';

		const message = document.createElement( 'p' );
		message.id = MODAL_IDS.confirmMessage;
		message.className = 'lihi-notice-modal__message';
		body.appendChild( message );

		const footer = document.createElement( 'div' );
		footer.className = 'lihi-modal__footer';

		const cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.className = 'button';
		cancel.id = MODAL_IDS.confirmCancel;
		cancel.textContent = lihiButton.notice?.cancel || 'Cancel';

		const confirm = document.createElement( 'button' );
		confirm.type = 'button';
		confirm.className = 'button button-primary';
		confirm.id = MODAL_IDS.confirmConfirm;
		confirm.textContent = lihiButton.notice?.confirm || 'OK';

		header.appendChild( title );
		footer.append( cancel, confirm );
		panel.append( header, body, footer );
		modal.append( backdrop, panel );
		document.body.appendChild( modal );

		return modal;
	}

	function showConfirm( message, options = {} ) {
		const modal = ensureConfirmModal();
		const cancel = modal.querySelector( idSelector( MODAL_IDS.confirmCancel ) );
		const confirm = modal.querySelector( idSelector( MODAL_IDS.confirmConfirm ) );
		const closeOnConfirm = options?.closeOnConfirm ?? true;
		const onConfirm = options?.onConfirm;
		modal.querySelector( idSelector( MODAL_IDS.confirmMessage ) ).textContent = message;
		cancel.disabled = false;
		cancel.hidden = false;
		cancel.textContent = lihiButton.notice?.cancel || 'Cancel';
		confirm.disabled = false;
		confirm.classList.remove( 'lihi-btn-loading' );
		confirm.textContent = lihiButton.notice?.confirm || 'OK';
		showModalElement( modal );
		cancel.focus();

		return new Promise( ( resolve ) => {
			cancel.onclick = () => {
				hideModalElement( modal );
				resolve( false );
			};
			confirm.onclick = () => {
				if ( typeof onConfirm === 'function' ) {
					try {
						if ( onConfirm() === false ) {
							hideModalElement( modal );
							resolve( false );
							return;
						}
					} catch {
						hideModalElement( modal );
						resolve( false );
						return;
					}
				}

				if ( closeOnConfirm ) {
					hideModalElement( modal );
				} else {
					cancel.disabled = true;
					cancel.hidden = true;
					confirm.disabled = true;
					confirm.classList.add( 'lihi-btn-loading' );
				}
				resolve( true );
			};
		} );
	}

	function closeConfirmModal() {
		const modal = document.getElementById( MODAL_IDS.confirm );
		if ( modal ) hideModalElement( modal );
	}

	function ensureCreateModal() {
		let modal = document.getElementById( MODAL_IDS.create );
		if ( modal ) return modal;

		const labels = lihiButton.modal;
		modal = document.createElement( 'div' );
		modal.id = MODAL_IDS.create;
		modal.className = 'lihi-modal';
		modal.hidden = true;

		const backdrop = document.createElement( 'div' );
		backdrop.className = 'lihi-modal__backdrop';
		backdrop.dataset.lihiClose = '';

		const panel = document.createElement( 'div' );
		panel.className = 'lihi-modal__panel';
		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-modal', 'true' );
		panel.setAttribute( 'aria-labelledby', MODAL_IDS.createTitle );

		const header = document.createElement( 'div' );
		header.className = 'lihi-modal__header';

		const title = document.createElement( 'h2' );
		title.id = MODAL_IDS.createTitle;
		title.textContent = labels.title;

		const close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'button-link lihi-modal__close';
		close.dataset.lihiClose = '';
		close.setAttribute( 'aria-label', labels.cancel );
		close.textContent = '\u00d7';

		header.append( title, close );

		const body = document.createElement( 'div' );
		body.className = 'lihi-modal__body';

		const domain = document.createElement( 'select' );
		domain.id = MODAL_IDS.domain;

		const tagInput = textInput( MODAL_IDS.tagInput );
		const tagAdd = document.createElement( 'button' );
		tagAdd.id = MODAL_IDS.tagAdd;
		tagAdd.type = 'button';
		tagAdd.className = 'button';
		tagAdd.textContent = labels.tagsAdd || 'Add';

		const tagEntry = document.createElement( 'div' );
		tagEntry.className = 'lihi-tag-entry';
		tagEntry.append( tagInput, tagAdd );

		const tags = document.createElement( 'div' );
		tags.id = MODAL_IDS.tags;
		tags.className = 'lihi-tag-list lihi-tag-list--modal';

		const tagControl = document.createElement( 'div' );
		tagControl.className = 'lihi-tag-control';
		tagControl.append( tagEntry, tags );

		const tagField = labelledBlock( labels.tags, tagControl );

		const utmGrid = document.createElement( 'div' );
		utmGrid.className = 'lihi-utm-grid';
		utmGrid.append(
			labelledControl( labels.utmSource, textInput( UTM_INPUT_IDS.source ) ),
			labelledControl( labels.utmMedium, textInput( UTM_INPUT_IDS.medium ) ),
			labelledControl( labels.utmCampaign, textInput( UTM_INPUT_IDS.campaign ) ),
			labelledControl( labels.utmTerm, textInput( UTM_INPUT_IDS.term ) ),
			labelledControl( labels.utmContent, textInput( UTM_INPUT_IDS.content ) )
		);

		body.append(
			labelledControl( labels.domain, domain ),
			tagField,
			utmGrid
		);

		const footer = document.createElement( 'div' );
		footer.className = 'lihi-modal__footer';

		const submit = document.createElement( 'button' );
		submit.type = 'button';
		submit.className = 'button button-primary';
		submit.id = MODAL_IDS.submit;
		submit.textContent = labels.submit;

		footer.append( submit );
		panel.append( header, body, footer );
		modal.append( backdrop, panel );

		document.body.appendChild( modal );
		modal.querySelectorAll( '[data-lihi-close]' ).forEach( ( node ) => {
			node.addEventListener( 'click', closeCreateModal );
		} );
		modal.querySelector( idSelector( MODAL_IDS.submit ) ).addEventListener( 'click', () => {
			if ( ! activeModalTarget || typeof createHandler !== 'function' ) return;
			const { container, btn } = activeModalTarget;
			const payload = collectCreateModalPayload();
			closeCreateModal();
			createHandler( container, btn, payload );
		} );
		modal.querySelector( idSelector( MODAL_IDS.tagAdd ) ).addEventListener( 'click', () => {
			addTagFromInput( modal.querySelector( idSelector( MODAL_IDS.tagInput ) ) );
		} );
		modal.querySelector( idSelector( MODAL_IDS.tagInput ) ).addEventListener( 'keydown', ( event ) => {
			if ( event.key !== 'Enter' ) return;
			event.preventDefault();
			addTagFromInput( event.currentTarget );
		} );
		modal.querySelector( idSelector( MODAL_IDS.tags ) ).addEventListener( 'click', ( event ) => {
			if ( ! ( event.target instanceof Element ) ) return;
			const remove = event.target.closest( '[data-lihi-remove-tag]' );
			if ( ! remove ) return;
			const chip = remove.closest( '[data-lihi-user-tag]' );
			if ( chip ) chip.remove();
		} );

		return modal;
	}

	function closeCreateModal() {
		const modal = document.getElementById( MODAL_IDS.create );
		if ( modal ) hideModalElement( modal );
		activeModalTarget = null;
	}

	function renderDomainOptions( domains ) {
		const select = document.getElementById( MODAL_IDS.domain );
		select.replaceChildren();

		domains.forEach( ( domain ) => {
			const option = document.createElement( 'option' );
			option.value = domain;
			option.textContent = domain;
			select.appendChild( option );
		} );
	}

	function renderDefaultTags( tags ) {
		const container = document.getElementById( MODAL_IDS.tags );
		tags.forEach( ( tag ) => {
			const chip = document.createElement( 'span' );
			chip.className = 'lihi-tag';
			chip.dataset.lihiDefaultTag = '';
			chip.dataset.tag = tag;
			chip.textContent = tag;
			container.appendChild( chip );
		} );
	}

	function collectUserTags( modal ) {
		return Array.from( modal.querySelectorAll( '[data-lihi-user-tag]' ) )
			.map( ( chip ) => chip.dataset.tag )
			.filter( Boolean );
	}

	function collectDefaultTags( modal ) {
		return Array.from( modal.querySelectorAll( '[data-lihi-default-tag]' ) )
			.map( ( chip ) => chip.dataset.tag )
			.filter( Boolean );
	}

	function renderUserTag( tag ) {
		const chip = document.createElement( 'span' );
		chip.className = 'lihi-tag lihi-tag--removable';
		chip.dataset.lihiUserTag = '';
		chip.dataset.tag = tag;

		const label = document.createElement( 'span' );
		label.textContent = tag;

		const remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'lihi-tag__remove';
		remove.dataset.lihiRemoveTag = '';
		remove.setAttribute( 'aria-label', ( lihiButton.modal?.removeTag || 'Remove tag' ) + ': ' + tag );
		remove.textContent = '\u00d7';

		chip.append( label, remove );
		return chip;
	}

	function addTagFromInput( input ) {
		const tag = input.value.trim();
		if ( ! tag ) return;

		const modal = ensureCreateModal();
		const existingTags = [ ...collectDefaultTags( modal ), ...collectUserTags( modal ) ];
		if ( ! existingTags.includes( tag ) ) {
			modal.querySelector( idSelector( MODAL_IDS.tags ) ).appendChild( renderUserTag( tag ) );
		}
		input.value = '';
		input.focus();
	}

	function collectCreateModalPayload() {
		const modal = ensureCreateModal();
		const utm = {};
		UTM_KEYS.forEach( ( key ) => {
			const value = modal.querySelector( idSelector( UTM_INPUT_IDS[ key ] ) ).value.trim();
			if ( value ) utm[ key ] = value;
		} );

		return {
			domain: modal.querySelector( idSelector( MODAL_IDS.domain ) ).value,
			tags: collectUserTags( modal ),
			utm,
		};
	}

	async function openCreateModal( container, btn, onSubmit ) {
		const modal = ensureCreateModal();
		const select = modal.querySelector( idSelector( MODAL_IDS.domain ) );
		const submit = modal.querySelector( idSelector( MODAL_IDS.submit ) );

		if ( typeof onSubmit === 'function' ) {
			createHandler = onSubmit;
		}
		activeModalTarget = { container, btn };
		modal.querySelector( idSelector( MODAL_IDS.tagInput ) ).value = '';
		modal.querySelector( idSelector( MODAL_IDS.tags ) ).replaceChildren();
		UTM_KEYS.forEach( ( key ) => {
			modal.querySelector( idSelector( UTM_INPUT_IDS[ key ] ) ).value = '';
		} );
		renderDefaultTags( defaultTagsForContainer( container ) );
		select.replaceChildren( new Option( lihiButton.modal.loading, '' ) );
		submit.disabled = true;
		showModalElement( modal );

		let data;
		try {
			data = await loadUrlOptions( container );
		} catch ( error ) {
			closeCreateModal();
			await showNotice( exceptionMessage( error ) );
			return;
		}

		if ( ! data.success ) {
			closeCreateModal();
			await showNotice( errorMessage( data ) );
			return;
		}

		const domains = data.data.domains || [];
		if ( domains.length === 0 ) {
			closeCreateModal();
			await showNotice( lihiButton.modal.noDomains );
			return;
		}

		renderDomainOptions( domains );
		submit.disabled = false;
	}

	window.LihiButtonModal = Object.freeze( {
		closeConfirmModal,
		openCreateModal,
		showConfirm,
		showNotice,
	} );
}() );
