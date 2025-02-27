const mustache = require( 'mustache' );
const fs = require( 'fs' );
const tableOfContentsTemplate = fs.readFileSync( 'includes/templates/TableOfContents.mustache', 'utf8' );
const tableOfContentsContentsTemplate = fs.readFileSync( 'includes/templates/TableOfContents__list.mustache', 'utf8' );
const tableOfContentsLineTemplate = fs.readFileSync( 'includes/templates/TableOfContents__line.mustache', 'utf8' );
const pinnableElementOpenTemplate = fs.readFileSync( 'includes/templates/PinnableElement/Open.mustache', 'utf8' );
const pinnableElementCloseTemplate = fs.readFileSync( 'includes/templates/PinnableElement/Close.mustache', 'utf8' );
const pinnableHeaderTemplate = fs.readFileSync( 'includes/templates/PinnableHeader.mustache', 'utf8' );
const initTableOfContents = require( '../../resources/skins.vector.es6/tableOfContents.js' );

let /** @type {HTMLElement} */ container,
	/** @type {HTMLElement} */ fooSection,
	/** @type {HTMLElement} */ barSection,
	/** @type {HTMLElement} */ bazSection,
	/** @type {HTMLElement} */ quxSection,
	/** @type {HTMLElement} */ quuxSection;
const onHeadingClick = jest.fn();
const onToggleClick = jest.fn();
const onTogglePinned = jest.fn();

const SECTIONS = [
	{
		toclevel: 1,
		number: '1',
		line: 'foo',
		anchor: 'foo',
		'is-top-level-section': true,
		'is-parent-section': false,
		'array-sections': null
	}, {
		toclevel: 1,
		number: '2',
		line: 'bar',
		anchor: 'bar',
		'is-top-level-section': true,
		'is-parent-section': true,
		'vector-button-label': 'Toggle bar subsection',
		'array-sections': [ {
			toclevel: 2,
			number: '2.1',
			line: 'baz',
			anchor: 'baz',
			'is-top-level-section': false,
			'is-parent-section': true,
			'array-sections': [ {
				toclevel: 3,
				number: '2.1.1',
				line: 'qux',
				anchor: 'qux',
				'is-top-level-section': false,
				'is-parent-section': false,
				'array-sections': null
			} ]
		} ]
	}, {
		toclevel: 1,
		number: '3',
		line: 'quux',
		anchor: 'quux',
		'is-top-level-section': true,
		'is-parent-section': false,
		'array-sections': null
	}
];

/**
 * @param {Object} templateProps
 * @return {string}
 */
function render( templateProps = {} ) {
	const templateData = Object.assign( {
		'is-vector-toc-beginning-enabled': true,
		'msg-vector-toc-beginning': 'Beginning',
		'vector-is-collapse-sections-enabled': false,
		'array-sections': SECTIONS,
		id: 'vector-toc',
		'data-pinnable-header': {
			'is-pinned': true,
			label: 'Contents',
			'label-tag-name': 'h2',
			'pin-label': 'move to sidebar',
			'unpin-label': 'hide',
			'data-name': 'vector-toc'
		}
	}, templateProps );

	return mustache.render( tableOfContentsTemplate, templateData, {
		'PinnableElement/Open': pinnableElementOpenTemplate,
		'PinnableElement/Close': pinnableElementCloseTemplate,
		PinnableHeader: pinnableHeaderTemplate,
		TableOfContents__list: tableOfContentsContentsTemplate, // eslint-disable-line camelcase
		TableOfContents__line: tableOfContentsLineTemplate // eslint-disable-line camelcase
	} );
}

/**
 * @param {Object} templateProps
 * @return {module:TableOfContents~TableOfContents}
 */
function mount( templateProps = {} ) {
	document.body.innerHTML = render( templateProps );

	container = /** @type {HTMLElement} */ ( document.getElementById( 'vector-toc' ) );
	fooSection = /** @type {HTMLElement} */ ( document.getElementById( 'toc-foo' ) );
	barSection = /** @type {HTMLElement} */ ( document.getElementById( 'toc-bar' ) );
	bazSection = /** @type {HTMLElement} */ ( document.getElementById( 'toc-baz' ) );
	quxSection = /** @type {HTMLElement} */ ( document.getElementById( 'toc-qux' ) );
	quuxSection = /** @type {HTMLElement} */ ( document.getElementById( 'toc-quux' ) );

	return initTableOfContents( {
		container,
		onHeadingClick,
		onToggleClick,
		onTogglePinned
	} );
}

describe( 'Table of contents', () => {
	beforeEach( () => {
		// @ts-ignore
		global.window.matchMedia = jest.fn( () => ( {} ) );
	} );

	describe( 'renders', () => {
		test( 'when `vector-is-collapse-sections-enabled` is false', () => {
			const toc = mount();
			expect( document.body.innerHTML ).toMatchSnapshot();
			expect( barSection.classList.contains( toc.EXPANDED_SECTION_CLASS ) ).toEqual( true );
		} );
		test( 'when `vector-is-collapse-sections-enabled` is true', () => {
			const toc = mount( { 'vector-is-collapse-sections-enabled': true } );
			expect( document.body.innerHTML ).toMatchSnapshot();
			expect( barSection.classList.contains( toc.EXPANDED_SECTION_CLASS ) ).toEqual( false );
		} );
		test( 'toggles for top level parent sections', () => {
			const toc = mount();
			expect( fooSection.getElementsByClassName( toc.TOGGLE_CLASS ).length ).toEqual( 0 );
			expect( barSection.getElementsByClassName( toc.TOGGLE_CLASS ).length ).toEqual( 1 );
			expect( bazSection.getElementsByClassName( toc.TOGGLE_CLASS ).length ).toEqual( 0 );
			expect( quxSection.getElementsByClassName( toc.TOGGLE_CLASS ).length ).toEqual( 0 );
			expect( quuxSection.getElementsByClassName( toc.TOGGLE_CLASS ).length ).toEqual( 0 );
		} );
	} );

	describe( 'binds event listeners', () => {
		test( 'for onHeadingClick', () => {
			const toc = mount();
			const heading = /** @type {HTMLElement} */ ( document.querySelector( `#toc-foo .${toc.LINK_CLASS}` ) );
			heading.click();

			expect( onToggleClick ).not.toBeCalled();
			expect( onHeadingClick ).toBeCalled();
		} );
		test( 'for onToggleClick', () => {
			const toc = mount();
			const toggle = /** @type {HTMLElement} */ ( document.querySelector( `#toc-bar .${toc.TOGGLE_CLASS}` ) );
			toggle.click();

			expect( onHeadingClick ).not.toBeCalled();
			expect( onToggleClick ).toBeCalled();
		} );
	} );

	describe( 'applies correct classes', () => {
		test( 'when changing active sections', () => {
			const toc = mount( { 'vector-is-collapse-sections-enabled': true } );
			let activeSections;
			let activeTopSections;

			/**
			 * @param {string} id
			 * @param {HTMLElement} activeSection
			 * @param {HTMLElement} activeTopSection
			 */
			function testActiveClasses( id, activeSection, activeTopSection ) {
				toc.changeActiveSection( id );
				activeSections = container.querySelectorAll( `.${toc.ACTIVE_SECTION_CLASS}` );
				activeTopSections = container.querySelectorAll( `.${toc.ACTIVE_TOP_SECTION_CLASS}` );
				expect( activeSections.length ).toEqual( 1 );
				expect( activeTopSections.length ).toEqual( 1 );
				expect( activeSections[ 0 ] ).toEqual( activeSection );
				expect( activeTopSections[ 0 ] ).toEqual( activeTopSection );
			}

			testActiveClasses( 'toc-foo', fooSection, fooSection );
			testActiveClasses( 'toc-bar', barSection, barSection );
			testActiveClasses( 'toc-baz', bazSection, barSection );
			testActiveClasses( 'toc-qux', quxSection, barSection );
			testActiveClasses( 'toc-quux', quuxSection, quuxSection );
		} );

		test( 'when expanding sections', () => {
			const toc = mount();
			toc.expandSection( 'toc-bar' );
			expect( barSection.classList.contains( toc.EXPANDED_SECTION_CLASS ) ).toEqual( true );
		} );

		test( 'when toggling sections', () => {
			const toc = mount();
			toc.toggleExpandSection( 'toc-bar' );
			expect( barSection.classList.contains( toc.EXPANDED_SECTION_CLASS ) ).toEqual( false );
			toc.toggleExpandSection( 'toc-bar' );
			expect( barSection.classList.contains( toc.EXPANDED_SECTION_CLASS ) ).toEqual( true );
		} );
	} );

	describe( 'applies the correct aria attributes', () => {
		test( 'when initialized', () => {
			const toc = mount();
			const toggleButton = /** @type {HTMLElement} */ ( barSection.querySelector( `.${toc.TOGGLE_CLASS}` ) );

			expect( toggleButton.getAttribute( 'aria-expanded' ) ).toEqual( 'true' );
		} );

		test( 'when expanding sections', () => {
			const toc = mount();
			const toggleButton = /** @type {HTMLElement} */ ( barSection.querySelector( `.${toc.TOGGLE_CLASS}` ) );

			toc.expandSection( 'toc-bar' );
			expect( toggleButton.getAttribute( 'aria-expanded' ) ).toEqual( 'true' );
		} );

		test( 'when toggling sections', () => {
			const toc = mount();
			const toggleButton = /** @type {HTMLElement} */ ( barSection.querySelector( `.${toc.TOGGLE_CLASS}` ) );

			toc.toggleExpandSection( 'toc-bar' );
			expect( toggleButton.getAttribute( 'aria-expanded' ) ).toEqual( 'false' );

			toc.toggleExpandSection( 'toc-bar' );
			expect( toggleButton.getAttribute( 'aria-expanded' ) ).toEqual( 'true' );
		} );
	} );

	describe( 'reloadTableOfContents', () => {
		test( 're-renders toc when wikipage.tableOfContents hook is fired with empty sections', async () => {
			const toc = mount();
			await toc.reloadTableOfContents( [] );

			expect( document.body.innerHTML ).toMatchSnapshot();
		} );

		test( 're-renders toc when wikipage.tableOfContents hook is fired with sections', async () => {
			// @ts-ignore
			// eslint-disable-next-line compat/compat
			jest.spyOn( mw.loader, 'using' ).mockImplementation( () => Promise.resolve() );
			// @ts-ignore
			mw.template.getCompiler = () => {};
			jest.spyOn( mw, 'message' ).mockImplementation( ( msg ) => {
				const msgFactory = ( /** @type {string} */ text ) => {
					return {
						parse: () => '',
						plain: () => '',
						escaped: () => '',
						exists: () => true,
						text: () => text
					};
				};
				switch ( msg ) {
					case 'vector-toc-beginning':
						return msgFactory( 'Beginning' );
					default:
						return msgFactory( '' );
				}

			} );
			// @ts-ignore
			jest.spyOn( mw.template, 'getCompiler' ).mockImplementation( () => {
				return {
					compile: () => {
						return {
							render: ( /** @type {Object} */ data ) => {
								return {
									html: () => {
										return render( data );
									}
								};
							}
						};
					}
				};
			} );

			const toc = mount();

			const toggleButton = /** @type {HTMLElement} */ ( barSection.querySelector( `.${toc.TOGGLE_CLASS}` ) );
			// Collapse section.
			toc.toggleExpandSection( 'toc-bar' );
			expect( toggleButton.getAttribute( 'aria-expanded' ) ).toEqual( 'false' );

			// wikipage.tableOfContents includes the nested sections in the top level
			// of the array.

			await toc.reloadTableOfContents( [
				// foo
				SECTIONS[ 0 ],
				// bar
				SECTIONS[ 1 ],
				// baz
				// @ts-ignore
				SECTIONS[ 1 ][ 'array-sections' ][ 0 ],
				// qux
				// @ts-ignore
				SECTIONS[ 1 ][ 'array-sections' ][ 0 ][ 'array-sections' ][ 0 ],
				// quux
				SECTIONS[ 2 ],
				// Add new section to see how the re-render performs.
				{
					toclevel: 1,
					number: '4',
					line: 'bat',
					anchor: 'bat',
					'is-top-level-section': true,
					'is-parent-section': false,
					'array-sections': null
				}
			] );

			const newToggleButton = /** @type {HTMLElement} */ ( document.querySelector( `#toc-bar .${toc.TOGGLE_CLASS}` ) );
			expect( newToggleButton ).not.toBeNull();
			// Check that the sections render in their expanded form.
			expect( newToggleButton.getAttribute( 'aria-expanded' ) ).toEqual( 'true' );

			// Verify newly rendered TOC html matches the expected html.
			expect( document.body.innerHTML ).toMatchSnapshot();
		} );
	} );
} );
