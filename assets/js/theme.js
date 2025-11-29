(function () {
    const tocContainerSelector = '#ebook-toc';

    function slugify(text) {
        return text
            .toString()
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9\u00C0-\u024f\s-]/g, '')
            .replace(/\s+/g, '-');
    }

    function buildTOC(root) {
        const tocContainer = document.querySelector(tocContainerSelector);
        if (!tocContainer || !root) {
            return;
        }

        const headings = root.querySelectorAll('h2, h3');
        if (!headings.length) {
            tocContainer.innerHTML = '<p class="text-slate-400 text-sm">Không có mục lục.</p>';
            return;
        }

        const list = document.createElement('div');
        const observerTargets = [];

        headings.forEach((heading) => {
            if (!heading.id) {
                heading.id = slugify(heading.textContent);
            }
            const level = heading.tagName === 'H2' ? 'pl-0 font-semibold' : 'pl-4 text-slate-500';
            const link = document.createElement('a');
            link.href = `#${heading.id}`;
            link.textContent = heading.textContent;
            link.className = `${level} block transition-colors duration-150`;
            list.appendChild(link);
            observerTargets.push({ heading, link });
        });

        tocContainer.innerHTML = '';
        tocContainer.appendChild(list);

        const io = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    const target = observerTargets.find((item) => item.heading === entry.target);
                    if (!target) {
                        return;
                    }
                    if (entry.isIntersecting) {
                        tocContainer.querySelectorAll('a').forEach((link) => link.classList.remove('is-active'));
                        target.link.classList.add('is-active');
                    }
                });
            },
            { rootMargin: '-30% 0px -65% 0px', threshold: 0 }
        );

        observerTargets.forEach((item) => io.observe(item.heading));
    }

    function highlightCurrentSidebarLink() {
        const current = window.location.pathname.replace(/\/+$/, '');
        document.querySelectorAll('.ebook-tree a').forEach((link) => {
            const linkPath = link.pathname.replace(/\/+$/, '');
            if (linkPath === current) {
                link.classList.add('text-indigo-600', 'font-semibold');
            } else {
                link.classList.remove('text-indigo-600', 'font-semibold');
            }
        });
    }

    function initTOC() {
        const articleRoot = document.querySelector('#ebook-main article');
        if (articleRoot) {
            buildTOC(articleRoot);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTOC();
        highlightCurrentSidebarLink();
    });

    document.body.addEventListener('htmx:afterSwap', (event) => {
        if (event.detail?.target?.id === 'ebook-main') {
            initTOC();
            highlightCurrentSidebarLink();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
})();
