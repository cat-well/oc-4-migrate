(function () {
  function slugify(text) {
    return String(text || '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9\u0400-\u04FF\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-');
  }

  function buildToc(root) {
    var toc = document.getElementById('blog-toc');
    if (!toc) return;

    var headings = root.querySelectorAll('h2, h3');
    if (!headings.length) {
      toc.closest('.blog-toc-card')?.classList.add('d-none');
      return;
    }

    var used = new Set();

    var ul = document.createElement('ul');
    ul.className = 'blog-toc-list';

    headings.forEach(function (h) {
      var title = (h.textContent || '').trim();
      if (!title) return;

      if (!h.id) {
        var base = slugify(title) || 'section';
        var id = base;
        var i = 2;
        while (used.has(id) || document.getElementById(id)) {
          id = base + '-' + i;
          i++;
        }
        h.id = id;
        used.add(id);
      }

      var li = document.createElement('li');
      li.className = 'blog-toc-item blog-toc-item--' + h.tagName.toLowerCase();

      var a = document.createElement('a');
      a.href = '#' + h.id;
      a.textContent = title;
      a.className = 'blog-toc-link';

      li.appendChild(a);
      ul.appendChild(li);
    });

    toc.innerHTML = '';
    toc.appendChild(ul);

    // Smooth scroll (respect reduced motion)
    toc.addEventListener('click', function (e) {
      var target = e.target;
      if (!(target instanceof Element)) return;
      if (!target.matches('a[href^="#"]')) return;

      var id = target.getAttribute('href').slice(1);
      var el = document.getElementById(id);
      if (!el) return;

      e.preventDefault();

      var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      el.scrollIntoView({ behavior: prefersReduced ? 'auto' : 'smooth', block: 'start' });

      // update URL without jumping
      try {
        history.replaceState(null, '', '#' + id);
      } catch (err) {}
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('blog-article-content');
    if (!root) return;
    buildToc(root);
  });
})();
