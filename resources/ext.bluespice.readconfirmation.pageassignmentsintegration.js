( ( mw, bs ) => {

	const isReadConfirmationNS = ( ns ) => {
		const namespaces = mw.config.get( 'bsgReadConfirmationActivatedNamespaces', [] );
		return ( namespaces.some( ( namespace ) => namespace == ns ) ); // eslint-disable-line eqeqeq
	};

	const showDialog = ( pageId, pageTitle ) => {
		const dialog = new OOJSPlus.ui.dialog.BookletDialog( {
			id: 'bs-readconfirmation-user-list',
			pages: async () => {
				await mw.loader.using( 'ext.readconfirmation.dialog.pages' );
				return new bs.readconfirmation.ui.ReadConfirmationPage( {
					data: {
						page: pageTitle,
						pageId: pageId
					}
				} );
			}
		} );
		dialog.show();
	};

	mw.hook( 'BSPageAssignmentsManagerPanelInit' ).add( ( gridCfg ) => {
		gridCfg.columns.all_assignees_have_read = { // eslint-disable-line camelcase
			headerText: mw.message( 'bs-readconfirmation-column-read' ).plain(),
			type: 'text',
			sortable: true,
			filter: { type: 'boolean' },
			valueParser: ( val, row ) => {
				if ( isReadConfirmationNS( row.page_namespace ) ) {
					return new OO.ui.HtmlSnippet( mw.html.element(
						'span',
						{
							class: 'oo-ui-widget oo-ui-widget-enabled oo-ui-iconElement oo-ui-iconElement-icon oo-ui-labelElement-invisible oo-ui-iconWidget ' +
							( val ? 'oo-ui-icon-check' : 'oo-ui-icon-close' )
						}
					) );
				}
				return mw.message( 'bs-readconfirmation-disabled-ns-short' ).plain();
			}
		};
		gridCfg.actions.readConfirmationLog = {
			headerText: mw.message( 'bs-readconfirmation-action-log' ).text(),
			title: mw.message( 'bs-readconfirmation-action-log' ).text(),
			type: 'action',
			actionId: 'readConfirmationLog',
			icon: 'article',
			invisibleHeader: true,
			width: 30,
			doActionOnRow: ( row ) => {
				window.location.href = mw.util.getUrl(
					'Special:Log', {
						page: row.page_prefixedtext,
						type: 'bs-readconfirmation'
					}
				);
			}
		};
		gridCfg.actions.readConfirmationRemind = {
			headerText: mw.message( 'bs-readconfirmation-action-remind' ).text(),
			title: mw.message( 'bs-readconfirmation-action-remind' ).text(),
			type: 'action',
			actionId: 'readConfirmationRemind',
			icon: 'bell',
			invisibleHeader: true,
			width: 30,
			doActionOnRow: ( row ) => {
				bs.util.confirm( 'bs-rc', {
					textMsg: 'bs-readconfirmation-action-remind-confirm'
				}, {
					ok: () => {
						bs.api.tasks.execSilent( 'readconfirmation', 'remind', {
							pageId: row.page_id
						} );
					}
				} );
			}
		};
		if ( mw.config.get( 'bsReadConfirmationsViewRight' ) ) {
			gridCfg.actions.readConfirmationView = {
				headerText: mw.message( 'bs-readconfirmation-view-confirmations' ).text(),
				title: mw.message( 'bs-readconfirmation-view-confirmations' ).text(),
				type: 'action',
				actionId: 'readConfirmationView',
				icon: 'eye',
				invisibleHeader: true,
				width: 30,
				doActionOnRow: ( row ) => {
					const pageId = row.page_id;
					const pageTitle = row.page_title;
					showDialog( pageId, pageTitle );
				}
			};
		}
	} );

	mw.hook( 'BSPageAssignmentsOverviewPanelInit' ).add( ( gridCfg ) => {
		gridCfg.columns.read_confirmation = { // eslint-disable-line camelcase
			headerText: mw.message( 'bs-readconfirmation-column-read-at' ).plain(),
			type: 'text',
			sortable: true,
			filter: { type: 'date' },
			valueParser: ( val ) => {
				if ( !val ) {
					return mw.message( 'bs-readconfirmation-not-read' ).plain();
				}
				if ( val === 'disabled' ) {
					return mw.message( 'bs-readconfirmation-disabled-ns' ).plain();
				}

				const date = Ext.Date.parse( val, 'YmdHis' );
				const dateRenderer = Ext.util.Format.dateRenderer( 'Y-m-d, H:i' );

				return dateRenderer( date );
			}
		};
	} );
} )( mediaWiki, blueSpice );
