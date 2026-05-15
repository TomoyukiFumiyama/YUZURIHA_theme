(function (blocks, blockEditor, components, element, i18n) {
	const { registerBlockType } = blocks;
	const { RichText, InspectorControls } = blockEditor;
	const { PanelBody, SelectControl, TextControl } = components;
	const { createElement: el, Fragment } = element;
	const { __ } = i18n;

	const speechControls = (attributes, setAttributes) => el(
		InspectorControls,
		null,
		el(
			PanelBody,
			{ title: __('Speech settings', 'yzrh'), initialOpen: true },
			el(TextControl, {
				label: __('Speaker initials', 'yzrh'),
				value: attributes.speakerInitials,
				onChange: (value) => setAttributes({ speakerInitials: value }),
			}),
			el(TextControl, {
				label: __('Speaker tag', 'yzrh'),
				value: attributes.speakerTag,
				onChange: (value) => setAttributes({ speakerTag: value }),
			}),
			el(SelectControl, {
				label: __('Alignment', 'yzrh'),
				value: attributes.alignment,
				options: [
					{ label: __('Left', 'yzrh'), value: 'left' },
					{ label: __('Right', 'yzrh'), value: 'right' },
				],
				onChange: (value) => setAttributes({ alignment: value }),
			}),
			el(SelectControl, {
				label: __('Bubble type', 'yzrh'),
				value: attributes.tone,
				options: [
					{ label: __('Question', 'yzrh'), value: 'question' },
					{ label: __('Answer', 'yzrh'), value: 'answer' },
				],
				onChange: (value) => setAttributes({ tone: value }),
			}),
			el(SelectControl, {
				label: __('Speaker style', 'yzrh'),
				value: attributes.speakerStyle,
				options: [
					{ label: __('Host', 'yzrh'), value: 'host' },
					{ label: __('Guest A', 'yzrh'), value: 'guest-a' },
					{ label: __('Guest B', 'yzrh'), value: 'guest-b' },
				],
				onChange: (value) => setAttributes({ speakerStyle: value }),
			})
		)
	);

	registerBlockType('yzrh/interview-speech', {
		title: __('Interview Speech Bubble', 'yzrh'),
		icon: 'format-chat',
		category: 'widgets',
		attributes: {
			content: { type: 'string', default: '' },
			speakerInitials: { type: 'string', default: 'S' },
			speakerTag: { type: 'string', default: 'interviewer' },
			alignment: { type: 'string', default: 'left' },
			tone: { type: 'string', default: 'question' },
			speakerStyle: { type: 'string', default: 'host' },
		},
		edit: ({ attributes, setAttributes }) => {
			const rowClasses = [
				'wp-block-yzrh-interview-speech',
				'yzrh-interview-bubble-row',
				attributes.alignment === 'right' ? 'is-right' : '',
			].filter(Boolean).join(' ');
			const speakerClasses = [
				'yzrh-interview-speaker',
				`is-${attributes.speakerStyle}`,
			].filter(Boolean).join(' ');
			const bubbleClasses = [
				'yzrh-interview-bubble',
				`is-${attributes.tone}`,
				attributes.speakerStyle === 'guest-b' ? 'is-guest-b' : '',
			].filter(Boolean).join(' ');

			return el(
				Fragment,
				null,
				speechControls(attributes, setAttributes),
				el(
					'div',
					{ className: rowClasses },
					el(
						'div',
						{ className: speakerClasses },
						attributes.speakerInitials,
						el('div', { className: 'yzrh-interview-speaker__tag' }, attributes.speakerTag)
					),
					el(RichText, {
						tagName: 'div',
						className: bubbleClasses,
						value: attributes.content,
						allowedFormats: ['core/bold', 'core/italic'],
						placeholder: __('Enter dialogue...', 'yzrh'),
						onChange: (value) => setAttributes({ content: value }),
					})
				)
			);
		},
		save: () => null,
	});

	registerBlockType('yzrh/interview-pullquote', {
		title: __('Interview Pull Quote', 'yzrh'),
		icon: 'format-quote',
		category: 'widgets',
		attributes: {
			quote: { type: 'string', default: '' },
			attribution: { type: 'string', default: '' },
		},
		edit: ({ attributes, setAttributes }) => el(
			'aside',
			{ className: 'wp-block-yzrh-interview-pullquote yzrh-interview-pullquote' },
			el(RichText, {
				tagName: 'p',
				className: 'yzrh-interview-pullquote__text',
				value: attributes.quote,
				allowedFormats: ['core/bold', 'core/italic'],
				placeholder: __('Enter pull quote...', 'yzrh'),
				onChange: (value) => setAttributes({ quote: value }),
			}),
			el(RichText, {
				tagName: 'div',
				className: 'yzrh-interview-pullquote__attr',
				value: attributes.attribution,
				allowedFormats: [],
				placeholder: __('Attribution', 'yzrh'),
				onChange: (value) => setAttributes({ attribution: value }),
			})
		),
		save: () => null,
	});
}(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n));
