/**
 * Dropzone — a file drop area with a "Choose file" button (.cv-dropzone).
 *
 * The component checks what was dropped or chosen and hands the accepted
 * files to `onFiles( files )`; it never reads them. Files that fail a check
 * go to `onReject( rejections )` as [ { file, reason, message } ] with reason
 * 'type' (not in `accept`), 'size' (over `maxSize` bytes) or 'count' (more
 * than one file without `multiple`), and their messages are listed under the
 * button until the next pick.
 *
 * accept uses the file input's syntax: extensions and MIME types, comma
 * separated ('.csv,text/csv', 'image/*'). Include the extension: browsers
 * report some types inconsistently (a CSV is often application/vnd.ms-excel).
 *
 * The area is the drop target and the button opens the file picker, for
 * keyboard and pointer alike. `help` is a line under the label (accepted
 * types, size limit), read with the button. Messages are replaceable through
 * `messages` for translation: { type( file ), size( file, maxSize ), count( total ) }.
 * Other props go to the root.
 */
import { useId, useRef, useState } from '@wordpress/element';
import { Button } from './Button';
import { classes } from './classes';

/** A size for people: 1.5 MB, 200 KB. */
function formatFileSize( bytes ) {
	const units = [ 'B', 'KB', 'MB', 'GB' ];
	let size = bytes;
	let unit = 0;
	while ( size >= 1024 && unit < units.length - 1 ) {
		size /= 1024;
		unit += 1;
	}
	return `${ new Intl.NumberFormat( undefined, { maximumFractionDigits: unit ? 1 : 0 } ).format( size ) } ${ units[ unit ] }`;
}

const DEFAULT_MESSAGES = {
	type: ( file ) => `${ file.name } is not a supported file type.`,
	size: ( file, maxSize ) => `${ file.name } is larger than ${ formatFileSize( maxSize ) }.`,
	count: () => 'Choose one file at a time.',
};

function accepts( file, accept ) {
	if ( ! accept ) {
		return true;
	}
	const name = ( file.name || '' ).toLowerCase();
	const type = ( file.type || '' ).toLowerCase();
	return accept
		.split( ',' )
		.map( ( token ) => token.trim().toLowerCase() )
		.filter( Boolean )
		.some( ( token ) => {
			if ( token.startsWith( '.' ) ) {
				return name.endsWith( token );
			}
			if ( token.endsWith( '/*' ) ) {
				return type.startsWith( token.slice( 0, -1 ) );
			}
			return type === token;
		} );
}

export function Dropzone( {
	onFiles,
	onReject,
	accept,
	maxSize,
	multiple = false,
	disabled = false,
	label,
	help,
	buttonLabel = 'Choose file',
	messages = {},
	className = '',
	...rest
} ) {
	const input = useRef( null );
	const depth = useRef( 0 );
	const [ dragging, setDragging ] = useState( false );
	const [ rejected, setRejected ] = useState( [] );
	const labelId = useId();
	const helpId = useId();
	const text = { ...DEFAULT_MESSAGES, ...messages };

	const receive = ( list ) => {
		const files = Array.from( list || [] );
		if ( disabled || ! files.length ) {
			return;
		}
		const rejections = [];
		const accepted = [];
		if ( ! multiple && files.length > 1 ) {
			const message = text.count( files.length );
			files.forEach( ( file ) => rejections.push( { file, reason: 'count', message } ) );
		} else {
			files.forEach( ( file ) => {
				if ( ! accepts( file, accept ) ) {
					rejections.push( { file, reason: 'type', message: text.type( file ) } );
				} else if ( maxSize > 0 && file.size > maxSize ) {
					rejections.push( { file, reason: 'size', message: text.size( file, maxSize ) } );
				} else {
					accepted.push( file );
				}
			} );
		}
		setRejected( rejections );
		if ( rejections.length && onReject ) {
			onReject( rejections );
		}
		if ( accepted.length && onFiles ) {
			onFiles( accepted );
		}
	};

	const choose = () => {
		if ( ! disabled ) {
			input.current?.click();
		}
	};

	// A file dropped next to the area would make the browser open it and leave
	// the screen, so every drag over the area is claimed, even while disabled.
	const onDragOver = ( event ) => {
		event.preventDefault();
		if ( event.dataTransfer ) {
			event.dataTransfer.dropEffect = disabled ? 'none' : 'copy';
		}
	};
	const onDragEnter = ( event ) => {
		event.preventDefault();
		depth.current += 1;
		if ( ! disabled ) {
			setDragging( true );
		}
	};
	const onDragLeave = () => {
		// Enter and leave fire for every child; the area is left when the count returns to zero.
		depth.current = Math.max( 0, depth.current - 1 );
		if ( 0 === depth.current ) {
			setDragging( false );
		}
	};
	const onDrop = ( event ) => {
		event.preventDefault();
		depth.current = 0;
		setDragging( false );
		receive( event.dataTransfer?.files );
	};

	const messagesShown = [ ...new Set( rejected.map( ( item ) => item.message ) ) ];

	return (
		<div
			{ ...rest }
			className={ classes( 'cv-dropzone', dragging && 'is-active', disabled && 'is-disabled', className ) }
			role="group"
			aria-labelledby={ label ? labelId : undefined }
			aria-disabled={ disabled || undefined }
			onDragEnter={ onDragEnter }
			onDragOver={ onDragOver }
			onDragLeave={ onDragLeave }
			onDrop={ onDrop }
		>
			{ label && <p className="cv-dropzone__label" id={ labelId }>{ label }</p> }
			{ help && <p className="cv-dropzone__help" id={ helpId }>{ help }</p> }
			<Button size="sm" onClick={ choose } disabled={ disabled } aria-describedby={ help ? helpId : undefined }>
				{ buttonLabel }
			</Button>
			<input
				ref={ input }
				type="file"
				hidden
				tabIndex={ -1 }
				accept={ accept }
				multiple={ multiple }
				disabled={ disabled }
				onChange={ ( event ) => {
					receive( event.target.files );
					// The same file chosen again must fire change again.
					event.target.value = '';
				} }
			/>
			<div className="cv-dropzone__errors" role="alert">
				{ messagesShown.map( ( message ) => (
					<p key={ message } className="cv-error-msg">{ message }</p>
				) ) }
			</div>
		</div>
	);
}
