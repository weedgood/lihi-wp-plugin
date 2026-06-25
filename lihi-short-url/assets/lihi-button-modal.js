( function () {
	const { errorMessage, exceptionMessage, loadUrlOptions } = window.LihiButtonApi;
	const UTM_KEYS = [ 'source', 'medium', 'campaign', 'term', 'content' ];
	const MODAL_IDS = Object.freeze( {
		create: 'lihi-create-modal',
		createTitle: 'lihi-modal-title',
		domain: 'lihi-modal-domain',
		tagInput: 'lihi-modal-tag-input',
		tagAdd: 'lihi-modal-tag-add',
		tagRecommendations: 'lihi-modal-tag-recommendations',
		tagSuggestions: 'lihi-modal-tag-suggestions',
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
		utmGrid: 'lihi-utm-grid',
	} );
	const UTM_INPUT_IDS = Object.freeze( {
		source: 'lihi-utm-source',
		medium: 'lihi-utm-medium',
		campaign: 'lihi-utm-campaign',
		term: 'lihi-utm-term',
		content: 'lihi-utm-content',
	} );
	const MEDIA_TYPE = 'attachment';
	const modalHideTimers = new WeakMap();
	let activeModalTarget = null;
	let createHandler = null;
	let dashboardTargetHandler = null;

	function idSelector( id ) {
		return '#' + id;
	}

	function blankUtmPayload() {
		return UTM_KEYS.reduce( ( values, key ) => ( {
			...values,
			[ key ]: '',
		} ), {} );
	}

	function shouldShowUtmFields( container ) {
		return container?.dataset?.type !== MEDIA_TYPE;
	}

	function setUtmFieldsVisible( visible ) {
		const grid = document.getElementById( MODAL_IDS.utmGrid );
		if ( grid ) grid.hidden = ! visible;
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

	function labelledControlWithAction( labelText, control, action ) {
		const wrapper = document.createElement( 'div' );
		const header = document.createElement( 'div' );
		const label = document.createElement( 'label' );

		wrapper.className = 'lihi-field';
		header.className = 'lihi-field__header';
		label.className = 'lihi-field__label';
		label.setAttribute( 'for', control.id );
		label.textContent = labelText;
		header.append( label, action );
		wrapper.append( header, control );

		return wrapper;
	}

	function textInput( id ) {
		const input = document.createElement( 'input' );
		input.id = id;
		input.type = 'text';
		return input;
	}

	function selectInput( id ) {
		const select = document.createElement( 'select' );
		select.id = id;
		return select;
	}

	function normalizeSelectOption( item ) {
		const value = typeof item === 'object' && item !== null
			? String( item.value || item.id || item.name || '' ).trim()
			: String( item || '' ).trim();
		const label = typeof item === 'object' && item !== null
			? String( item.label || item.name || value ).trim()
			: value;

		if ( ! value ) return null;

		return {
			value,
			label: label || value,
		};
	}

	function renderSelectOptions( select, items, options = {} ) {
		select.replaceChildren();

		if ( options.loading ) {
			select.replaceChildren( new Option( lihiButton.modal.loading, '' ) );
			select.disabled = true;
			return;
		}

		select.disabled = Boolean( options.disabled );

		if ( options.includeBlank ) {
			select.appendChild( new Option( options.blankLabel || '', '' ) );
		}

		if ( ! Array.isArray( items ) ) return;

		items.forEach( ( item ) => {
			const normalized = normalizeSelectOption( item );
			if ( ! normalized ) return;

			const option = document.createElement( 'option' );
			option.value = normalized.value;
			option.textContent = normalized.label;
			select.appendChild( option );
		} );
	}

	function normalizeTag( tag ) {
		return String( tag ?? '' ).trim();
	}

	function uniqueTags( tags ) {
		const seen = new Set();
		return tags
			.map( normalizeTag )
			.filter( ( tag ) => {
				if ( ! tag || seen.has( tag ) ) return false;
				seen.add( tag );
				return true;
			} );
	}

	function recommendedTagsForContainer( container ) {
		return uniqueTags( [ 'wordpress', lihiButton.siteHost, container.dataset.type ] );
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
		const onConfirm = options?.onConfirm;
		const close = () => {
			hideModalElement( modal );
		};
		const resetConfirmControls = () => {
			cancel.disabled = false;
			cancel.hidden = false;
			confirm.disabled = false;
			confirm.classList.remove( 'lihi-btn-loading' );
		};
		modal.querySelector( idSelector( MODAL_IDS.confirmMessage ) ).textContent = message;
		resetConfirmControls();
		cancel.textContent = lihiButton.notice?.cancel || 'Cancel';
		confirm.textContent = lihiButton.notice?.confirm || 'OK';
		showModalElement( modal );
		cancel.focus();

		// Resolves only when the modal closes; returning false from onConfirm keeps it open.
		return new Promise( ( resolve, reject ) => {
			cancel.onclick = () => {
				close();
				resolve();
			};
			confirm.onclick = async () => {
				cancel.disabled = true;
				cancel.hidden = true;
				confirm.disabled = true;
				confirm.classList.add( 'lihi-btn-loading' );

				if ( typeof onConfirm === 'function' ) {
					try {
						if ( await onConfirm() === false ) {
							resetConfirmControls();
							return;
						}
					} catch ( error ) {
						close();
						reject( error );
						return;
					}
				}

				close();
				resolve();
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

		const canOpenDashboard = lihiButton.canEditShortUrl === '1' ||
			lihiButton.canEditShortUrl === true;

		const domainDashboardConfig = lihiButton.domainDashboard || {};
		const domainDashboard = document.createElement( canOpenDashboard ? 'button' : 'a' );
		domainDashboard.className = 'button-link lihi-dashboard-link';
		domainDashboard.textContent = labels.customDomain || 'Custom domain?';
		if ( canOpenDashboard ) {
			domainDashboard.type = 'button';
			domainDashboard.dataset.lihiDashboardTarget = 'domainDashboard';
		} else {
			domainDashboard.href = domainDashboardConfig.externalUrl || 'https://lihidomain.com';
			domainDashboard.target = '_blank';
			domainDashboard.rel = 'noopener noreferrer';
		}

		const tagInput = textInput( MODAL_IDS.tagInput );
		const tagAdd = document.createElement( 'button' );
		tagAdd.id = MODAL_IDS.tagAdd;
		tagAdd.type = 'button';
		tagAdd.className = 'button';
		tagAdd.textContent = labels.tagsAdd || 'Add';

		const tags = document.createElement( 'div' );
		tags.id = MODAL_IDS.tags;
		tags.className = 'lihi-tag-list lihi-tag-list--input';

		const tagInputShell = document.createElement( 'div' );
		tagInputShell.className = 'lihi-tag-input';
		tagInputShell.append( tags, tagInput );

		const tagEntry = document.createElement( 'div' );
		tagEntry.className = 'lihi-tag-entry';
		tagEntry.append( tagInputShell, tagAdd );

		const tagSuggestions = document.createElement( 'div' );
		tagSuggestions.id = MODAL_IDS.tagSuggestions;
		tagSuggestions.className = 'lihi-tag-suggestions';

		const tagRecommendations = document.createElement( 'div' );
		tagRecommendations.id = MODAL_IDS.tagRecommendations;
		tagRecommendations.className = 'lihi-tag-recommendations';
		tagRecommendations.hidden = true;

		const tagRecommendationLabel = document.createElement( 'span' );
		tagRecommendationLabel.className = 'lihi-tag-recommendations__label';
		tagRecommendationLabel.textContent = labels.tagRecommendations || 'Recommended tags';
		tagRecommendations.append( tagRecommendationLabel, tagSuggestions );

		const tagControl = document.createElement( 'div' );
		tagControl.className = 'lihi-tag-control';
		tagControl.append( tagEntry, tagRecommendations );

		const tagField = labelledBlock( labels.tags, tagControl );

		const utmDashboard = document.createElement( 'button' );
		utmDashboard.type = 'button';
		utmDashboard.className = 'button-link lihi-dashboard-link';
		utmDashboard.dataset.lihiDashboardTarget = 'utmDashboard';
		utmDashboard.textContent = labels.manageOptions || 'Manage options?';
		utmDashboard.hidden = ! canOpenDashboard;

		const utmDashboardRow = document.createElement( 'div' );
		utmDashboardRow.className = 'lihi-utm-dashboard-row';
		utmDashboardRow.append( utmDashboard );

		const utmGrid = document.createElement( 'div' );
		utmGrid.id = MODAL_IDS.utmGrid;
		utmGrid.className = 'lihi-utm-grid';
		utmGrid.append(
			labelledControl( labels.utmSource, selectInput( UTM_INPUT_IDS.source ) ),
			labelledControl( labels.utmMedium, selectInput( UTM_INPUT_IDS.medium ) ),
			utmDashboardRow,
			labelledControl( labels.utmCampaign, textInput( UTM_INPUT_IDS.campaign ) ),
			labelledControl( labels.utmTerm, textInput( UTM_INPUT_IDS.term ) ),
			labelledControl( labels.utmContent, textInput( UTM_INPUT_IDS.content ) )
		);

		body.append(
			labelledControlWithAction( labels.domain, domain, domainDashboard ),
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
		modal.querySelectorAll( '[data-lihi-dashboard-target]' ).forEach( ( button ) => {
			button.addEventListener( 'click', async ( event ) => {
				if ( ! activeModalTarget || typeof dashboardTargetHandler !== 'function' ) return;
				event.preventDefault();
				const config = lihiButton[ event.currentTarget.dataset.lihiDashboardTarget ] || {};
				if ( ! config.confirmMessage ) return;
				const { container } = activeModalTarget;

				try {
					await showConfirm( config.confirmMessage, {
						onConfirm: async () => {
							try {
								await dashboardTargetHandler( container, config );
							} catch ( error ) {
								closeConfirmModal();
								await showNotice( exceptionMessage( error ) );
							}

							return true;
						},
					} );
				} catch ( error ) {
					await showNotice( exceptionMessage( error ) );
				}
			} );
		} );
		modal.querySelector( idSelector( MODAL_IDS.tagAdd ) ).addEventListener( 'click', () => {
			addTagFromInput( modal.querySelector( idSelector( MODAL_IDS.tagInput ) ) );
		} );
		modal.querySelector( idSelector( MODAL_IDS.tagInput ) ).addEventListener( 'keydown', ( event ) => {
			if ( event.key !== 'Enter' ) return;
			event.preventDefault();
			addTagFromInput( event.currentTarget );
		} );
		modal.querySelector( '.lihi-tag-input' ).addEventListener( 'click', ( event ) => {
			if ( event.target instanceof Element && event.target.closest( '[data-lihi-remove-tag]' ) ) return;
			modal.querySelector( idSelector( MODAL_IDS.tagInput ) ).focus();
		} );
		modal.querySelector( idSelector( MODAL_IDS.tagSuggestions ) ).addEventListener( 'click', ( event ) => {
			if ( ! ( event.target instanceof Element ) ) return;
			const button = event.target.closest( '[data-lihi-recommended-tag]' );
			if ( ! button ) return;
			addTag( button.dataset.tag );
		} );
		modal.querySelector( idSelector( MODAL_IDS.tags ) ).addEventListener( 'click', ( event ) => {
			if ( ! ( event.target instanceof Element ) ) return;
			const remove = event.target.closest( '[data-lihi-remove-tag]' );
			if ( ! remove ) return;
			const chip = remove.closest( '[data-lihi-user-tag]' );
			if ( chip ) {
				chip.remove();
				syncRecommendedTags( modal );
			}
		} );

		return modal;
	}

	function closeCreateModal() {
		const modal = document.getElementById( MODAL_IDS.create );
		if ( modal ) hideModalElement( modal );
		activeModalTarget = null;
		dashboardTargetHandler = null;
	}

	function renderDomainOptions( domains ) {
		const select = document.getElementById( MODAL_IDS.domain );
		renderSelectOptions( select, domains );
	}

	function renderUtmSelectOptions( key, values ) {
		const select = document.getElementById( UTM_INPUT_IDS[ key ] );
		renderSelectOptions( select, values, {
			includeBlank: true,
			blankLabel: lihiButton.modal?.selectPlaceholder || 'Please select',
		} );
	}

	function renderUtmOptions( options = {} ) {
		renderUtmSelectOptions( 'source', options.source || [] );
		renderUtmSelectOptions( 'medium', options.medium || [] );
	}

	function renderOptionLoadingState() {
		renderSelectOptions( document.getElementById( MODAL_IDS.domain ), [], { loading: true } );
		renderSelectOptions( document.getElementById( UTM_INPUT_IDS.source ), [], { loading: true } );
		renderSelectOptions( document.getElementById( UTM_INPUT_IDS.medium ), [], { loading: true } );
	}

	function renderRecommendedTags( tags ) {
		const modal = ensureCreateModal();
		const wrapper = modal.querySelector( idSelector( MODAL_IDS.tagRecommendations ) );
		const container = modal.querySelector( idSelector( MODAL_IDS.tagSuggestions ) );
		const normalizedTags = uniqueTags( tags );

		wrapper.hidden = normalizedTags.length === 0;
		container.replaceChildren( ...normalizedTags.map( ( tag ) => {
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'lihi-tag-suggestion';
			button.dataset.lihiRecommendedTag = '';
			button.dataset.tag = tag;
			button.setAttribute( 'aria-label', ( lihiButton.modal?.addRecommendedTag || 'Add recommended tag' ) + ': ' + tag );
			button.setAttribute( 'aria-pressed', 'false' );
			button.textContent = tag;
			return button;
		} ) );

		syncRecommendedTags( modal );
	}

	function collectUserTags( modal ) {
		return Array.from( modal.querySelectorAll( '[data-lihi-user-tag]' ) )
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

	function syncRecommendedTags( modal ) {
		const selectedTags = new Set( collectUserTags( modal ) );
		modal.querySelectorAll( '[data-lihi-recommended-tag]' ).forEach( ( button ) => {
			const selected = selectedTags.has( button.dataset.tag );
			button.classList.toggle( 'is-selected', selected );
			button.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
			button.disabled = selected;
		} );
	}

	function addTag( tag ) {
		const normalizedTag = normalizeTag( tag );
		if ( ! normalizedTag ) return false;

		const modal = ensureCreateModal();
		if ( collectUserTags( modal ).includes( normalizedTag ) ) {
			syncRecommendedTags( modal );
			return false;
		}

		modal.querySelector( idSelector( MODAL_IDS.tags ) ).appendChild( renderUserTag( normalizedTag ) );
		syncRecommendedTags( modal );
		return true;
	}

	function addTagFromInput( input ) {
		addTag( input.value );
		input.value = '';
		input.focus();
	}

	function collectCreateModalPayload() {
		const modal = ensureCreateModal();
		if ( activeModalTarget && ! shouldShowUtmFields( activeModalTarget.container ) ) {
			return {
				domain: modal.querySelector( idSelector( MODAL_IDS.domain ) ).value,
				tags: collectUserTags( modal ),
				utm: blankUtmPayload(),
			};
		}

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

	async function openCreateModal( container, btn, onSubmit, onDashboardTarget = null ) {
		const modal = ensureCreateModal();
		const submit = modal.querySelector( idSelector( MODAL_IDS.submit ) );

		createHandler = typeof onSubmit === 'function' ? onSubmit : null;
		dashboardTargetHandler = typeof onDashboardTarget === 'function' ? onDashboardTarget : null;
		activeModalTarget = { container, btn };
		setUtmFieldsVisible( shouldShowUtmFields( container ) );
		modal.querySelector( idSelector( MODAL_IDS.tagInput ) ).value = '';
		modal.querySelector( idSelector( MODAL_IDS.tags ) ).replaceChildren();
		UTM_KEYS.forEach( ( key ) => {
			modal.querySelector( idSelector( UTM_INPUT_IDS[ key ] ) ).value = '';
		} );
		renderRecommendedTags( recommendedTagsForContainer( container ) );
		renderOptionLoadingState();
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
		renderDomainOptions( domains );
		renderUtmOptions( data.data.utm_options || {} );
		if ( domains.length === 0 ) {
			await showNotice( lihiButton.modal.noDomains );
			return;
		}

		submit.disabled = false;
	}

	window.LihiButtonModal = Object.freeze( {
		closeConfirmModal,
		openCreateModal,
		showConfirm,
		showNotice,
	} );
}() );
