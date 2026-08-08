(() => {
	'use strict';

	const input = document.querySelector('[data-sp-wiki-search]');
	if (!input) return;

	const items = Array.from(document.querySelectorAll('[data-sp-wiki-item]'));
	const groups = Array.from(document.querySelectorAll('.sp-wiki__nav'));
	const empty = document.querySelector('[data-sp-wiki-empty]');

	input.addEventListener('input', () => {
		const query = input.value.trim().toLocaleLowerCase();
		let visibleCount = 0;

		items.forEach((item) => {
			const haystack = (item.dataset.search || item.textContent || '').toLocaleLowerCase();
			const visible = query === '' || haystack.includes(query);
			item.hidden = !visible;
			if (visible) visibleCount += 1;
		});

		groups.forEach((group) => {
			group.hidden = !Array.from(group.querySelectorAll('[data-sp-wiki-item]')).some((item) => !item.hidden);
		});

		if (empty) empty.hidden = visibleCount > 0;
	});
})();
