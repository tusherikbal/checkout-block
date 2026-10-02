import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from '../../block/block.json';
import Edit from './edit';
import { applyInputTypes } from '../input-types';
import { watchEditorCanvas } from '../styles';
import './step-panel';
import './editor.scss';

applyInputTypes();
watchEditorCanvas();

registerBlockType( metadata, {
	edit: Edit,
	save: () => <div { ...useBlockProps.save() } />,
} );
