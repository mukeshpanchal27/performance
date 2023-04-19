/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );

exports.options = [
	{
		argname: '-s, --slug <slug>',
		description: 'Standalone plugin slug to get version from plugins.json',
	},
];

/**
 * Command to get the plugin version based on the slug.
 *
 * @param {Object} opt Command options.
 */
exports.handler = async ( opt ) => {
	doRunGetPluginVersion( {
		pluginsJsonFile: 'plugins.json', // Path to plugins.json file.
		slug: opt.slug, // Plugin slug.
	} );
};

/**
 * Returns the match plugin version from plugins.json file.
 *
 * @param {Object} settings Plugin settings.
 */
function doRunGetPluginVersion( settings ) {
	if ( settings.slug === undefined ) {
		log(
			formats.error(
				'A slug must be provided via the --slug (-s) argument.'
			)
		);
		return;
	}

	const pluginsFile = path.join( '.', settings.pluginsJsonFile );

	// Buffer contents of plugins JSON file.
	let pluginsFileContent = '';

	try {
		pluginsFileContent = fs.readFileSync( pluginsFile, 'utf-8' );
	} catch ( e ) {
		log(
			formats.error( `Error reading file at "${ pluginsFile }": ${ e }` )
		);

		// Return with exit code 1 to trigger a failure in the deploy standalone workflow pipeline.
		process.exit( 1 );
	}

	// Validate that the plugins JSON file contains content before proceeding.
	if (
		'' === pluginsFileContent ||
		! pluginsFileContent
	) {
		log(
			formats.error(
				`Contents of file at "${ pluginsFile }" could not be read, or are empty.`
			)
		);

		// Return with exit code 1 to trigger a failure in the deploy standalone workflow pipeline.
		process.exit( 1 );
	}

	const plugins = JSON.parse( pluginsFileContent );

	// Check for valid and not empty object resulting from plugins JSON file parse.
	if (
		'object' !== typeof plugins ||
		0 === Object.keys( plugins ).length
	) {
		log(
			formats.error(
				`File at "${ pluginsFile }" parsed, but detected empty/non valid JSON object.`
			)
		);

		// Return with exit code 1 to trigger a failure in the deploy standalone workflow pipeline.
		process.exit( 1 );
	}

	for ( const moduleDir in plugins ) {
		const pluginVersion = plugins[ moduleDir ]?.version;
		const pluginSlug = plugins[ moduleDir ]?.slug;
		if ( pluginVersion && pluginSlug && ( settings.slug === pluginSlug ) ) {
			return pluginVersion;
		}
	}

	log(
		formats.error(
			`The "${ settings.slug }" module slug is missing in the file "${ pluginsFile }".`
		)
	);

	// Return with exit code 1 to trigger a failure in the deploy standalone workflow pipeline.
	process.exit( 1 );
}
