/**
 * @typedef {import("web-vitals").LCPMetric} LCPMetric
 * @typedef {import("types.d.ts").ElementData} ElementData
 * @typedef {import("types.d.ts").URLMetric} URLMetric
 * @typedef {import("types.d.ts").URLMetricGroupStatus} URLMetricGroupStatus
 * @typedef {import("types.d.ts").Extension} Extension
 */

const win = window;
const doc = win.document;

const consoleLogPrefix = '[Optimization Detective]';

const storageLockTimeSessionKey = 'odStorageLockTime';

/**
 * Checks whether storage is locked.
 *
 * @param {number} currentTime    - Current time in milliseconds.
 * @param {number} storageLockTTL - Storage lock TTL in seconds.
 * @return {boolean} Whether storage is locked.
 */
function isStorageLocked( currentTime, storageLockTTL ) {
	if ( storageLockTTL === 0 ) {
		return false;
	}

	try {
		const storageLockTime = parseInt(
			sessionStorage.getItem( storageLockTimeSessionKey )
		);
		return (
			! isNaN( storageLockTime ) &&
			currentTime < storageLockTime + storageLockTTL * 1000
		);
	} catch ( e ) {
		return false;
	}
}

/**
 * Set the storage lock.
 *
 * @param {number} currentTime - Current time in milliseconds.
 */
function setStorageLock( currentTime ) {
	try {
		sessionStorage.setItem(
			storageLockTimeSessionKey,
			String( currentTime )
		);
	} catch ( e ) {}
}

/**
 * Log a message.
 *
 * @param {...*} message
 */
function log( ...message ) {
	// eslint-disable-next-line no-console
	console.log( consoleLogPrefix, ...message );
}

/**
 * Log a warning.
 *
 * @param {...*} message
 */
function warn( ...message ) {
	// eslint-disable-next-line no-console
	console.warn( consoleLogPrefix, ...message );
}

/**
 * Log an error.
 *
 * @param {...*} message
 */
function error( ...message ) {
	// eslint-disable-next-line no-console
	console.error( consoleLogPrefix, ...message );
}

/**
 * Checks whether the URL metric(s) for the provided viewport width is needed.
 *
 * @param {number}                 viewportWidth          - Current viewport width.
 * @param {URLMetricGroupStatus[]} urlMetricGroupStatuses - Viewport group statuses.
 * @return {boolean} Whether URL metrics are needed.
 */
function isViewportNeeded( viewportWidth, urlMetricGroupStatuses ) {
	let lastWasLacking = false;
	for ( const { minimumViewportWidth, complete } of urlMetricGroupStatuses ) {
		if ( viewportWidth >= minimumViewportWidth ) {
			lastWasLacking = ! complete;
		} else {
			break;
		}
	}
	return lastWasLacking;
}

/**
 * Gets the current time in milliseconds.
 *
 * @return {number} Current time in milliseconds.
 */
function getCurrentTime() {
	return Date.now();
}

/**
 * Recursively freezes an object to prevent mutation.
 *
 * @param {Object} obj Object to recursively freeze.
 */
function recursiveFreeze( obj ) {
	for ( const prop of Object.getOwnPropertyNames( obj ) ) {
		const value = obj[ prop ];
		if ( null !== value && typeof value === 'object' ) {
			recursiveFreeze( value );
		}
	}
	Object.freeze( obj );
}

/**
 * Mapping of XPath to element data.
 *
 * @type {Map<string, ElementData>}
 */
const elementsByXPath = new Map();

/**
 * Gets element data.
 *
 * @param {string} xpath XPath.
 * @return {ElementData|null} Element data, or null if no element for the XPath exists.
 */
function getElementData( xpath ) {
	const elementData = elementsByXPath.get( xpath );
	if ( elementData ) {
		const cloned = structuredClone( elementData );
		recursiveFreeze( cloned );
		return cloned;
	}
	return null;
}

/**
 * Amends element data.
 *
 * @param {string} xpath      XPath.
 * @param {Object} properties Properties.
 * @return {boolean} Whether appending element data was successful.
 */
function amendElementData( xpath, properties ) {
	if ( ! elementsByXPath.has( xpath ) ) {
		return false;
	}
	const elementData = elementsByXPath.get( xpath );
	Object.assign( elementData, properties ); // TODO: Check if any core properties are being used.
	return true;
}

/**
 * Detects the LCP element, loaded images, client viewport and store for future optimizations.
 *
 * @param {Object}                 args                            Args.
 * @param {number}                 args.serveTime                  The serve time of the page in milliseconds from PHP via `microtime( true ) * 1000`.
 * @param {number}                 args.detectionTimeWindow        The number of milliseconds between now and when the page was first generated in which detection should proceed.
 * @param {string[]}               args.extensionModuleUrls        URLs for extension script modules to import.
 * @param {number}                 args.minViewportAspectRatio     Minimum aspect ratio allowed for the viewport.
 * @param {number}                 args.maxViewportAspectRatio     Maximum aspect ratio allowed for the viewport.
 * @param {boolean}                args.isDebug                    Whether to show debug messages.
 * @param {string}                 args.restApiEndpoint            URL for where to send the detection data.
 * @param {string}                 args.restApiNonce               Nonce for writing to the REST API.
 * @param {string}                 args.currentUrl                 Current URL.
 * @param {string}                 args.urlMetricSlug              Slug for URL metric.
 * @param {string}                 args.urlMetricNonce             Nonce for URL metric storage.
 * @param {URLMetricGroupStatus[]} args.urlMetricGroupStatuses     URL metric group statuses.
 * @param {number}                 args.storageLockTTL             The TTL (in seconds) for the URL metric storage lock.
 * @param {string}                 args.webVitalsLibrarySrc        The URL for the web-vitals library.
 * @param {Object}                 [args.urlMetricGroupCollection] URL metric group collection, when in debug mode.
 */
export default async function detect( {
	serveTime,
	detectionTimeWindow,
	minViewportAspectRatio,
	maxViewportAspectRatio,
	isDebug,
	extensionModuleUrls,
	restApiEndpoint,
	restApiNonce,
	currentUrl,
	urlMetricSlug,
	urlMetricNonce,
	urlMetricGroupStatuses,
	storageLockTTL,
	webVitalsLibrarySrc,
	urlMetricGroupCollection,
} ) {
	const currentTime = getCurrentTime();

	if ( isDebug ) {
		log( 'Stored URL metric group collection:', urlMetricGroupCollection );
	}

	// Abort running detection logic if it was served in a cached page.
	if ( currentTime - serveTime > detectionTimeWindow ) {
		if ( isDebug ) {
			warn(
				'Aborted detection due to being outside detection time window.'
			);
		}
		return;
	}

	// Abort if the current viewport is not among those which need URL metrics.
	if ( ! isViewportNeeded( win.innerWidth, urlMetricGroupStatuses ) ) {
		if ( isDebug ) {
			log( 'No need for URL metrics from the current viewport.' );
		}
		return;
	}

	// Abort if the viewport aspect ratio is not in a common range.
	const aspectRatio = win.innerWidth / win.innerHeight;
	if (
		aspectRatio < minViewportAspectRatio ||
		aspectRatio > maxViewportAspectRatio
	) {
		if ( isDebug ) {
			warn(
				`Viewport aspect ratio (${ aspectRatio }) is not in the accepted range of ${ minViewportAspectRatio } to ${ maxViewportAspectRatio }.`
			);
		}
		return;
	}

	// Ensure the DOM is loaded (although it surely already is since we're executing in a module).
	await new Promise( ( resolve ) => {
		if ( doc.readyState !== 'loading' ) {
			resolve();
		} else {
			doc.addEventListener( 'DOMContentLoaded', resolve, { once: true } );
		}
	} );

	// Wait until the resources on the page have fully loaded.
	await new Promise( ( resolve ) => {
		if ( doc.readyState === 'complete' ) {
			resolve();
		} else {
			win.addEventListener( 'load', resolve, { once: true } );
		}
	} );

	// Wait yet further until idle.
	if ( typeof requestIdleCallback === 'function' ) {
		await new Promise( ( resolve ) => {
			requestIdleCallback( resolve );
		} );
	}

	// TODO: Does this make sense here? Should it be moved up above the isViewportNeeded condition?
	// As an alternative to this, the od_print_detection_script() function can short-circuit if the
	// od_is_url_metric_storage_locked() function returns true. However, the downside with that is page caching could
	// result in metrics missed from being gathered when a user navigates around a site and primes the page cache.
	if ( isStorageLocked( currentTime, storageLockTTL ) ) {
		if ( isDebug ) {
			warn( 'Aborted detection due to storage being locked.' );
		}
		return;
	}

	// TODO: Does this make sense here?
	// Prevent detection when page is not scrolled to the initial viewport.
	if ( doc.documentElement.scrollTop > 0 ) {
		if ( isDebug ) {
			warn(
				'Aborted detection since initial scroll position of page is not at the top.'
			);
		}
		return;
	}

	if ( isDebug ) {
		log( 'Proceeding with detection' );
	}

	/** @type {Extension[]} */
	const extensions = [];
	for ( const extensionModuleUrl of extensionModuleUrls ) {
		const extension = await import( extensionModuleUrl );
		extensions.push( extension );
		// TODO: There should to be a way to pass additional args into the module. Perhaps extensionModuleUrls should be a mapping of URLs to args. It's important to pass webVitalsLibrarySrc to the extension so that onLCP, onCLS, or onINP can be obtained.
		// TODO: Pass additional functions from this module into the extensions.
		if ( extension.initialize instanceof Function ) {
			extension.initialize( { isDebug } );
		}
	}

	const breadcrumbedElements = doc.body.querySelectorAll( '[data-od-xpath]' );

	/** @type {Map<HTMLElement, string>} */
	const breadcrumbedElementsMap = new Map(
		[ ...breadcrumbedElements ].map(
			/**
			 * @param {HTMLElement} element
			 * @return {[HTMLElement, string]} Tuple of element and its XPath.
			 */
			( element ) => [ element, element.dataset.odXpath ]
		)
	);

	/** @type {IntersectionObserverEntry[]} */
	const elementIntersections = [];

	/** @type {?IntersectionObserver} */
	let intersectionObserver;

	function disconnectIntersectionObserver() {
		if ( intersectionObserver instanceof IntersectionObserver ) {
			intersectionObserver.disconnect();
			win.removeEventListener( 'scroll', disconnectIntersectionObserver ); // Clean up, even though this is registered with once:true.
		}
	}

	// Wait for the intersection observer to report back on the initially-visible elements.
	// Note that the first callback will include _all_ observed entries per <https://github.com/w3c/IntersectionObserver/issues/476>.
	if ( breadcrumbedElementsMap.size > 0 ) {
		await new Promise( ( resolve ) => {
			intersectionObserver = new IntersectionObserver(
				( entries ) => {
					for ( const entry of entries ) {
						elementIntersections.push( entry );
					}
					resolve();
				},
				{
					root: null, // To watch for intersection relative to the device's viewport.
					threshold: 0.0, // As soon as even one pixel is visible.
				}
			);

			for ( const element of breadcrumbedElementsMap.keys() ) {
				intersectionObserver.observe( element );
			}
		} );

		// Stop observing as soon as the page scrolls since we only want initial-viewport elements.
		win.addEventListener( 'scroll', disconnectIntersectionObserver, {
			once: true,
			passive: true,
		} );
	}

	const { onLCP } = await import( webVitalsLibrarySrc );

	/** @type {LCPMetric[]} */
	const lcpMetricCandidates = [];

	// Obtain at least one LCP candidate. More may be reported before the page finishes loading.
	await new Promise( ( resolve ) => {
		onLCP(
			( metric ) => {
				lcpMetricCandidates.push( metric );
				resolve();
			},
			{
				// This avoids needing to click to finalize LCP candidate. While this is helpful for testing, it also
				// ensures that we always get an LCP candidate reported. Otherwise, the callback may never fire if the
				// user never does a click or keydown, per <https://github.com/GoogleChrome/web-vitals/blob/07f6f96/src/onLCP.ts#L99-L107>.
				reportAllChanges: true,
			}
		);
	} );

	// Stop observing.
	disconnectIntersectionObserver();
	if ( isDebug ) {
		log( 'Detection is stopping.' );
	}

	/** @type {URLMetric} */
	const urlMetric = {
		url: currentUrl,
		viewport: {
			width: win.innerWidth,
			height: win.innerHeight,
		},
		elements: [],
	};

	const lcpMetric = lcpMetricCandidates.at( -1 );

	for ( const elementIntersection of elementIntersections ) {
		const xpath = breadcrumbedElementsMap.get( elementIntersection.target );
		if ( ! xpath ) {
			if ( isDebug ) {
				error( 'Unable to look up XPath for element' );
			}
			continue;
		}

		const isLCP =
			elementIntersection.target === lcpMetric?.entries[ 0 ]?.element;

		/** @type {ElementData} */
		const elementData = {
			isLCP,
			isLCPCandidate: !! lcpMetricCandidates.find(
				( lcpMetricCandidate ) =>
					lcpMetricCandidate.entries[ 0 ]?.element ===
					elementIntersection.target
			),
			xpath,
			intersectionRatio: elementIntersection.intersectionRatio,
			intersectionRect: elementIntersection.intersectionRect,
			boundingClientRect: elementIntersection.boundingClientRect,
		};

		urlMetric.elements.push( elementData );
		elementsByXPath.set( elementData.xpath, elementData );
	}

	if ( isDebug ) {
		log( 'Current URL metric:', urlMetric );
	}

	// Wait for the page to be hidden.
	await new Promise( ( resolve ) => {
		win.addEventListener( 'pagehide', resolve, { once: true } );
		win.addEventListener( 'pageswap', resolve, { once: true } );
		doc.addEventListener(
			'visibilitychange',
			() => {
				if ( document.visibilityState === 'hidden' ) {
					// TODO: This will fire even when switching tabs.
					resolve();
				}
			},
			{ once: true }
		);
	} );

	if ( extensions.length > 0 ) {
		/**
		 * Gets root URL Metric data.
		 *
		 * @return {URLMetric} URL Metric.
		 */
		const getRootData = () => {
			const immutableUrlMetric = structuredClone( urlMetric );
			recursiveFreeze( immutableUrlMetric );
			return immutableUrlMetric;
		};

		/**
		 * Amends root URL metric data.
		 *
		 * @param {Object} properties
		 */
		const amendRootData = ( properties ) => {
			Object.assign( urlMetric, properties ); // TODO: Prevent overriding core properties.
		};

		for ( const extension of extensions ) {
			if ( extension.finalize instanceof Function ) {
				extension.finalize( {
					isDebug,
					getRootData,
					getElementData,
					amendElementData,
					amendRootData,
				} );
			}
		}
	}

	// Even though the server may reject the REST API request, we still have to set the storage lock
	// because we can't look at the response when sending a beacon.
	setStorageLock( getCurrentTime() );

	if ( isDebug ) {
		log( 'Sending URL metric:', urlMetric );
	}

	const url = new URL( restApiEndpoint );
	url.searchParams.set( '_wpnonce', restApiNonce );
	url.searchParams.set( 'slug', urlMetricSlug );
	url.searchParams.set( 'nonce', urlMetricNonce );
	navigator.sendBeacon(
		url,
		new Blob( [ JSON.stringify( urlMetric ) ], {
			type: 'application/json',
		} )
	);

	// Clean up.
	breadcrumbedElementsMap.clear();
}
