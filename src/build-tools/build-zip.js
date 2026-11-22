/**
 * Creates a clean release zip for Captain Live Chat Pro:
 * - Includes only production files (assets, build, includes, languages, main php, readme, uninstall, index)
 * - Wraps everything in top-level folder 'captain-live-chat-pro/'
 * - Strictly excludes src/, node_modules/, webpack.config.js, package*.json, git files, etc.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const AdmZip = require( 'adm-zip' );

const root = path.resolve( __dirname, '..', '..' );
const pkg = require( path.join( root, 'package.json' ) );
const pluginSlug = pkg.name || 'captain-live-chat-pro';
const zipName = `${ pluginSlug }.zip`;
const zipPath = path.join( root, zipName );

const zip = new AdmZip();

const allowedEntries = [
	'assets',
	'build',
	'includes',
	'languages',
	`${ pluginSlug }.php`,
	'index.php',
	'readme.txt',
	'uninstall.php',
];

function addEntry( relPath ) {
	const fullPath = path.join( root, relPath );
	if ( ! fs.existsSync( fullPath ) ) return;

	const stat = fs.statSync( fullPath );
	if ( stat.isDirectory() ) {
		const files = fs.readdirSync( fullPath );
		for ( const file of files ) {
			addEntry( path.join( relPath, file ) );
		}
	} else if ( stat.isFile() ) {
		const zipEntryPath = path.join( pluginSlug, relPath ).replace( /\\/g, '/' );
		const zipEntryDir = path.dirname( zipEntryPath );
		zip.addLocalFile( fullPath, zipEntryDir );
	}
}

for ( const entry of allowedEntries ) {
	addEntry( entry );
}

zip.writeZip( zipPath );
const stats = fs.statSync( zipPath );
console.log( `Created release archive: ${ zipName } (${ ( stats.size / 1024 ).toFixed( 1 ) } KB) with top-level folder '${ pluginSlug }/'.` );
